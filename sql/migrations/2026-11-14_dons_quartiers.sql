-- ============================================================================
-- Migration du 14/11/2026 — dons, paiements liés, quartiers pris en compte
--
-- À exécuter une seule fois, APRÈS 2026-11-13_etape3_paiements.sql
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : sql/structure.sql contient déjà ces colonnes.
--
--   paiements : objet accepte « don » (un don ne compte pas dans ce qui a été
--               payé : il ne réduit pas le reste à payer).
--               groupe_id relie les paiements nés d'une même saisie (« les
--               deux », surplus en don) : il vaut le numéro du premier d'entre
--               eux. Annuler l'un annule les autres.
--   quartiers : est_actif, 1 = quartier pris en compte pour déterminer le
--               quartier d'une adresse (Paramètres > Options > Quartiers).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `paiements`
  MODIFY `objet` ENUM('adhesion','activites','don') NOT NULL COMMENT 'Ce que regle le paiement (distinction demandee par la comptabilite)',
  ADD COLUMN `groupe_id` INT UNSIGNED DEFAULT NULL COMMENT 'Paiements nes d une meme saisie : numero du premier ; NULL = paiement seul' AFTER `objet`,
  ADD KEY `idx_paiements_groupe` (`groupe_id`),
  ADD CONSTRAINT `fk_paiements_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `paiements` (`id`) ON DELETE RESTRICT;

ALTER TABLE `quartiers`
  ADD COLUMN `est_actif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = pris en compte pour le quartier d une adresse' AFTER `nom`;
