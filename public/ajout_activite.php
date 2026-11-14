<?php

declare(strict_types=1);

// Création d'une activité pour la saison affichée (rôle standard au minimum).
// Avant d'enregistrer, on vérifie qu'une activité semblable (nom et dates
// similaires) n'existe pas déjà : si c'est le cas, une confirmation est demandée.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_affichee = saison_courante();
if ($saison_affichee === null) {
    flash('erreur', 'Aucune saison n\'existe encore.');
    rediriger('index.php');
}
$saison_affichee_id = entier($saison_affichee['id']);

if (est_post()) {
    csrf_verifier();
    if (!jeton_unique_consommer('ajout_activite')) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger('activites.php');
    }
    // La saison affichée a pu changer dans un autre onglet depuis l'affichage du formulaire.
    if (entier(post('saison_id')) !== $saison_affichee_id) {
        flash('erreur', 'La saison affichée a changé : vérifiez la saison, puis recommencez.');
        ancien_memoriser();
        rediriger('ajout_activite.php');
    }

    $saisie = activite_saisie();
    $erreurs = erreurs_activite($saisie, $saison_affichee);
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger('ajout_activite.php');
    }

    // Activité déjà créée ? Nom et dates similaires : on demande une confirmation.
    $similaires = activites_similaires($saison_affichee_id, $saisie['nom'], $saisie['date_debut'], $saisie['date_fin']);
    if ($similaires !== [] && post('confirmer_similaire') !== '1') {
        foreach ($similaires as $similaire) {
            flash('avertissement', 'Une activité semblable existe déjà : « ' . texte($similaire['nom']) . ' », ' . activite_dates($similaire)
                . ' (' . texte($similaire['secteur_nom']) . ').');
        }
        flash('info', 'Si c\'est bien une autre activité, cochez « Créer quand même » en bas du formulaire.');
        $_SESSION['activite_similaire'] = true;
        ancien_memoriser();
        rediriger('ajout_activite.php');
    }

    $nouvel_id = creer_activite($saison_affichee_id, $saisie);
    flash('succes', 'L\'activité « ' . $saisie['nom'] . ' » est créée pour la saison ' . texte($saison_affichee['nom']) . '.');
    rediriger('activite.php?id=' . $nouvel_id);
}

// Il faut au moins un secteur, un type et une récurrence actifs pour créer une activité.
$manque_secteur = choix_proposes('secteurs') === [];
$listes_a_completer = [];
if ($manque_secteur) {
    $listes_a_completer[] = 'les secteurs (« Secteurs d\'activité » sur le tableau de bord)';
}
if (choix_proposes('types_activite') === []) {
    $listes_a_completer[] = 'les types d\'activité (« Options des activités » dans les paramètres)';
}
if (choix_proposes('recurrences') === []) {
    $listes_a_completer[] = 'les récurrences (« Options des activités » dans les paramètres)';
}

// La case « Créer quand même » n'apparaît qu'après la découverte d'une activité semblable.
$confirmation_demandee = ($_SESSION['activite_similaire'] ?? false) === true && ancien_existe();
unset($_SESSION['activite_similaire']);

$titre_page = 'Nouvelle activité';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Nouvelle activité — saison <?= e($saison_affichee['nom']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('activites.php')) ?>">Retour à la liste</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('tarifs.php')) ?>">Les tarifs</a>
        </div>
        <p class="d-none d-lg-block text-body-secondary small mt-3 mb-0">Le tarif voulu n'est pas dans la liste ? Ajoutez-le d'abord dans « Les tarifs ».</p>
    </div>
    <div class="col-md-8 order-md-1">
        <?php if ($listes_a_completer !== []) : ?>
            <div class="alert alert-warning" role="alert">
                Une activité a besoin d'un secteur, d'un type et d'une récurrence actifs.
                Un administrateur doit d'abord compléter : <?= e(implode(' ; ', $listes_a_completer)) ?>.
            </div>
        <?php else : ?>
            <form method="post" action="<?= e(chemin_site('ajout_activite.php')) ?>">
                <?= csrf_champ() ?>
                <?= jeton_unique_champ('ajout_activite') ?>
                <input type="hidden" name="saison_id" value="<?= $saison_affichee_id ?>">
                <input type="hidden" name="formulaire" value="activite">
                <?php afficher_champs_activite([], $saison_affichee); ?>
                <?php if ($confirmation_demandee) : ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmer_similaire" name="confirmer_similaire" value="1">
                        <label class="form-check-label" for="confirmer_similaire">Créer quand même : ce n'est pas la même activité que celle qui existe déjà.</label>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit">Créer l'activité</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
