<?php

declare(strict_types=1);

// Ajout d'un type d'activité (administrateurs seulement).
// Même page que ajout_secteur.php : seule la liste de choix change.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$liste_choix = 'types_activite';
$definition = choix_definition($liste_choix);

if (est_post()) {
    csrf_verifier();
    if (!jeton_unique_consommer($definition['page_ajout'])) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger($definition['page_liste']);
    }

    $nom_saisi = post('nom');
    $description_saisie = $definition['a_description'] ? post('description') : '';
    $erreur_nom = choix_erreur_nom($liste_choix, $nom_saisi) ?? choix_erreur_description($description_saisie);
    if ($erreur_nom !== null) {
        flash('erreur', $erreur_nom);
        ancien_memoriser();
        rediriger($definition['page_ajout']);
    }

    choix_creer($liste_choix, $nom_saisi, $description_saisie);
    flash('succes', '« ' . $nom_saisi . ' » est ajouté à la liste.');
    rediriger($definition['page_liste']);
}

$titre_page = 'Ajouter ' . $definition['un'];
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Ajouter <?= e($definition['un']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($definition['page_liste'])) ?>">Retour à la liste</a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site($definition['page_ajout'])) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ($definition['page_ajout']) ?>
            <div class="mb-3">
                <label class="form-label" for="nom">Nom</label>
                <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e(ancien('nom')) ?>" required>
            </div>
            <?php if ($definition['a_description']) : ?>
                <div class="mb-3">
                    <label class="form-label" for="description">Description <span class="text-body-secondary">(facultatif)</span></label>
                    <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000"><?= e(ancien('description')) ?></textarea>
                </div>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Ajouter</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
