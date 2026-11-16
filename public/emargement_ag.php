<?php

declare(strict_types=1);

// Feuille d'émargement de l'assemblée générale, au format PDF : adhérents
// majeurs et structures inscrits pour la saison affichée. Tout utilisateur
// connecté (aucun montant).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$saison_ag = saison_courante();
if ($saison_ag === null) {
    flash('erreur', 'Aucune saison n\'est définie.');
    rediriger('index.php');
}

$pdf = emargement_ag_pdf($saison_ag);
if ($pdf === null) {
    flash('erreur', 'Le document n\'a pas pu être produit : la bibliothèque PDF est absente du serveur.');
    rediriger('index.php');
}

document_envoyer($pdf, document_nom_fichier('emargement assemblee generale', texte($saison_ag['nom'])));
