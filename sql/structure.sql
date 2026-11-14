-- ============================================================================
-- bddv3 : structure de la base — schéma de référence
-- Cible : MariaDB 11.8, InnoDB, utf8mb4 / utf8mb4_unicode_ci
--
-- État : étape 3 (cotisations et paiements), dons et quartiers pris en compte. Chaque étape ajoute ses tables par un
-- fichier daté dans sql/migrations/, et ce fichier est mis à jour.
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
--    quartiers.est_actif : seuls les quartiers pris en compte (Paramètres >
--    Options > Quartiers) servent à déterminer le quartier d'une adresse.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quartiers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `commune`        VARCHAR(100) NOT NULL,
  `arrondissement` TINYINT UNSIGNED NOT NULL,
  `nom`            VARCHAR(150) NOT NULL,
  `est_actif`      TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = pris en compte pour le quartier d une adresse',
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

-- ----------------------------------------------------------------------------
-- 8. Familles, adhérents, dossiers (étape 1)
--    Modèle : famille -> dossier (par saison) -> adhérents.
--    Une structure (association, école...) est un adhérent sans famille, qui
--    a son propre dossier (dossiers.structure_id).
--    dossiers_adherents : un adhérent dans le dossier d une saison, avec son
--    adresse de la saison, l instantané des quartiers et sa scolarité.
--    anonymise_le : renseigné lors de l anonymisation (RGPD, 3 ans).
-- ----------------------------------------------------------------------------
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

-- ----------------------------------------------------------------------------
-- 9. Secteurs, types d'activité et récurrences (étape 2)
--    Trois listes communes à toutes les saisons. Une ligne utilisée par une
--    activité ne se supprime pas : elle se rend inactive (est_actif = 0) et
--    n'est alors plus proposée pour les nouvelles activités.
--    Les cinq types et les trois récurrences d'origine sont créés ici ; ils
--    se gèrent ensuite dans les paramètres (Options des activités).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secteurs` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `est_actif`   TINYINT(1) NOT NULL DEFAULT 1,
  `date_crea`   DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_secteurs_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `types_activite` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`       VARCHAR(100) NOT NULL,
  `est_actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_crea` DATETIME NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_types_activite_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `types_activite` (`id`, `nom`) VALUES
  (1, 'Atelier'),
  (2, 'Sortie loisirs'),
  (3, 'Sortie culturelle'),
  (4, 'Évènement'),
  (5, 'Séjour');

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

-- ----------------------------------------------------------------------------
-- 10. Tarifs des activités (étape 2)
--     Un tarif est une ligne nommée d'une saison ; plusieurs activités peuvent
--     le partager. La clé (id, saison_id) sert à garantir qu'une activité
--     utilise un tarif de sa propre saison.
--     type : 'adhesion' est réservé aux cotisations (étape 3) ; une activité
--     ne choisit qu'un tarif 'forfait' ou 'autre'.
--     montant_familial : facultatif ; montant pour toute la famille (exemple :
--     centre de loisirs, 5 € par enfant ou 10 € pour tous les enfants).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarifs` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saison_id`          INT UNSIGNED NOT NULL,
  `nom`                VARCHAR(100) NOT NULL,
  `type`               ENUM('forfait','adhesion','autre') NOT NULL COMMENT 'adhesion : reserve aux cotisations, jamais choisi pour une activite',
  `montant_individuel` DECIMAL(8,2) NOT NULL,
  `montant_familial`   DECIMAL(8,2) DEFAULT NULL COMMENT 'Pour toute la famille ; NULL = pas de tarif familial',
  `description`        TEXT DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarifs_saison_nom` (`saison_id`, `nom`),
  UNIQUE KEY `uq_tarifs_id_saison` (`id`, `saison_id`),
  KEY `idx_tarifs_creation_par` (`creation_par`),
  KEY `idx_tarifs_modification_par` (`modification_par`),
  CONSTRAINT `fk_tarifs_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`),
  CONSTRAINT `fk_tarifs_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_tarifs_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_tarifs_montants` CHECK (`montant_individuel` >= 0 AND (`montant_familial` IS NULL OR `montant_familial` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Activités (étape 2)
--     tarif_id NULL = activité gratuite. nb_places NULL = places non limitées.
--     Pas d'horaires, de séances ni de lieu (hors périmètre).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activites` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saison_id`          INT UNSIGNED NOT NULL,
  `nom`                VARCHAR(150) NOT NULL,
  `type_id`            INT UNSIGNED NOT NULL,
  `secteur_id`         INT UNSIGNED NOT NULL,
  `referent`           VARCHAR(150) NOT NULL COMMENT 'Personne referente (texte libre)',
  `recurrence_id`      INT UNSIGNED NOT NULL,
  `tarif_id`         INT UNSIGNED DEFAULT NULL COMMENT 'NULL = gratuit',
  `date_debut`         DATE NOT NULL,
  `date_fin`           DATE DEFAULT NULL,
  `nb_places`          SMALLINT UNSIGNED DEFAULT NULL COMMENT 'NULL = places non limitees',
  `a_liste_attente`    TINYINT(1) NOT NULL DEFAULT 0,
  `nb_accompagnateurs` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Simple information : ne consomme pas de places',
  `description`        TEXT DEFAULT NULL,
  `est_sans_adhesion`  TINYINT(1) NOT NULL DEFAULT 0,
  `date_crea`          DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`         DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`       INT UNSIGNED NOT NULL,
  `modification_par`   INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activites_saison` (`saison_id`, `date_debut`),
  KEY `idx_activites_type` (`type_id`),
  KEY `idx_activites_secteur` (`secteur_id`),
  KEY `idx_activites_recurrence` (`recurrence_id`),
  KEY `idx_activites_tarif` (`tarif_id`, `saison_id`),
  KEY `idx_activites_creation_par` (`creation_par`),
  KEY `idx_activites_modification_par` (`modification_par`),
  CONSTRAINT `fk_activites_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`),
  CONSTRAINT `fk_activites_type` FOREIGN KEY (`type_id`) REFERENCES `types_activite` (`id`),
  CONSTRAINT `fk_activites_secteur` FOREIGN KEY (`secteur_id`) REFERENCES `secteurs` (`id`),
  CONSTRAINT `fk_activites_recurrence` FOREIGN KEY (`recurrence_id`) REFERENCES `recurrences` (`id`),
  CONSTRAINT `fk_activites_tarif` FOREIGN KEY (`tarif_id`, `saison_id`) REFERENCES `tarifs` (`id`, `saison_id`),
  CONSTRAINT `fk_activites_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_activites_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_activites_dates` CHECK (`date_fin` IS NULL OR `date_fin` >= `date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Inscriptions aux activités (étape 2)
--     Une inscription porte un nombre de places (1 pour une personne,
--     plusieurs pour une structure) et son montant, copié au moment de
--     l'inscription (tarif de l'activité, ou montant négocié d'une structure).
--     tarif_id : le tarif appliqué. Un tarif « forfait » ne compte qu'une fois
--     par personne (ou par famille, au montant familial) dans le montant dû,
--     quel que soit le nombre d'activités qui l'utilisent.
--     statut : 'inscrit' (compte dans les places) ou 'attente' (liste d'attente).
--     Une inscription ne se supprime pas : elle s'annule (est_annulee = 1).
--     L'activité et le dossier sont de la même saison : vérifié en PHP.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inscriptions` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activite_id`      INT UNSIGNED NOT NULL,
  `dossier_id`       INT UNSIGNED NOT NULL,
  `adherent_id`      INT UNSIGNED NOT NULL,
  `nb_places`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `tarif_id`         INT UNSIGNED DEFAULT NULL COMMENT 'Tarif applique a l inscription ; NULL = gratuit ou montant negocie (structure)',
  `montant`          DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Copie du tarif au moment de l inscription, ou montant negocie',
  `est_tarif_familial` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = montant familial : compte une seule fois par famille et par activite (par forfait pour un tarif forfait)',
  `statut`           ENUM('inscrit','attente') NOT NULL DEFAULT 'inscrit',
  `attente_le`       DATETIME DEFAULT NULL COMMENT 'Date d entree en liste d attente ; NULL pour un inscrit',
  `est_annulee`      TINYINT(1) NOT NULL DEFAULT 0,
  `annulee_le`       DATETIME DEFAULT NULL,
  `annulation_par`   INT UNSIGNED DEFAULT NULL,
  `date_crea`        DATETIME NOT NULL DEFAULT current_timestamp(),
  `date_modif`       DATETIME DEFAULT NULL ON UPDATE current_timestamp(),
  `creation_par`     INT UNSIGNED NOT NULL,
  `modification_par` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inscriptions_activite` (`activite_id`, `est_annulee`, `statut`),
  KEY `idx_inscriptions_dossier` (`dossier_id`),
  KEY `idx_inscriptions_adherent` (`adherent_id`),
  KEY `idx_inscriptions_tarif` (`tarif_id`),
  KEY `idx_inscriptions_annulation_par` (`annulation_par`),
  KEY `idx_inscriptions_creation_par` (`creation_par`),
  KEY `idx_inscriptions_modification_par` (`modification_par`),
  CONSTRAINT `fk_inscriptions_activite` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`),
  CONSTRAINT `fk_inscriptions_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`),
  CONSTRAINT `fk_inscriptions_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`),
  CONSTRAINT `fk_inscriptions_tarif` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`),
  CONSTRAINT `fk_inscriptions_annulation_par` FOREIGN KEY (`annulation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_inscriptions_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_inscriptions_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_inscriptions_places` CHECK (`nb_places` >= 1),
  CONSTRAINT `ck_inscriptions_montant` CHECK (`montant` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. Cotisations, réductions, paiements (étape 3)
--     Tout est rattaché au dossier (famille ou structure, pour une saison).
--     cotisations : individuelle (une personne ou une structure) ou familiale
--       (toute la famille). L'accueil choisit le tarif « adhésion » ; son
--       montant est copié. Pour une structure, le montant est saisi (négocié).
--       Une cotisation ne se supprime pas : elle s'annule.
--     reductions : montant retiré du montant dû, pour une personne ou pour
--       toute la famille. Aucun motif ; une réduction se supprime.
--     paiements : montant toujours positif ; objet = ce que règle le paiement
--       (adhésion, activités ou don, pour la comptabilité). Un don ne compte
--       pas dans ce qui a été payé. Un paiement ne se supprime pas : il
--       s'annule (est_annule = 1). groupe_id relie les paiements nés d'une
--       même saisie (« les deux », surplus en don) : annuler l'un annule les
--       autres.
--     Montant dû = cotisations + inscriptions en cours (hors liste d'attente,
--       tarif familial compté une fois par activité) − réductions.
-- ----------------------------------------------------------------------------
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
  `objet`            ENUM('adhesion','activites','don') NOT NULL COMMENT 'Ce que regle le paiement (distinction demandee par la comptabilite)',
  `groupe_id`        INT UNSIGNED DEFAULT NULL COMMENT 'Paiements nes d une meme saisie : numero du premier ; NULL = paiement seul',
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
  KEY `idx_paiements_groupe` (`groupe_id`),
  KEY `idx_paiements_annulation_par` (`annulation_par`),
  KEY `idx_paiements_creation_par` (`creation_par`),
  KEY `idx_paiements_modification_par` (`modification_par`),
  CONSTRAINT `fk_paiements_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_paiements_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `paiements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_paiements_annulation_par` FOREIGN KEY (`annulation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_paiements_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_paiements_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_paiements_montant` CHECK (`montant` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
