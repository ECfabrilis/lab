<?php

declare(strict_types=1);

/**
 * Mise à jour de la base en ligne de commande, sur le serveur :
 *
 *     php outils/mise_a_jour.php
 *
 * Fait la même chose que la page « Mise à jour » : une sauvegarde, puis les
 * migrations en attente, dans l'ordre. Sert à mettre à jour une instance sans
 * ouvrir le site (plusieurs instances : une boucle, voir docs/deploiement.md).
 *
 * Code de retour : 0 si la base est à jour à la fin, 1 sinon.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

define('DOSSIER_PRIVE', dirname(__DIR__));

require_once DOSSIER_PRIVE . '/includes/mes_fonctions.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_sauvegarde.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_mise_a_jour.php';

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

/** Écrit une ligne sur la sortie. */
function sortie_ligne(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/** Écrit une erreur et termine avec le code 1. */
function sortie_echec(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$configuration = config_lire();
if ($configuration === null || $configuration['INSTALLATION_TERMINEE'] !== true) {
    sortie_echec('L\'installation n\'est pas terminée : lancez d\'abord l\'assistant (install.php).');
}

try {
    $en_attente = migrations_en_attente();
    if ($en_attente === []) {
        if (base_en_retard()) {
            migrations_noter_a_jour(db());
        }
        sortie_ligne('La base est déjà à jour.');
        exit(0);
    }
    sortie_ligne(count($en_attente) . ' migration(s) en attente : ' . implode(', ', $en_attente));

    // Sauvegarde obligatoire : sans elle, la mise à jour ne démarre pas.
    $nom_sauvegarde = sauvegarde_creer();
    parametre_enregistrer('derniere_sauvegarde', date('Y-m-d H:i:s'));
    journaliser('sauvegarde', 'sauvegardes', null, 'Création de la sauvegarde ' . $nom_sauvegarde . ' (avant mise à jour, ligne de commande)');
    sortie_ligne('Sauvegarde : sauvegardes/' . $nom_sauvegarde);
} catch (Throwable $erreur) {
    sortie_echec('Échec avant la mise à jour, rien n\'a été modifié : ' . $erreur->getMessage());
}

$bilan = mise_a_jour_appliquer(null);
foreach ($bilan['appliquees'] as $fichier) {
    sortie_ligne('Appliquée : ' . $fichier);
}
if ($bilan['erreur'] !== '') {
    sortie_echec('ÉCHEC de ' . $bilan['fichier_en_erreur'] . ' : ' . $bilan['erreur'] . PHP_EOL
        . 'La mise à jour est interrompue. Corrigez puis relancez, ou restaurez sauvegardes/' . $nom_sauvegarde . '.');
}
sortie_ligne('La base est à jour.');
