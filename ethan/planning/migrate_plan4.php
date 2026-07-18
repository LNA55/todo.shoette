<?php
// Jetable : ajoute un item récurrent (d = NULL). Curler une fois puis SUPPRIMER.
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $title = "Se faire des passes avec une balle - dehors ou dans l'appartement, balle de tennis, balle Moulin Roty ou balle en mousse";
    $dup = $pdo->prepare("SELECT COUNT(*) FROM plan_tasks WHERE d IS NULL AND title = ?");
    $dup->execute([$title]);
    if ((int) $dup->fetchColumn() > 0) { echo "déjà présent, rien fait.\n"; exit; }
    $pos = (int) $pdo->query("SELECT COALESCE(MAX(position), -1) + 1 FROM plan_tasks WHERE d IS NULL")->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO plan_tasks (title, position, active, created_at, d) VALUES (?, ?, 1, ?, NULL)");
    $ins->execute([$title, $pos, date('Y-m-d H:i:s')]);
    echo "Ajouté (position {$pos}).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
