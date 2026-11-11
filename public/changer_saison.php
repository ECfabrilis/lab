<?php

declare(strict_types=1);

// Changement de la saison affichée (liste déroulante du menu). Traitement seul, sans affichage.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

// Page de retour : uniquement une page du site, éventuellement avec son identifiant.
$retour = post('retour');
if (preg_match('/^[a-z_]+\.php(\?id=\d+)?$/', $retour) !== 1 || !is_file(__DIR__ . '/' . explode('?', $retour)[0])) {
    $retour = 'index.php';
}

if (est_post() && csrf_valide()) {
    $saison_choisie = saison_par_id(entier(post('saison_id')));
    if ($saison_choisie !== null) {
        $_SESSION['saison_id'] = entier($saison_choisie['id']);
    }
}

rediriger($retour);
