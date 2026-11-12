<?php

declare(strict_types=1);

// Après la validation d'une inscription : l'utilisateur choisit la suite
// (cahier des charges, section 12.2). Les choix dont la page n'existe pas
// encore (paiement) sont affichés « À venir ».

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$inscrit = adherent_par_id(entier($_GET['id'] ?? 0));
if ($inscrit === null) {
    rediriger('adherents.php');
}
$inscrit_id = entier($inscrit['id']);
$famille_id = entier($inscrit['famille_id']);

$cards = [
    ['titre' => 'Voir la fiche', 'texte' => 'Fiche de ' . nom_adherent($inscrit) . '.', 'page' => 'adherent.php?id=' . $inscrit_id, 'role' => 'lecteur'],
    ['titre' => 'Ajouter un paiement', 'texte' => 'Encaisser un paiement pour la saison.', 'page' => 'ajout_paiement.php?adherent=' . $inscrit_id, 'role' => 'standard'],
    ['titre' => 'Inscrire à une activité', 'texte' => 'Choisir une activité de la saison.', 'page' => 'ajout_inscription.php?adherent=' . $inscrit_id . '&origine=adherent', 'role' => 'standard'],
];
if ($famille_id > 0) {
    $cards[] = ['titre' => 'Inscrire une autre personne de la famille', 'texte' => 'Formulaire prérempli avec la famille ' . texte($inscrit['famille_nom']) . ', son adresse et son téléphone.', 'page' => 'ajout_adherent.php?famille=' . $famille_id, 'role' => 'standard'];
}
$cards[] = ['titre' => 'Nouvelle inscription', 'texte' => 'Formulaire vide, pour une autre personne ou une structure.', 'page' => 'ajout_adherent.php?nouveau=1', 'role' => 'standard'];

$titre_page = 'Inscription enregistrée';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Inscription enregistrée</h1>
<?php afficher_cards([['titre' => 'Et maintenant ?', 'role' => 'standard', 'cards' => $cards]]); ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
