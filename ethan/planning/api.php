<?php
// API du planning d'Ethan. Réutilise la connexion PDO de l'app (../../tasks/db.php).
//   ?action=day&d=YYYY-MM-DD              -> { date, items:[{id,title,done}] }
//   ?action=toggle  (POST JSON {d,item,done}) -> { ok:true }
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: application/json; charset=utf-8');

function out($d, int $c = 200): void { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function fail(string $m, int $c = 400): void { out(['error' => $m], $c); }
function validDate($d): bool {
    return is_string($d) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

$action = $_GET['action'] ?? '';

try {
    $pdo = db();

    if ($action === 'day') {
        $d = $_GET['d'] ?? '';
        if (!validDate($d)) fail('date invalide');
        // items récurrents (d IS NULL) + items propres à ce jour (d = date). Récurrents d'abord.
        $sel = $pdo->prepare("SELECT id, title FROM plan_tasks WHERE active = 1 AND (d IS NULL OR d = ?) ORDER BY (d IS NULL) DESC, position, id");
        $sel->execute([$d]);
        $items = $sel->fetchAll();
        $st = $pdo->prepare("SELECT task_id FROM plan_done WHERE d = ?");
        $st->execute([$d]);
        $done = array_flip(array_map('intval', array_column($st->fetchAll(), 'task_id')));
        foreach ($items as &$it) {
            $it['id'] = (int) $it['id'];
            $it['done'] = isset($done[$it['id']]);
        }
        unset($it);
        out(['date' => $d, 'items' => $items]);
    }

    if ($action === 'toggle') {
        $raw = file_get_contents('php://input');
        $in = ($raw && ($j = json_decode($raw, true)) && is_array($j)) ? $j : $_POST;
        $d = $in['d'] ?? '';
        $item = (int) ($in['item'] ?? 0);
        $done = !empty($in['done']);
        if (!validDate($d) || $item <= 0) fail('paramètres invalides');
        $chk = $pdo->prepare("SELECT COUNT(*) FROM plan_tasks WHERE id = ?");
        $chk->execute([$item]);
        if (!(int) $chk->fetchColumn()) fail('item inconnu', 404);
        if ($done) {
            $q = $pdo->prepare("INSERT IGNORE INTO plan_done (d, task_id, done_at) VALUES (?, ?, ?)");
            $q->execute([$d, $item, date('Y-m-d H:i:s')]);
        } else {
            $q = $pdo->prepare("DELETE FROM plan_done WHERE d = ? AND task_id = ?");
            $q->execute([$d, $item]);
        }
        out(['ok' => true, 'd' => $d, 'item' => $item, 'done' => $done]);
    }

    fail('action inconnue', 404);
} catch (Throwable $e) {
    fail('server error', 500);
}
