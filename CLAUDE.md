# Projet — todo.shoette.com

Dossier de travail de l'app **todo.shoette.com** (hébergement OVH mutualisé d'Elena).

## Dépôt Git
- Remote : **`git@github.com:LNA55/todo.shoette.git`** (branche `main`, accès SSH OK).
- Source de vérité du code. Le **déploiement reste séparé** (FTP, voir ci-dessous) : pousser sur GitHub ne met **pas** le site en ligne.
- `config.php` (identifiants MySQL) est dans `.gitignore` → **jamais commité**.

## Déploiement
Utiliser le skill **`shoette-deploy`** (skill utilisateur global, `~/.claude/skills/shoette-deploy/`).
- Connexion : `lftp shoette` (bookmark déjà configuré ; identifiants jamais en clair).
- Le sous-domaine `todo.shoette.com` est servi par le dossier FTP **`/todo`**.
- Le serveur OVH ne supporte que le **FTP simple** (pas de FTPS) → ajouter `set ftp:ssl-allow no`.
- Déployer un fichier : `lftp shoette -e "set ftp:ssl-allow no; cd /todo; put index.html; bye"`.
- ⚠️ **Ne jamais téléverser `CLAUDE.md` ni `.claude/`** (présents en local à la racine du projet). Déployer uniquement `index.html` (put) + le dossier `tasks/` (mirror -R local `tasks` → `/todo/tasks`).

## Stack (décidé le 2026-06-14)
- **PHP + MySQL** (PDO), front **HTML/CSS/JS « vanilla »** sans framework ni build → déployable par simple FTP.
- Page d'accueil `index.html` : titre + lien `tasks` (style calqué sur projet.shoette.com) → mène à `/tasks`.
- App dans le dossier `tasks/` (URL `todo.shoette.com/tasks`) :
  - `index.php` (coquille) · `assets/app.js` + `assets/style.css` (front) · `api.php` (API JSON, actions via `?action=`) · `db.php` (PDO) · `schema.sql` · `install.php` (création tables + seed, à supprimer après) · `config.php` (identifiants MySQL, **non versionné**, copié depuis `config.sample.php`).
- BDD : tables `tasks` (`parent_id` imbrication ≤ 6 niveaux, `position`, `done`/`done_at`, `collapsed`, `hidden`, `owner_id` **nullable**), `tags`, `task_tags`. Tables `users` et `task_events` (journal « qui coche quoi/quand ») **prévues mais non créées** — l'ajout ne cassera rien.
- Aujourd'hui : **pas d'auth**, page publique et éditable par tous (choix d'Elena). À terme : comptes utilisateurs (admin), vues par utilisateur, journal des actions.

## Pages-listes multiples (depuis 2026-06-16)
- Chaque page (`/tasks`, `/papa`, …) = **une liste dédiée**, données séparées par la colonne `list_key` (tables `tasks` et `tags`).
- **Template partagé** `tasks/page.php` (`$LIST_KEY` + `$PAGE_TITLE`) ; **API partagée** `tasks/api.php` scopée par `?list=` ; front partagé `tasks/assets/`. Une page = un `<slug>/index.php` d'une ligne incluant `../tasks/page.php`.
- En-tête « façon page d'accueil » (grand titre centré) pour toutes les pages-listes.
- **Créer une nouvelle page** : skill **`todo-new-page`** (global). En redéployant `tasks/`, EXCLURE les scripts admin : `--exclude-glob 'install.php' --exclude-glob 'migrate*.php' --exclude-glob 'seed_*.php'`.

## État au 2026-06-16
- ✅ **Déployé et en ligne** : http://todo.shoette.com (accueil + lien `tasks`), http://todo.shoette.com/tasks, et http://todo.shoette.com/papa (liste « papa » pré-remplie de 19 questions).
- Base MySQL OVH créée : base/utilisateur `shoettetodo`, hôte `shoettetodo.mysql.db` (serveur physique `mysql638.eu004`). Identifiants dans `tasks/config.php` (local + serveur, **non versionné**).
- Tables créées via `install.php`, qui a ensuite été **supprimé du serveur** (ne reste qu'en local). Jeu d'exemple (3 tâches + 1 tag) présent en base, supprimable depuis l'app.
- `https://` **pas encore actif** : certificat SSL du sous-domaine à activer dans l'espace OVH (manuel, hors FTP).
- À terme : comptes utilisateurs (admin) + journal « qui coche quoi/quand » (schéma déjà prêt, tables non créées).
