<?php
// Page "vault" : administration des documents de SANTÉ (list = sante).
// Page sur-mesure (pas la liste de tâches partagée). Authentifiée avec le mot de passe santé,
// car elle lit/écrit les documents et images de la liste `sante`.
require __DIR__ . '/../../tasks/auth.php';
gate_page('sante');

$ASSET   = '/tasks/assets';
$assetMt = static function (string $f): string {
    return (string) (@filemtime(__DIR__ . '/../../tasks/assets/' . $f) ?: '1');
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Coffre santé — todo.shoette.com</title>
  <link rel="stylesheet" href="<?= $ASSET ?>/vault.css?v=<?= $assetMt('vault.css') ?>">
  <script>window.VAULT_LIST = 'sante';</script>
</head>
<body class="vault">
  <header class="vault-head">
    <a class="home" href="/">← todo.shoette.com</a>
    <h1>Coffre santé</h1>
    <p class="lead">Tes documents médicaux : prescriptions, traductions et suivi de tes analyses.</p>
  </header>

  <main class="vault-main">

    <!-- Bloc 1 — Ordonnances & prescriptions -->
    <section class="block">
      <h2>Ordonnances &amp; prescriptions</h2>
      <p class="block-sub">Le statut est modifiable. Une analyse-résultat reliée le passe à « Done ».</p>
      <div id="rx-wrap"><div class="empty">Chargement…</div></div>
    </section>

    <!-- Bloc 2 — Documents (galerie + visionneuse) -->
    <section class="block">
      <h2>Documents</h2>
      <p class="block-sub">Triés par date du document, le plus récent en premier. Clique une vignette pour l'ouvrir.</p>
      <div id="thumbs" class="thumbs"><div class="empty">Chargement…</div></div>
      <div id="viewer" class="viewer" hidden>
        <div class="viewer-img">
          <a id="viewer-link" href="#" target="_blank" rel="noopener"><img id="viewer-image" alt="Document"></a>
        </div>
        <div class="viewer-tr">
          <div class="viewer-meta">
            <span id="viewer-date" class="v-date"></span>
            <span id="viewer-type" class="v-type"></span>
          </div>
          <h3 id="viewer-name">Document</h3>
          <h4 class="tr-title">Traduction française</h4>
          <div id="viewer-translation" class="translation"></div>
        </div>
      </div>
    </section>

    <!-- Bloc 3 — Résultats d'analyses dans le temps -->
    <section class="block">
      <h2>Résultats d'analyses dans le temps</h2>
      <div class="legend">
        <span><i class="dot dot-doc"></i> Référence inscrite sur le document</span>
        <span><i class="dot dot-mine"></i> Référence ajoutée (profil préménopause — à vérifier)</span>
      </div>
      <div id="lab-wrap"><div class="empty">Chargement…</div></div>
    </section>

    <!-- Bloc 4 — Analyse d'ensemble -->
    <section class="block">
      <h2>Analyse d'ensemble</h2>
      <div id="note" class="note"><div class="empty">Chargement…</div></div>
      <p class="disclaimer">Outil de suivi personnel — ceci n'est pas un avis médical. À recouper avec tes médecins, qui assurent ton suivi.</p>
    </section>

  </main>

  <div id="toast" class="toast" hidden></div>
  <script src="<?= $ASSET ?>/vault.js?v=<?= $assetMt('vault.js') ?>" defer></script>
</body>
</html>
