-- Schéma de la base — todo.shoette.com / pages-listes (tasks, papa, ...)
-- Encodage utf8mb4 (emojis OK). Moteur InnoDB (clés étrangères + cascade).
-- Idempotent : peut être rejoué sans risque (CREATE TABLE IF NOT EXISTS).
-- list_key = scope d'une liste dédiée (une page = une valeur de list_key).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id  INT UNSIGNED NULL,
    title      TEXT NOT NULL,
    done       TINYINT(1) NOT NULL DEFAULT 0,
    done_at    DATETIME NULL,
    collapsed  TINYINT(1) NOT NULL DEFAULT 0,
    hidden     TINYINT(1) NOT NULL DEFAULT 0,
    position   INT NOT NULL DEFAULT 0,
    list_key   VARCHAR(64) NOT NULL DEFAULT 'tasks',
    note       TEXT NULL,
    note_color VARCHAR(16) NOT NULL DEFAULT 'black',
    owner_id   INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_parent (parent_id),
    KEY idx_done (done),
    KEY idx_tasks_list (list_key),
    CONSTRAINT fk_task_parent FOREIGN KEY (parent_id)
        REFERENCES tasks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80) NOT NULL,
    color      VARCHAR(7) NOT NULL DEFAULT '#cccccc',
    list_key   VARCHAR(64) NOT NULL DEFAULT 'tasks',
    owner_id   INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tags_list (list_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_tags (
    task_id INT UNSIGNED NOT NULL,
    tag_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (task_id, tag_id),
    KEY idx_tag (tag_id),
    CONSTRAINT fk_tt_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_tag  FOREIGN KEY (tag_id)  REFERENCES tags  (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------
-- Prévu pour plus tard (NON créé aujourd'hui), sans casser l'existant :
--   users       : comptes (admin, ...). tasks.owner_id / tags.owner_id y pointeront.
--   task_events : journal « qui a coché quoi et quand »
--                 (id, task_id, user_id, action, created_at).
-- ------------------------------------------------------------------
