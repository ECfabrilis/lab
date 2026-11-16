<?php

declare(strict_types=1);

// Export vers Google Contacts (CSV au format d'import de Google) :
// - sans paramètre : adhérents de la saison affichée, libellé « Adhérents
//   [saison] », plus « Bénévoles » pour les bénévoles ;
// - avec id=… : participants de cette activité (inscrits ; liste d'attente et
//   inscriptions inactives sur demande), libellé « [activité] ([saison]) ».
// Seulement les personnes majeures et les structures qui ont accepté les
// communications. Rôle standard au minimum ; l'export est noté dans le journal.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$activite_id = entier($_GET['id'] ?? 0);
if ($activite_id > 0) {
    $activite = activite_par_id($activite_id);
    if ($activite === null) {
        flash('erreur', 'Cette activité n\'existe pas.');
        rediriger('activites.php');
    }
    $retour = 'activite.php?id=' . $activite_id;
    $contacts = contacts_activite($activite_id, isset($_GET['attente']), isset($_GET['inactifs']));
    $libelle = texte($activite['nom']) . ' (' . texte($activite['saison_nom']) . ')';
    $lignes_export = contacts_lignes($contacts, [$libelle], false);
    $nom_fichier = csv_nom_fichier('contacts', texte($activite['nom']));
    $description = 'Export vers Google Contacts des participants de l\'activité « ' . texte($activite['nom']) . ' »';
} else {
    $saison_export = saison_courante();
    if ($saison_export === null) {
        flash('erreur', 'Aucune saison n\'est définie.');
        rediriger('index.php');
    }
    $retour = 'index.php';
    $contacts = contacts_saison(entier($saison_export['id']));
    $lignes_export = contacts_lignes($contacts, ['Adhérents ' . texte($saison_export['nom'])], true);
    $nom_fichier = csv_nom_fichier('contacts adherents', texte($saison_export['nom']));
    $description = 'Export vers Google Contacts des adhérents de la saison ' . texte($saison_export['nom']);
}

if ($lignes_export === []) {
    flash('avertissement', 'Aucun contact à exporter : il faut une personne majeure ou une structure qui a accepté les communications, avec un courriel ou un téléphone.');
    rediriger($retour);
}

journaliser('export', $activite_id > 0 ? 'activites' : 'adherents', $activite_id > 0 ? $activite_id : null, $description . ' (' . count($lignes_export) . (count($lignes_export) > 1 ? ' contacts)' : ' contact)'));
csv_envoyer($nom_fichier, contacts_entetes(), $lignes_export, false);
