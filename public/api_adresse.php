<?php

declare(strict_types=1);

// Propositions d'adresses pour les formulaires (appelé par js/adresse.js).
// Le serveur interroge l'API Adresse à la place du navigateur : la politique
// de sécurité du site reste stricte et l'adresse saisie ne figure dans aucune
// adresse de page. Répond en JSON à un envoi POST, sans redirection.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

if (utilisateur_courant() === null || !est_post() || !csrf_valide()) {
    http_response_code(403);
    echo json_encode(['erreur' => 'Accès refusé.']);
    exit;
}

$propositions = adresse_api_chercher(post('q'), post('code_postal'));
if ($propositions === null) {
    http_response_code(502);
    echo json_encode(['erreur' => 'Le service d\'adresses ne répond pas.']);
    exit;
}

$resultats = [];
foreach ($propositions as $proposition) {
    $resultats[] = [
        'libelle'     => $proposition['libelle'],
        'adresse'     => $proposition['adresse'],
        'code_postal' => $proposition['code_postal'],
        'ville'       => $proposition['ville'],
    ];
}
echo json_encode(['resultats' => $resultats], JSON_UNESCAPED_UNICODE);
