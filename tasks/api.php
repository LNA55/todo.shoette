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

/* ---------- connexion ---------- */
try {
    $pdo = db();
} catch (Throwable $e) {
    fail($e->getMessage(), 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
$in     = ($method === 'POST') ? input() : [];

$writeActions = [
    'task.add', 'task.rename', 'task.toggle', 'task.delete',
    'task.indent', 'task.outdent', 'task.moveUp', 'task.moveDown',
    'task.collapse', 'task.hide', 'task.showAllHidden',
    'tag.add', 'tag.update', 'tag.delete', 'tasktag.toggle',
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

function siblingMaxPos(PDO $pdo, ?int $parentId): int
{
    $st = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM tasks WHERE parent_id <=> ?');
    $st->execute([$parentId]);
    return (int) $st->fetchColumn();
}

/* ---------- dispatch ---------- */

try {
    switch ($action) {

        case 'state': {
            $tasks = $pdo->query(
                'SELECT id, parent_id, title, done, done_at, collapsed, hidden, position
                 FROM tasks ORDER BY position ASC, id ASC'
            )->fetchAll();
            $tasks = array_map(static function ($r) {
                return [
                    'id'        => (int) $r['id'],
                    'parent_id' => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                    'title'     => $r['title'],
                    'done'      => (int) $r['done'] === 1,
                    'done_at'   => $r['done_at'],
                    'collapsed' => (int) $r['collapsed'] === 1,
                    'hidden'    => (int) $r['hidden'] === 1,
                    'position'  => (int) $r['position'],
                ];
            }, $tasks);

            $tags = $pdo->query('SELECT id, name, color FROM tags ORDER BY name ASC')->fetchAll();
            $tags = array_map(static function ($r) {
                return ['id' => (int) $r['id'], 'name' => $r['name'], 'color' => $r['color']];
            }, $tags);

            $links = $pdo->query('SELECT task_id, tag_id FROM task_tags')->fetchAll();
            $links = array_map(static function ($r) {
                return ['task_id' => (int) $r['task_id'], 'tag_id' => (int) $r['tag_id']];
            }, $links);

            out(['tasks' => $tasks, 'tags' => $tags, 'task_tags' => $links, 'max_level' => MAX_LEVEL]);
        }

        case 'task.add': {
            $title = trim((string) ($in['title'] ?? ''));
            if ($title === '') {
                fail('Titre vide.');
            }
            $title = mb_substr($title, 0, MAX_TITLE);
            $parentId = isset($in['parent_id']) && $in['parent_id'] !== null ? (int) $in['parent_id'] : null;

            if ($parentId !== null) {
                if (!getTask($pdo, $parentId)) {
                    fail('Tâche parente introuvable.');
                }
                if (levelOf($pdo, $parentId) + 1 > MAX_LEVEL) {
                    fail('Profondeur maximale atteinte (' . MAX_LEVEL . ' niveaux).');
                }
            }

            $pos = siblingMaxPos($pdo, $parentId) + 1;
            $st = $pdo->prepare(
                'INSERT INTO tasks (parent_id, title, position, created_at, updated_at)
                 VALUES (?,?,?,?,?)'
            );
            $st->execute([$parentId, $title, $pos, now(), now()]);
            out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        }

        case 'task.rename': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTask($pdo, $id)) {
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
            if (!getTask($pdo, $id)) {
                fail('Tâche introuvable.');
            }
            $done = !empty($in['done']);
            $pdo->prepare('UPDATE tasks SET done = ?, done_at = ?, updated_at = ? WHERE id = ?')
                ->execute([$done ? 1 : 0, $done ? now() : null, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.delete': {
            $id = (int) ($in['id'] ?? 0);
            if (!getTask($pdo, $id)) {
                fail('Tâche introuvable.');
            }
            $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]); // cascade enfants + tags
            out(['ok' => true]);
        }

        case 'task.indent': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTask($pdo, $id);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            $parentId = $t['parent_id'] !== null ? (int) $t['parent_id'] : null;
            // frère immédiatement au-dessus (même parent)
            $st = $pdo->prepare(
                'SELECT id FROM tasks WHERE parent_id <=> ? AND position < ?
                 ORDER BY position DESC, id DESC LIMIT 1'
            );
            $st->execute([$parentId, (int) $t['position']]);
            $prev = $st->fetchColumn();
            if ($prev === false) {
                fail("Impossible d'imbriquer : aucune tâche au-dessus au même niveau.");
            }
            $prevId = (int) $prev;
            if (levelOf($pdo, $prevId) + heightOf($pdo, $id) > MAX_LEVEL) {
                fail('Profondeur maximale atteinte (' . MAX_LEVEL . ' niveaux).');
            }
            $pos = siblingMaxPos($pdo, $prevId) + 1;
            $pdo->prepare('UPDATE tasks SET parent_id = ?, position = ?, updated_at = ? WHERE id = ?')
                ->execute([$prevId, $pos, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.outdent': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTask($pdo, $id);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            if ($t['parent_id'] === null) {
                fail('Déjà au niveau 1.');
            }
            $parent = getTask($pdo, (int) $t['parent_id']);
            $gp = $parent['parent_id'] !== null ? (int) $parent['parent_id'] : null;
            $insertPos = (int) $parent['position'] + 1;
            // on décale les frères du grand-parent pour insérer juste après l'ancien parent
            $pdo->prepare('UPDATE tasks SET position = position + 1 WHERE parent_id <=> ? AND position >= ?')
                ->execute([$gp, $insertPos]);
            $pdo->prepare('UPDATE tasks SET parent_id = ?, position = ?, updated_at = ? WHERE id = ?')
                ->execute([$gp, $insertPos, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.moveUp':
        case 'task.moveDown': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTask($pdo, $id);
            if (!$t) {
                fail('Tâche introuvable.');
            }
            $parentId = $t['parent_id'] !== null ? (int) $t['parent_id'] : null;
            if ($action === 'task.moveUp') {
                $st = $pdo->prepare(
                    'SELECT id, position FROM tasks WHERE parent_id <=> ? AND position < ?
                     ORDER BY position DESC, id DESC LIMIT 1'
                );
            } else {
                $st = $pdo->prepare(
                    'SELECT id, position FROM tasks WHERE parent_id <=> ? AND position > ?
                     ORDER BY position ASC, id ASC LIMIT 1'
                );
            }
            $st->execute([$parentId, (int) $t['position']]);
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
            if (!getTask($pdo, $id)) {
                fail('Tâche introuvable.');
            }
            $collapsed = !empty($in['collapsed']) ? 1 : 0;
            $pdo->prepare('UPDATE tasks SET collapsed = ?, updated_at = ? WHERE id = ?')
                ->execute([$collapsed, now(), $id]);
            out(['ok' => true]);
        }

        case 'task.hide': {
            $id = (int) ($in['id'] ?? 0);
            $t = getTask($pdo, $id);
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
            $pdo->exec('UPDATE tasks SET hidden = 0 WHERE hidden = 1');
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
            $pdo->prepare('INSERT INTO tags (name, color, created_at) VALUES (?,?,?)')
                ->execute([$name, $color, now()]);
            out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        }

        case 'tag.update': {
            $id = (int) ($in['id'] ?? 0);
            $st = $pdo->prepare('SELECT id FROM tags WHERE id = ?');
            $st->execute([$id]);
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
            $pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$id]); // cascade task_tags
            out(['ok' => true]);
        }

        case 'tasktag.toggle': {
            $taskId = (int) ($in['task_id'] ?? 0);
            $tagId  = (int) ($in['tag_id'] ?? 0);
            if (!getTask($pdo, $taskId)) {
                fail('Tâche introuvable.');
            }
            $st = $pdo->prepare('SELECT id FROM tags WHERE id = ?');
            $st->execute([$tagId]);
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

        default:
            fail('Action inconnue : ' . $action, 404);
    }
} catch (Throwable $e) {
    fail('Erreur serveur : ' . $e->getMessage(), 500);
}
