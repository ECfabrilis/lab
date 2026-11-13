<?php

declare(strict_types=1);

// Paiements d'une famille (ou d'une structure) pour la saison affichée :
// détail du montant dû, cotisations, réductions et paiements.
// La page s'ouvre par ?famille=N ou ?structure=N.
// Toute la page est réservée au rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$titulaire = compte_titulaire(
    entier(est_post() ? post('famille') : ($_GET['famille'] ?? 0)),
    entier(est_post() ? post('structure') : ($_GET['structure'] ?? 0))
);
if ($titulaire === null) {
    flash('erreur', 'Cette famille ou cette structure n\'existe pas.');
    rediriger('etat_paiements.php');
}
$page_compte = 'compte.php?' . $titulaire['lien'];
$est_structure = $titulaire['type'] === 'structure';

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);
$saison_affichee_nom = texte($saison_affichee['nom'] ?? '');

$dossier_id = compte_dossier_id($titulaire, $saison_affichee_id);
$dossier = $dossier_id > 0 ? dossier_par_id($dossier_id) : null;
$peut_modifier = $dossier !== null && !$titulaire['anonymise'];

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_compte);
    }
    if ($dossier === null || !$peut_modifier) {
        flash('erreur', 'Aucune modification n\'est possible pour cette saison.');
        rediriger($page_compte);
    }
    $action = post('action');

    // --- Nouvelle cotisation ---------------------------------------------------
    if ($action === 'ajouter_cotisation') {
        if (!jeton_unique_consommer('cotisation')) {
            flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez les cotisations avant de recommencer.');
            rediriger($page_compte);
        }
        // « Pour » : 0 = toute la famille ; un champ vide n'est pas un choix.
        $erreur = !$est_structure && preg_match('/^\d+$/', post('adherent_id')) !== 1
            ? 'Indiquez pour qui est la cotisation : toute la famille ou une personne.'
            : cotisation_creer($dossier, entier(post('adherent_id')), entier(post('tarif_id')), post('montant'), post('date_cotisation'));
        if ($erreur !== '') {
            flash('erreur', $erreur);
            ancien_memoriser();
        } else {
            flash('succes', 'La cotisation est enregistrée.');
        }
        rediriger($page_compte);
    }

    // --- Annulation d'une cotisation -------------------------------------------
    if ($action === 'annuler_cotisation') {
        if (cotisation_annuler(entier(post('cotisation_id')), $dossier_id)) {
            flash('succes', 'La cotisation est annulée.');
        } else {
            flash('info', 'Cette cotisation n\'est plus en cours : rien à annuler.');
        }
        rediriger($page_compte);
    }

    // --- Nouvelle réduction ----------------------------------------------------
    if ($action === 'ajouter_reduction') {
        if (!jeton_unique_consommer('reduction')) {
            flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez les réductions avant de recommencer.');
            rediriger($page_compte);
        }
        $erreur = reduction_creer($dossier_id, $est_structure ? 0 : entier(post('adherent_id')), post('montant'));
        if ($erreur !== '') {
            flash('erreur', $erreur);
            ancien_memoriser();
        } else {
            flash('succes', 'La réduction est enregistrée.');
        }
        rediriger($page_compte);
    }

    // --- Suppression d'une réduction -------------------------------------------
    if ($action === 'supprimer_reduction') {
        if (reduction_supprimer(entier(post('reduction_id')), $dossier_id)) {
            flash('succes', 'La réduction est supprimée.');
        }
        rediriger($page_compte);
    }

    rediriger($page_compte);
}

// --- Données affichées -------------------------------------------------------
$compte = compte_du_dossier($dossier_id);
$membres_saison = $dossier !== null ? dossier_membres($dossier_id) : [];
$cotisations = $dossier !== null ? cotisations_dossier($dossier_id) : [];
$reductions = $dossier !== null ? reductions_dossier($dossier_id) : [];
$paiements = $dossier !== null ? paiements_dossier($dossier_id) : [];
$inscriptions_comptees = $dossier !== null ? compte_inscriptions($dossier_id) : [];
$sans_cotisation = $dossier !== null ? cotisations_manquantes($dossier_id) : [];
$tarifs_cotisation = $est_structure ? [] : tarifs_adhesion($saison_affichee_id);
// Après une erreur, les valeurs saisies ne sont réaffichées que dans le formulaire concerné.
$formulaire_en_erreur = ancien_existe() ? ancien('action') : '';
$champ_titulaire = '<input type="hidden" name="' . e($titulaire['type']) . '" value="' . $titulaire['id'] . '">';

$titre_page = 'Paiements';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paiements — <?= e($titulaire['titre']) ?> <span class="badge text-bg-secondary">Saison <?= e($saison_affichee_nom) ?></span></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($peut_modifier) : ?>
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_paiement.php?' . $titulaire['lien'])) ?>">Nouveau paiement</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($titulaire['fiche'])) ?>">Retour à la <?= $est_structure ? 'structure' : 'famille' ?></a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('etat_paiements.php')) ?>">État des paiements</a>
        </div>
        <?php if ($compte !== null) : ?>
            <div class="mt-4">
                <?php afficher_resume_compte($compte, true); ?>
            </div>
        <?php endif; ?>
        <ul class="text-body-secondary small mt-3 mb-0 ps-3">
            <li>Le montant dû additionne les cotisations et les inscriptions en cours aux activités (hors liste d'attente), moins les réductions.</li>
            <li>Un tarif familial ne compte qu'une fois par activité, quel que soit le nombre de membres inscrits à ce tarif.</li>
        </ul>
    </div>

    <div class="col-md-8 order-md-1">
        <?php if ($dossier === null) : ?>
            <div class="alert alert-info" role="alert">
                <?= $est_structure ? 'Cette structure n\'est pas inscrite' : 'Aucun membre de cette famille n\'est inscrit' ?> pour la saison <?= e($saison_affichee_nom) ?> :
                il n'y a ni cotisation ni paiement à enregistrer.
            </div>
        <?php else : ?>
            <?php if ($sans_cotisation !== []) : ?>
                <div class="alert alert-warning" role="alert">
                    Sans cotisation pour la saison, alors qu'une de ses activités demande l'adhésion : <?= e(implode(', ', $sans_cotisation)) ?>.
                </div>
            <?php endif; ?>

            <?php // ----- Cotisations ----- ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h5">Cotisations</h2>
                <?php if ($cotisations === []) : ?>
                    <p class="text-body-secondary">Aucune cotisation pour cette saison.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Pour</th>
                                    <th scope="col">Date</th>
                                    <th scope="col" class="text-end">Montant</th>
                                    <th scope="col"><span class="visually-hidden">Action</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cotisations as $ligne_cotisation) : ?>
                                    <?php $cotisation_annulee = entier($ligne_cotisation['est_annulee']) === 1; ?>
                                    <tr class="<?= $cotisation_annulee ? 'text-body-secondary' : '' ?>">
                                        <td>
                                            <?= texte($ligne_cotisation['forme']) === 'familiale' ? 'Toute la famille' : e(nom_adherent($ligne_cotisation)) ?>
                                            <?php if (texte($ligne_cotisation['tarif_nom']) !== '') : ?>
                                                <span class="d-block small text-body-secondary"><?= e($ligne_cotisation['tarif_nom']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e(date_fr($ligne_cotisation['date_cotisation'])) ?></td>
                                        <td class="text-end"><?= e(montant_fr($ligne_cotisation['montant'])) ?></td>
                                        <td class="text-end">
                                            <?php if ($cotisation_annulee) : ?>
                                                <span class="badge text-bg-secondary">Annulée le <?= e(date_fr($ligne_cotisation['annulee_le'])) ?></span>
                                            <?php elseif ($peut_modifier) : ?>
                                                <form method="post" action="<?= e(chemin_site('compte.php')) ?>">
                                                    <?= csrf_champ() ?>
                                                    <?= $champ_titulaire ?>
                                                    <input type="hidden" name="cotisation_id" value="<?= entier($ligne_cotisation['id']) ?>">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="annuler_cotisation">Annuler</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($peut_modifier && !$est_structure && $tarifs_cotisation === []) : ?>
                    <p class="small text-body-secondary mb-0">
                        Aucun tarif « adhésion » n'existe pour cette saison :
                        <a href="<?= e(chemin_site('tarifs.php')) ?>">créez-en un dans les tarifs</a> pour enregistrer une cotisation.
                    </p>
                <?php elseif ($peut_modifier) : ?>
                    <?php
                    $reprise = $formulaire_en_erreur === 'ajouter_cotisation';
                    $pour_choisi = $reprise && ancien('adherent_id') !== '' ? entier(ancien('adherent_id')) : -1;
                    $tarif_choisi = $reprise ? entier(ancien('tarif_id')) : 0;
                    ?>
                    <form method="post" action="<?= e(chemin_site('compte.php')) ?>">
                        <?= csrf_champ() ?>
                        <?= jeton_unique_champ('cotisation') ?>
                        <?= $champ_titulaire ?>
                        <input type="hidden" name="action" value="ajouter_cotisation">
                        <div class="row g-2 align-items-end">
                            <?php if ($est_structure) : ?>
                                <div class="col-sm-4">
                                    <label class="form-label" for="cotisation_montant">Montant négocié (€)</label>
                                    <input class="form-control" type="text" inputmode="decimal" id="cotisation_montant" name="montant" maxlength="10" value="<?= e($reprise ? ancien('montant') : '') ?>" required>
                                </div>
                            <?php else : ?>
                                <div class="col-sm-4">
                                    <label class="form-label" for="cotisation_pour">Pour</label>
                                    <select class="form-select" id="cotisation_pour" name="adherent_id" required>
                                        <option value="">Choisir…</option>
                                        <option value="0" <?= $pour_choisi === 0 ? 'selected' : '' ?>>Toute la famille (cotisation familiale)</option>
                                        <?php foreach ($membres_saison as $membre) : ?>
                                            <option value="<?= entier($membre['id']) ?>" <?= $pour_choisi === entier($membre['id']) ? 'selected' : '' ?>><?= e(nom_adherent($membre)) ?> (individuelle)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label" for="cotisation_tarif">Tarif</label>
                                    <select class="form-select" id="cotisation_tarif" name="tarif_id" required>
                                        <?php if (count($tarifs_cotisation) > 1) : ?><option value="">Choisir…</option><?php endif; ?>
                                        <?php foreach ($tarifs_cotisation as $ligne_tarif) : ?>
                                            <option value="<?= entier($ligne_tarif['id']) ?>" <?= $tarif_choisi === entier($ligne_tarif['id']) ? 'selected' : '' ?>>
                                                <?= e(texte($ligne_tarif['nom']) . ' — ' . tarif_montants($ligne_tarif['montant_individuel'], $ligne_tarif['montant_familial'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-sm-4">
                                <label class="form-label" for="date_cotisation">Date</label>
                                <input class="form-control" type="date" id="date_cotisation" name="date_cotisation" max="<?= e(date('Y-m-d')) ?>" value="<?= e($reprise ? ancien('date_cotisation') : date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-sm-auto">
                                <button class="btn btn-outline-primary" type="submit">Ajouter la cotisation</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <?php // ----- Activités comptées dans le montant dû ----- ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h5">Activités</h2>
                <?php if ($inscriptions_comptees === []) : ?>
                    <p class="text-body-secondary mb-0">Aucune inscription en cours à une activité.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Activité</th>
                                    <th scope="col">Inscrit(e)</th>
                                    <th scope="col" class="text-end">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inscriptions_comptees as $ligne_inscription) : ?>
                                    <tr>
                                        <td><a href="<?= e(chemin_site('activite.php?id=' . entier($ligne_inscription['activite_id']))) ?>"><?= e($ligne_inscription['activite_nom']) ?></a></td>
                                        <td>
                                            <?= e(nom_adherent($ligne_inscription)) ?>
                                            <?php if (entier($ligne_inscription['nb_places']) > 1) : ?>
                                                <span class="small text-body-secondary">— <?= entier($ligne_inscription['nb_places']) ?> places</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if (entier($ligne_inscription['compte']) === 1) : ?>
                                                <?= e(montant_fr($ligne_inscription['montant'])) ?>
                                            <?php else : ?>
                                                <span class="text-body-secondary">déjà compté</span>
                                            <?php endif; ?>
                                            <?php if (entier($ligne_inscription['est_tarif_familial']) === 1) : ?>
                                                <span class="d-block small text-body-secondary">tarif familial</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php // ----- Réductions ----- ?>
            <div class="border rounded p-3 mb-3">
                <h2 class="h5">Réductions</h2>
                <?php if ($reductions === []) : ?>
                    <p class="text-body-secondary">Aucune réduction.</p>
                <?php else : ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($reductions as $ligne_reduction) : ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                <span>
                                    <?= e(montant_fr($ligne_reduction['montant'])) ?>
                                    <span class="text-body-secondary">— <?= $ligne_reduction['adherent_id'] === null ? ($est_structure ? 'structure' : 'toute la famille') : e(nom_adherent($ligne_reduction)) ?>,
                                        le <?= e(date_fr($ligne_reduction['date_crea'])) ?></span>
                                </span>
                                <?php if ($peut_modifier) : ?>
                                    <form method="post" action="<?= e(chemin_site('compte.php')) ?>">
                                        <?= csrf_champ() ?>
                                        <?= $champ_titulaire ?>
                                        <input type="hidden" name="reduction_id" value="<?= entier($ligne_reduction['id']) ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="supprimer_reduction">Supprimer</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($peut_modifier) : ?>
                    <?php $reprise = $formulaire_en_erreur === 'ajouter_reduction'; ?>
                    <form method="post" action="<?= e(chemin_site('compte.php')) ?>">
                        <?= csrf_champ() ?>
                        <?= jeton_unique_champ('reduction') ?>
                        <?= $champ_titulaire ?>
                        <input type="hidden" name="action" value="ajouter_reduction">
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-4">
                                <label class="form-label" for="reduction_montant">Montant (€)</label>
                                <input class="form-control" type="text" inputmode="decimal" id="reduction_montant" name="montant" maxlength="10" value="<?= e($reprise ? ancien('montant') : '') ?>" required>
                            </div>
                            <?php if (!$est_structure) : ?>
                                <div class="col-sm-4">
                                    <label class="form-label" for="reduction_pour">Pour</label>
                                    <select class="form-select" id="reduction_pour" name="adherent_id">
                                        <option value="0">Toute la famille</option>
                                        <?php foreach ($membres_saison as $membre) : ?>
                                            <option value="<?= entier($membre['id']) ?>" <?= $reprise && entier(ancien('adherent_id')) === entier($membre['id']) ? 'selected' : '' ?>><?= e(nom_adherent($membre)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-sm-auto">
                                <button class="btn btn-outline-primary" type="submit">Ajouter la réduction</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <?php // ----- Paiements ----- ?>
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h2 class="h5">Paiements</h2>
                    <?php if ($peut_modifier) : ?>
                        <a class="small" href="<?= e(chemin_site('ajout_paiement.php?' . $titulaire['lien'])) ?>">Nouveau paiement</a>
                    <?php endif; ?>
                </div>
                <?php if ($paiements === []) : ?>
                    <p class="text-body-secondary mb-0">Aucun paiement pour cette saison.</p>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Mode</th>
                                    <th scope="col">Objet</th>
                                    <th scope="col" class="text-end">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiements as $ligne_paiement) : ?>
                                    <?php $paiement_annule = entier($ligne_paiement['est_annule']) === 1; ?>
                                    <tr class="<?= $paiement_annule ? 'text-body-secondary' : '' ?>">
                                        <td>
                                            <a href="<?= e(chemin_site('paiement.php?id=' . entier($ligne_paiement['id']))) ?>"><?= e(date_fr($ligne_paiement['date_paiement'])) ?></a>
                                            <?php if ($paiement_annule) : ?><span class="badge text-bg-secondary">Annulé</span><?php endif; ?>
                                        </td>
                                        <td><?= e(libelles_modes_paiement()[texte($ligne_paiement['mode'])] ?? '') ?></td>
                                        <td><?= e(libelles_objets_paiement()[texte($ligne_paiement['objet'])] ?? '') ?></td>
                                        <td class="text-end <?= $paiement_annule ? 'text-decoration-line-through' : '' ?>"><?= e(montant_fr($ligne_paiement['montant'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
