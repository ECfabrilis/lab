-- ============================================================================
-- bddv3 : structure de la base — schéma de référence
-- Cible : MariaDB 11.8, InnoDB, utf8mb4 / utf8mb4_unicode_ci
--
-- État : étape 0 (socle). Les étapes suivantes ajoutent leurs tables par un
-- fichier daté dans sql/migrations/, puis ce fichier est mis à jour.
--
-- Ce script est exécuté par install.php (étape 3). Il ne supprime rien :
-- chaque table est créée seulement si elle n'existe pas.
--
-- Conventions
--   * identifiants : INT UNSIGNED AUTO_INCREMENT
--   * booléens : TINYINT(1) (0 = non, 1 = oui), préfixe est_ / a_
--   * listes courtes : ENUM en minuscules, sans accent ni espace
--   * un utilisateur se désactive (est_actif = 0), il ne se supprime pas
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. Utilisateurs de l'application
--    token : sert à la fois à l'invitation (7 jours) et au mot de passe
--    oublié (1 heure). Seul le hash du jeton est conservé.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prenom`                VARCHAR(100) NOT NULL,
  `mail`                  VARCHAR(150) NOT NULL,
  `mdp`                   VARCHAR(255) NOT NULL COMMENT 'Hash password_hash()',
  `role`                  ENUM('admin','standard','lecteur') NOT NULL DEFAULT 'lecteur',
  `est_actif`             TINYINT(1) NOT NULL DEFAULT 1,
  `tentatives_echec`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Echecs de connexion consecutifs',
  `verrouille_jusqua`     DATETIME DEFAULT NULL COMMENT 'Compte bloque jusqu a cette date',
  `token`                 VARCHAR(64) DEFAULT NULL COMMENT 'Hash SHA-256 (hex) du jeton d invitation ou de reinitialisation',
  `date_expiration_token` DATETIME DEFAULT NULL,
  `derniere_connexion`    DATETIME DEFAULT NULL,
  `date_crea`             DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utilisateurs_mail` (`mail`),
  KEY `idx_utilisateurs_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Paramètres de l'application (une ligne par réglage)
--    Réglages modifiables par un administrateur : nom_association,
--    adresse_association, logo... Les réglages techniques et secrets restent
--    dans config.php. Lecture côté PHP : parametre('cle', 'defaut').
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parametres` (
  `cle`              VARCHAR(50) NOT NULL,
  `valeur`           VARCHAR(255) DEFAULT NULL,
  `date_modif`       DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`cle`),
  KEY `idx_parametres_modification_par` (`modification_par`),
  CONSTRAINT `fk_parametres_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Saisons
--    Pas de chevauchement, une seule saison à venir, durée maximale 2 ans :
--    ces règles sont vérifiées en PHP (includes/fonctions_saisons.php).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saisons` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`        VARCHAR(50) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin`   DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saisons_nom` (`nom`),
  CONSTRAINT `ck_saisons_dates` CHECK (`date_debut` < `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Journal des actions (conservé 1 an)
--    utilisateur_id est NULL quand l'action n'a pas d'auteur connu
--    (connexion échouée, demande de mot de passe oublié, installation).
--    Ne jamais y enregistrer mdp ni token.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs_bdd` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilisateur_id`    INT UNSIGNED DEFAULT NULL,
  `date_action`       DATETIME NOT NULL DEFAULT current_timestamp(),
  `type_action`       VARCHAR(20) NOT NULL,
  `nom_table`         VARCHAR(50) NOT NULL,
  `id_enregistrement` INT UNSIGNED DEFAULT NULL,
  `description`       VARCHAR(255) NOT NULL,
  `donnee_ancienne`   JSON DEFAULT NULL,
  `donnee_nouvelle`   JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_logs_utilisateur` (`utilisateur_id`),
  KEY `idx_logs_date` (`date_action`),
  KEY `idx_logs_enregistrement` (`nom_table`, `id_enregistrement`),
  CONSTRAINT `fk_logs_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Référentiel des pays (données : sql/donnees_reference.sql)
--    continent et region servent aux statistiques par grande zone.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pays` (
  `iso3`        VARCHAR(10) NOT NULL,
  `iso2`        VARCHAR(2) DEFAULT NULL,
  `name_fr`     VARCHAR(150) NOT NULL,
  `name_en`     VARCHAR(150) DEFAULT NULL,
  `name_native` VARCHAR(150) DEFAULT NULL,
  `flag`        VARCHAR(255) DEFAULT NULL,
  `wikidata`    VARCHAR(20) DEFAULT NULL,
  `continent`   VARCHAR(50) DEFAULT NULL,
  `region`      VARCHAR(100) DEFAULT NULL COMMENT 'Region statistique ; NULL si non classe',
  `is_ue`       TINYINT(1) DEFAULT 0,
  `is_g7`       TINYINT(1) DEFAULT 0,
  `is_g20`      TINYINT(1) DEFAULT 0,
  `is_brics`    TINYINT(1) DEFAULT 0,
  `is_oecd`     TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`iso3`),
  UNIQUE KEY `uq_pays_name_fr` (`name_fr`),
  KEY `idx_pays_region` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Référentiels géographiques (données : sql/donnees_reference.sql)
--    Trois notions distinctes, conservées séparément :
--      quartiers              : quartiers de la ville
--      quartiers_prioritaires : quartiers prioritaires (QP)
--      quartiers_qpv          : quartiers de la politique de la ville (QPV)
--    Les adresses saisies en gardent un instantané (texte) : ces tables
--    peuvent être mises à jour sans toucher aux adresses déjà enregistrées.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quartiers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commune`        VARCHAR(100) NOT NULL,
  `arrondissement` TINYINT UNSIGNED NOT NULL,
  `nom`            VARCHAR(150) NOT NULL,
  `geom`           GEOMETRY NOT NULL,
  PRIMARY KEY (`id`),
  SPATIAL KEY `idx_spatial_quartiers` (`geom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quartiers_prioritaires` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commune`        VARCHAR(100) NOT NULL,
  `arrondissement` TINYINT UNSIGNED NOT NULL,
  `code_quartier`  VARCHAR(50) NOT NULL,
  `nom`            VARCHAR(150) NOT NULL,
  `geom`           GEOMETRY NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qp_code` (`code_quartier`),
  SPATIAL KEY `idx_spatial_qp` (`geom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quartiers_qpv` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commune`       VARCHAR(100) NOT NULL,
  `code_quartier` VARCHAR(50) NOT NULL,
  `nom`           VARCHAR(150) NOT NULL,
  `geom`          GEOMETRY NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qpv_code` (`code_quartier`),
  SPATIAL KEY `idx_spatial_qpv` (`geom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Base officielle des codes postaux (La Poste)
--    Sert à proposer le code postal dans les formulaires.
--    Remplie par install.php à partir de sql/019HexaSmal.csv.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `codes_postaux` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_postal`           CHAR(5) NOT NULL,
  `code_insee`            VARCHAR(5) NOT NULL COMMENT 'Code commune INSEE (sert a filtrer l API Adresse)',
  `commune`               VARCHAR(100) NOT NULL COMMENT 'Nom de la commune (majuscules, sans accent, comme La Poste)',
  `libelle_acheminement`  VARCHAR(100) NOT NULL,
  `ligne_5`               VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Lieu-dit ou commune deleguee, vide sinon',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cp_code_insee_ligne5` (`code_postal`, `code_insee`, `ligne_5`),
  KEY `idx_cp_commune` (`commune`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
