<?php

declare(strict_types=1);

// Attestation de participation d'un adhérent, au format PDF : pour une
// activité (inscription=…) ou pour toutes ses activités de la saison
// (inscription=0). Rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$adherent_id = entier($_GET['adherent'] ?? 0);
$adherent = adherent_par_id($adherent_id);
if ($adherent === null) {
    flash('erreur', 'Cette personne n\'existe pas.');
    rediriger('adherents.php');
}
$page_fiche = 'adherent.php?id=' . $adherent_id;
if (texte($adherent['anonymise_le']) !== '') {
    flash('erreur', 'Cette fiche est anonymisée : pas d\'attestation.');
    rediriger($page_fiche);
}
$saison = saison_par_id(entier($_GET['saison'] ?? 0));
if ($saison === null) {
    flash('erreur', 'Cette saison n\'existe pas.');
    rediriger($page_fiche);
}

$inscriptions = attestation_inscriptions($adherent_id, entier($saison['id']));
$inscription_id = entier($_GET['inscription'] ?? 0);
$toute_la_saison = $inscription_id === 0;
if (!$toute_la_saison) {
    $inscriptions = array_values(array_filter(
        $inscriptions,
        static fn (array $i): bool => entier($i['id']) === $inscription_id
    ));
}
if ($inscriptions === []) {
    flash('erreur', 'Aucune participation à attester pour cette saison.');
    rediriger($page_fiche);
}

$pdf = attestation_pdf($adherent, $saison, $inscriptions, $toute_la_saison);
if ($pdf === null) {
    flash('erreur', 'L\'attestation n\'a pas pu être produite : la bibliothèque PDF est absente du serveur.');
    rediriger($page_fiche);
}

document_envoyer($pdf, document_nom_fichier('attestation', nom_adherent($adherent), texte($saison['nom'])));
