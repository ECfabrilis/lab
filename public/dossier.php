<?php

declare(strict_types=1);

// Dossier d'une famille pour une saison : adhérents du dossier (avec leur
// adresse de la saison) et notes. Les inscriptions et les paiements s'y
// ajouteront aux étapes suivantes.
// Consultation : tout utilisateur connecté. Modification : rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$dossier_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$dossier = dossier_par_id($dossier_id);
if ($dossier === null) {
    flash('erreur', 'Ce dossier n\'existe pas.');
    rediriger('adherents.php');
}
$page_fiche = 'dossier.php?id=' . $dossier_id;
$famille_id = entier($dossier['famille_id']);
// Dossier d'une structure : pas de famille, un seul adhérent (la structure elle-même).
$est_structure = entier($dossier['structure_id']) > 0;
$titulaire = $est_structure ? 'structure ' . texte($dossier['structure_nom']) : 'famille ' . texte($dossier['famille_nom']);
$peut_modifier = role_au_moins('standard');

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    if (!$peut_modifier) {
        flash('erreur', 'Vous n\'avez pas les droits nécessaires pour modifier ce dossier.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Notes du dossier ----------------------------------------------------
    if ($action === 'notes') {
        $notes_saisies = post('notes');
        if (mb_strlen($notes_saisies) > 5000) {
            flash('erreur', 'Les notes sont trop longues (5 000 caractères au maximum).');
            ancien_memoriser();
        } elseif ($notes_saisies === texte($dossier['notes'])) {
            flash('info', 'Aucune modification à enregistrer.');
        } else {
            db_executer(
                'UPDATE dossiers SET notes = ?, modification_par = ? WHERE id = ?',
                [nul_si_vide($notes_saisies), entier($_SESSION['utilisateur_id'] ?? 0), $dossier_id]
            );
            journaliser('modification', 'dossiers', $dossier_id, 'Modification des notes du dossier', ['notes' => $dossier['notes']], ['notes' => $notes_saisies]);
            flash('succes', 'Les notes sont enregistrées.');
        }
        rediriger($page_fiche);
    }

    // --- Ajout d'un membre de la famille, avec sa dernière adresse connue ----
    if ($action === 'ajouter_membre') {
        $membre = adherent_par_id(entier(post('adherent_id')));
        if ($membre === null || $est_structure || entier($membre['famille_id']) !== $famille_id) {
            flash('erreur', 'Cette personne ne fait pas partie de la famille.');
            rediriger($page_fiche);
        }
        $membre_id = entier($membre['id']);
        $derniere = derniere_adresse_adherent($membre_id) ?? derniere_adresse_famille($famille_id);
        if ($derniere === null) {
            flash('erreur', 'Aucune adresse n\'est connue pour cette personne : saisissez-la sur sa fiche.');
            rediriger('adherent.php?id=' . $membre_id);
        }
        $adresse_reprise = [];
        foreach (['adresse', 'complement', 'code_postal', 'ville', 'etablissement', 'niveau'] as $champ) {
            $adresse_reprise[$champ] = texte($derniere[$champ] ?? '');
        }
        adresse_enregistrer($dossier_id, $membre_id, $adresse_reprise);
        flash('succes', texte($membre['prenom']) . ' est dans le dossier, avec sa dernière adresse connue. Vérifiez qu\'elle est toujours valable.');
        rediriger($page_fiche);
    }

    // --- Retrait d'un adhérent du dossier ------------------------------------
    if ($action === 'retirer_membre') {
        $membre_id = entier(post('adherent_id'));
        $ancienne = adresse_du_dossier($dossier_id, $membre_id);
        if ($ancienne !== null) {
            db_executer('DELETE FROM dossiers_adherents WHERE dossier_id = ? AND adherent_id = ?', [$dossier_id, $membre_id]);
            journaliser('suppression', 'dossiers_adherents', $membre_id, 'Retrait de l\'adhérent du dossier ' . $dossier_id, $ancienne);
            flash('succes', 'La personne est retirée du dossier de cette saison. Sa fiche est conservée.');
        }
        rediriger($page_fiche);
    }

    rediriger($page_fiche);
}

$membres = dossier_membres($dossier_id);
$presents = array_map(static fn (array $membre): int => entier($membre['id']), $membres);
$absents = $est_structure ? [] : array_values(array_filter(
    famille_membres($famille_id),
    static fn (array $membre): bool => !in_array(entier($membre['id']), $presents, true)
));

$titre_page = 'Dossier';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Dossier <?= e($dossier['saison_nom']) ?> — <?= e($titulaire) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if (!$est_structure) : ?>
                <?php if ($peut_modifier) : ?>
                    <a class="btn btn-primary" href="<?= e(chemin_site('ajout_adherent.php?famille=' . $famille_id)) ?>">Inscrire un nouveau membre</a>
                <?php endif; ?>
                <a class="btn btn-outline-primary" href="<?= e(chemin_site('famille.php?id=' . $famille_id)) ?>">Famille <?= e($dossier['famille_nom']) ?></a>
            <?php else : ?>
                <a class="btn btn-outline-primary" href="<?= e(chemin_site('adherent.php?id=' . entier($dossier['structure_id']))) ?>">Fiche de la structure</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('adherents.php')) ?>">Retour à la liste</a>
        </div>

        <?php if ($peut_modifier && $absents !== []) : ?>
            <h2 class="h6 mt-4">Membres de la famille absents de ce dossier</h2>
            <ul class="list-group">
                <?php foreach ($absents as $absent) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span><?= e($absent['prenom']) ?> <?= e($absent['nom']) ?></span>
                        <form method="post" action="<?= e(chemin_site('dossier.php')) ?>">
                            <?= csrf_champ() ?>
                            <input type="hidden" name="id" value="<?= $dossier_id ?>">
                            <input type="hidden" name="adherent_id" value="<?= entier($absent['id']) ?>">
                            <button class="btn btn-sm btn-outline-primary" type="submit" name="action" value="ajouter_membre">Ajouter</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <dl class="small text-body-secondary mt-4 mb-0">
            <dt>Dossier ouvert le</dt>
            <dd><?= e(date_fr($dossier['date_dossier'])) ?></dd>
        </dl>
    </div>
    <div class="col-md-8 order-md-1">
        <h2 class="h5">Adhérents du dossier</h2>
        <?php if ($membres === []) : ?>
            <div class="alert alert-info" role="alert">Ce dossier ne contient encore personne.</div>
        <?php else : ?>
            <div class="table-responsive mb-4">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Âge</th>
                            <th scope="col">Adresse de la saison</th>
                            <?php if ($peut_modifier) : ?><th scope="col"><span class="visually-hidden">Action</span></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($membres as $membre) : ?>
                            <tr>
                                <td><a href="<?= e(chemin_site('adherent.php?id=' . entier($membre['id']))) ?>"><?= e(nom_adherent($membre)) ?></a></td>
                                <td><?= $est_structure ? '—' : age_ans(texte($membre['date_naissance'])) . ' ans' ?></td>
                                <td class="small">
                                    <?= e($membre['adresse']) ?><?= texte($membre['complement']) !== '' ? ', ' . e($membre['complement']) : '' ?><br>
                                    <?= e($membre['code_postal']) ?> <?= e($membre['ville']) ?>
                                    <?php if (entier($membre['est_validee']) !== 1) : ?>
                                        <span class="badge text-bg-warning">Non certifiée</span>
                                    <?php endif; ?>
                                    <?php if (texte($membre['quartier']) !== '') : ?>
                                        <br><span class="text-body-secondary">Quartier : <?= e($membre['quartier']) ?></span>
                                    <?php endif; ?>
                                    <?php if (entier($membre['est_qpv']) === 1) : ?><span class="badge text-bg-primary">QPV</span><?php endif; ?>
                                    <?php if (entier($membre['est_qp']) === 1) : ?><span class="badge text-bg-primary">QP</span><?php endif; ?>
                                </td>
                                <?php if ($peut_modifier) : ?>
                                    <td class="text-end">
                                        <form method="post" action="<?= e(chemin_site('dossier.php')) ?>">
                                            <?= csrf_champ() ?>
                                            <input type="hidden" name="id" value="<?= $dossier_id ?>">
                                            <input type="hidden" name="adherent_id" value="<?= entier($membre['id']) ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="retirer_membre">Retirer</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="h5">Notes du dossier</h2>
        <form method="post" action="<?= e(chemin_site('dossier.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $dossier_id ?>">
            <input type="hidden" name="action" value="notes">
            <fieldset <?= $peut_modifier ? '' : 'disabled' ?>>
                <div class="mb-3">
                    <label class="form-label visually-hidden" for="notes">Notes du dossier</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" maxlength="5000"><?= e(ancien('notes', $dossier['notes'])) ?></textarea>
                </div>
                <?php if ($peut_modifier) : ?>
                    <button class="btn btn-primary" type="submit">Enregistrer les notes</button>
                <?php endif; ?>
            </fieldset>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
