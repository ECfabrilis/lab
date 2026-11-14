<?php

declare(strict_types=1);

// Fiche d'un type de tarif : nom, actif ou inactif, suppression s'il n'est
// utilisé par aucun tarif (administrateurs seulement). Les types « Adhésion »
// et « Forfait » sont figés : ils n'ont pas de fiche.
// Même page que mode_paiement.php : seule la liste de choix change.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$liste_choix = 'types_tarif';
$definition = choix_definition($liste_choix);

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = choix_par_id($liste_choix, $fiche_id);
if ($fiche === null) {
    flash('erreur', 'Ce choix n\'existe pas dans la liste.');
    rediriger($definition['page_liste']);
}
if (choix_est_fige($fiche)) {
    flash('info', '« ' . texte($fiche['nom']) . ' » est réservé : il ne se modifie pas et ne se supprime pas.');
    rediriger($definition['page_liste']);
}
$page_fiche = $definition['page_fiche'] . '?id=' . $fiche_id;
$nb_utilisations = entier($fiche['nb_activites']);

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }

    if (post('action') === 'supprimer') {
        if (choix_supprimer($liste_choix, $fiche)) {
            flash('succes', '« ' . texte($fiche['nom']) . ' » est supprimé de la liste.');
            rediriger($definition['page_liste']);
        }
        flash('erreur', 'Ce choix est utilisé : il ne peut pas être supprimé. Vous pouvez le rendre inactif.');
        rediriger($page_fiche);
    }

    $nom_saisi = post('nom');
    $erreur_nom = choix_erreur_nom($liste_choix, $nom_saisi, $fiche_id);
    if ($erreur_nom !== null) {
        flash('erreur', $erreur_nom);
        ancien_memoriser();
        rediriger($page_fiche);
    }
    if (choix_modifier($liste_choix, $fiche, $nom_saisi, post('est_actif') === '1')) {
        flash('succes', 'La modification est enregistrée.');
    } else {
        flash('info', 'Aucune modification à enregistrer.');
    }
    rediriger($page_fiche);
}

$est_actif_affiche = ancien_existe() ? ancien('est_actif') === '1' : entier($fiche['est_actif']) === 1;

$titre_page = $definition['titre'];
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e($fiche['nom']) ?>
    <?php if (entier($fiche['est_actif']) !== 1) : ?><span class="badge text-bg-secondary">Inactif</span><?php endif; ?>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($nb_utilisations === 0) : ?>
                <form class="d-grid" method="post" action="<?= e(chemin_site($definition['page_fiche'])) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <button class="btn btn-outline-danger" type="submit" name="action" value="supprimer">Supprimer</button>
                </form>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($definition['page_liste'])) ?>">Retour à la liste</a>
        </div>
        <p class="d-none d-lg-block text-body-secondary small mt-3 mb-0">
            <?php if ($nb_utilisations === 0) : ?>
                <?= e($definition['usage_aucun']) ?> ce choix : il peut être supprimé.
            <?php else : ?>
                <?= $nb_utilisations ?> <?= e($nb_utilisations > 1 ? $definition['usage_plusieurs'] : $definition['usage_un']) ?> ce choix, toutes saisons confondues : il ne peut pas être supprimé, mais il peut être rendu inactif.
            <?php endif; ?>
        </p>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site($definition['page_fiche'])) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $fiche_id ?>">
            <div class="mb-3">
                <label class="form-label" for="nom">Nom</label>
                <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e(ancien('nom', $fiche['nom'])) ?>" required>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="est_actif" name="est_actif" value="1" <?= $est_actif_affiche ? 'checked' : '' ?> aria-describedby="aide_actif">
                <label class="form-check-label" for="est_actif">Actif</label>
                <div class="form-text" id="aide_actif">Inactif : il n'est plus proposé dans les formulaires ; ce qui l'utilise déjà le garde.</div>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
