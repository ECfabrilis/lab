<?php

declare(strict_types=1);

// Adhérents proches de la personne en cours de saisie (appelé par js/inscription.js
// pendant l'étape 1 du formulaire d'inscription). Réponse JSON, voir api_exiger().

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

api_exiger('standard');

echo json_encode(
    ['resultats' => adherents_proches(post('nom'), post('prenom'), post('date_naissance'), post('structure') === '1')],
    JSON_UNESCAPED_UNICODE
);
