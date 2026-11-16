<?php

declare(strict_types=1);

// Export de la liste des adhérents pour un tableur (CSV : point-virgule,
// UTF-8 avec BOM, s'ouvre directement dans Excel). Reprend la recherche et
// les filtres de la page « Adhérents » (gardés en session), sans le découpage
// en pages. Rôle standard au minimum ; l'export est noté dans le journal.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_export = saison_courante();
$lignes_export = export_adherents_lignes(entier($saison_export['id'] ?? 0));
if ($lignes_export === []) {
    flash('avertissement', 'Aucun adhérent ne répond aux critères : rien à exporter.');
    rediriger('adherents.php');
}

journaliser('export', 'adherents', null, 'Export de la liste des adhérents pour un tableur (' . count($lignes_export) . (count($lignes_export) > 1 ? ' lignes)' : ' ligne)'));
csv_envoyer(csv_nom_fichier('adherents', texte($saison_export['nom'] ?? '')), export_adherents_entetes(), $lignes_export, true);
