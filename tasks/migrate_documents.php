<?php
declare(strict_types=1);

// Migration one-shot : crée la table `documents` (galerie de documents par liste).
// Idempotent (CREATE TABLE IF NOT EXISTS). À SUPPRIMER du serveur après exécution.
// (Comme install.php : ne reste qu'en local, jamais laissé en ligne.)

require __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS documents (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            list_key    VARCHAR(64) NOT NULL DEFAULT 'sante',
            title       VARCHAR(255) NULL,
            doc_type    VARCHAR(40) NULL,
            mime        VARCHAR(80) NOT NULL DEFAULT 'image/jpeg',
            lang        VARCHAR(16) NOT NULL DEFAULT 'he',
            translation MEDIUMTEXT NULL,
            action_text TEXT NULL,
            task_id     INT UNSIGNED NULL,
            position    INT NOT NULL DEFAULT 0,
            created_at  DATETIME NOT NULL,
            updated_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_documents_list (list_key),
            KEY idx_documents_task (task_id),
            CONSTRAINT fk_doc_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "OK — table `documents` prête.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERREUR : ' . $e->getMessage() . "\n";
}
