<?php

declare(strict_types=1);

// Fiche d'un paiement : détail, reçu (PDF, envoi par courriel) et annulation.
// Un paiement ne se modifie pas et ne se supprime pas : en cas d'erreur, on
// l'annule et on en saisit un autre. Rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = paiement_par_id($fiche_id);
if ($fiche === null) {
    flash('erreur', 'Ce paiement n\'existe pas.');
    rediriger('paiements.php');
}
$page_fiche = 'paiement.php?id=' . $fiche_id;
$est_annule = entier($fiche['est_annule']) === 1;
$lien_compte = paiement_lien_compte($fiche['famille_id'], $fiche['structure_id']);
$destinataires = recu_destinataires($fiche);
// Autres paiements de la même saisie (« les deux », surplus en don) : ils s'annulent ensemble.
$paiements_de_la_saisie = paiements_lies($fiche);
$lies_en_cours = array_values(array_filter(
    $paiements_de_la_saisie,
    static fn (array $ligne): bool => entier($ligne['est_annule']) !== 1
));

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Annulation --------------------------------------------------------------
    if ($action === 'annuler') {
        $nb_annules = paiement_annuler($fiche);
        if ($nb_annules > 1) {
            flash('succes', 'Ce paiement et ceux de la même saisie sont annulés (' . $nb_annules . ' paiements). Ils restent visibles dans la liste, barrés.');
        } elseif ($nb_annules === 1) {
            flash('succes', 'Le paiement de ' . montant_fr($fiche['montant']) . ' est annulé. Il reste visible dans la liste, barré.');
        } else {
            flash('info', 'Ce paiement était déjà annulé.');
        }
        rediriger($page_fiche);
    }

    // --- Envoi du reçu par courriel ---------------------------------------------
    if ($action === 'envoyer_recu') {
        $destinataire = post('destinataire');
        if ($est_annule) {
            flash('erreur', 'Ce paiement est annulé : il n\'a pas de reçu.');
        } elseif (!in_array($destinataire, $destinataires, true)) {
            flash('erreur', 'Choisissez l\'adresse à laquelle envoyer le reçu.');
        } elseif (envoyer_recu($destinataire, $fiche)) {
            journaliser('envoi', 'paiements', $fiche_id, 'Reçu n° ' . recu_numero($fiche_id) . ' envoyé par courriel');
            flash('succes', 'Le reçu est envoyé à ' . $destinataire . '.');
        } else {
            flash('erreur', 'Le reçu n\'a pas pu être envoyé. Vous pouvez l\'ouvrir en PDF et l\'imprimer.');
        }
        rediriger($page_fiche);
    }

    rediriger($page_fiche);
}

$titre_page = 'Paiement';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paiement de <?= e(montant_fr($fiche['montant'])) ?>
    <?php if ($est_annule) : ?><span class="badge text-bg-secondary">Annulé</span><?php endif; ?>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if (!$est_annule) : ?>
                <a class="btn btn-primary" href="<?= e(chemin_site('recu.php?id=' . $fiche_id)) ?>" target="_blank" rel="noopener">Reçu (PDF)</a>
                <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#fenetreAnnulation">Annuler le paiement</button>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('compte.php?' . $lien_compte)) ?>">Cotisations et paiements</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('ajout_paiement.php?' . $lien_compte)) ?>">Autre paiement</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('paiements.php')) ?>">Liste des paiements</a>
        </div>
    </div>

    <div class="col-md-8 order-md-1">
        <dl class="row mb-4">
            <dt class="col-sm-4 fw-normal text-body-secondary"><?= entier($fiche['famille_id']) > 0 ? 'Famille' : 'Structure' ?></dt>
            <dd class="col-sm-8"><a href="<?= e(chemin_site('compte.php?' . $lien_compte)) ?>"><?= e($fiche['titulaire_nom']) ?></a> — saison <?= e($fiche['saison_nom']) ?></dd>
            <dt class="col-sm-4 fw-normal text-body-secondary">Date du paiement</dt>
            <dd class="col-sm-8"><?= e(date_fr($fiche['date_paiement'])) ?></dd>
            <dt class="col-sm-4 fw-normal text-body-secondary">Montant</dt>
            <dd class="col-sm-8 <?= $est_annule ? 'text-decoration-line-through' : '' ?>"><?= e(montant_fr($fiche['montant'])) ?></dd>
            <dt class="col-sm-4 fw-normal text-body-secondary">Mode de paiement</dt>
            <dd class="col-sm-8"><?= e(libelles_modes_paiement()[texte($fiche['mode'])] ?? '') ?></dd>
            <dt class="col-sm-4 fw-normal text-body-secondary">Objet</dt>
            <dd class="col-sm-8"><?= e(libelles_objets_paiement()[texte($fiche['objet'])] ?? '') ?></dd>
            <?php if (texte($fiche['note']) !== '') : ?>
                <dt class="col-sm-4 fw-normal text-body-secondary">Note</dt>
                <dd class="col-sm-8"><?= e($fiche['note']) ?></dd>
            <?php endif; ?>
            <dt class="col-sm-4 fw-normal text-body-secondary">Saisi</dt>
            <dd class="col-sm-8">le <?= e(date_heure_fr($fiche['date_crea'])) ?><?= texte($fiche['saisi_par']) !== '' ? ' par ' . e($fiche['saisi_par']) : '' ?></dd>
            <?php if ($est_annule) : ?>
                <dt class="col-sm-4 fw-normal text-body-secondary">Annulé</dt>
                <dd class="col-sm-8">le <?= e(date_heure_fr($fiche['annule_le'])) ?><?= texte($fiche['annule_par']) !== '' ? ' par ' . e($fiche['annule_par']) : '' ?></dd>
            <?php endif; ?>
            <?php if ($paiements_de_la_saisie !== []) : ?>
                <dt class="col-sm-4 fw-normal text-body-secondary">Même saisie</dt>
                <dd class="col-sm-8">
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($paiements_de_la_saisie as $paiement_lie) : ?>
                            <li>
                                <a href="<?= e(chemin_site('paiement.php?id=' . entier($paiement_lie['id']))) ?>"><?= e(libelles_objets_paiement()[texte($paiement_lie['objet'])] ?? '') ?> : <?= e(montant_fr($paiement_lie['montant'])) ?></a>
                                <?php if (entier($paiement_lie['est_annule']) === 1) : ?><span class="badge text-bg-secondary">Annulé</span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <span class="small text-body-secondary">Ces paiements sont liés : annuler l'un annule les autres.</span>
                </dd>
            <?php endif; ?>
        </dl>

        <?php if (!$est_annule) : ?>
            <h2 class="h5">Reçu n° <?= e(recu_numero($fiche_id)) ?></h2>
            <p><a href="<?= e(chemin_site('recu.php?id=' . $fiche_id)) ?>" target="_blank" rel="noopener">Ouvrir le reçu (PDF)</a> pour l'imprimer.</p>
            <?php if ($destinataires === []) : ?>
                <p class="text-body-secondary mb-0">Aucune adresse e-mail n'est connue : le reçu ne peut pas être envoyé par courriel.</p>
            <?php else : ?>
                <form method="post" action="<?= e(chemin_site('paiement.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <input type="hidden" name="action" value="envoyer_recu">
                    <label class="form-label" for="destinataire">Envoyer le reçu par courriel à</label>
                    <div class="input-group">
                        <select class="form-select" id="destinataire" name="destinataire" required>
                            <?php foreach ($destinataires as $adresse_possible) : ?>
                                <option value="<?= e($adresse_possible) ?>"><?= e($adresse_possible) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary" type="submit">Envoyer</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!$est_annule) : ?>
    <?php // ----- Fenêtre : confirmation de l'annulation ----- ?>
    <div class="modal fade" id="fenetreAnnulation" tabindex="-1" aria-labelledby="fenetreAnnulationTitre" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="<?= e(chemin_site('paiement.php')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="fenetreAnnulationTitre">Annuler ce paiement ?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <input type="hidden" name="action" value="annuler">
                    <p>Le paiement de <strong><?= e(montant_fr($fiche['montant'])) ?></strong> du <?= e(date_fr($fiche['date_paiement'])) ?> ne comptera plus dans ce qui a été payé.</p>
                    <?php if ($lies_en_cours !== []) : ?>
                        <p>Les paiements de la même saisie seront annulés aussi :
                            <?= e(implode(', ', array_map(
                                static fn (array $ligne): string => mb_strtolower(libelles_objets_paiement()[texte($ligne['objet'])] ?? '') . ' ' . montant_fr($ligne['montant']),
                                $lies_en_cours
                            ))) ?>.</p>
                    <?php endif; ?>
                    <p class="mb-0">Une annulation est définitive : pour corriger une erreur, annulez puis saisissez un nouveau paiement.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Ne pas annuler</button>
                    <button type="submit" class="btn btn-danger">Annuler le paiement</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
