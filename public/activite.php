<?php

declare(strict_types=1);

// Fiche d'une activité : ses informations, ses inscrits, sa liste d'attente
// et ses inscriptions annulées. L'activité se modifie dans une fenêtre.
// Consultation : tout utilisateur connecté (liste d'inscrits, téléphones).
// Modification, suppression, annulation d'une inscription, passage des inscrits
// à la liste d'attente et inversement : rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = activite_par_id($fiche_id);
if ($fiche === null) {
    flash('erreur', 'Cette activité n\'existe pas.');
    rediriger('activites.php');
}
$page_fiche = 'activite.php?id=' . $fiche_id;
$peut_modifier = role_au_moins('standard');
$saison_activite = [
    'id'         => $fiche['saison_id'],
    'nom'        => $fiche['saison_nom'],
    'date_debut' => $fiche['saison_debut'],
    'date_fin'   => $fiche['saison_fin'],
];

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    if (!$peut_modifier) {
        flash('erreur', 'Vous n\'avez pas les droits nécessaires pour modifier cette activité.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Modification de l'activité -------------------------------------------
    if ($action === 'modifier') {
        $saisie = activite_saisie();
        $erreurs = erreurs_activite($saisie, $saison_activite, $fiche);
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger($page_fiche);
        }
        if (modifier_activite($fiche, $saisie)) {
            flash('succes', 'L\'activité est modifiée.');
        } else {
            flash('info', 'Aucune modification à enregistrer.');
        }
        rediriger($page_fiche);
    }

    // --- Suppression (activité sans aucune inscription) -------------------------
    if ($action === 'supprimer') {
        if (supprimer_activite($fiche)) {
            flash('succes', 'L\'activité « ' . texte($fiche['nom']) . ' » est supprimée.');
            rediriger('activites.php');
        }
        flash('erreur', 'Cette activité a des inscriptions : elle ne peut pas être supprimée.');
        rediriger($page_fiche);
    }

    // --- Inscriptions : annulation, passage en liste d'attente et inversement ---
    if (in_array($action, ['annuler_inscription', 'sortir_attente', 'mettre_attente'], true)) {
        $inscription = inscription_par_id(entier(post('inscription_id')));
        if ($inscription === null || entier($inscription['activite_id']) !== $fiche_id) {
            flash('erreur', 'Cette inscription n\'existe pas.');
        } elseif ($action === 'annuler_inscription') {
            if (inscription_annuler($inscription)) {
                flash('succes', 'L\'inscription de ' . nom_adherent($inscription) . ' est annulée.');
            } else {
                flash('info', 'Cette inscription était déjà annulée.');
            }
        } elseif ($action === 'mettre_attente') {
            $erreur_attente = inscription_mettre_en_attente($inscription);
            if ($erreur_attente === '') {
                flash('succes', nom_adherent($inscription) . ' passe des inscrits à la liste d\'attente.');
            } else {
                flash('erreur', $erreur_attente);
            }
        } else {
            $erreur_attente = inscription_sortir_attente($inscription);
            if ($erreur_attente === '') {
                flash('succes', nom_adherent($inscription) . ' passe de la liste d\'attente aux inscrits.');
            } else {
                flash('erreur', $erreur_attente);
            }
        }
        rediriger($page_fiche);
    }

    rediriger($page_fiche);
}

// --- Données affichées -------------------------------------------------------
$inscrits = [];
$en_attente = [];
$annulees = [];
foreach (inscriptions_activite($fiche_id) as $ligne_inscription) {
    if (entier($ligne_inscription['est_annulee']) === 1) {
        $annulees[] = $ligne_inscription;
    } elseif (texte($ligne_inscription['statut']) === 'attente') {
        $en_attente[] = $ligne_inscription;
    } else {
        $inscrits[] = $ligne_inscription;
    }
}
$places_prises = entier($fiche['places_prises']);
$places_attente = entier($fiche['places_attente']);
$restantes = places_restantes($fiche);
$a_liste_attente = entier($fiche['a_liste_attente']) === 1;
$est_vide = entier($fiche['nb_inscriptions']) === 0;
$fenetre_a_rouvrir = ancien_existe() ? ancien('action') : '';

/**
 * Affiche le tableau d'une partie des inscriptions (inscrits ou liste d'attente).
 *
 * @param list<array<string, mixed>> $lignes
 * @param 'inscrits'|'attente'       $partie
 */
$afficher_inscriptions = static function (array $lignes, string $partie) use ($fiche_id, $peut_modifier, $a_liste_attente): void {
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Places</th>
                    <?php if ($peut_modifier) : ?>
                        <th scope="col" class="text-end">Montant</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $ligne) : ?>
                    <?php $ligne_structure = entier($ligne['est_structure']) === 1; ?>
                    <tr>
                        <td>
                            <a href="<?= e(chemin_site('adherent.php?id=' . entier($ligne['adherent_id']))) ?>"><?= e(trim(texte($ligne['nom']) . ' ' . texte($ligne['prenom']))) ?></a>
                            <?php if ($ligne_structure) : ?>
                                <span class="badge text-bg-secondary">Structure</span>
                            <?php else : ?>
                                <span class="text-body-secondary small">— <?= age_ans(texte($ligne['date_naissance'])) ?> ans</span>
                            <?php endif; ?>
                            <?php if ($partie === 'attente') : ?>
                                <span class="d-block small text-body-secondary">en attente depuis le <?= e(date_fr($ligne['attente_le'] ?? $ligne['date_crea'])) ?></span>
                            <?php else : ?>
                                <span class="d-block small text-body-secondary">depuis le <?= e(date_fr($ligne['date_crea'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= e($ligne['tel']) ?>
                            <?php if (texte($ligne['responsable_nom']) !== '') : ?>
                                <span class="d-block">Resp. : <?= e(trim(texte($ligne['responsable_nom']) . ' ' . texte($ligne['responsable_tel']))) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= entier($ligne['nb_places']) ?></td>
                        <?php if ($peut_modifier) : ?>
                            <td class="text-end">
                                <?= e(montant_fr($ligne['montant'])) ?>
                                <?php if (entier($ligne['est_tarif_familial']) === 1) : ?>
                                    <span class="d-block small text-body-secondary">tarif familial</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <form class="d-inline-flex gap-1" method="post" action="<?= e(chemin_site('activite.php')) ?>">
                                    <?= csrf_champ() ?>
                                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                                    <input type="hidden" name="inscription_id" value="<?= entier($ligne['id']) ?>">
                                    <?php if ($partie === 'attente') : ?>
                                        <button class="btn btn-outline-primary btn-sm" type="submit" name="action" value="sortir_attente">Inscrire</button>
                                    <?php elseif ($a_liste_attente) : ?>
                                        <button class="btn btn-outline-secondary btn-sm text-nowrap" type="submit" name="action" value="mettre_attente">Mettre en attente</button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="annuler_inscription">Annuler</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};

$titre_page = 'Activité';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e($fiche['nom']) ?>
    <?php if ($restantes === 0) : ?><span class="badge text-bg-danger">Complet</span><?php endif; ?>
    <?php if (entier($fiche['est_sans_adhesion']) === 1) : ?><span class="badge text-bg-info">Sans adhésion</span><?php endif; ?>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($peut_modifier) : ?>
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_inscription.php?activite=' . $fiche_id)) ?>">Inscrire quelqu'un</a>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#fenetreActivite">Modifier l'activité</button>
                <?php if ($est_vide) : ?>
                    <form class="d-grid" method="post" action="<?= e(chemin_site('activite.php')) ?>">
                        <?= csrf_champ() ?>
                        <input type="hidden" name="id" value="<?= $fiche_id ?>">
                        <button class="btn btn-outline-danger" type="submit" name="action" value="supprimer">Supprimer l'activité</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('activites.php')) ?>">Retour à la liste</a>
        </div>

        <dl class="mt-4 mb-0">
            <dt class="small text-body-secondary fw-normal">Saison</dt>
            <dd><?= e($fiche['saison_nom']) ?></dd>
            <dt class="small text-body-secondary fw-normal">Secteur</dt>
            <dd><?= e($fiche['secteur_nom']) ?></dd>
            <dt class="small text-body-secondary fw-normal">Type</dt>
            <dd><?= e($fiche['type_nom']) ?> — <?= e($fiche['recurrence_nom']) ?></dd>
            <dt class="small text-body-secondary fw-normal">Référent</dt>
            <dd><?= e($fiche['referent']) ?></dd>
            <dt class="small text-body-secondary fw-normal">Dates</dt>
            <dd><?= e(activite_dates($fiche)) ?></dd>
            <dt class="small text-body-secondary fw-normal">Tarif</dt>
            <dd><?= $fiche['tarif_id'] === null ? 'Gratuit' : e(texte($fiche['tarif_nom']) . ' — ' . activite_prix($fiche)) ?></dd>
            <dt class="small text-body-secondary fw-normal">Places</dt>
            <dd>
                <?php if ($restantes === null) : ?>
                    Non limitées (<?= $places_prises ?> prise<?= $places_prises > 1 ? 's' : '' ?>)
                <?php else : ?>
                    <?= $places_prises ?> prise<?= $places_prises > 1 ? 's' : '' ?> sur <?= entier($fiche['nb_places']) ?> — <?= $restantes ?> libre<?= $restantes > 1 ? 's' : '' ?>
                <?php endif; ?>
            </dd>
            <dt class="small text-body-secondary fw-normal">Liste d'attente</dt>
            <dd><?= $a_liste_attente ? 'Oui (' . $places_attente . ' place' . ($places_attente > 1 ? 's' : '') . ' demandée' . ($places_attente > 1 ? 's' : '') . ')' : 'Non' ?></dd>
            <?php if ($fiche['nb_accompagnateurs'] !== null) : ?>
                <dt class="small text-body-secondary fw-normal">Accompagnateurs</dt>
                <dd><?= entier($fiche['nb_accompagnateurs']) ?></dd>
            <?php endif; ?>
        </dl>
        <?php if (texte($fiche['description']) !== '') : ?>
            <p class="small text-body-secondary fw-normal mt-3 mb-1">Description</p>
            <p class="mb-0"><?= nl2br(e($fiche['description'])) ?></p>
        <?php endif; ?>
    </div>

    <div class="col-md-8 order-md-1">
        <?php if ($peut_modifier && $en_attente !== [] && $restantes !== 0) : ?>
            <div class="alert alert-warning" role="alert">
                <?= $restantes === null ? 'Des places sont libres' : $restantes . ' place' . ($restantes > 1 ? 's libres' : ' libre') ?>
                et <?= count($en_attente) ?> inscription<?= count($en_attente) > 1 ? 's sont' : ' est' ?> en liste d'attente :
                utilisez « Inscrire » dans la liste d'attente pour choisir qui passe.
            </div>
        <?php endif; ?>

        <h2 class="h5 mb-3">Inscrits <span class="badge text-bg-secondary"><?= count($inscrits) ?></span></h2>
        <?php if ($peut_modifier && $fiche['tarif_montant_familial'] !== null) : ?>
            <p class="small text-body-secondary">Le montant « tarif familial » (<?= e(montant_fr($fiche['tarif_montant_familial'])) ?>) ne compte qu'une fois par famille, quel que soit le nombre d'inscrits de la famille à ce tarif.</p>
        <?php endif; ?>
        <?php if ($peut_modifier && texte($fiche['tarif_type'] ?? '') === 'forfait') : ?>
            <p class="small text-body-secondary">Le forfait « <?= e($fiche['tarif_nom']) ?> » ne se paie qu'une fois par personne (ou par famille, au tarif familial) : une personne déjà inscrite à une autre activité de ce forfait ne le doit pas une seconde fois.</p>
        <?php endif; ?>
        <?php if ($inscrits === []) : ?>
            <p class="text-body-secondary">Personne n'est encore inscrit à cette activité.</p>
        <?php else : ?>
            <?php $afficher_inscriptions($inscrits, 'inscrits'); ?>
        <?php endif; ?>

        <?php if ($a_liste_attente || $en_attente !== []) : ?>
            <h2 class="h5 mt-4 mb-3">Liste d'attente <span class="badge text-bg-secondary"><?= count($en_attente) ?></span></h2>
            <?php if ($en_attente === []) : ?>
                <p class="text-body-secondary">La liste d'attente est vide.</p>
            <?php else : ?>
                <?php $afficher_inscriptions($en_attente, 'attente'); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($annulees !== []) : ?>
            <h2 class="h5 mt-4 mb-3">Inscriptions annulées <span class="badge text-bg-secondary"><?= count($annulees) ?></span></h2>
            <ul class="list-unstyled small text-body-secondary mb-0">
                <?php foreach ($annulees as $ligne_annulee) : ?>
                    <li>
                        <a href="<?= e(chemin_site('adherent.php?id=' . entier($ligne_annulee['adherent_id']))) ?>"><?= e(trim(texte($ligne_annulee['nom']) . ' ' . texte($ligne_annulee['prenom']))) ?></a>
                        — <?= entier($ligne_annulee['nb_places']) ?> place<?= entier($ligne_annulee['nb_places']) > 1 ? 's' : '' ?>,
                        annulée le <?= e(date_fr($ligne_annulee['annulee_le'])) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php if ($peut_modifier) : ?>
    <?php // ----- Fenêtre : modification de l'activité ----- ?>
    <div class="modal fade" id="fenetreActivite" tabindex="-1" aria-labelledby="fenetreActiviteTitre" aria-hidden="true" <?= $fenetre_a_rouvrir === 'modifier' ? 'data-ouvrir-auto' : '' ?>>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" method="post" action="<?= e(chemin_site('activite.php')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="fenetreActiviteTitre">Modifier l'activité</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="formulaire" value="activite">
                    <?php afficher_champs_activite($fiche, $saison_activite); ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
