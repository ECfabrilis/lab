<?php

declare(strict_types=1);

// Reçu d'un paiement, au format PDF (affiché dans le navigateur, pour
// l'imprimer ou l'enregistrer). Rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$paiement_id = entier($_GET['id'] ?? 0);
$paiement = paiement_par_id($paiement_id);
if ($paiement === null) {
    flash('erreur', 'Ce paiement n\'existe pas.');
    rediriger('paiements.php');
}
if (entier($paiement['est_annule']) === 1) {
    flash('erreur', 'Ce paiement est annulé : il n\'a pas de reçu.');
    rediriger('paiement.php?id=' . $paiement_id);
}
$pdf = recu_pdf($paiement);
if ($pdf === null) {
    flash('erreur', 'Le reçu n\'a pas pu être produit : la bibliothèque PDF est absente du serveur.');
    rediriger('paiement.php?id=' . $paiement_id);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . recu_nom_fichier($paiement_id) . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
