-- ============================================================================
-- Migration du 12/11/2026 — noms en majuscules
--
-- À exécuter une seule fois sur une base installée avant cette date
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : les noms y sont enregistrés en majuscules dès la saisie.
--
-- Met en majuscules le nom des personnes et le nom des familles déjà
-- enregistrés. Les structures gardent leur nom tel qu'il a été saisi.
-- Les prénoms ne sont pas modifiés : ils se corrigent sur la fiche (un
-- enregistrement de la fiche leur donne la majuscule initiale).
-- Aucune modification de structure.
-- ============================================================================

SET NAMES utf8mb4;

UPDATE `adherents`
   SET `nom` = UPPER(`nom`)
 WHERE `est_structure` = 0
   AND `anonymise_le` IS NULL
   AND BINARY `nom` <> BINARY UPPER(`nom`);

UPDATE `familles`
   SET `nom` = UPPER(`nom`)
 WHERE `anonymise_le` IS NULL
   AND BINARY `nom` <> BINARY UPPER(`nom`);
