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

## État au 2026-06-14
- Code de l'app **écrit en local**, relu (pas de lint possible : ni php ni node sur la machine). **Pas encore déployé.**
- **En attente** : Elena crée la base MySQL côté OVH puis fournit hôte / nom de base / utilisateur / mot de passe → remplir `config.php`, déployer, lancer `install.php`, tester.
- En ligne actuellement : `index.html` placeholder (pas encore remplacé). **http://todo.shoette.com** (le `https://` échoue tant que le certificat SSL du sous-domaine n'est pas activé côté OVH).
