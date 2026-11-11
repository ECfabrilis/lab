<?php

declare(strict_types=1);

// Propositions pendant la frappe dans la recherche d'adhérents (appelé par
// js/recherche.js). La liste des adhérents est ouverte à tout utilisateur
// connecté : cette aide aussi. Réponse JSON, voir api_exiger().

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

api_exiger('lecteur');

$saison_affichee = saison_courante();

echo json_encode(
    ['resultats' => adherents_suggestions(post('q'), entier($saison_affichee['id'] ?? 0), post('perimetre') !== 'tous')],
    JSON_UNESCAPED_UNICODE
);
