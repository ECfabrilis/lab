-- ============================================================================
-- Migration du 13/11/2026 — étape 2, compléments
--
-- À exécuter une seule fois, APRÈS 2026-11-12_etape2_activites.sql
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : sql/structure.sql contient déjà ces colonnes.
--
--   secteurs     : description
--   tarifs       : type (forfait, adhésion, autre), montant individuel (l'ancien
--                  « montant »), montant familial, description
--   activites    : référent, récurrence (ponctuel, régulier, vacances)
--   inscriptions : tarif familial appliqué ou non
--
-- Lignes déjà présentes : les tarifs deviennent « forfait » et gardent leur
-- montant comme montant individuel. La table activites doit être vide (sinon
-- ses lignes reçoivent un référent vide et la récurrence « ponctuel »).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `secteurs`
  ADD COLUMN `description` TEXT DEFAULT NULL AFTER `nom`;

ALTER TABLE `tarifs`
  DROP CONSTRAINT `ck_tarifs_montant`;

ALTER TABLE `tarifs`
  CHANGE COLUMN `montant` `montant_individuel` DECIMAL(8,2) NOT NULL,
  ADD COLUMN `type` ENUM('forfait','adhesion','autre') NOT NULL COMMENT 'adhesion : reserve aux cotisations, jamais choisi pour une activite' AFTER `nom`,
  ADD COLUMN `montant_familial` DECIMAL(8,2) DEFAULT NULL COMMENT 'Pour toute la famille ; NULL = pas de tarif familial' AFTER `montant_individuel`,
  ADD COLUMN `description` TEXT DEFAULT NULL AFTER `montant_familial`;

ALTER TABLE `tarifs`
  ADD CONSTRAINT `ck_tarifs_montants` CHECK (`montant_individuel` >= 0 AND (`montant_familial` IS NULL OR `montant_familial` >= 0));

ALTER TABLE `activites`
  ADD COLUMN `referent` VARCHAR(150) NOT NULL COMMENT 'Personne referente (texte libre)' AFTER `secteur_id`,
  ADD COLUMN `recurrence` ENUM('ponctuel','regulier','vacances') NOT NULL AFTER `referent`;

ALTER TABLE `inscriptions`
  ADD COLUMN `est_tarif_familial` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = montant familial : compte une seule fois par famille et par activite' AFTER `montant`;
