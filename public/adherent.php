<?php

declare(strict_types=1);

// Fiche d'un adhérent : identité, contact, responsable, puis adresse et
// scolarité pour la saison affichée.
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
        $saisie = adherent_saisie();
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
            if (adherent_doublons($saisie['nom'], $saisie['prenom'], $saisie['date_naissance'], $fiche_id) !== []) {
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
            $dossier_id = dossier_obtenir($famille_id, $saison_affichee_id);
            $adresse_reconnue = adresse_enregistrer($dossier_id, $fiche_id, $saisie_adresse);
            db()->commit();
        } catch (Throwable $erreur) {
            db()->rollBack();
            throw $erreur;
        }
        flash('succes', 'L\'adresse de la saison ' . texte($saison_affichee['nom'] ?? '') . ' est enregistrée.');
        if (!$adresse_reconnue) {
            flash('avertissement', 'L\'adresse n\'a pas été reconnue au numéro près : le quartier n\'a pas pu être déterminé.');
        }
        rediriger($page_fiche);
    }

    rediriger($page_fiche);
}

// Adresse de la saison affichée ; à défaut, la dernière connue est proposée.
$dossier_saison_id = dossier_id_de_famille($famille_id, $saison_affichee_id);
$adresse_saison = $dossier_saison_id > 0 ? adresse_du_dossier($dossier_saison_id, $fiche_id) : null;
$adresse_proposee = $adresse_saison ?? derniere_adresse_adherent($fiche_id) ?? derniere_adresse_famille($famille_id) ?? [];
$naissance = texte($fiche['date_naissance']);

$titre_page = 'Adhérent';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e($fiche['prenom']) ?> <?= e($fiche['nom']) ?>
    <?php if (entier($fiche['est_benevole']) === 1) : ?><span class="badge text-bg-info">Bénévole</span><?php endif; ?>
    <?php if ($est_anonymise) : ?><span class="badge text-bg-secondary">Anonymisé</span><?php endif; ?>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-primary" href="<?= e(chemin_site('famille.php?id=' . $famille_id)) ?>">Famille <?= e($fiche['famille_nom']) ?></a>
            <?php if ($dossier_saison_id > 0) : ?>
                <a class="btn btn-outline-primary" href="<?= e(chemin_site('dossier.php?id=' . $dossier_saison_id)) ?>">Dossier <?= e($saison_affichee['nom'] ?? '') ?></a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('adherents.php')) ?>">Retour à la liste</a>
        </div>
        <dl class="small text-body-secondary mt-3 mb-0">
            <dt>Âge</dt>
            <dd><?= age_ans($naissance) ?> ans<?= est_mineur($naissance) ? ' (mineur)' : '' ?></dd>
            <?php if (texte($fiche['nationalite_nom']) !== '') : ?>
                <dt>Nationalité</dt>
                <dd><?= e($fiche['nationalite_nom']) ?></dd>
            <?php endif; ?>
            <dt>Communications</dt>
            <dd><?= entier($fiche['a_consenti']) === 1 ? 'Acceptées le ' . e(date_fr($fiche['consentement_le'])) : 'Non acceptées' ?></dd>
            <dt>Fiche créée</dt>
            <dd><?= e(date_heure_fr($fiche['date_crea'])) ?></dd>
            <?php if (texte($fiche['date_modif']) !== '') : ?>
                <dt>Dernière modification</dt>
                <dd><?= e(date_heure_fr($fiche['date_modif'])) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('adherent.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $fiche_id ?>">
            <input type="hidden" name="action" value="modifier">
            <input type="hidden" name="formulaire" value="adherent">
            <fieldset <?= $peut_modifier ? '' : 'disabled' ?>>
                <h2 class="h5">Identité</h2>
                <?php afficher_champs_adherent($fiche); ?>
                <?php if ($peut_modifier) : ?>
                    <button class="btn btn-primary" type="submit">Enregistrer la fiche</button>
                <?php endif; ?>
            </fieldset>
        </form>

        <hr class="my-4">

        <?php if ($saison_affichee === null) : ?>
            <p class="text-body-secondary">Aucune saison n'existe encore.</p>
        <?php else : ?>
            <h2 class="h5">Adresse pour la saison <?= e($saison_affichee['nom']) ?></h2>
            <?php if ($adresse_saison === null) : ?>
                <div class="alert alert-info" role="alert">
                    Cette personne n'est pas dans le dossier de la saison <?= e($saison_affichee['nom']) ?>.
                    <?= $peut_modifier ? 'Vérifiez l\'adresse ci-dessous puis enregistrez pour l\'y ajouter.' : '' ?>
                </div>
            <?php else : ?>
                <p class="mb-3">
                    <?php if (entier($adresse_saison['est_validee']) === 1) : ?>
                        <span class="badge text-bg-success">Adresse vérifiée</span>
                    <?php else : ?>
                        <span class="badge text-bg-warning">Adresse non reconnue</span>
                    <?php endif; ?>
                    <?php if (texte($adresse_saison['quartier']) !== '') : ?>
                        <span class="badge text-bg-light border">Quartier : <?= e($adresse_saison['quartier']) ?></span>
                    <?php endif; ?>
                    <?php if (entier($adresse_saison['est_qpv']) === 1) : ?>
                        <span class="badge text-bg-primary">QPV : <?= e($adresse_saison['qpv_nom']) ?></span>
                    <?php endif; ?>
                    <?php if (entier($adresse_saison['est_qp']) === 1) : ?>
                        <span class="badge text-bg-primary">QP : <?= e($adresse_saison['qp_nom']) ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <form method="post" action="<?= e(chemin_site('adherent.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="id" value="<?= $fiche_id ?>">
                <input type="hidden" name="action" value="adresse">
                <fieldset <?= $peut_modifier ? '' : 'disabled' ?>>
                    <?php afficher_champs_adresse($adresse_proposee); ?>
                    <?php if ($peut_modifier) : ?>
                        <button class="btn btn-primary" type="submit"><?= $adresse_saison === null ? 'Ajouter au dossier de la saison' : 'Enregistrer l\'adresse' ?></button>
                    <?php endif; ?>
                </fieldset>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
