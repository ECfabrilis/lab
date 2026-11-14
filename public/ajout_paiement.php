<?php

declare(strict_types=1);

// Nouveau paiement (rôle standard au minimum), pour la saison affichée.
// La page s'ouvre de trois façons :
//   sans paramètre   on cherche la personne ou la structure qui paie ;
//   ?adherent=N      on retrouve sa famille (ou la structure elle-même) ;
//   ?famille=N ou ?structure=N   le formulaire du paiement.
// Le nom cherché est gardé en session : aucun nom de personne n'apparaît dans l'adresse.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);
$saison_affichee_nom = texte($saison_affichee['nom'] ?? '');

// Un adhérent est donné : le paiement va à sa famille, ou à lui-même si c'est une structure.
$adherent_id = entier($_GET['adherent'] ?? 0);
if (!est_post() && $adherent_id > 0) {
    $payeur = adherent_par_id($adherent_id);
    $titulaire_trouve = $payeur !== null ? compte_titulaire_de_adherent($payeur) : null;
    if ($titulaire_trouve === null) {
        flash('erreur', 'Cet adhérent n\'existe pas.');
        rediriger('ajout_paiement.php');
    }
    rediriger('ajout_paiement.php?' . $titulaire_trouve['lien']);
}

$famille_id = entier(est_post() ? post('famille') : ($_GET['famille'] ?? 0));
$structure_id = entier(est_post() ? post('structure') : ($_GET['structure'] ?? 0));
$titulaire = $famille_id > 0 || $structure_id > 0 ? compte_titulaire($famille_id, $structure_id) : null;
if (($famille_id > 0 || $structure_id > 0) && $titulaire === null) {
    flash('erreur', 'Cette famille ou cette structure n\'existe pas.');
    rediriger('ajout_paiement.php');
}
$page_ici = 'ajout_paiement.php' . ($titulaire !== null ? '?' . $titulaire['lien'] : '');

// Un paiement se rattache à la saison : il faut y être inscrit.
$dossier_id = $titulaire !== null ? compte_dossier_id($titulaire, $saison_affichee_id) : 0;
if ($titulaire !== null && ($dossier_id <= 0 || $titulaire['anonymise'])) {
    flash('erreur', $titulaire['titre'] . ' : personne n\'est inscrit pour la saison ' . $saison_affichee_nom . ', aucun paiement ne peut être enregistré.');
    rediriger($titulaire['fiche']);
}

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_ici);
    }
    $action = post('action');

    // --- Recherche de la personne qui paie (texte gardé en session) -----------
    if ($action === 'chercher') {
        $_SESSION['recherche_paiement'] = mb_substr(post('recherche'), 0, 100);
        rediriger('ajout_paiement.php');
    }

    // --- Enregistrement du paiement --------------------------------------------
    if ($action === 'payer' && $titulaire !== null) {
        if (!jeton_unique_consommer('ajout_paiement')) {
            flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez les paiements avant de recommencer.');
            rediriger('compte.php?' . $titulaire['lien']);
        }
        $saisie = paiement_saisie();
        $erreurs = erreurs_paiement($saisie);
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger($page_ici);
        }
        // Selon le choix et le reste à payer : un paiement, ou plusieurs paiements liés (un par objet).
        $resultat = paiements_enregistrer($dossier_id, $saisie);
        if ($resultat['erreur'] !== '') {
            flash('erreur', $resultat['erreur']);
            ancien_memoriser();
            rediriger($page_ici);
        }
        unset($_SESSION['recherche_paiement']);
        $detail_parts = [];
        foreach ($resultat['parts'] as $objet_part => $centimes_part) {
            $detail_parts[] = mb_strtolower(libelles_objets_paiement()[$objet_part] ?? '') . ' ' . centimes_fr($centimes_part);
        }
        flash('succes', 'Le paiement de ' . montant_fr(montant_lire($saisie['montant'])) . ' est enregistré'
            . (count($detail_parts) > 1 ? ', réparti en ' . count($detail_parts) . ' paiements liés : ' . implode(', ', $detail_parts) . '.' : '.'));
        if ($saisie['objet'] !== 'don' && ($resultat['parts']['don'] ?? 0) > 0) {
            flash('avertissement', 'Le montant dépassait le reste à payer : ' . centimes_fr($resultat['parts']['don']) . ' ont été enregistrés en don.');
        }
        rediriger('paiement.php?id=' . ($resultat['ids'][0] ?? 0));
    }

    rediriger($page_ici);
}

// --- Données affichées -------------------------------------------------------
$recherche = '';
$propositions = [];
$compte = null;
$objet_propose = 'activites';
if ($titulaire === null) {
    $recherche = texte($_SESSION['recherche_paiement'] ?? '');
    $propositions = adherents_suggestions($recherche, $saison_affichee_id, true);
} else {
    $compte = compte_du_dossier($dossier_id);
    // Objet proposé : un don quand plus rien n'est dû ; sinon l'adhésion tant qu'elle n'est pas réglée.
    if ($compte !== null && $compte['solde'] <= 0) {
        $objet_propose = 'don';
    } elseif ($compte !== null && $compte['cotisations'] > $compte['paye_adhesion']) {
        $objet_propose = 'adhesion';
    }
}
$mode_choisi = entier(ancien('mode'));
$objet_choisi = ancien('objet', $objet_propose);
// Reste à payer (en centimes) : il décide de l'affichage du choix « les deux » (js/paiements.js).
$reste_a_payer = $compte !== null ? max(0, $compte['solde']) : 0;

$titre_page = 'Nouveau paiement';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Nouveau paiement <span class="badge text-bg-secondary">Saison <?= e($saison_affichee_nom) ?></span></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($titulaire !== null) : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('compte.php?' . $titulaire['lien'])) ?>">Cotisations et paiements</a>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('ajout_paiement.php')) ?>">Choisir quelqu'un d'autre</a>
            <?php else : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('paiements.php')) ?>">Liste des paiements</a>
            <?php endif; ?>
        </div>
        <?php if ($compte !== null) : ?>
            <div class="mt-4">
                <?php afficher_resume_compte($compte, true); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8 order-md-1">
        <?php if ($saison_affichee === null) : ?>
            <div class="alert alert-info" role="alert">Aucune saison n'existe encore.</div>
        <?php elseif ($titulaire === null) : ?>
            <?php // ----- Qui paie ? ----- ?>
            <h2 class="h5 mb-3">Qui paie ?</h2>
            <form class="mb-3" method="post" action="<?= e(chemin_site('ajout_paiement.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="chercher">
                <label class="form-label" for="recherche">Rechercher un adhérent de la saison <?= e($saison_affichee_nom) ?></label>
                <div class="mb-2">
                    <input class="form-control" type="search" id="recherche" name="recherche" maxlength="100" autocomplete="off"
                           data-recherche-url="<?= e(chemin_site('api_adherents.php')) ?>"
                           data-recherche-fiche="<?= e(chemin_site('ajout_paiement.php?adherent=')) ?>"
                           value="<?= e($recherche) ?>" placeholder="Nom ou prénom (3 lettres au moins)" aria-describedby="aide_recherche">
                    <div class="form-text" id="aide_recherche">Le paiement est enregistré pour la famille de la personne choisie, ou pour la structure.</div>
                </div>
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
            </form>
            <?php if ($propositions !== []) : ?>
                <div class="list-group">
                    <?php foreach ($propositions as $proposition) : ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(chemin_site('ajout_paiement.php?adherent=' . $proposition['id'])) ?>">
                            <strong><?= e($proposition['nom']) ?></strong>
                            <span class="d-block small text-body-secondary"><?= e($proposition['detail']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif (mb_strlen($recherche) >= 3) : ?>
                <div class="alert alert-info" role="alert">Aucun adhérent de la saison ne correspond à cette recherche.</div>
            <?php endif; ?>
        <?php else : ?>
            <?php // ----- Le paiement ----- ?>
            <h2 class="h5 mb-3">Paiement de : <?= e($titulaire['titre']) ?></h2>
            <form method="post" action="<?= e(chemin_site('ajout_paiement.php')) ?>">
                <?= csrf_champ() ?>
                <?= jeton_unique_champ('ajout_paiement') ?>
                <input type="hidden" name="<?= e($titulaire['type']) ?>" value="<?= $titulaire['id'] ?>">
                <input type="hidden" name="action" value="payer">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="montant">Montant (€)</label>
                        <input class="form-control" type="text" inputmode="decimal" id="montant" name="montant" maxlength="10" placeholder="120,00" value="<?= e(ancien('montant')) ?>" required autofocus>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="date_paiement">Date du paiement</label>
                        <input class="form-control" type="date" id="date_paiement" name="date_paiement" max="<?= e(date('Y-m-d')) ?>" value="<?= e(ancien('date_paiement', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="mode">Mode de paiement</label>
                        <select class="form-select" id="mode" name="mode" required>
                            <option value="">Choisir…</option>
                            <?php foreach (choix_proposes('modes_paiement') as $code_mode => $libelle_mode) : ?>
                                <option value="<?= $code_mode ?>" <?= $mode_choisi === $code_mode ? 'selected' : '' ?>><?= e($libelle_mode) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <?php // « les deux » n'apparaît que si le montant saisi couvre le reste à payer (js/paiements.js) ; la règle est revérifiée à l'enregistrement. ?>
                        <fieldset data-paiement-reste="<?= $reste_a_payer ?>" data-paiement-defaut="<?= e($objet_propose) ?>">
                            <legend class="form-label fs-6">Ce paiement règle</legend>
                            <?php foreach (libelles_choix_paiement() as $code_objet => $libelle_objet) : ?>
                                <div class="form-check form-check-inline <?= $code_objet === 'deux' ? 'd-none' : '' ?>" <?= $code_objet === 'deux' ? 'data-paiement-deux' : '' ?>>
                                    <input class="form-check-input" type="radio" id="objet_<?= e($code_objet) ?>" name="objet" value="<?= e($code_objet) ?>" <?= $objet_choisi === $code_objet ? 'checked' : '' ?> required>
                                    <label class="form-check-label" for="objet_<?= e($code_objet) ?>"><?= e($libelle_objet) ?></label>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-text">Pour la comptabilité. « Les deux » est proposé quand le montant couvre tout le reste à payer : un paiement est alors enregistré pour l'adhésion et un autre pour les activités, liés entre eux.</div>
                            <div class="form-text text-warning-emphasis fw-semibold" data-paiement-surplus role="status"></div>
                        </fieldset>
                    </div>
                    <div class="col-sm-12">
                        <label class="form-label" for="note">Note <span class="text-body-secondary">(facultatif)</span></label>
                        <input class="form-control" type="text" id="note" name="note" maxlength="255" placeholder="Numéro de chèque, banque…" value="<?= e(ancien('note')) ?>">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Enregistrer le paiement</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
