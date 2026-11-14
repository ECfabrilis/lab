<?php

declare(strict_types=1);

// Changement de famille d'une personne.
// - Elle n'est pas seule dans sa famille : elle peut en être retirée (une
//   nouvelle famille est créée à son nom).
// - Elle est seule dans sa famille : elle peut rejoindre une autre famille.
// Pour passer d'une famille de plusieurs personnes à une autre, on fait les
// deux opérations à la suite. Rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$personne_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$personne = adherent_par_id($personne_id);
if ($personne === null) {
    flash('erreur', 'Cet adhérent n\'existe pas.');
    rediriger('adherents.php');
}
$page_fiche = 'adherent.php?id=' . $personne_id;
$page_changement = 'changer_famille.php?id=' . $personne_id;
$refus = changement_famille_refus($personne);
if ($refus !== '') {
    flash('erreur', $refus);
    rediriger($page_fiche);
}
$nom_personne = nom_adherent($personne);
$famille_actuelle_id = entier($personne['famille_id']);
$famille_actuelle_nom = texte($personne['famille_nom']);
$est_seule = famille_membres_nb($famille_actuelle_id) === 1;

// La recherche de famille est gardée en session, pour cette personne seulement.
$recherche_gardee = $_SESSION['recherche_changer_famille'] ?? null;
$recherche = is_array($recherche_gardee) && entier($recherche_gardee['id'] ?? 0) === $personne_id
    ? texte($recherche_gardee['texte'] ?? '')
    : '';

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_changement);
    }
    $action = post('action');

    // --- Retrait de la famille -------------------------------------------------
    if ($action === 'retirer') {
        $retrait = adherent_quitter_famille($personne_id);
        if ($retrait === null) {
            flash('erreur', 'Cette personne est seule dans sa famille : elle ne peut pas en être retirée.');
            rediriger($page_changement);
        }
        flash('succes', $nom_personne . ' est retiré(e) de la famille ' . $famille_actuelle_nom . '. Une nouvelle famille est créée à son nom ; vérifiez les paiements des deux familles.');
        rediriger('famille.php?id=' . $retrait['famille_id']);
    }

    // --- Recherche d'une famille d'accueil -------------------------------------
    if ($action === 'chercher') {
        $_SESSION['recherche_changer_famille'] = ['id' => $personne_id, 'texte' => mb_substr(post('recherche'), 0, 100)];
        rediriger($page_changement);
    }

    // --- Entrée dans une autre famille -----------------------------------------
    if ($action === 'rejoindre') {
        $cible_id = entier(post('famille'));
        $cible = famille_par_id($cible_id);
        if ($cible === null) {
            flash('erreur', 'Choisissez la famille à rejoindre.');
            rediriger($page_changement);
        }
        $erreur = adherent_rejoindre_famille($personne_id, $cible_id);
        if ($erreur !== '') {
            flash('erreur', $erreur);
            rediriger($page_changement);
        }
        unset($_SESSION['recherche_changer_famille']);
        flash('succes', $nom_personne . ' fait maintenant partie de la famille ' . texte($cible['nom']) . '.');
        rediriger('famille.php?id=' . $cible_id);
    }

    rediriger($page_changement);
}

// Personne seule : familles proposées (noms proches, puis résultat de la recherche).
$familles_proposees = [];
if ($est_seule) {
    foreach ([familles_proches(texte($personne['nom'])), familles_par_texte($recherche)] as $liste) {
        foreach ($liste as $famille_proposee) {
            if ($famille_proposee['id'] !== $famille_actuelle_id) {
                $familles_proposees[$famille_proposee['id']] ??= $famille_proposee;
            }
        }
    }
}

// Personne dans une famille de plusieurs membres : les comptes tels qu'ils
// seraient après le retrait (simulation, rien n'est enregistré).
$simulation = $est_seule ? null : adherent_quitter_famille($personne_id, true);
$saisons_retrait = $simulation['saisons'] ?? [];
$paiements_a_revoir = false;
foreach ($saisons_retrait as $ligne_saison) {
    if (entier($ligne_saison['personne']['du'] ?? 0) > 0 && entier($ligne_saison['origine']['paye'] ?? 0) > 0) {
        $paiements_a_revoir = true;
    }
}
// Résumé d'un compte en une ligne : dû, payé, reste à payer ou trop-perçu.
$resume_compte = static function (?array $compte): string {
    $solde = entier($compte['solde'] ?? 0);
    $suite = match (true) {
        $solde > 0 => 'reste à payer ' . centimes_fr($solde),
        $solde < 0 => 'trop-perçu ' . centimes_fr(-$solde),
        default    => 'rien à payer',
    };
    return 'dû ' . centimes_fr(entier($compte['du'] ?? 0)) . ', payé ' . centimes_fr(entier($compte['paye'] ?? 0)) . ' : ' . $suite;
};

$titre_page = 'Changer de famille';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Changer de famille — <?= e($nom_personne) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($page_fiche)) ?>">Retour à la fiche</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('famille.php?id=' . $famille_actuelle_id)) ?>">Voir la famille <?= e($famille_actuelle_nom) ?></a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
<?php if (!$est_seule) : ?>
        <?php // ----- Retirer la personne de sa famille ----- ?>
        <h2 class="h5">Retirer de la famille <?= e($famille_actuelle_nom) ?></h2>
        <p>Une nouvelle famille sera créée au nom de <?= e($personne['nom']) ?> ; <?= e($nom_personne) ?> y sera seul(e) et pourra ensuite rejoindre une autre famille.</p>
        <ul>
            <li><strong>Suivent <?= e($nom_personne) ?></strong>, pour toutes les saisons : son adresse et sa scolarité, ses inscriptions aux activités, sa cotisation individuelle, ses réductions personnelles.</li>
            <li><strong>Restent à la famille <?= e($famille_actuelle_nom) ?></strong> : la cotisation familiale, les réductions de toute la famille, les notes des saisons et tous les paiements.</li>
            <li>Une inscription faite au tarif familial garde son montant : il peut alors être compté dans chacune des deux familles.</li>
        </ul>

        <?php if ($saisons_retrait === []) : ?>
            <p class="text-body-secondary"><?= e($nom_personne) ?> n'est inscrit(e) pour aucune saison : rien d'autre ne change.</p>
        <?php else : ?>
            <h3 class="h6">Les comptes après le retrait</h3>
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Saison</th>
                            <th scope="col">Famille <?= e($famille_actuelle_nom) ?></th>
                            <th scope="col"><?= e($nom_personne) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saisons_retrait as $ligne_saison) : ?>
                            <tr>
                                <th scope="row" class="fw-normal"><?= e($ligne_saison['saison_nom']) ?></th>
                                <td><?= e($resume_compte($ligne_saison['origine'])) ?></td>
                                <td><?= e($resume_compte($ligne_saison['personne'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($paiements_a_revoir) : ?>
            <div class="alert alert-warning" role="alert">
                Des paiements déjà enregistrés resteront à la famille <?= e($famille_actuelle_nom) ?>, alors que des montants dus suivront <?= e($nom_personne) ?>.
                Après le retrait, corrigez si besoin en annulant le paiement puis en le ressaisissant dans la bonne famille.
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(chemin_site('changer_famille.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $personne_id ?>">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($page_fiche)) ?>">Annuler</a>
            <button class="btn btn-danger" type="submit" name="action" value="retirer">Retirer de la famille</button>
        </form>
<?php else : ?>
        <?php // ----- Faire entrer la personne dans une autre famille ----- ?>
        <h2 class="h5">Rejoindre une autre famille</h2>
        <p>
            <?= e($nom_personne) ?> est seul(e) dans la famille <?= e($famille_actuelle_nom) ?>. Tout ce que contient cette famille
            (inscriptions, cotisations, réductions, paiements, notes des saisons) passera dans la famille choisie, et la famille <?= e($famille_actuelle_nom) ?>, vide, sera supprimée.
        </p>

        <form method="post" action="<?= e(chemin_site('changer_famille.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $personne_id ?>">
            <?php if ($familles_proposees !== []) : ?>
                <div class="list-group mb-3">
                    <?php foreach ($familles_proposees as $famille_proposee) : ?>
                        <label class="list-group-item d-flex gap-2">
                            <input class="form-check-input flex-shrink-0" type="radio" name="famille" value="<?= $famille_proposee['id'] ?>">
                            <span>
                                Famille <strong><?= e($famille_proposee['nom']) ?></strong>
                                <span class="d-block small text-body-secondary"><?= e($famille_proposee['membres']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($recherche !== '') : ?>
                <p class="text-body-secondary small">Aucune famille ne correspond à « <?= e($recherche) ?> ».</p>
            <?php else : ?>
                <p class="text-body-secondary small">Aucune autre famille n'a un nom proche de « <?= e($personne['nom']) ?> ».</p>
            <?php endif; ?>

            <label class="form-label" for="recherche">Rechercher une autre famille</label>
            <div class="input-group mb-4">
                <input class="form-control" type="search" id="recherche" name="recherche" maxlength="100" value="<?= e($recherche) ?>" placeholder="Nom de la famille ou d'un membre">
                <button class="btn btn-outline-secondary" type="submit" name="action" value="chercher">Rechercher</button>
            </div>

            <a class="btn btn-outline-secondary" href="<?= e(chemin_site($page_fiche)) ?>">Annuler</a>
            <?php if ($familles_proposees !== []) : ?>
                <button class="btn btn-primary" type="submit" name="action" value="rejoindre">Rejoindre cette famille</button>
            <?php endif; ?>
        </form>
<?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
