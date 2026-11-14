-- ============================================================================
-- Migration du 14/11/2026 — étape 6 : statistiques
--
-- Groupes de lieu de résidence, pour la répartition des adhérents par lieu de
-- résidence (Paramètres > Options > Statistiques).
--   groupes_residence       : un groupe (« Paris 17e », « Arrondissements
--                             proches »...), affiché selon son rang (ordre).
--   groupes_residence_codes : les codes postaux du groupe. Un code est un code
--                             postal complet (75017) ou un début de code (75 :
--                             tout Paris). Un code n'appartient qu'à un groupe ;
--                             le code le plus précis l'emporte.
-- Une adresse qui ne correspond à aucun code est comptée dans « Autres ».
--
-- Les tranches d'âge ne demandent pas de table : elles sont dans le réglage
-- « stats_tranches_age » de la table parametres.
--
-- À passer une seule fois, sur la base MariaDB, avant de déployer le code.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `groupes_residence` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`       VARCHAR(100) NOT NULL,
  `ordre`     SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Rang d affichage (du plus petit au plus grand)',
  `date_crea` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_groupes_residence_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groupes_residence_codes` (
  `code`      VARCHAR(5) NOT NULL COMMENT 'Code postal complet (75017) ou debut de code (75)',
  `groupe_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`code`),
  KEY `idx_grc_groupe` (`groupe_id`),
  CONSTRAINT `fk_grc_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `groupes_residence` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `groupes_residence` (`id`, `nom`, `ordre`) VALUES
  (1, 'Paris 17e', 1),
  (2, 'Arrondissements proches (8e, 9e, 16e, 18e)', 2),
  (3, 'Autres arrondissements de Paris', 3),
  (4, 'Communes limitrophes (Clichy, Saint-Ouen, Levallois)', 4);

INSERT IGNORE INTO `groupes_residence_codes` (`code`, `groupe_id`) VALUES
  ('75017', 1),
  ('75008', 2), ('75009', 2), ('75016', 2), ('75018', 2),
  ('75', 3),
  ('92110', 4), ('93400', 4), ('92300', 4);
