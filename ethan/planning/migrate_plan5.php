<?php
// Jetable : crée l'item spécial « Chill » (active = 0, jamais listé par la requête
// normale) que l'API renvoie seul pour les jours passés. Curler une fois puis SUPPRIMER.
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $dup = $pdo->prepare("SELECT COUNT(*) FROM plan_tasks WHERE d IS NULL AND title = 'Chill' AND active = 0");
    $dup->execute();
    if ((int) $dup->fetchColumn() > 0) { echo "déjà présent, rien fait.\n"; exit; }
    $ins = $pdo->prepare("INSERT INTO plan_tasks (title, position, active, created_at, d) VALUES ('Chill', 0, 0, ?, NULL)");
    $ins->execute([date('Y-m-d H:i:s')]);
    echo "Item « Chill » créé (id " . $pdo->lastInsertId() . ").\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
