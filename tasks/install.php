<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// Installeur à usage unique : crée les tables puis (si la base est vide)
// ajoute un petit jeu d'exemple. À visiter une fois, puis à supprimer.

header('Content-Type: text/html; charset=utf-8');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$messages = [];
$error = null;

try {
    $pdo = db();

    // 1) Création des tables depuis schema.sql (idempotent).
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('schema.sql introuvable.');
    }
    // On retire les lignes de commentaire "--" puis on exécute statement par statement.
    $clean = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
        $pdo->exec($stmt);
    }
    $messages[] = 'Tables prêtes : tasks, tags, task_tags.';

    // 2) Jeu d'exemple uniquement si aucune tâche n'existe encore.
    $count = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    if ($count === 0) {
        $now = date('Y-m-d H:i:s');

        $pdo->prepare('INSERT INTO tags (name, color, created_at) VALUES (?,?,?)')
            ->execute(['exemple', '#4f86ff', $now]);
        $tagId = (int) $pdo->lastInsertId();

        $insTask = $pdo->prepare(
            'INSERT INTO tasks (parent_id, title, done, done_at, position, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?)'
        );

        $insTask->execute([null, 'Bienvenue 👋 clique sur un titre pour le modifier', 0, null, 0, $now, $now]);
        $welcome = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO task_tags (task_id, tag_id) VALUES (?,?)')->execute([$welcome, $tagId]);

        $insTask->execute([$welcome, 'Une sous-tâche (bouton → pour imbriquer)', 0, null, 0, $now, $now]);
        $insTask->execute([$welcome, 'Encore une sous-tâche', 0, null, 1, $now, $now]);
        $insTask->execute([null, 'Coche-moi : je file dans la section DONE', 1, $now, 1, $now, $now]);

        $messages[] = "Jeu d'exemple ajouté (3 tâches + 1 tag). Tu pourras tout supprimer.";
    } else {
        $messages[] = "La base contient déjà $count tâche(s) : aucun exemple ajouté.";
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation — tasks</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
           max-width: 620px; margin: 3rem auto; padding: 0 1rem; color: #111; line-height: 1.5; }
    .ok { color: #157f3b; } .err { color: #b00020; }
    code { background: #f2f2f2; padding: .1em .35em; border-radius: 4px; }
    a { color: #111; }
  </style>
</head>
<body>
  <h1>Installation de la base</h1>
  <?php if ($error): ?>
    <p class="err"><strong>Échec :</strong> <?= h($error) ?></p>
    <p>Vérifie les identifiants dans <code>config.php</code> puis recharge cette page.</p>
  <?php else: ?>
    <?php foreach ($messages as $m): ?>
      <p class="ok">✓ <?= h($m) ?></p>
    <?php endforeach; ?>
    <p><strong>C'est prêt.</strong> Direction <a href="/tasks">/tasks</a>.</p>
    <p>Par propreté, tu peux maintenant supprimer le fichier <code>install.php</code> du serveur.</p>
  <?php endif; ?>
</body>
</html>
