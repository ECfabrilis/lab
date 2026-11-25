-- ============================================================================
-- Migration du 25/11/2026 : suivi des migrations (version 0.14.0)
--
-- Table migrations : une ligne par fichier de sql/migrations/ déjà passé.
-- L'application compare cette table aux fichiers présents et applique ceux
-- qui manquent depuis Paramètres > La base de données > Mise à jour.
--
-- C'est la dernière migration qui peut se passer à la main : la page « Mise à
-- jour » la passe elle-même quand la table n'existe pas encore, puis inscrit
-- les migrations antérieures comme déjà passées.
-- Relançable sans risque.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `migrations` (
  `fichier`       VARCHAR(150) NOT NULL COMMENT 'Nom du fichier de sql/migrations/',
  `appliquee_le`  DATETIME NOT NULL DEFAULT current_timestamp(),
  `appliquee_par` INT UNSIGNED DEFAULT NULL,
  `est_reprise`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = inscrite sans etre rejouee (installation, ou passee a la main avant la version 0.14)',
  PRIMARY KEY (`fichier`),
  KEY `idx_migrations_appliquee_par` (`appliquee_par`),
  CONSTRAINT `fk_migrations_appliquee_par` FOREIGN KEY (`appliquee_par`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
