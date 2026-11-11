<?php

declare(strict_types=1);

// Déconnexion : uniquement par l'envoi du formulaire du menu (POST).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

if (est_post() && csrf_valide() && utilisateur_courant() !== null) {
    journaliser('deconnexion', 'utilisateurs', entier($_SESSION['utilisateur_id'] ?? 0), 'Déconnexion');
    deconnecter();
    flash('info', 'Vous êtes déconnecté.');
    rediriger('connexion.php');
}

rediriger('index.php');
