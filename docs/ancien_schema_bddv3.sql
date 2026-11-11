-- ============================================================================
-- bddv3 : structure de la base (version réécrite)
-- Cible : MariaDB 11.8, InnoDB, utf8mb4 / utf8mb4_unicode_ci
--
-- Ce script crée uniquement la STRUCTURE. Les données de référence (pays,
-- quartiers, QP, QPV) sont dans le fichier bddv3_donnees_reference.sql, à
-- importer ensuite.
--
-- ATTENTION : le script supprime d'abord les tables existantes (voir plus bas).
-- À n'exécuter que sur une base dont le contenu peut être perdu.
--
-- Conventions
--   * identifiants : INT UNSIGNED AUTO_INCREMENT
--   * booléens : TINYINT(1) (0 = non, 1 = oui), préfixe est_ / a_
--   * listes courtes : ENUM en minuscules, sans accent ni espace
--   * traçabilité : creation_par / modification_par = utilisateurs.id
--   * un utilisateur se désactive (est_actif = 0), il ne se supprime pas
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
  `parametres`, `logs_bdd`, `paiements`, `inscriptions_activites`, `adresses_dossiers`,
  `dossiers`, `activites`, `adherents`, `familles`, `tarifs`, `secteurs`,
  `saisons`, `utilisateurs`, `quartiers_qpv`, `quartiers_prioritaires`,
  `quartiers`, `codes_postaux`, `pays`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. Utilisateurs de l'application
-- ----------------------------------------------------------------------------
CREATE TABLE `utilisateurs` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prenom`                VARCHAR(100) NOT NULL,
  `mail`                  VARCHAR(150) NOT NULL,
  `mdp`                   VARCHAR(255) NOT NULL COMMENT 'Hash password_hash()',
  `role`                  ENUM('admin','standard','lecteur') NOT NULL DEFAULT 'lecteur',
  `est_actif`             TINYINT(1) NOT NULL DEFAULT 1,
  `tentatives_echec`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Echecs de connexion consecutifs',
  `verrouille_jusqua`     DATETIME DEFAULT NULL COMMENT 'Compte bloque jusqu a cette date',
  `token`                 VARCHAR(64) DEFAULT NULL COMMENT 'Hash SHA-256 (hex) du jeton de reinitialisation',
  `date_expiration_token` DATETIME DEFAULT NULL,
  `derniere_connexion`    DATETIME DEFAULT NULL,
  `date_crea`             DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utilisateurs_mail` (`mail`),
  KEY `idx_utilisateurs_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 1 bis. Paramètres de l'application (une ligne par réglage)
--    Réglages modifiables par un administrateur : nom_association,
--    couleur_principale, logo... Les réglages techniques et secrets restent
--    dans config.php. Lecture côté PHP : fonction parametre('cle', 'defaut').
-- ----------------------------------------------------------------------------
CREATE TABLE `parametres` (
  `cle`              VARCHAR(50) NOT NULL,
  `valeur`           VARCHAR(255) DEFAULT NULL,
  `date_modif`       DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`cle`),
  KEY `idx_parametres_modification_par` (`modification_par`),
  CONSTRAINT `fk_parametres_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Saisons, secteurs, tarifs
-- ----------------------------------------------------------------------------
CREATE TABLE `saisons` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`        VARCHAR(50) NOT NULL,
  `date_debut` DATE NOT NULL,
  `date_fin`   DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saisons_nom` (`nom`),
  CONSTRAINT `ck_saisons_dates` CHECK (`date_debut` < `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `secteurs` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saison_id`   INT UNSIGNED NOT NULL,
  `nom`         VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_secteurs_saison_nom` (`saison_id`, `nom`),
  UNIQUE KEY `uq_secteurs_id_saison` (`id`, `saison_id`),
  CONSTRAINT `fk_secteurs_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarifs` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saison_id`       INT UNSIGNED NOT NULL,
  `nom`             VARCHAR(100) NOT NULL,
  `type`            ENUM('forfait','adhesion','autre') NOT NULL,
  `montant_indiv`   DECIMAL(8,2) NOT NULL,
  `montant_famille` DECIMAL(8,2) DEFAULT NULL COMMENT 'Applique quand plusieurs membres d''une meme famille sont inscrits',
  `description`     VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tarifs_saison` (`saison_id`),
  UNIQUE KEY `uq_tarifs_id_saison` (`id`, `saison_id`),
  CONSTRAINT `fk_tarifs_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`),
  CONSTRAINT `ck_tarifs_montants` CHECK (`montant_indiv` >= 0 AND (`montant_famille` IS NULL OR `montant_famille` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Activités
--    Les clés étrangères composites (x_id, saison_id) garantissent que le
--    secteur et le tarif appartiennent bien à la même saison que l'activité.
-- ----------------------------------------------------------------------------
CREATE TABLE `activites` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saison_id`        INT UNSIGNED NOT NULL,
  `tarif_id`         INT UNSIGNED NOT NULL,
  `secteur_id`       INT UNSIGNED NOT NULL,
  `nom`              VARCHAR(150) NOT NULL,
  `referent`         VARCHAR(100) NOT NULL,
  `type`             ENUM('atelier','sortie_culturelle','sortie_loisirs','evenement','sejour') NOT NULL,
  `recurrence`       ENUM('ponctuel','regulier','vacances') NOT NULL,
  `date_debut`       DATE NOT NULL,
  `date_fin`         DATE DEFAULT NULL COMMENT 'NULL = activite sur une seule journee (date_debut)',
  `nb_places_adh`    SMALLINT UNSIGNED NOT NULL,
  `nb_places_acc`    SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Compteur de places accompagnants',
  `a_liste_attente`  TINYINT(1) NOT NULL DEFAULT 0,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activites_saison` (`saison_id`),
  KEY `idx_activites_secteur` (`secteur_id`, `saison_id`),
  KEY `idx_activites_tarif` (`tarif_id`, `saison_id`),
  KEY `idx_activites_creation_par` (`creation_par`),
  KEY `idx_activites_modification_par` (`modification_par`),
  CONSTRAINT `fk_activites_secteur` FOREIGN KEY (`secteur_id`, `saison_id`) REFERENCES `secteurs` (`id`, `saison_id`),
  CONSTRAINT `fk_activites_tarif` FOREIGN KEY (`tarif_id`, `saison_id`) REFERENCES `tarifs` (`id`, `saison_id`),
  CONSTRAINT `fk_activites_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_activites_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_activites_dates` CHECK (`date_fin` IS NULL OR `date_debut` <= `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Référentiel des pays (données dans bddv3_donnees_reference.sql)
--    La colonne region sert aux statistiques par grande zone. Elle est vide
--    pour les territoires et pays non présents dans la liste de régions.
-- ----------------------------------------------------------------------------
CREATE TABLE `pays` (
  `iso3`        VARCHAR(10) NOT NULL,
  `iso2`        VARCHAR(2) DEFAULT NULL,
  `name_fr`     VARCHAR(150) NOT NULL,
  `name_en`     VARCHAR(150) DEFAULT NULL,
  `name_native` VARCHAR(150) DEFAULT NULL,
  `flag`        VARCHAR(255) DEFAULT NULL,
  `wikidata`    VARCHAR(20) DEFAULT NULL,
  `continent`   VARCHAR(50) DEFAULT NULL,
  `region`      VARCHAR(100) DEFAULT NULL COMMENT 'Region statistique (Afrique du Nord, Antilles, Europe de l''Ouest, Amerique du Nord...) ; NULL si non classe',
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
-- 5. Familles, adhérents, dossiers
--    anonymise_le : renseigné lors de l'anonymisation (3 ans sans dossier).
--    Une ligne anonymisée est conservée pour les statistiques et les paiements.
-- ----------------------------------------------------------------------------
CREATE TABLE `familles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`          VARCHAR(100) NOT NULL,
  `anonymise_le` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `adherents` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `famille_id`       INT UNSIGNED NOT NULL,
  `est_structure`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = association / partenaire : ni prenom, ni sexe, ni date de naissance',
  `nom`              VARCHAR(100) NOT NULL,
  `prenom`           VARCHAR(100) DEFAULT NULL,
  `sexe`             ENUM('H','F','A') DEFAULT NULL COMMENT 'H = homme, F = femme, A = autre ; NULL pour une structure',
  `date_naissance`   DATE DEFAULT NULL,
  `tel`              VARCHAR(20) DEFAULT NULL,
  `mail`             VARCHAR(150) DEFAULT NULL,
  `responsable_nom`  VARCHAR(150) DEFAULT NULL COMMENT 'Nom et prenom du responsable (adherent mineur)',
  `responsable_tel`  VARCHAR(20) DEFAULT NULL,
  `responsable_mail` VARCHAR(150) DEFAULT NULL,
  `nationalite`      VARCHAR(100) DEFAULT NULL COMMENT 'Texte libre ; pour les statistiques, y stocker pays.name_fr afin de joindre pays et obtenir la region',
  `est_benevole`    TINYINT(1) NOT NULL DEFAULT 0,
  `anonymise_le`     DATETIME DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_adherents_famille` (`famille_id`),
  KEY `idx_adherents_nom` (`nom`, `prenom`),
  KEY `idx_adherents_nationalite` (`nationalite`),
  KEY `idx_adherents_creation_par` (`creation_par`),
  KEY `idx_adherents_modification_par` (`modification_par`),
  CONSTRAINT `fk_adherents_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles` (`id`),
  CONSTRAINT `fk_adherents_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_adherents_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_adherents_type` CHECK (
        (`est_structure` = 0 AND `prenom` IS NOT NULL AND `sexe` IS NOT NULL AND `date_naissance` IS NOT NULL)
     OR (`est_structure` = 1 AND `prenom` IS NULL AND `sexe` IS NULL AND `date_naissance` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dossiers` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `famille_id`       INT UNSIGNED NOT NULL,
  `saison_id`        INT UNSIGNED NOT NULL,
  `date_dossier`     DATE NOT NULL,
  `notes`            TEXT DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dossiers_famille_saison` (`famille_id`, `saison_id`),
  KEY `idx_dossiers_saison` (`saison_id`),
  KEY `idx_dossiers_creation_par` (`creation_par`),
  KEY `idx_dossiers_modification_par` (`modification_par`),
  CONSTRAINT `fk_dossiers_famille` FOREIGN KEY (`famille_id`) REFERENCES `familles` (`id`),
  CONSTRAINT `fk_dossiers_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`),
  CONSTRAINT `fk_dossiers_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_dossiers_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Adresse d'un adhérent pour un dossier (donc pour une saison)
--    Les quartiers sont un INSTANTANÉ : ils sont calculés à la saisie de
--    l'adresse (API adresse + tables de quartiers) puis copiés ici sous forme
--    de texte, sans clé étrangère. Si les périmètres changent ensuite, les
--    anciennes adresses gardent leur valeur d'origine.
-- ----------------------------------------------------------------------------
CREATE TABLE `adresses_dossiers` (
  `dossier_id`      INT UNSIGNED NOT NULL,
  `adherent_id`     INT UNSIGNED NOT NULL,
  `numero`          SMALLINT UNSIGNED DEFAULT NULL,
  `rep`             VARCHAR(10) DEFAULT NULL,
  `voie`            VARCHAR(255) DEFAULT NULL,
  `complement`      VARCHAR(255) DEFAULT NULL COMMENT 'Batiment, escalier, etage, chez...',
  `code_postal`     CHAR(5) NOT NULL,
  `ville`           VARCHAR(100) NOT NULL,
  `est_validee`     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = adresse trouvee au numero pres par l API Adresse (BAN)',
  `latitude`        DECIMAL(9,6) DEFAULT NULL,
  `longitude`       DECIMAL(9,6) DEFAULT NULL,
  `quartier`        VARCHAR(150) DEFAULT NULL COMMENT 'Quartier de la ville (instantane)',
  `est_qp`          TINYINT(1) NOT NULL DEFAULT 0,
  `qp_code`         VARCHAR(50) DEFAULT NULL,
  `qp_nom`          VARCHAR(150) DEFAULT NULL,
  `est_qpv`         TINYINT(1) NOT NULL DEFAULT 0,
  `qpv_code`        VARCHAR(50) DEFAULT NULL,
  `qpv_nom`         VARCHAR(150) DEFAULT NULL,
  `quartiers_le`    DATETIME DEFAULT NULL COMMENT 'Date du calcul des quartiers',
  PRIMARY KEY (`dossier_id`, `adherent_id`),
  KEY `idx_adresses_adherent` (`adherent_id`),
  CONSTRAINT `fk_adresses_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_adresses_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Inscriptions aux activités
--    Une ligne par adhérent et par activité : une désinscription puis une
--    réinscription modifie le statut au lieu de créer une deuxième ligne.
--    tarif_applique : prix retenu au moment de l'inscription (instantané).
-- ----------------------------------------------------------------------------
CREATE TABLE `inscriptions_activites` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`     INT UNSIGNED NOT NULL,
  `adherent_id`    INT UNSIGNED NOT NULL,
  `activite_id`    INT UNSIGNED NOT NULL,
  `statut`         ENUM('inscrit','liste_attente','desinscrit') NOT NULL DEFAULT 'inscrit',
  `inscrit_le`     DATETIME NOT NULL DEFAULT current_timestamp(),
  `tarif_applique` DECIMAL(8,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ia_adherent_activite` (`adherent_id`, `activite_id`),
  KEY `idx_ia_dossier` (`dossier_id`),
  KEY `idx_ia_activite_statut` (`activite_id`, `statut`),
  CONSTRAINT `fk_ia_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ia_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`),
  CONSTRAINT `fk_ia_activite` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`),
  CONSTRAINT `ck_ia_tarif` CHECK (`tarif_applique` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Paiements (rattachés à un dossier)
--    montant toujours positif ; le sens est donné par objet_paiement
--    ('remboursement' = somme rendue). Un paiement erroné est annulé
--    (est_annule = 1), jamais supprimé : le dossier ne peut pas être supprimé
--    tant qu'il a des paiements (ON DELETE RESTRICT).
-- ----------------------------------------------------------------------------
CREATE TABLE `paiements` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dossier_id`       INT UNSIGNED NOT NULL,
  `montant`          DECIMAL(8,2) NOT NULL,
  `date_paiement`    DATETIME NOT NULL,
  `moyen_paiement`   ENUM('especes','cheque','cb','autre') NOT NULL,
  `objet_paiement`   ENUM('forfait','adhesion','don','remboursement') NOT NULL,
  `est_annule`       TINYINT(1) NOT NULL DEFAULT 0,
  `note`             VARCHAR(255) DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_paiements_dossier` (`dossier_id`),
  KEY `idx_paiements_date` (`date_paiement`),
  KEY `idx_paiements_creation_par` (`creation_par`),
  KEY `idx_paiements_modification_par` (`modification_par`),
  CONSTRAINT `fk_paiements_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_paiements_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_paiements_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_paiements_montant` CHECK (`montant` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Journal des actions (conservé 1 an, voir requête de purge en fin de script)
--    Ne jamais y enregistrer mdp ni token dans donnee_ancienne / donnee_nouvelle.
-- ----------------------------------------------------------------------------
CREATE TABLE `logs_bdd` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilisateur_id`    INT UNSIGNED NOT NULL,
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
-- 10. Référentiels géographiques (données dans bddv3_donnees_reference.sql)
--     Trois notions distinctes, conservées séparément :
--       quartiers              : quartiers de la ville
--       quartiers_prioritaires : quartiers prioritaires (QP)
--       quartiers_qpv          : quartiers prioritaires de la politique de la ville (QPV)
--     Ces tables peuvent être complétées ou mises à jour sans toucher aux
--     adresses déjà saisies (voir l'instantané dans adresses_dossiers).
-- ----------------------------------------------------------------------------
CREATE TABLE `quartiers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commune`        VARCHAR(100) NOT NULL,
  `arrondissement` TINYINT UNSIGNED NOT NULL,
  `nom`            VARCHAR(150) NOT NULL,
  `geom`           GEOMETRY NOT NULL,
  PRIMARY KEY (`id`),
  SPATIAL KEY `idx_spatial_quartiers` (`geom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `quartiers_prioritaires` (
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

CREATE TABLE `quartiers_qpv` (
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
-- 11. Base officielle des codes postaux (La Poste)
--     Sert à l'autocomplétion du code postal dans le formulaire d'inscription.
--     Remplie par outils/import_codes_postaux.php (fichier CSV de La Poste).
-- ----------------------------------------------------------------------------
CREATE TABLE `codes_postaux` (
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

-- ============================================================================
-- Requêtes de maintenance (à lancer depuis l'application ou une tâche cron ;
-- laissées en commentaire volontairement)
-- ============================================================================

-- Purge du journal : conservation 1 an
-- DELETE FROM `logs_bdd` WHERE `date_action` < (NOW() - INTERVAL 1 YEAR);

-- Familles à anonymiser : dernier dossier datant de plus de 3 ans
-- SELECT f.`id`
--   FROM `familles` f
--   JOIN `dossiers` d ON d.`famille_id` = f.`id`
--  WHERE f.`anonymise_le` IS NULL
--  GROUP BY f.`id`
-- HAVING MAX(d.`date_dossier`) < (CURDATE() - INTERVAL 3 YEAR);
