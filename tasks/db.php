<?php
declare(strict_types=1);

/**
 * Connexion PDO partagée (singleton). Lit les identifiants depuis config.php.
 * Lève une exception en cas de problème : l'appelant décide du format d'erreur
 * (JSON pour l'API, HTML pour l'installeur).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException(
            "config.php manquant : copie config.sample.php en config.php et renseigne les identifiants MySQL."
        );
    }

    $cfg = require $configPath;
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['dbname'],
        $cfg['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
