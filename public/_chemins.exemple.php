<?php

declare(strict_types=1);

/**
 * Modèle de _chemins.php : à copier sous le nom _chemins.php, puis à adapter.
 * _chemins.php n'est pas versionné et ne doit jamais être écrasé lors d'un déploiement.
 *
 * DOSSIER_PRIVE = dossier qui contient includes/, config.php, sql/, logs/, sauvegardes/.
 *
 * Local (WAMP), pages dans <projet>/public/ :
 *     define('DOSSIER_PRIVE', dirname(__DIR__));
 *
 * Hostinger, pages dans public_html/<sous-dossier>/ :
 *     define('DOSSIER_PRIVE', dirname(__DIR__, 2) . '/app_bddv3');
 *     (ajouter 1 par niveau de sous-dossier supplémentaire)
 */
define('DOSSIER_PRIVE', dirname(__DIR__));
