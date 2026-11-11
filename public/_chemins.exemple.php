<?php

declare(strict_types=1);

/**
 * Modèle de _chemins.php : à copier sous le nom _chemins.php, puis à adapter.
 * _chemins.php n'est pas versionné et ne doit jamais être écrasé lors d'un déploiement.
 *
 * DOSSIER_PRIVE = dossier qui contient includes/, config.php, sql/, logs/, sauvegardes/.
 *
 * En local (WAMP) comme sur Hostinger, les pages sont dans <projet>/public/ et
 * la partie privée dans <projet>/ : la ligne ci-dessous convient telle quelle.
 * Elle ne change que si la partie privée est placée ailleurs.
 */
define('DOSSIER_PRIVE', dirname(__DIR__));
