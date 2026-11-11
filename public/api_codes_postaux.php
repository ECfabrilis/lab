<?php

declare(strict_types=1);

// Propositions de codes postaux pour les formulaires d'adresse (appelé par js/adresse.js).
// Réponse JSON, voir api_exiger().

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

api_exiger('standard');

echo json_encode(['resultats' => codes_postaux_chercher(post('q'))], JSON_UNESCAPED_UNICODE);
