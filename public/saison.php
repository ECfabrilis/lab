<?php

declare(strict_types=1);

// Fiche d'une saison : modification du nom et des dates (administrateurs seulement).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = saison_par_id($fiche_id);
if ($fiche === null) {
    flash('erreur', 'Cette saison n\'existe pas.');
    rediriger('saisons.php');
}
$page_fiche = 'saison.php?id=' . $fiche_id;

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }

    $nom_saisi = post('nom');
    $debut_saisi = post('date_debut');
    $fin_saisie = post('date_fin');

    $verification = verifier_saison($nom_saisi, $debut_saisi, $fin_saisie, $fiche_id);
    $erreurs = $verification['erreurs'];
    $etait_passee = etat_saison(texte($fiche['date_debut']), texte($fiche['date_fin'])) === 'passee';
    if ($erreurs === [] && $verification['est_passee'] && !$etait_passee && post('confirmer_passee') !== '1') {
        $erreurs[] = 'Avec ces dates, la saison serait déjà terminée : cochez la case de confirmation pour l\'enregistrer quand même.';
    }
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger($page_fiche);
    }

    $nouveau = ['nom' => $nom_saisi, 'date_debut' => $debut_saisi, 'date_fin' => $fin_saisie];
    $ancien = ['nom' => $fiche['nom'], 'date_debut' => $fiche['date_debut'], 'date_fin' => $fiche['date_fin']];
    if ($nouveau == $ancien) {
        flash('info', 'Aucune modification à enregistrer.');
        rediriger($page_fiche);
    }

    db_executer('UPDATE saisons SET nom = ?, date_debut = ?, date_fin = ? WHERE id = ?', [$nom_saisi, $debut_saisi, $fin_saisie, $fiche_id]);
    journaliser('modification', 'saisons', $fiche_id, 'Modification de la saison « ' . $nom_saisi . ' »', $ancien, $nouveau);
    flash('succes', 'La saison est modifiée.');
    rediriger($page_fiche);
}

$titre_page = 'Saison';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Saison <?= e($fiche['nom']) ?>
    <span class="badge text-bg-secondary"><?= e(libelle_etat_saison(etat_saison(texte($fiche['date_debut']), texte($fiche['date_fin'])))) ?></span>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('saisons.php')) ?>">Retour à la liste</a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('saison.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $fiche_id ?>">
            <div class="mb-3">
                <label class="form-label" for="nom">Nom</label>
                <input class="form-control" type="text" id="nom" name="nom" maxlength="50" value="<?= e(ancien('nom', $fiche['nom'])) ?>" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="date_debut">Date de début</label>
                    <input class="form-control" type="date" id="date_debut" name="date_debut" value="<?= e(ancien('date_debut', $fiche['date_debut'])) ?>" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="date_fin">Date de fin</label>
                    <input class="form-control" type="date" id="date_fin" name="date_fin" value="<?= e(ancien('date_fin', $fiche['date_fin'])) ?>" required>
                </div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="confirmer_passee" name="confirmer_passee" value="1" <?= ancien('confirmer_passee') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="confirmer_passee">Je confirme que la saison sera déjà terminée avec ces dates (à cocher seulement dans ce cas).</label>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
