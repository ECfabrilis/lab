-- ============================================================================
-- Migration du 14/11/2026 — types de tarif et modes de paiement modifiables
--
-- À exécuter une seule fois, APRÈS 2026-11-14_forfaits.sql (phpMyAdmin, onglet
-- SQL, serveur MariaDB). Une nouvelle installation n'en a pas besoin :
-- sql/structure.sql contient déjà ces tables.
--
--   types_tarif    : liste des types de tarif, commune à toutes les saisons,
--                    gérée par un administrateur (Paramètres, « Options des
--                    paiements »). « Adhésion » et « Forfait » ont un
--                    comportement particulier (colonne comportement) : ils
--                    sont figés. Les autres types sont libres.
--   tarifs         : la colonne type (liste figée) est remplacée par type_id.
--   modes_paiement : liste des modes de paiement proposés à la saisie d'un
--                    paiement, gérée de la même façon.
--   paiements      : la colonne mode (liste figée) est remplacée par mode_id.
--
-- Lignes déjà présentes : chaque tarif et chaque paiement garde son type ou
-- son mode, qui devient une ligne des nouvelles tables.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Types de tarif
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `types_tarif` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`          VARCHAR(100) NOT NULL,
  `comportement` ENUM('adhesion','forfait') DEFAULT NULL COMMENT 'Type fige : adhesion = reserve aux cotisations ; forfait = ne se paie qu une fois. NULL = type libre',
  `est_actif`    TINYINT(1) NOT NULL DEFAULT 1,
  `date_crea`    DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_types_tarif_nom` (`nom`),
  UNIQUE KEY `uq_types_tarif_comportement` (`comportement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `types_tarif` (`id`, `nom`, `comportement`) VALUES
  (1, 'Adhésion', 'adhesion'),
  (2, 'Forfait', 'forfait'),
  (3, 'Autre', NULL);

ALTER TABLE `tarifs`
  ADD COLUMN `type_id` INT UNSIGNED DEFAULT NULL AFTER `nom`;

UPDATE `tarifs`
   SET `type_id` = CASE `type` WHEN 'adhesion' THEN 1 WHEN 'forfait' THEN 2 ELSE 3 END,
       `date_modif` = `date_modif`;

ALTER TABLE `tarifs`
  MODIFY `type_id` INT UNSIGNED NOT NULL,
  DROP COLUMN `type`,
  ADD KEY `idx_tarifs_type` (`type_id`),
  ADD CONSTRAINT `fk_tarifs_type` FOREIGN KEY (`type_id`) REFERENCES `types_tarif` (`id`);

-- ---------------------------------------------------------------------------
-- Modes de paiement
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `modes_paiement` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`       VARCHAR(100) NOT NULL,
  `est_actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_crea` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modes_paiement_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `modes_paiement` (`id`, `nom`) VALUES
  (1, 'Espèces'),
  (2, 'Chèque'),
  (3, 'Carte bancaire'),
  (4, 'Autre');

ALTER TABLE `paiements`
  ADD COLUMN `mode_id` INT UNSIGNED DEFAULT NULL AFTER `date_paiement`;

UPDATE `paiements`
   SET `mode_id` = CASE `mode` WHEN 'especes' THEN 1 WHEN 'cheque' THEN 2 WHEN 'cb' THEN 3 ELSE 4 END,
       `date_modif` = `date_modif`;

ALTER TABLE `paiements`
  MODIFY `mode_id` INT UNSIGNED NOT NULL,
  DROP COLUMN `mode`,
  ADD KEY `idx_paiements_mode` (`mode_id`),
  ADD CONSTRAINT `fk_paiements_mode` FOREIGN KEY (`mode_id`) REFERENCES `modes_paiement` (`id`);
