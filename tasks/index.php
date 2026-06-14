<?php // Coquille HTML de l'app. Aucune logique serveur ici : tout passe par api.php. ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>tasks — todo.shoette.com</title>
  <link rel="stylesheet" href="/tasks/assets/style.css">
</head>
<body>
  <header class="topbar">
    <a class="home" href="/">← todo.shoette.com</a>
    <h1>tasks</h1>
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

  <script src="/tasks/assets/app.js" defer></script>
</body>
</html>
