<?php

declare(strict_types=1);

// Inscription d'un adhérent à une activité (rôle standard au minimum).
// La page s'ouvre de trois façons :
//   ?activite=N              depuis la fiche d'une activité : on cherche l'adhérent ;
//   ?adherent=N              depuis la fiche d'un adhérent : on choisit l'activité ;
//   ?activite=N&adherent=N   les deux sont connus : on confirme l'inscription.
// « origine=adherent » indique que l'on revient ensuite sur la fiche de l'adhérent.
// Le nom d'adhérent cherché est gardé en session : aucun nom de personne
// n'apparaît dans l'adresse. Le nom d'activité cherché (« q ») peut y figurer.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$activite_id = entier(est_post() ? post('activite') : ($_GET['activite'] ?? 0));
$adherent_id = entier(est_post() ? post('adherent') : ($_GET['adherent'] ?? 0));
$origine = texte(est_post() ? post('origine') : ($_GET['origine'] ?? '')) === 'adherent' ? 'adherent' : 'activite';

$activite = $activite_id > 0 ? activite_par_id($activite_id) : null;
$inscrit = $adherent_id > 0 ? adherent_par_id($adherent_id) : null;
if ($activite_id > 0 && $activite === null) {
    flash('erreur', 'Cette activité n\'existe pas.');
    rediriger('activites.php');
}
if ($adherent_id > 0 && $inscrit === null) {
    flash('erreur', 'Cet adhérent n\'existe pas.');
    rediriger('adherents.php');
}
if ($activite === null && $inscrit === null) {
    rediriger('activites.php');
}

$suffixe_origine = $origine === 'adherent' ? '&origine=adherent' : '';
$page_ici = 'ajout_inscription.php?'
    . ($activite !== null ? 'activite=' . $activite_id : '')
    . ($activite !== null && $inscrit !== null ? '&' : '')
    . ($inscrit !== null ? 'adherent=' . $adherent_id : '')
    . $suffixe_origine;
$page_retour = $origine === 'adherent' && $inscrit !== null
    ? 'adherent.php?id=' . $adherent_id
    : ($activite !== null ? 'activite.php?id=' . $activite_id : 'adherent.php?id=' . $adherent_id);

// Saison concernée : celle de l'activité si elle est connue, sinon la saison affichée.
$saison_affichee = saison_courante();
$saison_id = $activite !== null ? entier($activite['saison_id']) : entier($saison_affichee['id'] ?? 0);
$saison_nom = $activite !== null ? texte($activite['saison_nom']) : texte($saison_affichee['nom'] ?? '');

// L'adhérent doit être inscrit pour la saison avant de l'être à une activité.
if ($inscrit !== null) {
    $dossier_id = dossier_id_de_adherent($inscrit, $saison_id);
    if ($dossier_id <= 0 || adresse_du_dossier($dossier_id, $adherent_id) === null) {
        flash('erreur', nom_adherent($inscrit) . ' n\'est pas inscrit(e) pour la saison ' . $saison_nom . ' : faites-le d\'abord sur sa fiche.');
        rediriger('adherent.php?id=' . $adherent_id);
    }
}
$est_structure = $inscrit !== null && entier($inscrit['est_structure']) === 1;

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_ici);
    }
    $action = post('action');

    // --- Recherche d'un adhérent (texte gardé en session) ----------------------
    if ($action === 'chercher') {
        $_SESSION['recherche_inscription'] = mb_substr(post('recherche'), 0, 100);
        rediriger($page_ici);
    }

    // --- Inscription ----------------------------------------------------------
    if ($action === 'inscrire' && $activite !== null && $inscrit !== null) {
        if (!jeton_unique_consommer('ajout_inscription')) {
            flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste des inscrits avant de recommencer.');
            rediriger($page_retour);
        }
        $nb_places = 1;
        $montant = null;
        $erreurs = [];
        if ($est_structure) {
            $nb_places = preg_match('/^\d{1,4}$/', post('nb_places')) === 1 ? entier(post('nb_places')) : 0;
            if ($nb_places < 1) {
                $erreurs[] = 'Le nombre de places est obligatoire (au moins 1).';
            }
            $montant = montant_lire(post('montant'));
            if ($montant === null) {
                $erreurs[] = 'Le montant négocié est obligatoire : un nombre positif ou nul, par exemple 150 ou 0.';
            }
        }
        if ($erreurs === []) {
            $resultat = inscription_creer($activite_id, $inscrit, $nb_places, $montant, post('en_attente') === '1', post('tarif_applique') === 'familial');
            if ($resultat['erreur'] !== '') {
                $erreurs[] = $resultat['erreur'];
            } elseif ($resultat['statut'] === 'attente' && post('en_attente') !== '1') {
                flash('avertissement', 'Il n\'y a plus assez de places : ' . nom_adherent($inscrit) . ' est en liste d\'attente pour « ' . texte($activite['nom']) . ' ».');
            } elseif ($resultat['statut'] === 'attente') {
                flash('succes', nom_adherent($inscrit) . ' est en liste d\'attente pour « ' . texte($activite['nom']) . ' ».');
            } else {
                flash('succes', nom_adherent($inscrit) . ' est inscrit(e) à « ' . texte($activite['nom']) . ' ».');
            }
        }
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger($page_ici);
        }
        unset($_SESSION['recherche_inscription']);
        rediriger($page_retour);
    }

    rediriger($page_ici);
}

// --- Données affichées -------------------------------------------------------
$recherche = '';
$propositions = [];
$activites_proposees = [];
$deja_inscrit = [];
$recherche_activite = mb_substr(trim(mb_scrub(texte($_GET['q'] ?? ''), 'UTF-8')), 0, 100);
if ($activite !== null && $inscrit === null) {
    // Étape « qui ? » : adhérents de la saison de l'activité.
    $recherche = texte($_SESSION['recherche_inscription'] ?? '');
    $propositions = adherents_suggestions($recherche, $saison_id, true);
} elseif ($activite === null) {
    // Étape « quelle activité ? » : activités de la saison affichée.
    $activites_proposees = activites_liste($saison_id, 0, 0, $recherche_activite);
    foreach (inscriptions_adherent($adherent_id, $saison_id) as $ligne_inscription) {
        if (entier($ligne_inscription['est_annulee']) === 0) {
            $deja_inscrit[entier($ligne_inscription['activite_id'])] = true;
        }
    }
}
$restantes = $activite !== null ? places_restantes($activite) : null;
// Tarif familial : proposé à une personne quand le tarif de l'activité a un montant familial.
$montant_familial = $activite !== null && !$est_structure ? ($activite['tarif_montant_familial'] ?? null) : null;
$famille_deja_au_familial = $montant_familial !== null && $inscrit !== null
    && inscription_familiale_existe($activite_id, dossier_id_de_adherent($inscrit, $saison_id));
$a_liste_attente = $activite !== null && entier($activite['a_liste_attente']) === 1;
// Les propositions pendant la frappe portent sur la saison affichée : elles ne
// sont utiles que si c'est aussi la saison de l'activité.
$propositions_en_direct = $saison_id === entier($saison_affichee['id'] ?? 0);

$titre_page = 'Inscription à une activité';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Inscription à une activité</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($page_retour)) ?>">Retour <?= $origine === 'adherent' || $activite === null ? 'à la fiche' : 'à l\'activité' ?></a>
        </div>
        <?php if ($activite !== null) : ?>
            <dl class="mt-4 mb-0">
                <dt class="small text-body-secondary fw-normal">Activité</dt>
                <dd><?= e($activite['nom']) ?><br><span class="small text-body-secondary"><?= e(activite_dates($activite)) ?> — saison <?= e($saison_nom) ?></span></dd>
                <dt class="small text-body-secondary fw-normal">Tarif</dt>
                <dd><?= e(activite_prix($activite)) ?></dd>
                <dt class="small text-body-secondary fw-normal">Places</dt>
                <dd>
                    <?php if ($restantes === null) : ?>
                        Non limitées
                    <?php else : ?>
                        <?= $restantes ?> libre<?= $restantes > 1 ? 's' : '' ?> sur <?= entier($activite['nb_places']) ?>
                    <?php endif; ?>
                    <?= $a_liste_attente ? '— liste d\'attente' : '' ?>
                </dd>
            </dl>
        <?php endif; ?>
    </div>

    <div class="col-md-8 order-md-1">
        <?php if ($activite !== null && $inscrit === null) : ?>
            <?php // ----- Qui inscrire ? ----- ?>
            <h2 class="h5 mb-3">Qui inscrire à « <?= e($activite['nom']) ?> » ?</h2>
            <form class="mb-3" method="post" action="<?= e(chemin_site('ajout_inscription.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="activite" value="<?= $activite_id ?>">
                <input type="hidden" name="action" value="chercher">
                <label class="form-label" for="recherche">Rechercher un adhérent de la saison <?= e($saison_nom) ?></label>
                <div class="mb-2">
                    <input class="form-control" type="search" id="recherche" name="recherche" maxlength="100" autocomplete="off"
                           <?php if ($propositions_en_direct) : ?>
                               data-recherche-url="<?= e(chemin_site('api_adherents.php')) ?>"
                               data-recherche-fiche="<?= e(chemin_site('ajout_inscription.php?activite=' . $activite_id . '&adherent=')) ?>"
                           <?php endif; ?>
                           value="<?= e($recherche) ?>" placeholder="Nom ou prénom (3 lettres au moins)" aria-describedby="aide_recherche">
                    <div class="form-text" id="aide_recherche">Seules les personnes et structures inscrites pour la saison sont proposées.</div>
                </div>
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
            </form>
            <?php if ($propositions !== []) : ?>
                <div class="list-group">
                    <?php foreach ($propositions as $proposition) : ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(chemin_site('ajout_inscription.php?activite=' . $activite_id . '&adherent=' . $proposition['id'])) ?>">
                            <strong><?= e($proposition['nom']) ?></strong>
                            <span class="d-block small text-body-secondary"><?= e($proposition['detail']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif (mb_strlen($recherche) >= 3) : ?>
                <div class="alert alert-info" role="alert">
                    Aucun adhérent de la saison ne correspond. Une personne qui n'est pas encore inscrite pour la saison doit d'abord l'être, depuis sa fiche ou par une nouvelle inscription.
                </div>
            <?php endif; ?>

        <?php elseif ($activite === null && $inscrit !== null) : ?>
            <?php // ----- À quelle activité ? ----- ?>
            <h2 class="h5 mb-3">À quelle activité inscrire <?= e(nom_adherent($inscrit)) ?> ?</h2>
            <form class="mb-3" method="get" action="<?= e(chemin_site('ajout_inscription.php')) ?>">
                <input type="hidden" name="adherent" value="<?= $adherent_id ?>">
                <?php if ($origine === 'adherent') : ?><input type="hidden" name="origine" value="adherent"><?php endif; ?>
                <label class="form-label" for="q">Rechercher une activité de la saison <?= e($saison_nom) ?></label>
                <div class="d-flex gap-2">
                    <input class="form-control" type="search" id="q" name="q" maxlength="100" value="<?= e($recherche_activite) ?>" placeholder="Nom de l'activité">
                    <button class="btn btn-outline-primary" type="submit">Rechercher</button>
                    <?php if ($recherche_activite !== '') : ?>
                        <a class="btn btn-outline-secondary" href="<?= e(chemin_site($page_ici)) ?>">Effacer</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($activites_proposees === []) : ?>
                <div class="alert alert-info" role="alert">
                    <?= $recherche_activite !== '' ? 'Aucune activité de la saison ' . e($saison_nom) . ' ne correspond à « ' . e($recherche_activite) . ' ».' : 'Aucune activité n\'existe pour la saison ' . e($saison_nom) . '.' ?>
                </div>
            <?php else : ?>
                <div class="list-group">
                    <?php foreach ($activites_proposees as $ligne_activite) : ?>
                        <?php
                        $ligne_id = entier($ligne_activite['id']);
                        $ligne_restantes = places_restantes($ligne_activite);
                        $ligne_attente = entier($ligne_activite['a_liste_attente']) === 1;
                        $ligne_detail = e(texte($ligne_activite['type_nom']) . ' — ' . activite_dates($ligne_activite) . ' — ' . activite_prix($ligne_activite));
                        ?>
                        <?php if (!$est_structure && isset($deja_inscrit[$ligne_id])) : ?>
                            <div class="list-group-item text-body-secondary">
                                <?= e($ligne_activite['nom']) ?> <span class="badge text-bg-secondary">Déjà inscrit(e)</span>
                                <span class="d-block small"><?= $ligne_detail ?></span>
                            </div>
                        <?php elseif ($ligne_restantes === 0 && !$ligne_attente) : ?>
                            <div class="list-group-item text-body-secondary">
                                <?= e($ligne_activite['nom']) ?> <span class="badge text-bg-danger">Complet</span>
                                <span class="d-block small"><?= $ligne_detail ?></span>
                            </div>
                        <?php else : ?>
                            <a class="list-group-item list-group-item-action" href="<?= e(chemin_site('ajout_inscription.php?activite=' . $ligne_id . '&adherent=' . $adherent_id . $suffixe_origine)) ?>">
                                <strong><?= e($ligne_activite['nom']) ?></strong>
                                <?php if ($ligne_restantes === 0) : ?>
                                    <span class="badge text-bg-warning">Complet — liste d'attente</span>
                                <?php elseif ($ligne_restantes !== null) : ?>
                                    <span class="badge text-bg-light border"><?= $ligne_restantes ?> place<?= $ligne_restantes > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                                <span class="d-block small text-body-secondary"><?= $ligne_detail ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($activite !== null) : ?>
            <?php // ----- Confirmation : l'activité et l'adhérent sont connus ----- ?>
            <h2 class="h5 mb-3">Inscrire <?= e(nom_adherent($inscrit)) ?> à « <?= e($activite['nom']) ?> »</h2>
            <?php if (!$est_structure && inscription_existe($activite_id, $adherent_id)) : ?>
                <div class="alert alert-info" role="alert"><?= e(nom_adherent($inscrit)) ?> a déjà une inscription en cours à cette activité.</div>
            <?php elseif ($restantes === 0 && !$a_liste_attente) : ?>
                <div class="alert alert-danger" role="alert">Cette activité est complète et n'a pas de liste d'attente.</div>
            <?php else : ?>
                <?php if ($restantes === 0) : ?>
                    <div class="alert alert-warning" role="alert">Cette activité est complète : l'inscription ira en liste d'attente.</div>
                <?php endif; ?>
                <form method="post" action="<?= e(chemin_site('ajout_inscription.php')) ?>">
                    <?= csrf_champ() ?>
                    <?= jeton_unique_champ('ajout_inscription') ?>
                    <input type="hidden" name="activite" value="<?= $activite_id ?>">
                    <input type="hidden" name="adherent" value="<?= $adherent_id ?>">
                    <input type="hidden" name="origine" value="<?= e($origine) ?>">
                    <input type="hidden" name="action" value="inscrire">
                    <?php if ($est_structure) : ?>
                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="nb_places">Nombre de places</label>
                                <input class="form-control" type="number" id="nb_places" name="nb_places" min="1" max="9999" step="1" value="<?= e(ancien('nb_places', '1')) ?>" required aria-describedby="aide_nb_places">
                                <div class="form-text" id="aide_nb_places">Si toutes les places demandées ne sont pas libres, l'inscription entière va en liste d'attente<?= $a_liste_attente ? '' : ' (cette activité n\'en a pas : elle sera refusée)' ?>.</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="montant">Montant négocié (€)</label>
                                <input class="form-control" type="text" inputmode="decimal" id="montant" name="montant" maxlength="10" value="<?= e(ancien('montant')) ?>" required aria-describedby="aide_montant">
                                <div class="form-text" id="aide_montant">Montant total pour la structure, à la place du tarif de l'activité.</div>
                            </div>
                        </div>
                    <?php elseif ($montant_familial !== null) : ?>
                        <?php $familial_coche = ancien_existe() ? ancien('tarif_applique') === 'familial' : $famille_deja_au_familial; ?>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Une place, à quel tarif ?</legend>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="tarif_individuel" name="tarif_applique" value="individuel" <?= $familial_coche ? '' : 'checked' ?>>
                                <label class="form-check-label" for="tarif_individuel">Tarif individuel : <strong><?= e(montant_fr($activite['tarif_montant'])) ?></strong></label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="tarif_familial" name="tarif_applique" value="familial" <?= $familial_coche ? 'checked' : '' ?>>
                                <label class="form-check-label" for="tarif_familial">Tarif familial : <strong><?= e(montant_fr($montant_familial)) ?></strong> pour toute la famille</label>
                            </div>
                            <div class="form-text">
                                <?= $famille_deja_au_familial ? 'Un membre de la famille est déjà inscrit à cette activité au tarif familial.' : '' ?>
                                Le montant familial ne compte qu'une fois, quel que soit le nombre de membres de la famille inscrits à ce tarif.
                            </div>
                        </fieldset>
                    <?php else : ?>
                        <p>Une place, au tarif de l'activité : <strong><?= e(activite_prix($activite)) ?></strong>.</p>
                    <?php endif; ?>
                    <?php if ($a_liste_attente && $restantes !== 0) : ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="en_attente" name="en_attente" value="1" <?= ancien('en_attente') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="en_attente">Inscrire directement en liste d'attente, même s'il reste des places</label>
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit"><?= $restantes === 0 ? 'Mettre en liste d\'attente' : 'Inscrire' ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
