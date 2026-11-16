-- ============================================================================
-- Migration du 16/11/2026 — quartiers non pris en compte par défaut
--
-- À exécuter APRÈS 2026-11-15_montant_groupe.sql (phpMyAdmin, onglet SQL,
-- serveur MariaDB). Elle peut être relancée sans risque. Une nouvelle
-- installation n'en a pas besoin : sql/structure.sql est déjà à jour.
--
--   quartiers.est_actif : valeur par défaut 0. Un quartier importé (table
--            remplacée, nouvelle commune ou nouvel arrondissement) n'est pas
--            pris en compte tant qu'un administrateur ne l'a pas coché dans
--            Paramètres > Options > Quartiers. La page Paramètres affiche un
--            avertissement tant qu'aucun quartier n'est pris en compte.
--
-- Lignes déjà présentes : inchangées (le choix déjà fait est conservé).
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `quartiers`
  ALTER COLUMN `est_actif` SET DEFAULT 0;
