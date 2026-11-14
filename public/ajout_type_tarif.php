<?php

declare(strict_types=1);

// Ajout d'un type de tarif (administrateurs seulement). Un type ajouté ici est
// libre : son tarif est dû à chaque inscription.
// Même page que ajout_mode_paiement.php : seule la liste de choix change.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$liste_choix = 'types_tarif';
$definition = choix_definition($liste_choix);

if (est_post()) {
    csrf_verifier();
    if (!jeton_unique_consommer($definition['page_ajout'])) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger($definition['page_liste']);
    }

    $nom_saisi = post('nom');
    $erreur_nom = choix_erreur_nom($liste_choix, $nom_saisi);
    if ($erreur_nom !== null) {
        flash('erreur', $erreur_nom);
        ancien_memoriser();
        rediriger($definition['page_ajout']);
    }

    choix_creer($liste_choix, $nom_saisi);
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
            <button class="btn btn-primary" type="submit">Ajouter</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
