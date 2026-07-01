<?php
declare(strict_types=1);

// Protection par mot de passe, PAR PAGE (liste).
// Les mots de passe sont dans passwords.php : tableau list_key => mot de passe (en clair).
// Une liste absente du tableau (ou fichier absent) = page LIBRE (pas de mot de passe).
// Transmission : sur http le mot de passe n'est pas chiffré → protection « douce »
// tant que le HTTPS (certificat SSL OVH) n'est pas activé.

function page_password(string $list): ?string
{
    static $map = null;
    if ($map === null) {
        $p = __DIR__ . '/passwords.php';
        $loaded = is_file($p) ? require $p : [];
        $map = is_array($loaded) ? $loaded : [];
    }
    $pw = isset($map[$list]) ? (string) $map[$list] : '';
    return $pw !== '' ? $pw : null;
}

function auth_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

// --- « Rester connecté 10 jours » : cookie persistant, sans état serveur. ---------------
// Jeton = "<expiry>.<hmac>", hmac = HMAC-SHA256("liste|expiry", clé = mot de passe de la liste).
// → ne révèle pas le mot de passe ; changer le mot de passe invalide tous les jetons émis.
const REMEMBER_TTL = 10 * 24 * 60 * 60; // 10 jours, en secondes

function remember_cookie_name(string $list): string
{
    return 'todo_keep_' . preg_replace('/[^a-z0-9_-]/i', '', $list);
}

function remember_make_token(string $list, string $password, int $expiry): string
{
    return $expiry . '.' . hash_hmac('sha256', $list . '|' . $expiry, $password);
}

// Vrai si le cookie persistant prouve une authentification encore valide pour cette liste.
function remember_valid(string $list, string $password): bool
{
    $raw = (string) ($_COOKIE[remember_cookie_name($list)] ?? '');
    $dot = strpos($raw, '.');
    if ($dot === false) {
        return false;
    }
    $expiry = (int) substr($raw, 0, $dot);
    if ($expiry <= time()) {
        return false; // jeton expiré
    }
    return hash_equals(remember_make_token($list, $password, $expiry), $raw);
}

function remember_set_cookie(string $list, string $password): void
{
    $expiry = time() + REMEMBER_TTL;
    setcookie(remember_cookie_name($list), remember_make_token($list, $password, $expiry), [
        'expires'  => $expiry,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (($_SERVER['HTTPS'] ?? 'off') !== 'off'),
    ]);
}

// Pour l'API (et tout appel programmatique) : true si la liste est libre ou si déjà authentifié.
function is_page_authed(string $list): bool
{
    $expected = page_password($list);
    if ($expected === null) {
        return true;
    }
    auth_session_start();
    if (!empty($_SESSION['auth'][$list])) {
        return true;
    }
    if (remember_valid($list, $expected)) {
        $_SESSION['auth'][$list] = true; // réhydrate la session depuis le cookie persistant
        return true;
    }
    return false;
}

// Pour une PAGE HTML : si protégée et non authentifiée, affiche le formulaire et STOPPE.
// Doit être appelée avant toute sortie (pour pouvoir démarrer la session / rediriger).
function gate_page(string $list): void
{
    $expected = page_password($list);
    if ($expected === null) {
        return;
    }
    auth_session_start();
    if (!empty($_SESSION['auth'][$list])) {
        return;
    }
    // Cookie « rester connecté 10 jours » encore valide → on relance la session.
    if (remember_valid($list, $expected)) {
        $_SESSION['auth'][$list] = true;
        return;
    }
    $error = false;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['page_password'])) {
        if (hash_equals($expected, (string) $_POST['page_password'])) {
            $_SESSION['auth'][$list] = true;
            if (!empty($_POST['remember'])) {
                remember_set_cookie($list, $expected); // persiste 10 jours sur cet appareil
            }
            $uri = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
            header('Location: ' . $uri);
            exit;
        }
        $error = true;
    }
    render_password_form($list, $error);
    exit;
}

function render_password_form(string $list, bool $error): void
{
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    $h = htmlspecialchars($list, ENT_QUOTES, 'UTF-8');
    ?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $h ?> — accès protégé</title>
  <style>
    html, body { height: 100%; margin: 0; }
    body { display: flex; align-items: center; justify-content: center; min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: #fff; color: #111; }
    .lock { display: flex; flex-direction: column; align-items: center; gap: .8rem; padding: 0 1rem; width: min(340px, 90vw); }
    .lock h1 { margin: 0; font-size: 2rem; letter-spacing: -.02em; }
    .lock p { margin: 0; color: #777; font-size: .9rem; text-align: center; }
    .lock form { display: flex; flex-direction: column; gap: 8px; width: 100%; margin-top: .4rem; }
    .lock .row { display: flex; gap: 8px; }
    .lock input[type="password"] { flex: 1; padding: 10px 12px; font-size: 1rem; border: 1px solid #e6e6e6; border-radius: 8px; }
    .lock input[type="password"]:focus { outline: none; border-color: #111; }
    .lock .keep { display: flex; align-items: center; gap: 6px; color: #777; font-size: .82rem; cursor: pointer; user-select: none; }
    .lock .keep input { accent-color: #111; cursor: pointer; }
    .lock button { padding: 0 16px; border: 1px solid #111; background: #111; color: #fff; border-radius: 8px; cursor: pointer; font: inherit; }
    .lock .err { color: #b00020; }
    .lock a { color: #777; font-size: .8rem; text-decoration: none; border-bottom: 1px solid #ccc; margin-top: .4rem; }
  </style>
</head>
<body>
  <main class="lock">
    <h1>🔒 <?= $h ?></h1>
    <p>Cette page est protégée par un mot de passe.</p>
    <?php if ($error): ?><p class="err">Mot de passe incorrect.</p><?php endif; ?>
    <form method="post">
      <div class="row">
        <input type="password" name="page_password" placeholder="Mot de passe" autofocus required>
        <button type="submit">Entrer</button>
      </div>
      <label class="keep">
        <input type="checkbox" name="remember" value="1">
        Rester connecté 10 jours sur cet appareil
      </label>
    </form>
    <a href="/">← todo.shoette.com</a>
  </main>
</body>
</html><?php
}
