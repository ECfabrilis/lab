-- ============================================================================
-- Migration du 13/11/2026 — étape 3 : cotisations, réductions, paiements
--
-- À exécuter une seule fois, APRÈS 2026-11-13_attente_le.sql (phpMyAdmin,
-- onglet SQL, serveur MariaDB). Une nouvelle installation n'en a pas besoin :
-- sql/structure.sql contient déjà ces tables.
--
--   cotisations : adhésion d'une personne (individuelle), d'une famille
--                 (familiale) ou d'une structure, pour une saison.
--   reductions  : montant retiré du montant dû, pour une personne ou pour
--                 toute la famille.
--   paiements   : sommes encaissées ; un paiement s'annule, il ne se supprime pas.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `cotisations` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`       INT UNSIGNED NOT NULL,
  `adherent_id`      INT UNSIGNED DEFAULT NULL COMMENT 'NULL pour une cotisation familiale',
  `tarif_id`         INT UNSIGNED DEFAULT NULL COMMENT 'Tarif adhesion choisi ; NULL = montant saisi (structure)',
  `forme`            ENUM('individuelle','familiale') NOT NULL,
  `montant`          DECIMAL(8,2) NOT NULL COMMENT 'Copie du tarif au moment de la cotisation, ou montant negocie',
  `date_cotisation`  DATE NOT NULL,
  `est_annulee`      TINYINT(1) NOT NULL DEFAULT 0,
  `annulee_le`       DATETIME DEFAULT NULL,
  `annulation_par`   INT UNSIGNED DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cotisations_dossier` (`dossier_id`, `est_annulee`),
  KEY `idx_cotisations_adherent` (`adherent_id`),
  KEY `idx_cotisations_tarif` (`tarif_id`),
  KEY `idx_cotisations_annulation_par` (`annulation_par`),
  KEY `idx_cotisations_creation_par` (`creation_par`),
  KEY `idx_cotisations_modification_par` (`modification_par`),
  CONSTRAINT `fk_cotisations_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`),
  CONSTRAINT `fk_cotisations_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`),
  CONSTRAINT `fk_cotisations_tarif` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`),
  CONSTRAINT `fk_cotisations_annulation_par` FOREIGN KEY (`annulation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_cotisations_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_cotisations_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_cotisations_montant` CHECK (`montant` >= 0),
  CONSTRAINT `ck_cotisations_forme` CHECK (
        (`forme` = 'familiale' AND `adherent_id` IS NULL)
     OR (`forme` = 'individuelle' AND `adherent_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reductions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`   INT UNSIGNED NOT NULL,
  `adherent_id`  INT UNSIGNED DEFAULT NULL COMMENT 'NULL = reduction pour toute la famille',
  `montant`      DECIMAL(8,2) NOT NULL,
  `date_crea`    DATETIME NOT NULL DEFAULT current_timestamp(),
  `creation_par` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reductions_dossier` (`dossier_id`),
  KEY `idx_reductions_adherent` (`adherent_id`),
  KEY `idx_reductions_creation_par` (`creation_par`),
  CONSTRAINT `fk_reductions_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`),
  CONSTRAINT `fk_reductions_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`),
  CONSTRAINT `fk_reductions_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_reductions_montant` CHECK (`montant` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `paiements` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`       INT UNSIGNED NOT NULL,
  `montant`          DECIMAL(8,2) NOT NULL,
  `date_paiement`    DATE NOT NULL,
  `mode`             ENUM('especes','cheque','cb','autre') NOT NULL,
  `objet`            ENUM('adhesion','activites') NOT NULL COMMENT 'Ce que regle le paiement (distinction demandee par la comptabilite)',
  `note`             VARCHAR(255) DEFAULT NULL COMMENT 'Numero de cheque, banque, precision',
  `est_annule`       TINYINT(1) NOT NULL DEFAULT 0,
  `annule_le`        DATETIME DEFAULT NULL,
  `annulation_par`   INT UNSIGNED DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_paiements_dossier` (`dossier_id`, `est_annule`),
  KEY `idx_paiements_date` (`date_paiement`),
  KEY `idx_paiements_annulation_par` (`annulation_par`),
  KEY `idx_paiements_creation_par` (`creation_par`),
  KEY `idx_paiements_modification_par` (`modification_par`),
  CONSTRAINT `fk_paiements_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_paiements_annulation_par` FOREIGN KEY (`annulation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_paiements_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_paiements_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_paiements_montant` CHECK (`montant` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
