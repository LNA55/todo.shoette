<?php
declare(strict_types=1);

// Sert l'image d'un document — MAIS protégée par le mot de passe de la liste.
// Les fichiers réels sont dans tasks/uploads/<list>/ (accès direct bloqué par .htaccess) :
// la seule façon de les voir passe par ici, après authentification.
//   media.php?list=sante&id=12          → image originale
//   media.php?list=sante&id=12&thumb=1  → vignette

require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

$list  = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_GET['list'] ?? '')));
$id    = (int) ($_GET['id'] ?? 0);
$thumb = !empty($_GET['thumb']);

if ($list === '' || $id <= 0) {
    http_response_code(404);
    exit;
}
if (!is_page_authed($list)) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Accès protégé.');
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT mime FROM documents WHERE id = ? AND list_key = ?');
    $st->execute([$id, $list]);
    $doc = $st->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    exit;
}
if (!$doc) {
    http_response_code(404);
    exit;
}

$dir  = __DIR__ . '/uploads/' . $list;
$path = null;
$mime = 'image/jpeg';

if ($thumb) {
    $path = $dir . '/' . $id . '.thumb.jpg';
} else {
    // l'original est <id>.<ext> (on ignore la vignette <id>.thumb.jpg)
    foreach (glob($dir . '/' . $id . '.*') ?: [] as $f) {
        if (substr($f, -10) === '.thumb.jpg') {
            continue;
        }
        $path = $f;
        break;
    }
    $mime = (string) ($doc['mime'] ?: 'image/jpeg');
}

if ($path === null || !is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
