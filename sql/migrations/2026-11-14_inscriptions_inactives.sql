-- ============================================================================
-- Migration du 14/11/2026 — inscriptions inactives
--
-- À exécuter une seule fois, APRÈS 2026-11-14_etape6_statistiques.sql
-- (phpMyAdmin, onglet SQL, serveur MariaDB). Une nouvelle installation n'en a
-- pas besoin : sql/structure.sql contient déjà ces colonnes.
--
--   inscriptions : statut reçoit une troisième valeur, 'inactif'. Une
--                  inscription inactive libère ses places dans l'activité,
--                  mais reste comptée dans les statistiques et dans le
--                  montant dû (à la différence d'une inscription annulée).
--                  inactive_le et inactivation_par : date et auteur, vidés
--                  quand l'inscription est réactivée.
--
-- Aucune ligne existante n'est modifiée.
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `inscriptions`
  MODIFY COLUMN `statut` ENUM('inscrit','attente','inactif') NOT NULL DEFAULT 'inscrit',
  ADD COLUMN `inactive_le` DATETIME DEFAULT NULL COMMENT 'Date ou l inscription a ete rendue inactive ; NULL sinon' AFTER `attente_le`,
  ADD COLUMN `inactivation_par` INT UNSIGNED DEFAULT NULL AFTER `inactive_le`,
  ADD KEY `idx_inscriptions_inactivation_par` (`inactivation_par`),
  ADD CONSTRAINT `fk_inscriptions_inactivation_par` FOREIGN KEY (`inactivation_par`) REFERENCES `utilisateurs` (`id`);
