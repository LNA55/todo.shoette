<?php
declare(strict_types=1);

// Migration one-shot pour le vault santé. Idempotente. À SUPPRIMER du serveur après exécution
// (comme install.php / migrate_documents.php : ne reste qu'en local).
//   - étend `documents` : emission_date, status, user_comment, related_doc_id
//   - crée `lab_metrics` (une ligne = une valeur d'analyse : rubrique, nom, référence, tendance)
//   - crée `lab_values`  (mesures dans le temps : metric_id + date + valeur)
//   - crée `vault_meta`  (note d'analyse d'ensemble, par liste)

require __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

function colExists(PDO $pdo, string $table, string $col): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $col]);
    return (int) $st->fetchColumn() > 0;
}

try {
    $pdo = db();

    // 1) Colonnes ajoutées à `documents`
    $alter = [
        'emission_date'  => "ALTER TABLE documents ADD COLUMN emission_date DATE NULL AFTER doc_type",
        'status'         => "ALTER TABLE documents ADD COLUMN status VARCHAR(16) NULL DEFAULT 'todo' AFTER action_text",
        'user_comment'   => "ALTER TABLE documents ADD COLUMN user_comment TEXT NULL AFTER status",
        'related_doc_id' => "ALTER TABLE documents ADD COLUMN related_doc_id INT UNSIGNED NULL AFTER user_comment",
    ];
    foreach ($alter as $col => $sql) {
        if (colExists($pdo, 'documents', $col)) {
            echo "documents.$col : déjà présent\n";
        } else {
            $pdo->exec($sql);
            echo "documents.$col : ajouté\n";
        }
    }

    // 2) lab_metrics : définition d'une valeur d'analyse (référence + tendance)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lab_metrics (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            list_key    VARCHAR(64) NOT NULL DEFAULT 'sante',
            rubric      VARCHAR(160) NOT NULL DEFAULT '',
            rubric_pos  INT NOT NULL DEFAULT 0,
            name        VARCHAR(190) NOT NULL,
            name_pos    INT NOT NULL DEFAULT 0,
            unit        VARCHAR(40) NULL,
            ref_text    VARCHAR(160) NULL,
            ref_source  VARCHAR(10) NOT NULL DEFAULT 'doc',   -- doc | claude
            trend_note  TEXT NULL,
            created_at  DATETIME NOT NULL,
            updated_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_metric (list_key, rubric, name),
            KEY idx_lm_list (list_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "table lab_metrics : prête\n";

    // 3) lab_values : valeurs mesurées par date
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS lab_values (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_id   INT UNSIGNED NOT NULL,
            measured_on DATE NOT NULL,
            value_text  VARCHAR(80) NOT NULL,
            document_id INT UNSIGNED NULL,
            created_at  DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_value (metric_id, measured_on),
            KEY idx_lv_metric (metric_id),
            CONSTRAINT fk_lv_metric FOREIGN KEY (metric_id) REFERENCES lab_metrics (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "table lab_values : prête\n";

    // 4) vault_meta : note d'analyse d'ensemble, par liste
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vault_meta (
            list_key      VARCHAR(64) NOT NULL,
            analysis_note MEDIUMTEXT NULL,
            updated_at    DATETIME NOT NULL,
            PRIMARY KEY (list_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "table vault_meta : prête\n";

    echo "OK — migration vault terminée.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERREUR : ' . $e->getMessage() . "\n";
}
