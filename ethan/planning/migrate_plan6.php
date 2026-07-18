<?php
// Jetable : état des items (jour + récurrents) puis ajout de « Départ de Stephane »
// en premier item du 2026-07-18 (position -1). Curler une fois puis SUPPRIMER.
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    echo "-- items par jour (d IS NOT NULL) :\n";
    foreach ($pdo->query("SELECT id, d, title, position, active FROM plan_tasks WHERE d IS NOT NULL ORDER BY d, position") as $r) {
        echo "  {$r['d']}  pos {$r['position']}  active {$r['active']}  #{$r['id']}  {$r['title']}\n";
    }
    echo "-- récurrents (d IS NULL) :\n";
    foreach ($pdo->query("SELECT id, title, position, active FROM plan_tasks WHERE d IS NULL ORDER BY position") as $r) {
        echo "  pos {$r['position']}  active {$r['active']}  #{$r['id']}  {$r['title']}\n";
    }
    $dup = $pdo->prepare("SELECT COUNT(*) FROM plan_tasks WHERE d = '2026-07-18' AND title = 'Départ de Stephane'");
    $dup->execute();
    if ((int) $dup->fetchColumn() > 0) { echo "\n« Départ de Stephane » déjà présent, rien fait.\n"; exit; }
    $ins = $pdo->prepare("INSERT INTO plan_tasks (title, position, active, created_at, d) VALUES ('Départ de Stephane', -1, 1, ?, '2026-07-18')");
    $ins->execute([date('Y-m-d H:i:s')]);
    echo "\nAjouté « Départ de Stephane » (id " . $pdo->lastInsertId() . ", pos -1, d 2026-07-18).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
