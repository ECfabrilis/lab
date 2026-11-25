-- ============================================================================
-- Migration du 26/11/2026 : limite des essais de connexion par adresse IP
-- (version 0.18.0)
--
-- Table connexions_echecs : une ligne par essai refusé (connexion, demande de
-- mot de passe oublié), avec l'adresse IP du visiteur. Après 10 essais refusés
-- en 15 minutes, les essais de cette adresse sont ignorés pendant 15 minutes.
-- Les lignes de plus de 24 heures sont effacées à chaque connexion réussie :
-- l'adresse IP n'est gardée que pour la sécurité, et très peu de temps.
-- Relançable sans risque.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `connexions_echecs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adresse_ip` VARCHAR(45) NOT NULL COMMENT 'Adresse IP du visiteur (IPv4 ou IPv6)',
  `date_echec` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_connexions_echecs_ip` (`adresse_ip`, `date_echec`),
  KEY `idx_connexions_echecs_date` (`date_echec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
