-- ============================================================================
-- Migration du 13/11/2026 — date d'entrée en liste d'attente
--
-- À exécuter une seule fois, APRÈS 2026-11-13_etape2_complements.sql
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : sql/structure.sql contient déjà cette colonne.
--
--   inscriptions : attente_le, date d'entrée en liste d'attente. La liste
--                  d'attente d'une activité s'affiche dans cet ordre. La date
--                  est vidée quand l'inscription passe aux inscrits.
--
-- Lignes déjà en liste d'attente : elles reprennent leur date d'inscription.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `inscriptions`
  ADD COLUMN `attente_le` DATETIME DEFAULT NULL COMMENT 'Date d entree en liste d attente ; NULL pour un inscrit' AFTER `statut`;

UPDATE `inscriptions`
   SET `attente_le` = `date_crea`, `date_modif` = `date_modif`
 WHERE `statut` = 'attente';
