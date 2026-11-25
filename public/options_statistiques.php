<?php

declare(strict_types=1);

// Options des statistiques (administrateurs seulement) :
//   - les tranches d'âge, définies par leurs âges de début ;
//   - la date de référence de l'âge (31 décembre, premier ou dernier jour de la saison) ;
//   - les groupes de lieu de résidence, définis par des codes postaux ;
//   - les couleurs des graphiques : une palette prédéfinie ou personnalisée.
// Ces réglages sont communs à toutes les saisons : les modifier change aussi
// les chiffres affichés pour les saisons passées.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

if (est_post()) {
    csrf_verifier();
    $action = post('action');

    // --- Tranches d'âge --------------------------------------------------------
    if ($action === 'tranches') {
        $debuts = stats_tranches_lire(post('tranches'));
        if ($debuts === null) {
            flash('erreur', 'Indiquez les âges de début de tranche en chiffres, séparés par des virgules (de 1 à 120, ' . STATS_TRANCHES_MAX . ' âges au maximum). Exemple : 7, 12, 18, 26, 60.');
            ancien_memoriser();
            rediriger('options_statistiques.php');
        }
        $anciens = stats_tranches_debuts();
        if ($anciens === $debuts) {
            flash('info', 'Aucune modification à enregistrer.');
        } else {
            parametre_enregistrer('stats_tranches_age', implode(',', $debuts), entier(utilisateur_courant()['id'] ?? 0));
            journaliser(
                'modification',
                'parametres',
                null,
                'Tranches d\'âge des statistiques modifiées',
                ['stats_tranches_age' => implode(',', $anciens)],
                ['stats_tranches_age' => implode(',', $debuts)]
            );
            flash('succes', 'Tranches d\'âge enregistrées : ' . implode(', ', array_column(stats_tranches_age($debuts), 'libelle')) . '.');
        }
        rediriger('options_statistiques.php');
    }

    // --- Date de référence de l'âge ---------------------------------------------
    if ($action === 'age_reference') {
        $references = stats_age_references();
        $choisie = post('age_reference');
        if (!array_key_exists($choisie, $references)) {
            flash('erreur', 'Cette date de référence n\'existe pas.');
            rediriger('options_statistiques.php');
        }
        $ancienne = stats_age_reference();
        if ($ancienne === $choisie) {
            flash('info', 'Aucune modification à enregistrer.');
        } else {
            parametre_enregistrer('stats_age_reference', $choisie, entier(utilisateur_courant()['id'] ?? 0));
            journaliser(
                'modification',
                'parametres',
                null,
                'Date de référence de l\'âge des statistiques modifiée',
                ['stats_age_reference' => $ancienne],
                ['stats_age_reference' => $choisie]
            );
            flash('succes', 'Date de référence de l\'âge enregistrée : ' . mb_strtolower(mb_substr($references[$choisie], 0, 1)) . mb_substr($references[$choisie], 1) . '.');
        }
        rediriger('options_statistiques.php');
    }

    // --- Couleurs des graphiques -------------------------------------------------
    if ($action === 'palette') {
        $choix = post('palette');
        $moi_id = entier(utilisateur_courant()['id'] ?? 0);
        $avant = ['stats_palette' => stats_palette_choisie(), 'stats_palette_perso' => parametre('stats_palette_perso')];
        if ($choix === STATS_PALETTE_PERSO) {
            $saisies = [];
            for ($rang = 1; $rang <= STATS_PALETTE_TAILLE; $rang++) {
                $saisies[] = post('couleur_' . $rang);
            }
            $couleurs = stats_palette_lire($saisies);
            if ($couleurs === null) {
                flash('erreur', 'La palette personnalisée demande ' . STATS_PALETTE_TAILLE . ' couleurs valides.');
                ancien_memoriser();
                rediriger('options_statistiques.php');
            }
            parametre_enregistrer('stats_palette_perso', implode(',', $couleurs), $moi_id);
        } elseif (!array_key_exists($choix, stats_palettes())) {
            flash('erreur', 'Choisissez une palette dans la liste.');
            rediriger('options_statistiques.php');
        }
        parametre_enregistrer('stats_palette', $choix, $moi_id);
        $apres = ['stats_palette' => stats_palette_choisie(), 'stats_palette_perso' => parametre('stats_palette_perso')];
        if ($apres === $avant) {
            flash('info', 'Aucune modification à enregistrer.');
        } else {
            journaliser('modification', 'parametres', null, 'Couleurs des graphiques des statistiques modifiées', $avant, $apres);
            flash('succes', 'Les couleurs des graphiques sont enregistrées.');
        }
        rediriger('options_statistiques.php');
    }

    // --- Groupes de lieu de résidence : ajout ou modification -------------------
    if ($action === 'ajouter' || $action === 'modifier') {
        $groupe = $action === 'modifier' ? groupe_residence_par_id(entier(post('groupe_id'))) : null;
        if ($action === 'modifier' && $groupe === null) {
            flash('erreur', 'Ce groupe n\'existe pas.');
            rediriger('options_statistiques.php');
        }
        if ($action === 'ajouter' && !jeton_unique_consommer('groupe_residence')) {
            flash('avertissement', 'Ce formulaire a déjà été envoyé.');
            rediriger('options_statistiques.php');
        }
        $nom = post('nom');
        $ordre = post('ordre');
        $erreurs = erreurs_groupe_residence($nom, $ordre, post('codes'), $groupe['id'] ?? 0);
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger('options_statistiques.php');
        }
        $codes = residence_codes_lire(post('codes')) ?? [];
        if ($groupe === null) {
            groupe_residence_creer($nom, (int) $ordre, $codes);
            flash('succes', 'Le groupe « ' . $nom . ' » est créé.');
        } elseif (groupe_residence_modifier($groupe, $nom, (int) $ordre, $codes)) {
            flash('succes', 'Le groupe « ' . $nom . ' » est modifié.');
        } else {
            flash('info', 'Aucune modification à enregistrer.');
        }
        rediriger('options_statistiques.php');
    }

    // --- Groupes de lieu de résidence : suppression -----------------------------
    if ($action === 'supprimer') {
        $groupe = groupe_residence_par_id(entier(post('groupe_id')));
        if ($groupe === null) {
            flash('erreur', 'Ce groupe n\'existe pas.');
        } else {
            groupe_residence_supprimer($groupe);
            flash('succes', 'Le groupe « ' . $groupe['nom'] . ' » est supprimé : ses adresses sont maintenant comptées dans un autre groupe ou dans « ' . RESIDENCE_AUTRES . ' ».');
        }
        rediriger('options_statistiques.php');
    }

    rediriger('options_statistiques.php');
}

$tranches_saisie = ancien('action') === 'tranches' ? ancien('tranches') : implode(', ', stats_tranches_debuts());
$groupes = groupes_residence_liste();
$ajout_en_cours = ancien('action') === 'ajouter';
$groupe_en_cours = ancien('action') === 'modifier' ? entier(ancien('groupe_id')) : 0;
$ordre_suivant = $groupes === [] ? 1 : max(array_column($groupes, 'ordre')) + 1;

// Couleurs des graphiques : la palette cochée et les couleurs des 8 sélecteurs
// (la saisie refusée, sinon la palette personnalisée, sinon la palette en vigueur).
$palettes = stats_palettes();
$palette_en_cours = ancien('action') === 'palette';
$palette_cochee = $palette_en_cours ? ancien('palette') : stats_palette_choisie();
$couleurs_perso = stats_palette_perso() ?? stats_palette_couleurs();
if ($palette_en_cours) {
    foreach ($couleurs_perso as $rang => $couleur_perso) {
        $resaisie = ancien('couleur_' . ($rang + 1));
        $couleurs_perso[$rang] = couleur_valide($resaisie) ? $resaisie : $couleur_perso;
    }
}

$titre_page = 'Options des statistiques';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Options des statistiques</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('statistiques.php')) ?>">Voir les statistiques</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Ces réglages valent pour toutes les saisons : les modifier change aussi les chiffres affichés pour les saisons passées.</li>
            <li>L'âge est calculé à la date de référence choisie ici, jamais à la date du jour : les chiffres d'une saison passée ne changent pas d'une année sur l'autre.</li>
            <li>Un code postal n'appartient qu'à un seul groupe. Un début de code (75) réunit tous les codes qui commencent ainsi ; le code le plus précis l'emporte (75017 avant 75).</li>
            <li>Une adresse qui n'est dans aucun groupe est comptée dans « <?= e(RESIDENCE_AUTRES) ?> ».</li>
            <li>Dans les histogrammes, les barres de la saison précédente restent grises, quelle que soit la palette.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <h2 class="h5 mb-3">Tranches d'âge</h2>
        <form class="mb-5" method="post" action="<?= e(chemin_site('options_statistiques.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="tranches">
            <div class="mb-3">
                <label class="form-label" for="tranches">Âges de début de tranche</label>
                <input class="form-control" type="text" id="tranches" name="tranches" maxlength="100" required value="<?= e($tranches_saisie) ?>" aria-describedby="tranchesAide">
                <div class="form-text" id="tranchesAide">
                    Séparés par des virgules. La première tranche commence à 0 an, la dernière n'a pas de limite.
                    Tranches actuelles : <?= e(implode(', ', array_column(stats_tranches_age(), 'libelle'))) ?>.
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer les tranches</button>
        </form>

        <h2 class="h5 mb-3">Date de référence de l'âge</h2>
        <form class="mb-5" method="post" action="<?= e(chemin_site('options_statistiques.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="age_reference">
            <fieldset class="mb-3">
                <legend class="form-label fs-6">L'âge des adhérents est calculé à cette date</legend>
                <?php foreach (stats_age_references() as $valeur_reference => $libelle_reference) : ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="age_<?= e($valeur_reference) ?>" name="age_reference" value="<?= e($valeur_reference) ?>" <?= stats_age_reference() === $valeur_reference ? 'checked' : '' ?>>
                        <label class="form-check-label" for="age_<?= e($valeur_reference) ?>"><?= e($libelle_reference) ?></label>
                    </div>
                <?php endforeach; ?>
                <div class="form-text">Pour une période libre, le premier et le dernier jour sont ceux de la période. Une saison sur l'année civile se termine le 31 décembre : les deux réglages donnent alors le même âge.</div>
            </fieldset>
            <button class="btn btn-primary" type="submit">Enregistrer la date de référence</button>
        </form>

        <h2 class="h5 mb-3">Groupes de lieu de résidence</h2>
        <?php if ($groupes === []) : ?>
            <p class="text-body-secondary">Aucun groupe : toutes les adresses sont comptées dans « <?= e(RESIDENCE_AUTRES) ?> ».</p>
        <?php endif; ?>
        <?php foreach ($groupes as $groupe) : ?>
            <?php
            $groupe_id = $groupe['id'];
            $resaisi = $groupe_en_cours === $groupe_id;
            ?>
            <form class="border rounded p-3 mb-3" method="post" action="<?= e(chemin_site('options_statistiques.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="groupe_id" value="<?= $groupe_id ?>">
                <div class="row g-3">
                    <div class="col-sm-9">
                        <label class="form-label" for="nom_<?= $groupe_id ?>">Nom du groupe</label>
                        <input class="form-control" type="text" id="nom_<?= $groupe_id ?>" name="nom" maxlength="100" required value="<?= e($resaisi ? ancien('nom') : $groupe['nom']) ?>">
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label" for="ordre_<?= $groupe_id ?>">Rang</label>
                        <input class="form-control" type="number" id="ordre_<?= $groupe_id ?>" name="ordre" min="0" max="999" required value="<?= e($resaisi ? ancien('ordre') : $groupe['ordre']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="codes_<?= $groupe_id ?>">Codes postaux</label>
                        <input class="form-control" type="text" id="codes_<?= $groupe_id ?>" name="codes" maxlength="700" required value="<?= e($resaisi ? ancien('codes') : implode(', ', $groupe['codes'])) ?>">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" type="submit" name="action" value="modifier">Enregistrer</button>
                        <button class="btn btn-outline-danger btn-sm" type="submit" name="action" value="supprimer">Supprimer</button>
                    </div>
                </div>
            </form>
        <?php endforeach; ?>

        <h3 class="h6 mt-4 mb-3">Ajouter un groupe</h3>
        <form class="border rounded p-3" method="post" action="<?= e(chemin_site('options_statistiques.php')) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ('groupe_residence') ?>
            <input type="hidden" name="action" value="ajouter">
            <div class="row g-3">
                <div class="col-sm-9">
                    <label class="form-label" for="nom_nouveau">Nom du groupe</label>
                    <input class="form-control" type="text" id="nom_nouveau" name="nom" maxlength="100" required value="<?= e($ajout_en_cours ? ancien('nom') : '') ?>">
                </div>
                <div class="col-sm-3">
                    <label class="form-label" for="ordre_nouveau">Rang</label>
                    <input class="form-control" type="number" id="ordre_nouveau" name="ordre" min="0" max="999" required value="<?= e($ajout_en_cours ? ancien('ordre') : $ordre_suivant) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="codes_nouveau">Codes postaux</label>
                    <input class="form-control" type="text" id="codes_nouveau" name="codes" maxlength="700" required value="<?= e($ajout_en_cours ? ancien('codes') : '') ?>" placeholder="75010, 75011, 92" aria-describedby="codesAide">
                    <div class="form-text" id="codesAide">Séparés par des virgules : un code complet (75017) ou un début de code (92 pour tout le département).</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Ajouter le groupe</button>
                </div>
            </div>
        </form>

        <h2 class="h5 mt-5 mb-3">Couleurs des graphiques</h2>
        <form method="post" action="<?= e(chemin_site('options_statistiques.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="palette">
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Palette</legend>
                <?php foreach ($palettes as $cle_palette => $palette) : ?>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="radio" id="palette_<?= e($cle_palette) ?>" name="palette" value="<?= e($cle_palette) ?>" <?= $palette_cochee === $cle_palette ? 'checked' : '' ?>>
                        <label class="form-check-label" for="palette_<?= e($cle_palette) ?>">
                            <?php stats_afficher_pastilles($palette['couleurs']); ?>
                            <span class="ms-1"><?= e($palette['nom']) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="palette_perso" name="palette" value="<?= e(STATS_PALETTE_PERSO) ?>" <?= $palette_cochee === STATS_PALETTE_PERSO ? 'checked' : '' ?>>
                    <label class="form-check-label" for="palette_perso">Palette personnalisée (les <?= STATS_PALETTE_TAILLE ?> couleurs ci-dessous)</label>
                </div>
            </fieldset>
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Couleurs de la palette personnalisée</legend>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($couleurs_perso as $rang => $couleur_perso) : ?>
                        <input class="form-control form-control-color" type="color" name="couleur_<?= $rang + 1 ?>" value="<?= e($couleur_perso) ?>" title="Couleur <?= $rang + 1 ?>" aria-label="Couleur <?= $rang + 1 ?>">
                    <?php endforeach; ?>
                </div>
                <div class="form-text">
                    Elles ne servent que si « Palette personnalisée » est cochée. La première couleur est celle des histogrammes ;
                    les anneaux prennent les couleurs dans l'ordre.
                </div>
            </fieldset>
            <button class="btn btn-primary" type="submit">Enregistrer les couleurs</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
