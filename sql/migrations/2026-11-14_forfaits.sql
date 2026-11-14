-- ============================================================================
-- Migration du 14/11/2026 — un forfait ne se paie qu'une fois
--
-- À exécuter une seule fois, APRÈS 2026-11-14_recurrences.sql (phpMyAdmin,
-- onglet SQL, serveur MariaDB). Une nouvelle installation n'en a pas besoin :
-- sql/structure.sql contient déjà cette colonne.
--
--   inscriptions : tarif_id, le tarif appliqué au moment de l'inscription.
--                  Il sert au calcul du montant dû : un tarif « forfait » ne
--                  compte qu'une fois par personne (ou par famille, au montant
--                  familial), quel que soit le nombre d'activités qui
--                  l'utilisent. NULL : activité gratuite, ou montant négocié
--                  d'une structure.
--
-- Lignes déjà présentes : elles reprennent le tarif actuel de leur activité,
-- sauf celles des structures (montant négocié), qui restent sans tarif.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `inscriptions`
  ADD COLUMN `tarif_id` INT UNSIGNED DEFAULT NULL COMMENT 'Tarif applique a l inscription ; NULL = gratuit ou montant negocie (structure)' AFTER `nb_places`,
  ADD KEY `idx_inscriptions_tarif` (`tarif_id`),
  ADD CONSTRAINT `fk_inscriptions_tarif` FOREIGN KEY (`tarif_id`) REFERENCES `tarifs` (`id`);

UPDATE `inscriptions` i
  JOIN `activites` ac ON ac.`id` = i.`activite_id`
  JOIN `adherents` a ON a.`id` = i.`adherent_id`
   SET i.`tarif_id` = ac.`tarif_id`, i.`date_modif` = i.`date_modif`
 WHERE a.`est_structure` = 0 AND ac.`tarif_id` IS NOT NULL;
