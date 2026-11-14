<?php

declare(strict_types=1);

// Fiche d'un adhérent (personne ou structure).
// Les informations sont affichées en clair ; elles se modifient dans des
// fenêtres (modals Bootstrap) : « Modifier la fiche » et l'adresse de la saison.
// Seul le dossier de la saison affichée est montré ; les autres saisons de la
// personne sont rappelées par un lien discret, qui change la saison affichée.
// Consultation : tout utilisateur connecté. Modification : rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = adherent_par_id($fiche_id);
if ($fiche === null) {
    flash('erreur', 'Cet adhérent n\'existe pas.');
    rediriger('adherents.php');
}
$page_fiche = 'adherent.php?id=' . $fiche_id;
$famille_id = entier($fiche['famille_id']);
$est_structure = entier($fiche['est_structure']) === 1;
$est_anonymise = texte($fiche['anonymise_le']) !== '';
$peut_modifier = role_au_moins('standard') && !$est_anonymise;

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    if (!$peut_modifier) {
        flash('erreur', 'Vous n\'avez pas les droits nécessaires pour modifier cette fiche.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Identité, contact, responsable ------------------------------------
    if ($action === 'modifier') {
        $saisie = adherent_saisie($est_structure);
        $erreurs = erreurs_adherent($saisie);
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger($page_fiche);
        }
        if (modifier_adherent($fiche, $saisie)) {
            flash('succes', 'La fiche est modifiée.');
            if (!$est_structure && adherent_doublons($saisie['nom'], $saisie['prenom'], $saisie['date_naissance'], $fiche_id) !== []) {
                flash('avertissement', 'Attention : une autre personne porte ce nom, ce prénom et cette date de naissance.');
            }
        } else {
            flash('info', 'Aucune modification à enregistrer.');
        }
        rediriger($page_fiche);
    }

    // --- Adresse et scolarité de la saison affichée -------------------------
    if ($action === 'adresse' && $saison_affichee_id > 0) {
        $saisie_adresse = adresse_saisie();
        // L'établissement scolaire ne concerne que les moins de 18 ans.
        if (!est_mineur(texte($fiche['date_naissance']))) {
            $saisie_adresse['etablissement'] = '';
            $saisie_adresse['niveau'] = '';
        }
        $erreurs = erreurs_adresse($saisie_adresse);
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger($page_fiche);
        }
        db()->beginTransaction();
        try {
            $dossier_id = dossier_obtenir_pour_adherent($fiche, $saison_affichee_id);
            $adresse_reconnue = adresse_enregistrer($dossier_id, $fiche_id, $saisie_adresse);
            db()->commit();
        } catch (Throwable $erreur) {
            db()->rollBack();
            throw $erreur;
        }
        flash('succes', 'L\'adresse de la saison ' . texte($saison_affichee['nom'] ?? '') . ' est enregistrée.');
        if (!$adresse_reconnue && adresse_api_indisponible()) {
            flash('avertissement', MESSAGE_API_ADRESSE_INDISPONIBLE . ' Le quartier n\'a pas pu être déterminé.');
        } elseif (!$adresse_reconnue) {
            flash('avertissement', 'L\'adresse n\'est pas certifiée (elle n\'a pas été trouvée au numéro près) : le quartier n\'a pas pu être déterminé.');
        }
        rediriger($page_fiche);
    }

    // --- Notes de la saison (de la famille, ou de la structure) ; structure : retrait de la saison
    // (pour une personne, le retrait de la saison est sur la fiche de sa famille)
    if ($action === 'notes' || ($est_structure && $action === 'retirer_saison')) {
        $dossier_id = dossier_id_de_adherent($fiche, $saison_affichee_id);
        $notes_saisies = post('notes');
        if ($dossier_id <= 0 || adresse_du_dossier($dossier_id, $fiche_id) === null) {
            flash('erreur', ($est_structure ? 'Cette structure n\'est pas inscrite' : 'Cette personne n\'est pas inscrite') . ' pour la saison.');
        } elseif ($action === 'retirer_saison') {
            if (inscriptions_actives_nb($dossier_id, $fiche_id) > 0) {
                flash('erreur', 'Cette structure a des inscriptions en cours à des activités de la saison : annulez-les d\'abord.');
            } elseif (cotisations_actives_nb($dossier_id, $fiche_id) > 0) {
                flash('erreur', 'Cette structure a une cotisation en cours pour la saison : annulez-la d\'abord, dans ses paiements.');
            } elseif (retirer_de_la_saison($dossier_id, $fiche_id)) {
                flash('succes', 'La structure est retirée de la saison ' . texte($saison_affichee['nom'] ?? '') . '. Sa fiche est conservée.');
            }
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

// --- Données affichées -------------------------------------------------------
$naissance = texte($fiche['date_naissance']);
$mineur = est_mineur($naissance);

// Dossier de la saison affichée : adresse de l'adhérent et autres membres de la famille.
$dossier_saison_id = dossier_id_de_adherent($fiche, $saison_affichee_id);
$adresse_saison = $dossier_saison_id > 0 ? adresse_du_dossier($dossier_saison_id, $fiche_id) : null;
$dossier_saison = $adresse_saison !== null ? dossier_par_id($dossier_saison_id) : null;
$autres_membres = $adresse_saison === null ? [] : array_values(array_filter(
    dossier_membres($dossier_saison_id),
    static fn (array $membre): bool => entier($membre['id']) !== $fiche_id
));

// Adresse proposée dans la fenêtre de modification : celle de la saison, sinon la dernière connue.
$adresse_proposee = $adresse_saison ?? derniere_adresse_adherent($fiche_id) ?? ($est_structure ? null : derniere_adresse_famille($famille_id)) ?? [];
$autres_saisons = autres_saisons_adherent($fiche_id, $saison_affichee_id);
$inscriptions_saison = $adresse_saison !== null ? inscriptions_adherent($fiche_id, $saison_affichee_id) : [];
// Lien vers les cotisations et paiements de la famille (ou de la structure) : rôle standard au minimum.
$lien_compte = $adresse_saison !== null && role_au_moins('standard')
    ? 'compte.php?' . ($est_structure ? 'structure=' . $fiche_id : 'famille=' . $famille_id)
    : '';

// Après une erreur de saisie, la fenêtre concernée est rouverte avec les valeurs saisies.
$fenetre_a_rouvrir = ancien_existe() ? ancien('action') : '';

$titre_page = $est_structure ? 'Structure' : 'Adhérent';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e(nom_adherent($fiche)) ?>
    <?php if ($est_structure) : ?><span class="badge text-bg-secondary">Structure</span><?php endif; ?>
    <?php if (entier($fiche['est_benevole']) === 1) : ?><span class="badge text-bg-info">Bénévole</span><?php endif; ?>
    <?php if ($est_anonymise) : ?><span class="badge text-bg-secondary">Anonymisé</span><?php endif; ?>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($peut_modifier) : ?>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#fenetreFiche">Modifier la fiche</button>
            <?php endif; ?>
            <?php if ($peut_modifier && $est_structure && $adresse_saison !== null) : ?>
                <form class="d-grid" method="post" action="<?= e(chemin_site('adherent.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <button class="btn btn-outline-danger" type="submit" name="action" value="retirer_saison">Retirer de la saison <?= e($saison_affichee['nom'] ?? '') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($lien_compte !== '') : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site($lien_compte)) ?>">Cotisations et paiements</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('adherents.php')) ?>">Retour à la liste</a>
        </div>

        <?php // Informations de la personne : affichées en clair, modifiables par la fenêtre « Modifier la fiche ». ?>
        <dl class="mt-4 mb-0">
            <?php if (!$est_structure) : ?>
                <dt class="small text-body-secondary fw-normal">Date de naissance</dt>
                <dd><?= e(date_fr($naissance)) ?> (<?= age_ans($naissance) ?> ans<?= $mineur ? ', mineur' : '' ?>)</dd>
                <dt class="small text-body-secondary fw-normal">Sexe</dt>
                <dd><?= e(libelles_sexes()[texte($fiche['sexe'])] ?? '') ?></dd>
                <dt class="small text-body-secondary fw-normal">Nationalité</dt>
                <dd><?= e(texte($fiche['nationalite_nom']) !== '' ? $fiche['nationalite_nom'] : 'Non renseignée') ?></dd>
            <?php endif; ?>
            <dt class="small text-body-secondary fw-normal">Adresse e-mail</dt>
            <dd class="text-break"><?= e(texte($fiche['mail']) !== '' ? $fiche['mail'] : '—') ?></dd>
            <dt class="small text-body-secondary fw-normal">Téléphone</dt>
            <dd><?= e(texte($fiche['tel']) !== '' ? $fiche['tel'] : '—') ?></dd>
            <dt class="small text-body-secondary fw-normal">Communications</dt>
            <dd><?= entier($fiche['a_consenti']) === 1 ? 'Acceptées le ' . e(date_fr($fiche['consentement_le'])) : 'Non acceptées' ?></dd>
            <?php if (texte($fiche['responsable_nom']) !== '') : ?>
                <dt class="small text-body-secondary fw-normal">Responsable</dt>
                <dd class="text-break">
                    <?= e($fiche['responsable_nom']) ?>
                    <?php if (texte($fiche['responsable_tel']) !== '') : ?><br><?= e($fiche['responsable_tel']) ?><?php endif; ?>
                    <?php if (texte($fiche['responsable_mail']) !== '') : ?><br><?= e($fiche['responsable_mail']) ?><?php endif; ?>
                </dd>
            <?php endif; ?>
        </dl>

        <?php if ($autres_saisons !== []) : ?>
            <?php // Autres saisons : choisir une saison change la saison affichée et revient sur cette fiche. ?>
            <form class="mt-3 small text-body-secondary" method="post" action="<?= e(chemin_site('changer_saison.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="retour" value="<?= e($page_fiche) ?>">
                Autres saisons :
                <?php foreach ($autres_saisons as $autre_saison) : ?>
                    <button class="btn btn-link btn-sm p-0 align-baseline" type="submit" name="saison_id" value="<?= entier($autre_saison['id']) ?>"><?= e($autre_saison['nom']) ?></button>
                <?php endforeach; ?>
            </form>
        <?php endif; ?>

        <p class="small text-body-secondary mt-3 mb-0">
            Fiche créée le <?= e(date_fr($fiche['date_crea'])) ?><?= texte($fiche['date_modif']) !== '' ? ', modifiée le ' . e(date_fr($fiche['date_modif'])) : '' ?>.
        </p>
    </div>

    <div class="col-md-8 order-md-1">
        <?php if ($saison_affichee === null) : ?>
            <p class="text-body-secondary">Aucune saison n'existe encore.</p>
        <?php elseif ($adresse_saison === null) : ?>
            <?php // ----- Pas de dossier pour la saison affichée ----- ?>
            <h2 class="h5">Saison <?= e($saison_affichee['nom']) ?></h2>
            <div class="alert alert-info" role="alert">
                <?= e(nom_adherent($fiche)) ?> n'est pas inscrit(e) pour la saison <?= e($saison_affichee['nom']) ?>.
            </div>
            <?php if ($peut_modifier) : ?>
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#fenetreAdresse">Inscrire pour la saison <?= e($saison_affichee['nom']) ?></button>
            <?php endif; ?>
        <?php else : ?>
            <?php // ----- Dossier de la saison affichée ----- ?>
            <h2 class="h5 mb-3">Saison <?= e($saison_affichee['nom']) ?></h2>

            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h3 class="h6">Adresse</h3>
                    <?php if ($peut_modifier) : ?>
                        <button class="btn btn-link btn-sm p-0" type="button" data-bs-toggle="modal" data-bs-target="#fenetreAdresse">Modifier</button>
                    <?php endif; ?>
                </div>
                <p class="mb-2">
                    <?= e($adresse_saison['adresse']) ?><?= texte($adresse_saison['complement']) !== '' ? ', ' . e($adresse_saison['complement']) : '' ?><br>
                    <?= e($adresse_saison['code_postal']) ?> <?= e($adresse_saison['ville']) ?>
                </p>
                <p class="mb-0">
                    <?php if (entier($adresse_saison['est_validee']) === 1) : ?>
                        <span class="badge text-bg-success">Adresse certifiée</span>
                    <?php else : ?>
                        <span class="badge text-bg-warning">Adresse non certifiée</span>
                    <?php endif; ?>
                    <?php if (texte($adresse_saison['quartier']) !== '') : ?>
                        <span class="badge text-bg-light border">Quartier : <?= e($adresse_saison['quartier']) ?></span>
                    <?php endif; ?>
                    <?php if (entier($adresse_saison['est_qp']) === 1) : ?>
                        <span class="badge text-bg-primary">QP : <?= e($adresse_saison['qp_nom']) ?></span>
                    <?php endif; ?>
                    <?php if (entier($adresse_saison['est_qpv']) === 1) : ?>
                        <span class="badge text-bg-primary">QPV : <?= e($adresse_saison['qpv_nom']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if (texte($adresse_saison['etablissement']) !== '' || texte($adresse_saison['niveau']) !== '') : ?>
                    <p class="mt-2 mb-0"><span class="text-body-secondary">Scolarité :</span> <?= e(trim(texte($adresse_saison['etablissement']) . ' ' . texte($adresse_saison['niveau']))) ?></p>
                <?php endif; ?>
            </div>

            <?php if (!$est_structure) : ?>
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h3 class="h6">Famille <?= e($fiche['famille_nom']) ?></h3>
                        <a class="small" href="<?= e(chemin_site('famille.php?id=' . $famille_id)) ?>">Voir la famille</a>
                    </div>
                    <?php if ($autres_membres === []) : ?>
                        <p class="text-body-secondary mb-0">Seule personne de la famille inscrite cette saison.</p>
                    <?php else : ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($autres_membres as $membre) : ?>
                                <li>
                                    <a href="<?= e(chemin_site('adherent.php?id=' . entier($membre['id']))) ?>"><?= e(nom_adherent($membre)) ?></a>
                                    <span class="text-body-secondary">— <?= age_ans(texte($membre['date_naissance'])) ?> ans</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h3 class="h6">Inscriptions aux activités</h3>
                    <?php if ($peut_modifier) : ?>
                        <a class="small" href="<?= e(chemin_site('ajout_inscription.php?adherent=' . $fiche_id . '&origine=adherent')) ?>">Inscrire à une activité</a>
                    <?php endif; ?>
                </div>
                <?php if ($inscriptions_saison === []) : ?>
                    <p class="text-body-secondary mb-0">Aucune inscription à une activité pour cette saison.</p>
                <?php else : ?>
                    <?php afficher_inscriptions_activites($inscriptions_saison); ?>
                <?php endif; ?>
            </div>

            <?php // Notes de la saison : celles de la structure, ou de toute la famille (les mêmes que sur la fiche famille). ?>
            <?php if ($peut_modifier) : ?>
                <form class="border rounded p-3" method="post" action="<?= e(chemin_site('adherent.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <input type="hidden" name="action" value="notes">
                    <h3 class="h6"><label for="notes"><?= $est_structure ? 'Notes' : 'Notes de la famille' ?></label></h3>
                    <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="5000" <?= $est_structure ? '' : 'aria-describedby="aide_notes"' ?>><?= e($fenetre_a_rouvrir === 'notes' ? ancien('notes') : ($dossier_saison['notes'] ?? '')) ?></textarea>
                    <?php if (!$est_structure) : ?>
                        <div class="form-text" id="aide_notes">Elles concernent toute la famille pour la saison <?= e($saison_affichee['nom']) ?>, pas seulement cette personne.</div>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Enregistrer les notes</button>
                </form>
            <?php elseif (texte($dossier_saison['notes'] ?? '') !== '') : ?>
                <div class="border rounded p-3">
                    <h3 class="h6"><?= $est_structure ? 'Notes' : 'Notes de la famille' ?></h3>
                    <p class="mb-0"><?= nl2br(e($dossier_saison['notes'] ?? '')) ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($peut_modifier) : ?>
    <?php // ----- Fenêtre : modification de la fiche ----- ?>
    <div class="modal fade" id="fenetreFiche" tabindex="-1" aria-labelledby="fenetreFicheTitre" aria-hidden="true" <?= $fenetre_a_rouvrir === 'modifier' ? 'data-ouvrir-auto' : '' ?>>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="post" action="<?= e(chemin_site('adherent.php')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="fenetreFicheTitre">Modifier la fiche</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="formulaire" value="adherent">
                    <?php afficher_champs_adherent($fiche); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($saison_affichee !== null) : ?>
        <?php // ----- Fenêtre : adresse et scolarité de la saison affichée ----- ?>
        <div class="modal fade" id="fenetreAdresse" tabindex="-1" aria-labelledby="fenetreAdresseTitre" aria-hidden="true" <?= $fenetre_a_rouvrir === 'adresse' ? 'data-ouvrir-auto' : '' ?>>
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="post" action="<?= e(chemin_site('adherent.php')) ?>">
                    <div class="modal-header">
                        <h2 class="modal-title h5" id="fenetreAdresseTitre">
                            <?= $adresse_saison === null ? 'Inscrire pour la saison ' . e($saison_affichee['nom']) : 'Adresse pour la saison ' . e($saison_affichee['nom']) ?>
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="id" value="<?= $fiche_id ?>">
                        <input type="hidden" name="action" value="adresse">
                        <?php if ($adresse_saison === null) : ?>
                            <p class="text-body-secondary">Vérifiez l'adresse (la dernière connue est proposée), puis enregistrez.</p>
                        <?php endif; ?>
                        <?php afficher_champs_adresse($adresse_proposee, $mineur); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><?= $adresse_saison === null ? 'Inscrire' : 'Enregistrer' ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
