<?php
// Jetable : ajoute des items récurrents (d = NULL) à la suite. Curler une fois puis SUPPRIMER.
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $items = [
        "Aider au rangement de la maison, aux courses si besoin. Porter les paquets.",
        "Débarrasser la table à manger, vider le lave-vaisselle",
        "Piscine ou plage ou activité au parc",
        "Basket",
    ];
    $pos = (int) $pdo->query("SELECT COALESCE(MAX(position), -1) + 1 FROM plan_tasks WHERE d IS NULL")->fetchColumn();
    $ins = $pdo->prepare("INSERT INTO plan_tasks (title, position, active, created_at, d) VALUES (?, ?, 1, ?, NULL)");
    $dup = $pdo->prepare("SELECT COUNT(*) FROM plan_tasks WHERE d IS NULL AND title = ?");
    $now = date('Y-m-d H:i:s');
    $added = 0;
    foreach ($items as $t) {
        $dup->execute([$t]);
        if ((int) $dup->fetchColumn() > 0) { echo "déjà présent : {$t}\n"; continue; }
        $ins->execute([$t, $pos, $now]);
        $pos++; $added++;
    }
    echo "Ajoutés : {$added} item(s).\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
