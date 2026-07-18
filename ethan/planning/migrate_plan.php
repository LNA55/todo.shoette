<?php
// Script jetable : crée les tables du planning et sème l'item par défaut.
// À curler UNE fois puis SUPPRIMER du serveur.
declare(strict_types=1);
require __DIR__ . '/../../tasks/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS plan_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        position INT NOT NULL DEFAULT 0,
        active TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS plan_done (
        d DATE NOT NULL,
        task_id INT NOT NULL,
        done_at DATETIME NOT NULL,
        PRIMARY KEY (d, task_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $n = (int) $pdo->query("SELECT COUNT(*) FROM plan_tasks")->fetchColumn();
    if ($n === 0) {
        $ins = $pdo->prepare("INSERT INTO plan_tasks (title, position, active, created_at) VALUES (?, ?, 1, ?)");
        $ins->execute(["Être gentil avec papy et mamie", 0, date('Y-m-d H:i:s')]);
        echo "Seed : 1 item ajouté.\n";
    } else {
        echo "plan_tasks contient déjà {$n} item(s), pas de seed.\n";
    }
    echo "Migration OK : plan_tasks + plan_done prêtes.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERREUR : " . $e->getMessage() . "\n";
}
