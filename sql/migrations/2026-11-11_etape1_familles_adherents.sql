-- ============================================================================
-- Migration du 11/11/2026 — étape 1 : familles, adhérents, dossiers
--
-- À exécuter une seule fois sur une base installée avant cette date
-- (phpMyAdmin, onglet SQL). Une nouvelle installation n'en a pas besoin :
-- install.php crée ces tables à partir de sql/structure.sql.
--
-- ATTENTION : si la base contient encore des tables `familles`, `adherents`
-- ou `dossiers` d'une ancienne version, ce script ne les remplace pas
-- (CREATE TABLE IF NOT EXISTS) et l'application ne fonctionnera pas avec.
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `familles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`          VARCHAR(100) NOT NULL,
  `anonymise_le` DATETIME DEFAULT NULL,
  `date_crea`    DATETIME NOT NULL DEFAULT current_timestamp(),
  `creation_par` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_familles_nom` (`nom`),
  KEY `idx_familles_creation_par` (`creation_par`),
  CONSTRAINT `fk_familles_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `adherents` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `est_structure`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = association ou structure : ni famille, ni prenom, ni sexe, ni date de naissance',
  `famille_id`        INT UNSIGNED DEFAULT NULL COMMENT 'NULL pour une structure',
  `nom`               VARCHAR(100) NOT NULL COMMENT 'Nom de famille, ou nom de la structure',
  `prenom`            VARCHAR(100) DEFAULT NULL,
  `sexe`              ENUM('F','H','A') DEFAULT NULL COMMENT 'F = femme, H = homme, A = autre ; NULL pour une structure',
  `date_naissance`    DATE DEFAULT NULL,
  `nationalite_iso3`  VARCHAR(10) DEFAULT NULL COMMENT 'Code du pays (table pays) ; le continent s en deduit',
  `mail`              VARCHAR(150) DEFAULT NULL,
  `tel`               VARCHAR(20) DEFAULT NULL,
  `responsable_nom`   VARCHAR(150) DEFAULT NULL COMMENT 'Nom et prenom du responsable adulte (adherent mineur)',
  `responsable_mail`  VARCHAR(150) DEFAULT NULL,
  `responsable_tel`   VARCHAR(20) DEFAULT NULL,
  `est_benevole`      TINYINT(1) NOT NULL DEFAULT 0,
  `a_consenti`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Consentement aux communications (export Google Contacts)',
  `consentement_le`   DATETIME DEFAULT NULL COMMENT 'Date a laquelle le consentement a ete donne',
  `anonymise_le`      DATETIME DEFAULT NULL,
  `date_crea`         DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`        DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`      INT UNSIGNED NOT NULL,
  `modification_par`  INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adherents_famille` (`famille_id`),
  KEY `idx_adherents_nom` (`nom`, `prenom`),
  KEY `idx_adherents_nationalite` (`nationalite_iso3`),
  KEY `idx_adherents_creation_par` (`creation_par`),
  KEY `idx_adherents_modification_par` (`modification_par`),
  CONSTRAINT `fk_adherents_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles` (`id`),
  CONSTRAINT `fk_adherents_nationalite` FOREIGN KEY (`nationalite_iso3`) REFERENCES `pays` (`iso3`),
  CONSTRAINT `fk_adherents_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_adherents_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_adherents_type` CHECK (
        (`est_structure` = 0 AND `famille_id` IS NOT NULL AND `prenom` IS NOT NULL AND `sexe` IS NOT NULL AND `date_naissance` IS NOT NULL)
     OR (`est_structure` = 1 AND `famille_id` IS NULL AND `prenom` IS NULL AND `sexe` IS NULL AND `date_naissance` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dossiers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `famille_id`       INT UNSIGNED DEFAULT NULL COMMENT 'Dossier d une famille',
  `structure_id`     INT UNSIGNED DEFAULT NULL COMMENT 'Dossier d une structure (adherents.id) : l un ou l autre, jamais les deux',
  `saison_id`        INT UNSIGNED NOT NULL,
  `date_dossier`     DATE NOT NULL,
  `notes`            TEXT DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dossiers_famille_saison` (`famille_id`, `saison_id`),
  UNIQUE KEY `uq_dossiers_structure_saison` (`structure_id`, `saison_id`),
  KEY `idx_dossiers_saison` (`saison_id`),
  KEY `idx_dossiers_creation_par` (`creation_par`),
  KEY `idx_dossiers_modification_par` (`modification_par`),
  CONSTRAINT `fk_dossiers_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles` (`id`),
  CONSTRAINT `fk_dossiers_structure` FOREIGN KEY (`structure_id`) REFERENCES `adherents` (`id`),
  CONSTRAINT `fk_dossiers_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`),
  CONSTRAINT `fk_dossiers_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_dossiers_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_dossiers_titulaire` CHECK ((`famille_id` IS NULL) <> (`structure_id` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dossiers_adherents` (
  `dossier_id`    INT UNSIGNED NOT NULL,
  `adherent_id`   INT UNSIGNED NOT NULL,
  `adresse`       VARCHAR(255) NOT NULL COMMENT 'Numero et voie',
  `complement`    VARCHAR(255) DEFAULT NULL COMMENT 'Batiment, escalier, etage, chez...',
  `code_postal`   CHAR(5) NOT NULL,
  `ville`         VARCHAR(100) NOT NULL,
  `est_validee`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = adresse trouvee au numero pres par l API Adresse (BAN)',
  `latitude`      DECIMAL(9,6) DEFAULT NULL,
  `longitude`     DECIMAL(9,6) DEFAULT NULL,
  `quartier`      VARCHAR(150) DEFAULT NULL COMMENT 'Quartier de la ville (instantane)',
  `est_qp`        TINYINT(1) NOT NULL DEFAULT 0,
  `qp_code`       VARCHAR(50) DEFAULT NULL,
  `qp_nom`        VARCHAR(150) DEFAULT NULL,
  `est_qpv`       TINYINT(1) NOT NULL DEFAULT 0,
  `qpv_code`      VARCHAR(50) DEFAULT NULL,
  `qpv_nom`       VARCHAR(150) DEFAULT NULL,
  `quartiers_le`  DATETIME DEFAULT NULL COMMENT 'Date du calcul des quartiers',
  `etablissement` VARCHAR(150) DEFAULT NULL COMMENT 'Etablissement scolaire pour cette saison',
  `niveau`        VARCHAR(50) DEFAULT NULL COMMENT 'Niveau scolaire pour cette saison',
  `date_crea`     DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`    DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`dossier_id`, `adherent_id`),
  KEY `idx_da_adherent` (`adherent_id`),
  KEY `idx_da_code_postal` (`code_postal`),
  CONSTRAINT `fk_da_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`),
  CONSTRAINT `fk_da_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
