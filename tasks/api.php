<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

const MAX_LEVEL = 6;     // profondeur d'imbrication maximale
const MAX_TITLE = 2000;  // longueur max d'un titre
const MAX_TAG   = 80;    // longueur max d'un nom de tag

function out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): void
{
    out(['error' => $msg], $code);
}
function input(): array
{
    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return $j;
        }
    }
    return $_POST;
}
function now(): string
{
    return date('Y-m-d H:i:s');
}

// Clé de la liste courante : scope toutes les données (tâches + tags). Défaut 'tasks'.
function listKey(array $in): string
{
    $raw = $_GET['list'] ?? ($in['list'] ?? 'tasks');
    $raw = strtolower((string) $raw);
    $raw = preg_replace('/[^a-z0-9_-]/', '', $raw);
    if ($raw === '' || strlen($raw) > 64) {
        $raw = 'tasks';
    }
    return $raw;
}

/* ---------- connexion ---------- */
try {
    $pdo = db();
} catch (Throwable $e) {
    fail($e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$in     = ($method === 'POST') ? input() : [];
$list   = listKey($in);

// Protection par mot de passe (par liste) : l'API doit aussi être gardée.
require __DIR__ . '/auth.php';
if (!is_page_authed($list)) {
    fail('Accès protégé : mot de passe requis pour cette liste.', 401);
}

$writeActions = [
    'task.add', 'task.rename', 'task.toggle', 'task.delete',
    'task.indent', 'task.outdent', 'task.moveUp', 'task.moveDown',
    'task.collapse', 'task.hide', 'task.showAllHidden', 'task.note',
    'tag.add', 'tag.update', 'tag.delete', 'tasktag.toggle',
    'doc.add', 'doc.delete', 'doc.update',
    'lab.save', 'vault.note',
];
if (in_array($action, $writeActions, true) && $method !== 'POST') {
    fail('Méthode non autorisée (POST requis).', 405);
}

/* ---------- helpers structure ---------- */

function getTask(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

// Tâche restreinte à une liste (null si absente ou appartenant à une autre liste).
function getTaskInList(PDO $pdo, int $id, string $list): ?array
{
    $t = getTask($pdo, $id);
    if (!$t) {
        return null;
    }
    if (($t['list_key'] ?? 'tasks') !== $list) {
        return null;
    }
    return $t;
}

// Niveau (1 = racine) d'une tâche existante.
function levelOf(PDO $pdo, int $id): int
{
    $level = 0;
    $cur = $id;
    $guard = 0;
    while ($cur !== null) {
        $t = getTask($pdo, $cur);
        if (!$t) {
            break;
        }
        $level++;
        $cur = $t['parent_id'] !== null ? (int) $t['parent_id'] : null;
        if (++$guard > 200) {
            break;
        }
    }
    return $level;
}

// Hauteur du sous-arbre (1 = feuille).
function heightOf(PDO $pdo, int $id): int
{
    $st = $pdo->prepare('SELECT id FROM tasks WHERE parent_id = ?');
    $st->execute([$id]);
    $kids = $st->fetchAll(PDO::FETCH_COLUMN);
    $h = 1;
    foreach ($kids as $k) {
        $h = max($h, 1 + heightOf($pdo, (int) $k));
    }
    return $h;
}

function siblingMaxPos(PDO $pdo, ?int $parentId, string $list): int
{
    $st = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM tasks WHERE parent_id <=> ? AND list_key = ?');
    $st->execute([$parentId, $list]);
    return (int) $st->fetchColumn();
}

/* ---------- dispatch ---------- */

try {
    switch ($action) {

        case 'state': {
            $st = $pdo->prepare(
                'SELECT id, parent_id, title, done, done_at, collapsed, hidden, position, note, note_color
                 FROM tasks WHERE list_key = ? ORDER BY position ASC, id ASC'
            );
            $st->execute([$list]);
            $tasks = array_map(static function ($r) {
                return [
                    'id'         => (int) $r['id'],
                    'parent_id'  => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                    'title'      => $r['title'],
                    'done'       => (int) $r['done'] === 1,
                    'done_at'    => $r['done_at'],
                    'collapsed'  => (int) $r['collapsed'] === 1,
                    'hidden'     => (int) $r['hidden'] === 1,
                    'position'   => (int) $r['position'],
                    'note'       => $r['note'],
                    'note_color' => $r['note_color'] ?? 'black',
                ];
            }, $st->fetchAll());

            $st = $pdo->prepare('SELECT id, name, color FROM tags WHERE list_key = ? ORDER BY name ASC');
            $st->execute([$list]);
            $tags = array_map(static function ($r) {
                return ['id' => (int) $r['id'], 'name' => $r['name'], 'color' => $r['color']];
            }, $st->fetchAll());

            $st = $pdo->prepare(
                'SELECT tt.task_id, tt.tag_id FROM task_tags tt
                 JOIN tasks t ON t.id = tt.task_id WHERE t.list_key = ?'
            );
            $st->execute([$list]);
            $links = array_map(static function ($r) {
                return ['task_id' => (int) $r['task_id'], 'tag_id' => (int) $r['tag_id']];
            }, $st->fetchAll());

            out(['tasks' => $tasks, 'tags' => $tags, 'task_tags' => $links, 'max_level' => MAX_LEVEL, 'list' => $list]);
        }

        case 'task.add': {
            $title = trim((string) ($in['title'] ?? ''));
            if ($title === '') {
                fail('Titre vide.');
            }
            $title = mb_substr($title, 0, MAX_TITLE);
            $parentId = isset($in['parent_id']) && $in['parent_id'] !== null ? (int) $in['parent_id'] : null;

            if ($parentId !== null) {
                if (!getTaskInList($pdo, $parentId, $list)) {
                    fail('Tâche parente introuvable.');
                }
                if (levelOf($pdo, $parentId) + 1 > MAX_LEVEL) {
                    fail('Profondeur maximale atteinte (' . MAX_LEVEL . ' niveaux).');
                }
            }

            $pos = siblingMaxPos($pdo, $parentId, $list) + 1;
            $st = $pdo->prepare(
                'INSERT INTO tasks (parent_id, title, position, list_key, created_at, updated_at)
                 VALUES (?,?,?,?,?,?)'
            );
            $st->execute([$parentId, $title, $pos, $list, now(), now()]);
            out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        }

        case 'task.rename': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTaskInList($pdo, $id, $list)) {
                fail('Tâche introuvable.');
            }
            $title = trim((string) ($in['title'] ?? ''));
            if ($title === '') {
                fail('Titre vide.');
            }
            $title = mb_substr($title, 0, MAX_TITLE);
            $pdo->prepare('UPDATE tasks SET title = ?, updated_at = ? WHERE id = ?')
                ->execute([$title, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.toggle': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTaskInList($pdo, $id, $list)) {
                fail('Tâche introuvable.');
            }
            $done = !empty($in['done']);
            $pdo->prepare('UPDATE tasks SET done = ?, done_at = ?, updated_at = ? WHERE id = ?')
                ->execute([$done ? 1 : 0, $done ? now() : null, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.delete': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTaskInList($pdo, $id, $list)) {
                fail('Tâche introuvable.');
            }
            $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]); // cascade enfants + tags
            out(['ok' => true]);
        }

        case 'task.indent': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTaskInList($pdo, $id, $list);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            $parentId = $t['parent_id'] !== null ? (int) $t['parent_id'] : null;
            $st = $pdo->prepare(
                'SELECT id FROM tasks WHERE parent_id <=> ? AND list_key = ? AND position < ?
                 ORDER BY position DESC, id DESC LIMIT 1'
            );
            $st->execute([$parentId, $list, (int) $t['position']]);
            $prev = $st->fetchColumn();
            if ($prev === false) {
                fail("Impossible d'imbriquer : aucune tâche au-dessus au même niveau.");
            }
            $prevId = (int) $prev;
            if (levelOf($pdo, $prevId) + heightOf($pdo, $id) > MAX_LEVEL) {
                fail('Profondeur maximale atteinte (' . MAX_LEVEL . ' niveaux).');
            }
            $pos = siblingMaxPos($pdo, $prevId, $list) + 1;
            $pdo->prepare('UPDATE tasks SET parent_id = ?, position = ?, updated_at = ? WHERE id = ?')
                ->execute([$prevId, $pos, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.outdent': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTaskInList($pdo, $id, $list);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            if ($t['parent_id'] === null) {
                fail('Déjà au niveau 1.');
            }
            $parent = getTask($pdo, (int) $t['parent_id']);
            $gp = $parent['parent_id'] !== null ? (int) $parent['parent_id'] : null;
            $insertPos = (int) $parent['position'] + 1;
            $pdo->prepare('UPDATE tasks SET position = position + 1 WHERE parent_id <=> ? AND list_key = ? AND position >= ?')
                ->execute([$gp, $list, $insertPos]);
            $pdo->prepare('UPDATE tasks SET parent_id = ?, position = ?, updated_at = ? WHERE id = ?')
                ->execute([$gp, $insertPos, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.moveUp':
        case 'task.moveDown': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTaskInList($pdo, $id, $list);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            $parentId = $t['parent_id'] !== null ? (int) $t['parent_id'] : null;
            if ($action === 'task.moveUp') {
                $st = $pdo->prepare(
                    'SELECT id, position FROM tasks WHERE parent_id <=> ? AND list_key = ? AND position < ?
                     ORDER BY position DESC, id DESC LIMIT 1'
                );
            } else {
                $st = $pdo->prepare(
                    'SELECT id, position FROM tasks WHERE parent_id <=> ? AND list_key = ? AND position > ?
                     ORDER BY position ASC, id ASC LIMIT 1'
                );
            }
            $st->execute([$parentId, $list, (int) $t['position']]);
            $neighbor = $st->fetch();
            if (!$neighbor) {
                out(['ok' => true]); // déjà en bout de liste
            }
            $pdo->prepare('UPDATE tasks SET position = ?, updated_at = ? WHERE id = ?')
                ->execute([(int) $neighbor['position'], now(), $id]);
            $pdo->prepare('UPDATE tasks SET position = ? WHERE id = ?')
                ->execute([(int) $t['position'], (int) $neighbor['id']]);
            out(['ok' => true]);
        }

        case 'task.collapse': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTaskInList($pdo, $id, $list)) {
                fail('Tâche introuvable.');
            }
            $collapsed = !empty($in['collapsed']) ? 1 : 0;
            $pdo->prepare('UPDATE tasks SET collapsed = ?, updated_at = ? WHERE id = ?')
                ->execute([$collapsed, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.hide': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTaskInList($pdo, $id, $list);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            if ($t['parent_id'] !== null) {
                fail('Seuls les groupes de niveau 1 peuvent être masqués.');
            }
            $hidden = !empty($in['hidden']) ? 1 : 0;
            $pdo->prepare('UPDATE tasks SET hidden = ?, updated_at = ? WHERE id = ?')
                ->execute([$hidden, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.showAllHidden': {
            $pdo->prepare('UPDATE tasks SET hidden = 0 WHERE hidden = 1 AND list_key = ?')->execute([$list]);
            out(['ok' => true]);
        }

        case 'tag.add': {
            $name = trim((string) ($in['name'] ?? ''));
            if ($name === '') {
                fail('Nom de tag vide.');
            }
            $name = mb_substr($name, 0, MAX_TAG);
            $color = trim((string) ($in['color'] ?? '#cccccc'));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#cccccc';
            }
            $pdo->prepare('INSERT INTO tags (name, color, list_key, created_at) VALUES (?,?,?,?)')
                ->execute([$name, $color, $list, now()]);
            out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        }

        case 'tag.update': {
            $id = (int) ($in['id'] ?? 0);
            $st = $pdo->prepare('SELECT id FROM tags WHERE id = ? AND list_key = ?');
            $st->execute([$id, $list]);
            if (!$st->fetch()) {
                fail('Tag introuvable.');
            }
            $fields = [];
            $params = [];
            if (isset($in['name'])) {
                $name = trim((string) $in['name']);
                if ($name === '') {
                    fail('Nom de tag vide.');
                }
                $fields[] = 'name = ?';
                $params[] = mb_substr($name, 0, MAX_TAG);
            }
            if (isset($in['color'])) {
                $color = trim((string) $in['color']);
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    fail('Couleur invalide (format attendu #rrggbb).');
                }
                $fields[] = 'color = ?';
                $params[] = $color;
            }
            if (!$fields) {
                out(['ok' => true]);
            }
            $params[] = $id;
            $pdo->prepare('UPDATE tags SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
            out(['ok' => true]);
        }

        case 'tag.delete': {
            $id = (int) ($in['id'] ?? 0);
            $pdo->prepare('DELETE FROM tags WHERE id = ? AND list_key = ?')->execute([$id, $list]); // cascade task_tags
            out(['ok' => true]);
        }

        case 'tasktag.toggle': {
            $taskId = (int) ($in['task_id'] ?? 0);
            $tagId  = (int) ($in['tag_id'] ?? 0);
            if (!getTaskInList($pdo, $taskId, $list)) {
                fail('Tâche introuvable.');
            }
            $st = $pdo->prepare('SELECT id FROM tags WHERE id = ? AND list_key = ?');
            $st->execute([$tagId, $list]);
            if (!$st->fetch()) {
                fail('Tag introuvable.');
            }
            if (!empty($in['on'])) {
                $pdo->prepare('INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?,?)')
                    ->execute([$taskId, $tagId]);
            } else {
                $pdo->prepare('DELETE FROM task_tags WHERE task_id = ? AND tag_id = ?')
                    ->execute([$taskId, $tagId]);
            }
            out(['ok' => true]);
        }

        case 'task.note': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTaskInList($pdo, $id, $list)) {
                fail('Tâche introuvable.');
            }
            $fields = [];
            $params = [];
            if (array_key_exists('note', $in)) {
                $note = trim((string) $in['note']);
                $note = mb_substr($note, 0, 10000);
                $fields[] = 'note = ?';
                $params[] = ($note === '') ? null : $note;
            }
            if (array_key_exists('color', $in)) {
                $color = (string) $in['color'];
                if (!in_array($color, ['black', 'green', 'red'], true)) {
                    fail('Couleur invalide.');
                }
                $fields[] = 'note_color = ?';
                $params[] = $color;
            }
            if (!$fields) {
                out(['ok' => true]);
            }
            $fields[] = 'updated_at = ?';
            $params[] = now();
            $params[] = $id;
            $pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
            out(['ok' => true]);
        }

        /* ---------- documents (galerie par liste) ---------- */

        case 'doc.list': {
            // Triés par date d'émission (du document) décroissante ; les non datés en dernier.
            $st = $pdo->prepare(
                'SELECT id, title, doc_type, emission_date, lang, translation, action_text,
                        status, user_comment, related_doc_id, task_id, created_at
                 FROM documents WHERE list_key = ?
                 ORDER BY (emission_date IS NULL) ASC, emission_date DESC, id DESC'
            );
            $st->execute([$list]);
            $docs = array_map(static function ($r) {
                return [
                    'id'             => (int) $r['id'],
                    'title'          => $r['title'],
                    'doc_type'       => $r['doc_type'],
                    'emission_date'  => $r['emission_date'],
                    'lang'           => $r['lang'],
                    'translation'    => $r['translation'],
                    'action_text'    => $r['action_text'],
                    'status'         => $r['status'] ?: 'todo',
                    'user_comment'   => $r['user_comment'],
                    'related_doc_id' => $r['related_doc_id'] !== null ? (int) $r['related_doc_id'] : null,
                    'task_id'        => $r['task_id'] !== null ? (int) $r['task_id'] : null,
                    'created_at'     => $r['created_at'],
                ];
            }, $st->fetchAll());
            out(['documents' => $docs, 'list' => $list]);
        }

        case 'doc.add': {
            // Métadonnées d'un document. Le fichier image est téléversé séparément (FTP)
            // sous uploads/<list>/<id>.jpg (+ <id>.thumb.jpg) une fois l'id connu.
            $title = trim((string) ($in['title'] ?? ''));
            $title = $title !== '' ? mb_substr($title, 0, 255) : null;
            $type  = trim((string) ($in['doc_type'] ?? ''));
            $type  = $type !== '' ? mb_substr($type, 0, 40) : null;
            $mime  = trim((string) ($in['mime'] ?? 'image/jpeg'));
            if (!preg_match('#^[a-z]+/[a-z0-9.+-]+$#i', $mime)) {
                $mime = 'image/jpeg';
            }
            $lang = trim((string) ($in['lang'] ?? 'he'));
            $lang = $lang !== '' ? mb_substr($lang, 0, 16) : 'he';
            $translation = isset($in['translation']) ? mb_substr((string) $in['translation'], 0, 60000) : null;
            $action      = isset($in['action_text']) ? mb_substr((string) $in['action_text'], 0, 4000) : null;

            // Tâche liée : soit un id existant, soit on en crée une depuis task_title.
            $taskId = isset($in['task_id']) && $in['task_id'] !== null ? (int) $in['task_id'] : null;
            if ($taskId !== null && !getTaskInList($pdo, $taskId, $list)) {
                $taskId = null;
            }
            $taskTitle = trim((string) ($in['task_title'] ?? ''));
            if ($taskId === null && $taskTitle !== '') {
                $taskTitle = mb_substr($taskTitle, 0, MAX_TITLE);
                $pos = siblingMaxPos($pdo, null, $list) + 1;
                $pdo->prepare(
                    'INSERT INTO tasks (parent_id, title, position, list_key, created_at, updated_at)
                     VALUES (NULL,?,?,?,?,?)'
                )->execute([$taskTitle, $pos, $list, now(), now()]);
                $taskId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare(
                'INSERT INTO documents (list_key, title, doc_type, mime, lang, translation, action_text, task_id, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$list, $title, $type, $mime, $lang, $translation, $action, $taskId, now(), now()]);
            out(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'task_id' => $taskId]);
        }

        case 'doc.delete': {
            $id = (int) ($in['id'] ?? 0);
            $st = $pdo->prepare('SELECT id FROM documents WHERE id = ? AND list_key = ?');
            $st->execute([$id, $list]);
            if (!$st->fetch()) {
                fail('Document introuvable.');
            }
            // supprime les fichiers (original + vignette) puis la ligne (la tâche liée est conservée)
            $dir = __DIR__ . '/uploads/' . $list;
            foreach (glob($dir . '/' . $id . '.*') ?: [] as $f) {
                @unlink($f);
            }
            $pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
            out(['ok' => true]);
        }

        case 'doc.update': {
            // Met à jour les métadonnées d'un document (nom, type, date, statut, commentaire, lien, traduction).
            $id = (int) ($in['id'] ?? 0);
            $st = $pdo->prepare('SELECT id FROM documents WHERE id = ? AND list_key = ?');
            $st->execute([$id, $list]);
            if (!$st->fetch()) {
                fail('Document introuvable.');
            }
            $fields = [];
            $params = [];
            if (array_key_exists('title', $in)) {
                $v = trim((string) $in['title']);
                $fields[] = 'title = ?';
                $params[] = $v !== '' ? mb_substr($v, 0, 255) : null;
            }
            if (array_key_exists('doc_type', $in)) {
                $v = trim((string) $in['doc_type']);
                $fields[] = 'doc_type = ?';
                $params[] = $v !== '' ? mb_substr($v, 0, 40) : null;
            }
            if (array_key_exists('emission_date', $in)) {
                $v = trim((string) $in['emission_date']);
                $fields[] = 'emission_date = ?';
                $params[] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
            }
            if (array_key_exists('status', $in)) {
                $v = (string) $in['status'];
                if (!in_array($v, ['todo', 'in_progress', 'done'], true)) {
                    fail('Statut invalide.');
                }
                $fields[] = 'status = ?';
                $params[] = $v;
            }
            if (array_key_exists('user_comment', $in)) {
                $v = (string) $in['user_comment'];
                $fields[] = 'user_comment = ?';
                $params[] = $v !== '' ? mb_substr($v, 0, 10000) : null;
            }
            if (array_key_exists('related_doc_id', $in)) {
                $rid = ($in['related_doc_id'] !== null && $in['related_doc_id'] !== '') ? (int) $in['related_doc_id'] : null;
                if ($rid !== null) {
                    $c = $pdo->prepare('SELECT id FROM documents WHERE id = ? AND list_key = ?');
                    $c->execute([$rid, $list]);
                    if (!$c->fetch() || $rid === $id) {
                        $rid = null;
                    }
                }
                $fields[] = 'related_doc_id = ?';
                $params[] = $rid;
            }
            if (array_key_exists('translation', $in)) {
                $fields[] = 'translation = ?';
                $params[] = mb_substr((string) $in['translation'], 0, 200000);
            }
            if (array_key_exists('action_text', $in)) {
                $v = (string) $in['action_text'];
                $fields[] = 'action_text = ?';
                $params[] = $v !== '' ? mb_substr($v, 0, 4000) : null;
            }
            if (array_key_exists('lang', $in)) {
                $v = trim((string) $in['lang']);
                $fields[] = 'lang = ?';
                $params[] = $v !== '' ? mb_substr($v, 0, 16) : 'he';
            }
            if (!$fields) {
                out(['ok' => true]);
            }
            $fields[] = 'updated_at = ?';
            $params[] = now();
            $params[] = $id;
            $pdo->prepare('UPDATE documents SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
            out(['ok' => true]);
        }

        /* ---------- analyses dans le temps (lab) ---------- */

        case 'lab.list': {
            $st = $pdo->prepare(
                'SELECT id, rubric, rubric_pos, name, name_pos, unit, ref_text, ref_source, trend_note
                 FROM lab_metrics WHERE list_key = ?
                 ORDER BY rubric_pos ASC, rubric ASC, name_pos ASC, name ASC'
            );
            $st->execute([$list]);
            $metrics = $st->fetchAll();

            $valuesByMetric = [];
            $dates = [];
            $ids = array_map(static fn ($m) => (int) $m['id'], $metrics);
            if ($ids) {
                $place = implode(',', array_fill(0, count($ids), '?'));
                $vst = $pdo->prepare(
                    "SELECT metric_id, measured_on, value_text FROM lab_values
                     WHERE metric_id IN ($place) ORDER BY measured_on ASC"
                );
                $vst->execute($ids);
                foreach ($vst->fetchAll() as $v) {
                    $valuesByMetric[(int) $v['metric_id']][$v['measured_on']] = $v['value_text'];
                    $dates[$v['measured_on']] = true;
                }
            }
            $dateList = array_keys($dates);
            sort($dateList); // chronologique : plus ancien à gauche, dernière valeur à droite

            $outMetrics = array_map(static function ($m) use ($valuesByMetric) {
                $mid = (int) $m['id'];
                return [
                    'id'         => $mid,
                    'rubric'     => $m['rubric'],
                    'name'       => $m['name'],
                    'unit'       => $m['unit'],
                    'ref_text'   => $m['ref_text'],
                    'ref_source' => $m['ref_source'] ?: 'doc',
                    'trend_note' => $m['trend_note'],
                    'values'     => isset($valuesByMetric[$mid]) ? $valuesByMetric[$mid] : (object) [],
                ];
            }, $metrics);

            $nst = $pdo->prepare('SELECT analysis_note FROM vault_meta WHERE list_key = ?');
            $nst->execute([$list]);
            $note = $nst->fetchColumn();

            out([
                'metrics'       => $outMetrics,
                'dates'         => $dateList,
                'analysis_note' => $note !== false ? $note : null,
                'list'          => $list,
            ]);
        }

        case 'lab.save': {
            // Upsert d'une valeur d'analyse (par rubrique+nom) et de ses mesures dans le temps.
            $name = trim((string) ($in['name'] ?? ''));
            if ($name === '') {
                fail('Nom de la valeur requis.');
            }
            $name       = mb_substr($name, 0, 190);
            $rubric     = mb_substr(trim((string) ($in['rubric'] ?? '')), 0, 160);
            $rubricPos  = (int) ($in['rubric_pos'] ?? 0);
            $namePos    = (int) ($in['name_pos'] ?? 0);
            $unit       = isset($in['unit']) ? mb_substr(trim((string) $in['unit']), 0, 40) : null;
            $refText    = isset($in['ref_text']) ? mb_substr(trim((string) $in['ref_text']), 0, 160) : null;
            $refSource  = (string) ($in['ref_source'] ?? 'doc');
            if (!in_array($refSource, ['doc', 'claude'], true)) {
                $refSource = 'doc';
            }
            $trend = isset($in['trend_note']) ? mb_substr((string) $in['trend_note'], 0, 4000) : null;

            $st = $pdo->prepare('SELECT id FROM lab_metrics WHERE list_key = ? AND rubric = ? AND name = ?');
            $st->execute([$list, $rubric, $name]);
            $mid = $st->fetchColumn();
            if ($mid === false) {
                $pdo->prepare(
                    'INSERT INTO lab_metrics (list_key, rubric, rubric_pos, name, name_pos, unit, ref_text, ref_source, trend_note, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$list, $rubric, $rubricPos, $name, $namePos, $unit, $refText, $refSource, $trend, now(), now()]);
                $mid = (int) $pdo->lastInsertId();
            } else {
                $mid = (int) $mid;
                $pdo->prepare(
                    'UPDATE lab_metrics SET rubric_pos = ?, name_pos = ?, unit = ?, ref_text = ?, ref_source = ?, trend_note = ?, updated_at = ? WHERE id = ?'
                )->execute([$rubricPos, $namePos, $unit, $refText, $refSource, $trend, now(), $mid]);
            }

            $values = $in['values'] ?? [];
            if (is_array($values)) {
                $ins = $pdo->prepare(
                    'INSERT INTO lab_values (metric_id, measured_on, value_text, document_id, created_at)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), document_id = VALUES(document_id)'
                );
                foreach ($values as $v) {
                    $d = trim((string) ($v['date'] ?? ''));
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                        continue;
                    }
                    $val = mb_substr(trim((string) ($v['value'] ?? '')), 0, 80);
                    if ($val === '') {
                        continue;
                    }
                    $docId = (isset($v['document_id']) && $v['document_id'] !== null && $v['document_id'] !== '') ? (int) $v['document_id'] : null;
                    $ins->execute([$mid, $d, $val, $docId, now()]);
                }
            }
            out(['ok' => true, 'metric_id' => $mid]);
        }

        case 'vault.note': {
            $note = isset($in['analysis_note']) ? mb_substr((string) $in['analysis_note'], 0, 200000) : null;
            $pdo->prepare(
                'INSERT INTO vault_meta (list_key, analysis_note, updated_at) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE analysis_note = VALUES(analysis_note), updated_at = VALUES(updated_at)'
            )->execute([$list, $note, now()]);
            out(['ok' => true]);
        }

        default:
            fail('Action inconnue : ' . $action, 404);
    }
} catch (Throwable $e) {
    fail('Erreur serveur : ' . $e->getMessage(), 500);
}
