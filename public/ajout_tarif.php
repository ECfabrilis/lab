<?php

declare(strict_types=1);

// Ajout d'un tarif pour la saison affichée (rôle standard au minimum).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_affichee = saison_courante();
if ($saison_affichee === null) {
    flash('erreur', 'Aucune saison n\'existe encore.');
    rediriger('index.php');
}
$saison_affichee_id = entier($saison_affichee['id']);

if (est_post()) {
    csrf_verifier();
    if (!jeton_unique_consommer('ajout_tarif')) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger('tarifs.php');
    }
    // La saison affichée a pu changer dans un autre onglet depuis l'affichage du formulaire.
    if (entier(post('saison_id')) !== $saison_affichee_id) {
        flash('erreur', 'La saison affichée a changé : vérifiez la saison, puis recommencez.');
        ancien_memoriser();
        rediriger('ajout_tarif.php');
    }

    $saisie = tarif_saisie();
    $erreurs = erreurs_tarif($saisie, $saison_affichee_id);
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger('ajout_tarif.php');
    }

    creer_tarif($saison_affichee_id, $saisie);
    flash('succes', 'Le tarif « ' . $saisie['nom'] . ' » est créé pour la saison ' . texte($saison_affichee['nom']) . '.');
    rediriger('tarifs.php');
}

$titre_page = 'Ajouter un tarif';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Ajouter un tarif — saison <?= e($saison_affichee['nom']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('tarifs.php')) ?>">Retour à la liste</a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('ajout_tarif.php')) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ('ajout_tarif') ?>
            <input type="hidden" name="saison_id" value="<?= $saison_affichee_id ?>">
            <?php afficher_champs_tarif([]); ?>
            <button class="btn btn-primary" type="submit">Créer le tarif</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
