<?php

declare(strict_types=1);

/**
 * Porte d'entrée unique de toutes les pages (sauf install.php) :
 * chargement des fonctions, configuration, gestion des erreurs,
 * session sécurisée, en-têtes de sécurité.
 *
 * Une page commence toujours par :
 *     require __DIR__ . '/_chemins.php';
 *     require DOSSIER_PRIVE . '/includes/init.php';
 */

require_once DOSSIER_PRIVE . '/includes/mes_fonctions.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_auth.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_utilisateurs.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_saisons.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_courriel.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_sauvegarde.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_familles.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_adherents.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_adresses.php';

// Bibliothèques installées par Composer (PHPMailer)
if (is_file(DOSSIER_PRIVE . '/vendor/autoload.php')) {
    require_once DOSSIER_PRIVE . '/vendor/autoload.php';
}

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// Tant que l'installation n'est pas terminée, tout renvoie vers l'assistant.
$configuration = config_lire();
if ($configuration === null || $configuration['INSTALLATION_TERMINEE'] !== true) {
    header('Location: install.php', true, 303);
    exit;
}
unset($configuration);

// Erreurs : toujours écrites dans logs/, affichées seulement si config.php le demande (local).
error_reporting(E_ALL);
ini_set('display_errors', config('AFFICHER_ERREURS') === true ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', DOSSIER_PRIVE . '/logs/php_erreurs.log');

set_exception_handler(static function (Throwable $erreur): void {
    error_log('Exception non gérée : ' . $erreur->getMessage() . ' dans ' . $erreur->getFile() . ':' . $erreur->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title></head><body>';
    echo '<h1>Une erreur est survenue</h1><p>L\'action n\'a pas pu aboutir. L\'erreur a été enregistrée.</p>';
    if (config('AFFICHER_ERREURS') === true) {
        echo '<pre>' . e((string) $erreur) . '</pre>';
    }
    echo '</body></html>';
});

// Session : le cookie est limité au dossier du site.
$chemin_cookie = parse_url(texte(config('APP_URL')), PHP_URL_PATH);
demarrer_session(rtrim(is_string($chemin_cookie) ? $chemin_cookie : '', '/') . '/');
unset($chemin_cookie);

envoyer_entetes_securite();
