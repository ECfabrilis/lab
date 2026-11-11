<?php

declare(strict_types=1);

// Propositions de codes postaux pour les formulaires d'adresse (appelé par js/adresse.js).
// Répond en JSON à un envoi POST : ce n'est pas une page, il n'y a donc pas de redirection.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

if (utilisateur_courant() === null || !est_post() || !csrf_valide()) {
    http_response_code(403);
    echo json_encode(['erreur' => 'Accès refusé.']);
    exit;
}

echo json_encode(['resultats' => codes_postaux_chercher(post('q'))], JSON_UNESCAPED_UNICODE);
