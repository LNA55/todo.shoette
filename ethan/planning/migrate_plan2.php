<?php
// Jetable : ajoute la colonne d (récurrent/spécifique) et (ré)écrit la liste récurrente.
// Curler une fois puis SUPPRIMER du serveur.
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    try {
        $pdo->exec("ALTER TABLE plan_tasks ADD COLUMN d DATE NULL");
        echo "Colonne d ajoutée.\n";
    } catch (Throwable $e) {
        echo "Colonne d déjà présente.\n";
    }

    // Remplace uniquement les items récurrents (d IS NULL) ; laisse intacts les items propres à un jour.
    $pdo->exec("DELETE FROM plan_tasks WHERE d IS NULL");

    $items = [
        "Fables de la Fontaine",
        "Lecture et écriture en Hébreu",
        "Lecture et écriture en Français",
        "Cahier d'exercice",
        "Télévision en hébreu",
        "Télévision intelligente avec des humains",
        "Mathématique (discussion ou exercice)",
    ];
    $ins = $pdo->prepare("INSERT INTO plan_tasks (title, position, active, created_at, d) VALUES (?, ?, 1, ?, NULL)");
    $now = date('Y-m-d H:i:s');
    foreach ($items as $i => $t) {
        $ins->execute([$t, $i, $now]);
    }
    // Nettoie les cases cochées qui pointaient vers des items supprimés.
    $pdo->exec("DELETE FROM plan_done WHERE task_id NOT IN (SELECT id FROM plan_tasks)");

    echo "Liste récurrente définie : " . count($items) . " items.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
