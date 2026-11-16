<?php

declare(strict_types=1);

// Documents d'une activité, au format PDF (affichés dans le navigateur, pour
// les imprimer ou les enregistrer) : liste des participants, liste de sortie,
// feuille d'émargement. La liste d'attente et les inscriptions inactives
// s'ajoutent sur demande. Aucun montant : tout utilisateur connecté.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$activite_id = entier($_GET['id'] ?? 0);
$activite = activite_par_id($activite_id);
if ($activite === null) {
    flash('erreur', 'Cette activité n\'existe pas.');
    rediriger('activites.php');
}
$modele = texte($_GET['modele'] ?? '');
$modeles = activite_documents_modeles();
if (!isset($modeles[$modele])) {
    flash('erreur', 'Ce document n\'existe pas.');
    rediriger('activite.php?id=' . $activite_id);
}

$pdf = activite_document_pdf($activite, $modele, isset($_GET['attente']), isset($_GET['inactifs']));
if ($pdf === null) {
    flash('erreur', 'Le document n\'a pas pu être produit : la bibliothèque PDF est absente du serveur.');
    rediriger('activite.php?id=' . $activite_id);
}

document_envoyer($pdf, document_nom_fichier($modeles[$modele], texte($activite['nom'])));
