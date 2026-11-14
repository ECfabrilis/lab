-- ============================================================================
-- Migration du 14/11/2026 — récurrences des activités modifiables
--
-- À exécuter une seule fois, APRÈS 2026-11-14_dons_quartiers.sql
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : sql/structure.sql contient déjà cette table et cette colonne.
--
--   recurrences : nouvelle liste commune à toutes les saisons, gérée comme les
--                 types d'activité (Paramètres > Options > Options des
--                 activités). Elle reçoit les trois valeurs d'origine.
--   activites   : la colonne recurrence (liste figée) est remplacée par
--                 recurrence_id. Chaque activité garde sa récurrence.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `recurrences` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`       VARCHAR(100) NOT NULL,
  `est_actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_crea` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recurrences_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `recurrences` (`id`, `nom`) VALUES
  (1, 'Ponctuel'),
  (2, 'Régulier'),
  (3, 'Vacances');

ALTER TABLE `activites`
  ADD COLUMN `recurrence_id` INT UNSIGNED DEFAULT NULL AFTER `recurrence`;

UPDATE `activites`
   SET `recurrence_id` = CASE `recurrence` WHEN 'ponctuel' THEN 1 WHEN 'regulier' THEN 2 ELSE 3 END,
       `date_modif` = `date_modif`;

ALTER TABLE `activites`
  MODIFY `recurrence_id` INT UNSIGNED NOT NULL,
  DROP COLUMN `recurrence`,
  ADD KEY `idx_activites_recurrence` (`recurrence_id`),
  ADD CONSTRAINT `fk_activites_recurrence` FOREIGN KEY (`recurrence_id`) REFERENCES `recurrences` (`id`);
