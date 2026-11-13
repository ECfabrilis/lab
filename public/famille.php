<?php

declare(strict_types=1);

// Fiche d'une famille : ses membres, leur inscription pour la saison affichée
// (inscrire, retirer), les notes de la saison et le rappel des autres saisons.
// Le « dossier » (lien famille-saison) est créé automatiquement à la première
// inscription de la saison ; il n'est jamais montré à l'écran.
// Consultation : tout utilisateur connecté. Modification : rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$famille_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$famille = famille_par_id($famille_id);
if ($famille === null) {
    flash('erreur', 'Cette famille n\'existe pas.');
    rediriger('familles.php');
}
$page_fiche = 'famille.php?id=' . $famille_id;
$peut_modifier = role_au_moins('standard') && texte($famille['anonymise_le']) === '';

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);
$saison_affichee_nom = texte($saison_affichee['nom'] ?? '');

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    if (!$peut_modifier) {
        flash('erreur', 'Vous n\'avez pas les droits nécessaires pour modifier cette famille.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Nom de la famille ---------------------------------------------------
    if ($action === 'renommer') {
        $nom_saisi = nom_normaliser(post('nom_famille'));
        if ($nom_saisi === '' || mb_strlen($nom_saisi) > 100) {
            flash('erreur', 'Le nom de la famille est obligatoire (100 caractères au maximum).');
            ancien_memoriser();
        } elseif ($nom_saisi === texte($famille['nom'])) {
            flash('info', 'Aucune modification à enregistrer.');
        } else {
            db_executer('UPDATE familles SET nom = ? WHERE id = ?', [$nom_saisi, $famille_id]);
            journaliser('modification', 'familles', $famille_id, 'Changement du nom de la famille', ['nom' => $famille['nom']], ['nom' => $nom_saisi]);
            flash('succes', 'Le nom de la famille est modifié.');
        }
        rediriger($page_fiche);
    }

    // --- Inscription d'un membre pour la saison, avec sa dernière adresse connue ---
    if ($action === 'inscrire_membre' && $saison_affichee_id > 0) {
        $membre = adherent_par_id(entier(post('adherent_id')));
        if ($membre === null || entier($membre['famille_id']) !== $famille_id) {
            flash('erreur', 'Cette personne ne fait pas partie de la famille.');
            rediriger($page_fiche);
        }
        $membre_id = entier($membre['id']);
        $derniere = derniere_adresse_adherent($membre_id) ?? derniere_adresse_famille($famille_id);
        if ($derniere === null) {
            flash('erreur', 'Aucune adresse n\'est connue pour cette personne : inscrivez-la depuis sa fiche, en saisissant son adresse.');
            rediriger('adherent.php?id=' . $membre_id);
        }
        $adresse_reprise = [];
        foreach (['adresse', 'complement', 'code_postal', 'ville', 'etablissement', 'niveau'] as $champ) {
            $adresse_reprise[$champ] = texte($derniere[$champ] ?? '');
        }
        db()->beginTransaction();
        try {
            adresse_enregistrer(dossier_obtenir($famille_id, $saison_affichee_id), $membre_id, $adresse_reprise);
            db()->commit();
        } catch (Throwable $erreur) {
            db()->rollBack();
            throw $erreur;
        }
        flash('succes', texte($membre['prenom']) . ' est inscrit(e) pour la saison ' . $saison_affichee_nom . ', avec sa dernière adresse connue. Vérifiez sur sa fiche qu\'elle est toujours valable.');
        rediriger($page_fiche);
    }

    // --- Retrait d'un membre de la saison (sa fiche est conservée) -----------
    if ($action === 'retirer_membre') {
        $dossier_id = dossier_id_de_famille($famille_id, $saison_affichee_id);
        if ($dossier_id > 0 && inscriptions_actives_nb($dossier_id, entier(post('adherent_id'))) > 0) {
            flash('erreur', 'Cette personne a des inscriptions en cours à des activités de la saison : annulez-les d\'abord.');
        } elseif ($dossier_id > 0 && cotisations_actives_nb($dossier_id, entier(post('adherent_id'))) > 0) {
            flash('erreur', 'Cette personne a une cotisation en cours pour la saison : annulez-la d\'abord, dans les paiements de la famille.');
        } elseif ($dossier_id > 0 && retirer_de_la_saison($dossier_id, entier(post('adherent_id')))) {
            flash('succes', 'La personne est retirée de la saison ' . $saison_affichee_nom . '. Sa fiche est conservée.');
        }
        rediriger($page_fiche);
    }

    // --- Notes de la famille pour la saison ----------------------------------
    if ($action === 'notes') {
        $dossier_id = dossier_id_de_famille($famille_id, $saison_affichee_id);
        $notes_saisies = post('notes');
        if ($dossier_id <= 0) {
            flash('erreur', 'Les notes se saisissent quand un membre de la famille est inscrit pour la saison.');
        } elseif (mb_strlen($notes_saisies) > 5000) {
            flash('erreur', 'Les notes sont trop longues (5 000 caractères au maximum).');
            ancien_memoriser();
        } elseif (notes_saison_enregistrer($dossier_id, $notes_saisies)) {
            flash('succes', 'Les notes sont enregistrées.');
        } else {
            flash('info', 'Aucune modification à enregistrer.');
        }
        rediriger($page_fiche);
    }

    rediriger($page_fiche);
}

$membres = famille_membres($famille_id, $saison_affichee_id);
$dossier_saison_id = dossier_id_de_famille($famille_id, $saison_affichee_id);
$notes_saison = $dossier_saison_id > 0 ? texte(dossier_par_id($dossier_saison_id)['notes'] ?? '') : '';
// Paiements de la famille pour la saison : rôle standard au minimum.
$compte_saison = role_au_moins('standard') ? compte_du_dossier($dossier_saison_id) : null;
$sans_cotisation = $compte_saison !== null ? cotisations_manquantes($dossier_saison_id) : [];
// Saisons où au moins un membre de la famille est inscrit.
$saisons_famille = array_values(array_filter(
    dossiers_de_famille($famille_id),
    static fn (array $ligne): bool => entier($ligne['nb_adherents']) > 0
));

$titre_page = 'Famille';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Famille <?= e($famille['nom']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($peut_modifier) : ?>
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_adherent.php?famille=' . $famille_id)) ?>">Inscrire un nouveau membre</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('familles.php')) ?>">Retour à la liste</a>
        </div>

        <?php if ($peut_modifier) : ?>
            <form class="mt-4" method="post" action="<?= e(chemin_site('famille.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="id" value="<?= $famille_id ?>">
                <input type="hidden" name="action" value="renommer">
                <label class="form-label" for="nom_famille">Nom de la famille</label>
                <div class="input-group">
                    <input class="form-control" type="text" id="nom_famille" name="nom_famille" maxlength="100" value="<?= e(ancien('nom_famille', $famille['nom'])) ?>" required>
                    <button class="btn btn-outline-secondary" type="submit">Renommer</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($saisons_famille !== []) : ?>
            <h2 class="h6 mt-4">Saisons</h2>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($saisons_famille as $saison_famille) : ?>
                    <li><?= e($saison_famille['saison_nom']) ?> : <?= entier($saison_famille['nb_adherents']) ?> inscrit<?= entier($saison_famille['nb_adherents']) > 1 ? 's' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="col-md-8 order-md-1">
        <h2 class="h5">Membres</h2>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">Âge</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Saison <?= e($saison_affichee_nom) ?></th>
                        <?php if ($peut_modifier && $saison_affichee !== null) : ?><th scope="col"><span class="visually-hidden">Action</span></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($membres as $membre) : ?>
                        <?php $est_inscrit = entier($membre['dans_dossier']) === 1; ?>
                        <tr>
                            <td><a href="<?= e(chemin_site('adherent.php?id=' . entier($membre['id']))) ?>"><?= e($membre['prenom']) ?> <?= e($membre['nom']) ?></a></td>
                            <td><?= age_ans(texte($membre['date_naissance'])) ?> ans</td>
                            <td class="small">
                                <?= e($membre['tel']) ?>
                                <?php if (texte($membre['tel']) !== '' && texte($membre['mail']) !== '') : ?><br><?php endif; ?>
                                <?= e($membre['mail']) ?>
                            </td>
                            <td>
                                <?php if ($est_inscrit) : ?>
                                    <span class="badge text-bg-success">Inscrit(e)</span>
                                <?php else : ?>
                                    <span class="badge text-bg-secondary">Non inscrit(e)</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($peut_modifier && $saison_affichee !== null) : ?>
                                <td class="text-end">
                                    <form method="post" action="<?= e(chemin_site('famille.php')) ?>">
                                        <?= csrf_champ() ?>
                                        <input type="hidden" name="id" value="<?= $famille_id ?>">
                                        <input type="hidden" name="adherent_id" value="<?= entier($membre['id']) ?>">
                                        <?php if ($est_inscrit) : ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="retirer_membre">Retirer de la saison</button>
                                        <?php else : ?>
                                            <button class="btn btn-sm btn-outline-primary" type="submit" name="action" value="inscrire_membre">Inscrire pour la saison</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($compte_saison !== null) : ?>
            <?php // Montants : réservés au rôle standard au minimum. ?>
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h2 class="h5">Paiements — saison <?= e($saison_affichee_nom) ?></h2>
                <a class="small" href="<?= e(chemin_site('compte.php?famille=' . $famille_id)) ?>">Cotisations et paiements</a>
            </div>
            <div class="mb-4">
                <?php afficher_resume_compte($compte_saison); ?>
                <?php if ($sans_cotisation !== []) : ?>
                    <p class="small text-warning-emphasis mb-0">Sans cotisation pour la saison, alors qu'une de ses activités demande l'adhésion : <?= e(implode(', ', $sans_cotisation)) ?>.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($dossier_saison_id > 0) : ?>
            <h2 class="h5">Notes — saison <?= e($saison_affichee_nom) ?></h2>
            <form method="post" action="<?= e(chemin_site('famille.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="id" value="<?= $famille_id ?>">
                <input type="hidden" name="action" value="notes">
                <fieldset <?= $peut_modifier ? '' : 'disabled' ?>>
                    <div class="mb-3">
                        <label class="form-label visually-hidden" for="notes">Notes de la famille pour la saison</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" maxlength="5000"><?= e(ancien('notes', $notes_saison)) ?></textarea>
                    </div>
                    <?php if ($peut_modifier) : ?>
                        <button class="btn btn-primary" type="submit">Enregistrer les notes</button>
                    <?php endif; ?>
                </fieldset>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
