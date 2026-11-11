<?php

declare(strict_types=1);

// Création d'une saison (administrateurs seulement).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

if (est_post()) {
    csrf_verifier();
    if (!jeton_unique_consommer('ajout_saison')) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger('saisons.php');
    }

    $nom_saisi = post('nom');
    $debut_saisi = post('date_debut');
    $fin_saisie = post('date_fin');

    $verification = verifier_saison($nom_saisi, $debut_saisi, $fin_saisie);
    $erreurs = $verification['erreurs'];
    if ($erreurs === [] && $verification['est_passee'] && post('confirmer_passee') !== '1') {
        $erreurs[] = 'Cette saison est déjà terminée : cochez la case de confirmation pour la créer quand même.';
    }
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger('ajout_saison.php');
    }

    db_executer('INSERT INTO saisons (nom, date_debut, date_fin) VALUES (?, ?, ?)', [$nom_saisi, $debut_saisi, $fin_saisie]);
    $nouvel_id = entier(db()->lastInsertId());
    journaliser('creation', 'saisons', $nouvel_id, 'Création de la saison « ' . $nom_saisi . ' »', null, [
        'nom'        => $nom_saisi,
        'date_debut' => $debut_saisi,
        'date_fin'   => $fin_saisie,
    ]);
    flash('succes', 'La saison « ' . $nom_saisi . ' » est créée.');
    rediriger('saisons.php');
}

$titre_page = 'Ajouter une saison';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Ajouter une saison</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('saisons.php')) ?>">Retour à la liste</a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('ajout_saison.php')) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ('ajout_saison') ?>
            <div class="mb-3">
                <label class="form-label" for="nom">Nom</label>
                <input class="form-control" type="text" id="nom" name="nom" maxlength="50" placeholder="2027-2028" value="<?= e(ancien('nom')) ?>" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="date_debut">Date de début</label>
                    <input class="form-control" type="date" id="date_debut" name="date_debut" value="<?= e(ancien('date_debut')) ?>" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="date_fin">Date de fin</label>
                    <input class="form-control" type="date" id="date_fin" name="date_fin" value="<?= e(ancien('date_fin')) ?>" required>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="confirmer_passee" name="confirmer_passee" value="1" <?= ancien('confirmer_passee') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="confirmer_passee">Je confirme la création d'une saison déjà terminée (à cocher seulement dans ce cas).</label>
            </div>
            <button class="btn btn-primary" type="submit">Créer la saison</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
