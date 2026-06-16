<?php
// Template partagé d'une page-liste (réutilisé par /tasks, /papa, ...).
// L'appelant définit AVANT le require :
//   $LIST_KEY  : clé de la liste (scope des données), ex. 'papa'
//   $PAGE_TITLE: titre affiché (façon page d'accueil), ex. 'papa'
$LIST_KEY   = isset($LIST_KEY) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $LIST_KEY) : 'tasks';
if ($LIST_KEY === '') { $LIST_KEY = 'tasks'; }
$PAGE_TITLE = isset($PAGE_TITLE) && $PAGE_TITLE !== '' ? (string) $PAGE_TITLE : $LIST_KEY;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8') ?> — todo.shoette.com</title>
  <link rel="stylesheet" href="/tasks/assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: '1' ?>">
  <script>window.TODO_LIST = <?= json_encode($LIST_KEY, JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body class="listpage">
  <header class="page-head">
    <a class="home" href="/">← todo.shoette.com</a>
    <h1 class="page-title"><?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8') ?></h1>
  </header>

  <div class="toolbar">
    <form id="add-form" class="add">
      <input id="add-input" type="text" placeholder="Nouvelle tâche…" autocomplete="off">
      <button type="submit">Ajouter</button>
    </form>

    <div class="controls">
      <label class="ctl">Profondeur
        <select id="depth">
          <option value="1">Niv 1</option>
          <option value="2">Niv 2</option>
          <option value="3">Niv 3</option>
          <option value="4">Niv 4</option>
          <option value="5">Niv 5</option>
          <option value="6" selected>Niv 6</option>
          <option value="all">Tout</option>
        </select>
      </label>

      <div class="ctl">
        <span class="ctl-label">Tags :</span>
        <div id="tag-filter" class="chips"></div>
      </div>

      <div class="ctl hidden-menu">
        <button id="hidden-btn" type="button" class="linkbtn">Masqués (<span id="hidden-count">0</span>)</button>
        <div id="hidden-pop" class="popover" hidden></div>
      </div>

      <button id="manage-tags" type="button" class="linkbtn">Gérer les tags</button>
    </div>
  </div>

  <main>
    <ul id="active-list" class="tree"></ul>

    <section id="done-section" style="display:none">
      <h2>DONE</h2>
      <ul id="done-list" class="tree done"></ul>
    </section>
  </main>

  <!-- Popover d'affectation des tags à une tâche -->
  <div id="task-tag-pop" class="popover" hidden></div>

  <!-- Gestionnaire de tags -->
  <dialog id="tag-dialog">
    <form method="dialog" class="dialog-head">
      <h3>Tags</h3>
      <button value="close" class="x" aria-label="Fermer">✕</button>
    </form>
    <ul id="tag-editor" class="tag-editor"></ul>
    <form id="tag-add-form" class="tag-add">
      <input type="color" id="new-tag-color" value="#4f86ff" aria-label="Couleur du tag">
      <input type="text" id="new-tag-name" placeholder="Nouveau tag…" maxlength="80" autocomplete="off">
      <button type="submit">Ajouter</button>
    </form>
  </dialog>

  <div id="toast" class="toast" hidden></div>

  <script src="/tasks/assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: '1' ?>" defer></script>
</body>
</html>
