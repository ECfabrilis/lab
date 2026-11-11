<?php

declare(strict_types=1);

// Aide à la saisie des adresses (appelé par js/adresse.js). Deux usages :
//   - propositions pendant la frappe (champ q, et code_postal pour orienter) ;
//   - vérification d'une adresse complète (champ verifier = 1) : dit si elle
//     est certifiée et donne le quartier, le QP et le QPV.
// Le serveur interroge l'API Adresse à la place du navigateur : la politique
// de sécurité du site reste stricte. Réponse JSON, voir api_exiger().

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

api_exiger('standard');

// --- Vérification d'une adresse complète ---------------------------------------
if (post('verifier') === '1') {
    $saisie = ['adresse' => post('adresse'), 'complement' => '', 'code_postal' => post('code_postal'), 'ville' => post('ville'), 'etablissement' => '', 'niveau' => ''];
    if ($saisie['adresse'] === '' || preg_match('/^\d{5}$/', $saisie['code_postal']) !== 1) {
        echo json_encode(['certifiee' => false, 'incomplete' => true]);
        exit;
    }
    $adresse = adresse_preparer($saisie);
    echo json_encode([
        'certifiee'   => $adresse['est_validee'] === 1,
        'adresse'     => $adresse['adresse'],
        'code_postal' => $adresse['code_postal'],
        'ville'       => $adresse['ville'],
        'quartiers'   => quartiers_resume($adresse),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Propositions pendant la frappe ----------------------------------------------
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
