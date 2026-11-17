-- ============================================================================
-- Migration du 17/11/2026 — partenaire d'une activité
--
-- À exécuter APRÈS 2026-11-16_quartiers_non_pris_par_defaut.sql (phpMyAdmin,
-- onglet SQL, serveur MariaDB). Elle peut être relancée sans risque (IF NOT
-- EXISTS). Une nouvelle installation n'en a pas besoin : sql/structure.sql
-- contient déjà cette colonne.
--
--   activites : partenaire, facultatif. Nom de la structure partenaire de
--            l'activité (texte libre, sans lien avec les structures
--            adhérentes). NULL = activité sans partenaire. Il sert de filtre
--            « Avec partenaire » dans la liste des activités et dans les
--            statistiques.
--
-- Lignes déjà présentes : activités sans partenaire (NULL).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `activites`
  ADD COLUMN IF NOT EXISTS `partenaire` VARCHAR(150) DEFAULT NULL COMMENT 'Structure partenaire (texte libre) ; NULL = sans partenaire' AFTER `referent`;
