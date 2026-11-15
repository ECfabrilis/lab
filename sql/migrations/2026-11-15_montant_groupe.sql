-- ============================================================================
-- Migration du 15/11/2026 — tarif groupe
--
-- À exécuter APRÈS 2026-11-14_types_tarif_modes_paiement.sql (phpMyAdmin,
-- onglet SQL, serveur MariaDB). Elle peut être relancée sans risque (IF NOT
-- EXISTS / IF EXISTS). Une nouvelle installation n'en a pas besoin :
-- sql/structure.sql contient déjà ces colonnes.
--
--   tarifs : montant_groupe, facultatif. Montant par personne, appliqué dès
--            que deux membres d'une même famille sont inscrits à la même
--            activité (exemple : 20 € pour une personne seule, 15 € chacun à
--            partir de deux). Un tarif n'a jamais à la fois un montant groupe
--            et un montant familial. Le montant groupe n'existe pas pour un
--            tarif « adhésion » ni pour un « forfait » (contrôle fait par
--            l'application).
--
--   inscriptions : est_tarif_groupe, 1 quand l'inscription est au montant
--            groupe. Elle compte dans le montant dû comme une inscription au
--            montant individuel (une fois par personne).
--
-- Lignes déjà présentes : tarifs sans montant groupe (NULL), inscriptions au
-- montant qu'elles avaient (est_tarif_groupe = 0).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `tarifs`
  ADD COLUMN IF NOT EXISTS `montant_groupe` DECIMAL(8,2) DEFAULT NULL COMMENT 'Par personne, des que deux membres d une meme famille sont inscrits a l activite ; NULL = pas de tarif groupe' AFTER `montant_individuel`,
  DROP CONSTRAINT IF EXISTS `ck_tarifs_montants`;

ALTER TABLE `tarifs`
  ADD CONSTRAINT `ck_tarifs_montants` CHECK (
    `montant_individuel` >= 0
    AND (`montant_groupe` IS NULL OR `montant_groupe` >= 0)
    AND (`montant_familial` IS NULL OR `montant_familial` >= 0)
    AND (`montant_groupe` IS NULL OR `montant_familial` IS NULL)
  );

ALTER TABLE `inscriptions`
  ADD COLUMN IF NOT EXISTS `est_tarif_groupe` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = montant groupe : par personne, applique des que deux membres de la famille sont inscrits a l activite' AFTER `est_tarif_familial`,
  DROP CONSTRAINT IF EXISTS `ck_inscriptions_tarif_applique`;

ALTER TABLE `inscriptions`
  ADD CONSTRAINT `ck_inscriptions_tarif_applique` CHECK (`est_tarif_familial` = 0 OR `est_tarif_groupe` = 0);
