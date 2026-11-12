-- ============================================================================
-- Migration du 12/11/2026 — étape 2 : activités et inscriptions
--
-- À exécuter une seule fois sur une base installée avant cette date
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : install.php crée ces tables à partir de sql/structure.sql.
--
-- Ajoute cinq tables ; aucune table existante n'est modifiée.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 9. Secteurs et types d'activité (étape 2)
--    Deux listes communes à toutes les saisons. Une ligne utilisée par une
--    activité ne se supprime pas : elle se rend inactive (est_actif = 0) et
--    n'est alors plus proposée pour les nouvelles activités.
--    Les cinq types d'origine sont créés ici ; ils se gèrent ensuite dans les
--    paramètres.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secteurs` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`       VARCHAR(100) NOT NULL,
  `est_actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_crea` DATETIME NOT NULL DEFAULT current_timestamp(),
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

-- ----------------------------------------------------------------------------
-- 10. Tarifs des activités (étape 2)
--     Un tarif est une ligne nommée d'une saison, avec un montant unique ;
--     plusieurs activités peuvent le partager. La clé (id, saison_id) sert à
--     garantir qu'une activité utilise un tarif de sa propre saison.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarifs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `saison_id`        INT UNSIGNED NOT NULL,
  `nom`              VARCHAR(100) NOT NULL,
  `montant`          DECIMAL(8,2) NOT NULL,
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
  CONSTRAINT `ck_tarifs_montant` CHECK (`montant` >= 0)
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
  `tarif_id`           INT UNSIGNED DEFAULT NULL COMMENT 'NULL = gratuit',
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
  KEY `idx_activites_tarif` (`tarif_id`, `saison_id`),
  KEY `idx_activites_creation_par` (`creation_par`),
  KEY `idx_activites_modification_par` (`modification_par`),
  CONSTRAINT `fk_activites_saison` FOREIGN KEY (`saison_id`) REFERENCES `saisons` (`id`),
  CONSTRAINT `fk_activites_type` FOREIGN KEY (`type_id`) REFERENCES `types_activite` (`id`),
  CONSTRAINT `fk_activites_secteur` FOREIGN KEY (`secteur_id`) REFERENCES `secteurs` (`id`),
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
  `montant`          DECIMAL(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Copie du tarif au moment de l inscription, ou montant negocie',
  `statut`           ENUM('inscrit','attente') NOT NULL DEFAULT 'inscrit',
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
  KEY `idx_inscriptions_annulation_par` (`annulation_par`),
  KEY `idx_inscriptions_creation_par` (`creation_par`),
  KEY `idx_inscriptions_modification_par` (`modification_par`),
  CONSTRAINT `fk_inscriptions_activite` FOREIGN KEY (`activite_id`) REFERENCES `activites` (`id`),
  CONSTRAINT `fk_inscriptions_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `dossiers` (`id`),
  CONSTRAINT `fk_inscriptions_adherent` FOREIGN KEY (`adherent_id`) REFERENCES `adherents` (`id`),
  CONSTRAINT `fk_inscriptions_annulation_par` FOREIGN KEY (`annulation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_inscriptions_creation_par` FOREIGN KEY (`creation_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `fk_inscriptions_modification_par` FOREIGN KEY (`modification_par`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `ck_inscriptions_places` CHECK (`nb_places` >= 1),
  CONSTRAINT `ck_inscriptions_montant` CHECK (`montant` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
