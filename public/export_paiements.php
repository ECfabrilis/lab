<?php

declare(strict_types=1);

// Export de la liste des paiements pour un tableur (CSV : point-virgule,
// UTF-8 avec BOM, s'ouvre directement dans Excel). Reprend les filtres de la
// page « Paiements » (période, mode, objet, annulés), passés dans l'adresse.
// Rôle standard au minimum ; l'export est noté dans le journal.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$filtres_export = paiements_filtres($_GET);
$retour = 'paiements.php?' . paiements_filtres_requete($filtres_export);
$lignes_export = export_paiements_lignes($filtres_export);
if ($lignes_export === []) {
    flash('avertissement', 'Aucun paiement sur cette période : rien à exporter.');
    rediriger($retour);
}

$nb_lignes = count($lignes_export);
journaliser('export', 'paiements', null, 'Export de la liste des paiements du ' . date_fr($filtres_export['du']) . ' au ' . date_fr($filtres_export['au'])
    . ' pour un tableur (' . $nb_lignes . ($nb_lignes > 1 ? ' lignes)' : ' ligne)'));
csv_envoyer(
    csv_nom_fichier('paiements', $filtres_export['du'], $filtres_export['au']),
    export_paiements_entetes($filtres_export['annules']),
    $lignes_export,
    true
);
