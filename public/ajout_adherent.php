<?php

declare(strict_types=1);

// Création d'un adhérent (rôle standard au minimum).
//   - sans paramètre : nouvelle personne, donc nouvelle famille (qui porte son nom) ;
//   - avec ?famille=ID : nouveau membre d'une famille existante.
// L'adhérent est ajouté au dossier de la saison affichée (ouvert si besoin).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$famille_id = entier(est_post() ? post('famille_id') : ($_GET['famille'] ?? 0));
$famille = $famille_id > 0 ? famille_par_id($famille_id) : null;
if ($famille_id > 0 && $famille === null) {
    flash('erreur', 'Cette famille n\'existe pas.');
    rediriger('adherents.php');
}
$saison_affichee = saison_courante();
if ($saison_affichee === null) {
    flash('erreur', 'Aucune saison n\'existe : créez-en une avant d\'ajouter des adhérents.');
    rediriger('index.php');
}
$saison_affichee_id = entier($saison_affichee['id']);
$page_formulaire = 'ajout_adherent.php' . ($famille_id > 0 ? '?famille=' . $famille_id : '');

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_formulaire);
    }
    if (!jeton_unique_consommer('ajout_adherent')) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger($famille_id > 0 ? 'famille.php?id=' . $famille_id : 'adherents.php');
    }

    $saisie = adherent_saisie();
    $saisie_adresse = adresse_saisie();
    $erreurs = [...erreurs_adherent($saisie), ...erreurs_adresse($saisie_adresse)];
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger($page_formulaire);
    }

    // Doublon possible : on demande une confirmation avant de créer.
    $doublons = adherent_doublons($saisie['nom'], $saisie['prenom'], $saisie['date_naissance']);
    if ($doublons !== [] && post('confirmer_doublon') !== '1') {
        $familles_doublons = array_map(static fn (array $doublon): string => texte($doublon['famille_nom']), $doublons);
        flash('avertissement', 'Une personne avec ce nom, ce prénom et cette date de naissance existe déjà (famille '
            . implode(', ', array_unique($familles_doublons)) . '). Cochez la case de confirmation pour la créer quand même.');
        $_POST['doublon_detecte'] = '1';
        ancien_memoriser();
        rediriger($page_formulaire);
    }

    db()->beginTransaction();
    try {
        if ($famille === null) {
            $famille_id = creer_famille($saisie['nom']);
        }
        $nouvel_id = creer_adherent($famille_id, $saisie);
        $dossier_id = dossier_obtenir($famille_id, $saison_affichee_id);
        $adresse_reconnue = adresse_enregistrer($dossier_id, $nouvel_id, $saisie_adresse);
        db()->commit();
    } catch (Throwable $erreur) {
        db()->rollBack();
        throw $erreur;
    }

    flash('succes', $saisie['prenom'] . ' ' . $saisie['nom'] . ' est enregistré(e) pour la saison ' . texte($saison_affichee['nom']) . '.');
    if (!$adresse_reconnue) {
        flash('avertissement', 'L\'adresse n\'a pas été reconnue au numéro près : le quartier n\'a pas pu être déterminé. Vérifiez-la sur la fiche.');
    }
    rediriger('famille.php?id=' . $famille_id);
}

// Valeurs proposées : le nom et la dernière adresse de la famille.
$defauts = [];
$defauts_adresse = [];
if ($famille !== null) {
    $defauts['nom'] = $famille['nom'];
    $defauts_adresse = derniere_adresse_famille($famille_id) ?? [];
}

$titre_page = $famille === null ? 'Nouvel adhérent' : 'Nouveau membre de la famille';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= $famille === null ? 'Nouvel adhérent' : 'Nouveau membre de la famille ' . e($famille['nom']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($famille === null) : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('adherents.php')) ?>">Retour à la liste</a>
            <?php else : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('famille.php?id=' . $famille_id)) ?>">Retour à la famille</a>
            <?php endif; ?>
        </div>
        <ul class="text-body-secondary small mt-3 mb-0 ps-3">
            <?php if ($famille === null) : ?>
                <li>Une nouvelle famille est créée au nom de cette personne. Les autres membres s'ajoutent ensuite depuis la fiche de la famille.</li>
                <li>Si la personne est déjà venue, cherchez-la d'abord parmi « Tous les adhérents ».</li>
            <?php else : ?>
                <li>L'adresse proposée est la dernière connue dans la famille.</li>
            <?php endif; ?>
            <li>La personne est ajoutée au dossier de la saison <?= e($saison_affichee['nom']) ?>.</li>
            <li>Le quartier est déterminé automatiquement à partir de l'adresse.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ('ajout_adherent') ?>
            <input type="hidden" name="formulaire" value="adherent">
            <input type="hidden" name="famille_id" value="<?= $famille_id ?>">

            <?php if (ancien('doublon_detecte') === '1') : ?>
                <div class="alert alert-warning">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="confirmer_doublon" name="confirmer_doublon" value="1">
                        <label class="form-check-label" for="confirmer_doublon">Ce n'est pas un doublon : créer quand même cette personne.</label>
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="h5">Identité</h2>
            <?php afficher_champs_adherent($defauts); ?>

            <h2 class="h5">Adresse pour la saison <?= e($saison_affichee['nom']) ?></h2>
            <?php afficher_champs_adresse($defauts_adresse); ?>

            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
