<?php

declare(strict_types=1);

// Fiche d'un tarif : nom, type, montants, description, suppression s'il n'est utilisé
// par aucune activité ni aucune cotisation (administrateurs seulement).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = tarif_par_id($fiche_id);
if ($fiche === null) {
    flash('erreur', 'Ce tarif n\'existe pas.');
    rediriger('tarifs.php');
}
$page_fiche = 'tarif.php?id=' . $fiche_id;
$nb_activites = entier($fiche['nb_activites']);
$nb_cotisations = entier($fiche['nb_cotisations']);

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }

    if (post('action') === 'supprimer') {
        if (supprimer_tarif($fiche)) {
            flash('succes', 'Le tarif « ' . texte($fiche['nom']) . ' » est supprimé.');
            rediriger('tarifs.php');
        }
        flash('erreur', 'Des activités ou des cotisations utilisent ce tarif : il ne peut pas être supprimé.');
        rediriger($page_fiche);
    }

    $saisie = tarif_saisie();
    $erreurs = erreurs_tarif($saisie, entier($fiche['saison_id']), $fiche_id);
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger($page_fiche);
    }
    if (modifier_tarif($fiche, $saisie)) {
        flash('succes', 'Le tarif est modifié. Les inscriptions et les cotisations déjà enregistrées gardent leur montant.');
    } else {
        flash('info', 'Aucune modification à enregistrer.');
    }
    rediriger($page_fiche);
}

$titre_page = 'Tarif';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e($fiche['nom']) ?> <span class="badge text-bg-light border"><?= e(libelles_types_tarif()[texte($fiche['type'])] ?? '') ?></span> <span class="badge text-bg-secondary">Saison <?= e($fiche['saison_nom']) ?></span></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($nb_activites === 0 && $nb_cotisations === 0) : ?>
                <form class="d-grid" method="post" action="<?= e(chemin_site('tarif.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <button class="btn btn-outline-danger" type="submit" name="action" value="supprimer">Supprimer</button>
                </form>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('tarifs.php')) ?>">Retour à la liste</a>
        </div>
        <p class="text-body-secondary small mt-3 mb-0">
            <?php if ($nb_cotisations > 0) : ?>
                <?= $nb_cotisations ?> cotisation<?= $nb_cotisations > 1 ? 's utilisent' : ' utilise' ?> ce tarif : il ne peut pas être supprimé.
            <?php elseif ($nb_activites === 0) : ?>
                Ce tarif n'est pas utilisé : il peut être supprimé.
            <?php else : ?>
                <?= $nb_activites ?> activité<?= $nb_activites > 1 ? 's utilisent' : ' utilise' ?> ce tarif : il ne peut pas être supprimé.
            <?php endif; ?>
            Modifier les montants ne change ni les inscriptions ni les cotisations déjà enregistrées.
        </p>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('tarif.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $fiche_id ?>">
            <?php afficher_champs_tarif($fiche); ?>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
