<?php

declare(strict_types=1);

// Statistiques (cahier des charges, section 10) : adhérents, activités,
// scolarité, pour une saison ou une période libre, avec la comparaison à la
// saison précédente (ou à la même période un an plus tôt). Les activités se
// filtrent par secteur, type et récurrence. Un bouton affiche ou retire les
// chiffres de comparaison (N-1).
// Les filtres sont dans l'adresse de la page : aucune donnée personnelle n'y figure.
// Consultation : tout utilisateur connecté (aucun montant n'est affiché).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

// --- Filtres -------------------------------------------------------------------
$vues = ['adherents' => 'Adhérents', 'activites' => 'Activités', 'scolarite' => 'Scolarité'];
$vue = texte($_GET['vue'] ?? '');
$vue = array_key_exists($vue, $vues) ? $vue : 'adherents';

$saisons_filtre = [];
foreach (saisons_liste() as $ligne_saison) {
    $saisons_filtre[entier($ligne_saison['id'])] = $ligne_saison;
}
$filtre_saison = entier($_GET['saison'] ?? 0);
if (!array_key_exists($filtre_saison, $saisons_filtre)) {
    $filtre_saison = entier(saison_courante()['id'] ?? 0);
}

$secteurs_filtre = [];
foreach (choix_liste('secteurs') as $ligne_choix) {
    $secteurs_filtre[entier($ligne_choix['id'])] = texte($ligne_choix['nom']);
}
$types_filtre = [];
foreach (choix_liste('types_activite') as $ligne_choix) {
    $types_filtre[entier($ligne_choix['id'])] = texte($ligne_choix['nom']);
}
$filtre_secteur = entier($_GET['secteur'] ?? 0);
$filtre_secteur = array_key_exists($filtre_secteur, $secteurs_filtre) ? $filtre_secteur : 0;
$filtre_type = entier($_GET['type'] ?? 0);
$filtre_type = array_key_exists($filtre_type, $types_filtre) ? $filtre_type : 0;
$recurrences_filtre = [];
foreach (choix_liste('recurrences') as $ligne_choix) {
    $recurrences_filtre[entier($ligne_choix['id'])] = texte($ligne_choix['nom']);
}
$filtre_recurrence = entier($_GET['recurrence'] ?? 0);
$filtre_recurrence = array_key_exists($filtre_recurrence, $recurrences_filtre) ? $filtre_recurrence : 0;

// Évolution : la comparaison avec la saison précédente (N-1) s'affiche, sauf si elle a été retirée.
$afficher_evolution = texte($_GET['evolution'] ?? '1') !== '0';

// Période libre : elle remplace la saison quand ses deux dates sont valables.
$filtre_debut = texte($_GET['debut'] ?? '');
$filtre_fin = texte($_GET['fin'] ?? '');
$erreur_periode = '';
$periode_valable = false;
if ($filtre_debut !== '' || $filtre_fin !== '') {
    if (!date_valide($filtre_debut) || !date_valide($filtre_fin)) {
        $erreur_periode = 'Pour une période libre, indiquez la date de début et la date de fin. Les chiffres affichés sont ceux de la saison.';
    } elseif ($filtre_debut > $filtre_fin) {
        $erreur_periode = 'La date de fin de la période doit être après la date de début. Les chiffres affichés sont ceux de la saison.';
    } else {
        $periode_valable = true;
    }
    if (!$periode_valable) {
        $filtre_debut = date_valide($filtre_debut) ? $filtre_debut : '';
        $filtre_fin = date_valide($filtre_fin) ? $filtre_fin : '';
    }
}

// --- Périmètre et calculs --------------------------------------------------------
$perimetre = null;
if ($periode_valable) {
    $perimetre = stats_perimetre_periode($filtre_debut, $filtre_fin, $filtre_secteur, $filtre_type, $filtre_recurrence);
} elseif (isset($saisons_filtre[$filtre_saison])) {
    $perimetre = stats_perimetre_saison($saisons_filtre[$filtre_saison], $filtre_secteur, $filtre_type, $filtre_recurrence);
}
$perimetre_precedent = $perimetre === null || !$afficher_evolution ? null : stats_perimetre_precedent($perimetre);
$nom_serie = $perimetre === null ? '' : ($perimetre['mode'] === 'saison' ? mb_substr($perimetre['libelle'], 7) : 'Période');
$nom_precedent = '';
if ($perimetre_precedent !== null) {
    $nom_precedent = $perimetre_precedent['mode'] === 'saison' ? mb_substr($perimetre_precedent['libelle'], 7) : 'Un an plus tôt';
}

$chiffres = null;
$chiffres_precedents = null;
$activites = null;
$activites_precedentes = null;
if ($perimetre !== null && $vue === 'activites') {
    $activites = stats_activites_calculer($perimetre);
    $activites_precedentes = $perimetre_precedent === null ? null : stats_activites_calculer($perimetre_precedent);
} elseif ($perimetre !== null) {
    $chiffres = stats_adherents_calculer(stats_adherents_lignes($perimetre), $perimetre['date_age']);
    $chiffres_precedents = $perimetre_precedent === null
        ? null
        : stats_adherents_calculer(stats_adherents_lignes($perimetre_precedent), $perimetre_precedent['date_age']);
}

// Adresse d'une vue, avec les filtres en cours.
$filtres_adresse = array_filter([
    'saison'  => $periode_valable ? 0 : $filtre_saison,
    'debut'   => $periode_valable ? $filtre_debut : '',
    'fin'     => $periode_valable ? $filtre_fin : '',
    'secteur' => $filtre_secteur,
    'type'    => $filtre_type,
    'recurrence' => $filtre_recurrence,
], static fn (int|string $valeur): bool => $valeur !== 0 && $valeur !== '');
// Le choix d'afficher ou non l'évolution suit l'utilisateur d'un onglet à l'autre, et survit à « Effacer ».
$choix_evolution = $afficher_evolution ? [] : ['evolution' => '0'];
$adresse_vue = static fn (string $nom_vue): string => chemin_site('statistiques.php?' . http_build_query(['vue' => $nom_vue] + $filtres_adresse + $choix_evolution));

$public_filtre = $filtre_secteur > 0 || $filtre_type > 0 || $filtre_recurrence > 0;
$mot_adherents = $public_filtre ? 'Participants' : 'Adhérents';

$titre_page = 'Statistiques';
$scripts_page = ['chart.umd.min.js', 'statistiques.js'];
require DOSSIER_PRIVE . '/includes/header.php';
?>
<div class="page-statistiques" data-palette="<?= e(implode(',', stats_palette_couleurs())) ?>">
<h1 class="h3 mb-4">Statistiques<?= $perimetre === null ? '' : ' — ' . e($perimetre['libelle']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <form method="get" action="<?= e(chemin_site('statistiques.php')) ?>">
            <input type="hidden" name="vue" value="<?= e($vue) ?>">
            <div class="mb-3">
                <label class="form-label" for="saison">Saison</label>
                <select class="form-select" id="saison" name="saison">
                    <?php foreach ($saisons_filtre as $choix_id => $choix_saison) : ?>
                        <option value="<?= $choix_id ?>" <?= $filtre_saison === $choix_id ? 'selected' : '' ?>><?= e($choix_saison['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Ou période libre</legend>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="debut">Du</label>
                        <input class="form-control" type="date" id="debut" name="debut" value="<?= e($filtre_debut) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="fin">Au</label>
                        <input class="form-control" type="date" id="fin" name="fin" value="<?= e($filtre_fin) ?>">
                    </div>
                </div>
                <div class="form-text">Si les deux dates sont remplies, la période remplace la saison.</div>
            </fieldset>
            <div class="mb-3">
                <label class="form-label" for="secteur">Secteur</label>
                <select class="form-select" id="secteur" name="secteur">
                    <option value="0">Tous les secteurs</option>
                    <?php foreach ($secteurs_filtre as $choix_id => $choix_nom) : ?>
                        <option value="<?= $choix_id ?>" <?= $filtre_secteur === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="type">Type d'activité</label>
                <select class="form-select" id="type" name="type">
                    <option value="0">Tous les types</option>
                    <?php foreach ($types_filtre as $choix_id => $choix_nom) : ?>
                        <option value="<?= $choix_id ?>" <?= $filtre_type === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="recurrence">Récurrence</label>
                <select class="form-select" id="recurrence" name="recurrence">
                    <option value="0">Toutes les récurrences</option>
                    <?php foreach ($recurrences_filtre as $choix_id => $choix_nom) : ?>
                        <option value="<?= $choix_id ?>" <?= $filtre_recurrence === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <?php // Case décochée : rien n'est envoyé ; le champ caché dit alors « sans évolution ». ?>
                <input type="hidden" name="evolution" value="0">
                <input class="btn-check" type="checkbox" id="evolution" name="evolution" value="1" autocomplete="off" <?= $afficher_evolution ? 'checked' : '' ?> data-envoi-auto>
                <label class="btn btn-outline-primary w-100" for="evolution">Afficher l'évolution (N-1 / N)</label>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit">Afficher</button>
                <?php if ($filtres_adresse !== [] || $erreur_periode !== '') : ?>
                    <a class="btn btn-outline-secondary" href="<?= e(chemin_site('statistiques.php?' . http_build_query(['vue' => $vue] + $choix_evolution))) ?>">Effacer</a>
                <?php endif; ?>
                <button class="btn btn-outline-secondary d-none" type="button" data-imprimer>Imprimer</button>
            </div>
        </form>
        <?php if ($perimetre !== null) : ?>
            <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
                <li>L'âge est calculé au <?= e(date_fr($perimetre['date_age'])) ?>.</li>
                <li>Les structures ne sont pas comptées parmi les personnes.</li>
                <li>Avec un secteur, un type ou une récurrence, les chiffres des adhérents sont ceux du public de ces activités.</li>
                <?php if (role_au_moins('admin')) : ?>
                    <li><a href="<?= e(chemin_site('options_statistiques.php')) ?>">Tranches d'âge et groupes de lieu de résidence</a></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="col-md-8 order-md-1 stats-contenu">
        <?php if ($erreur_periode !== '') : ?>
            <div class="alert alert-warning" role="alert"><?= e($erreur_periode) ?></div>
        <?php endif; ?>
        <ul class="nav nav-tabs mb-4">
            <?php foreach ($vues as $nom_vue => $libelle_vue) : ?>
                <li class="nav-item">
                    <a class="nav-link <?= $vue === $nom_vue ? 'active' : '' ?>" <?= $vue === $nom_vue ? 'aria-current="page"' : '' ?> href="<?= e($adresse_vue($nom_vue)) ?>"><?= e($libelle_vue) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($public_filtre) : ?>
            <p class="text-body-secondary">
                <?= $filtre_secteur > 0 ? 'Secteur : ' . e($secteurs_filtre[$filtre_secteur]) . '. ' : '' ?>
                <?= $filtre_type > 0 ? 'Type : ' . e($types_filtre[$filtre_type]) . '. ' : '' ?>
                <?= $filtre_recurrence > 0 ? 'Récurrence : ' . e($recurrences_filtre[$filtre_recurrence]) . '. ' : '' ?>
            </p>
        <?php endif; ?>

        <?php if ($perimetre === null) : ?>
            <div class="alert alert-info" role="alert">Aucune saison n'existe encore.</div>

        <?php elseif ($chiffres !== null && $vue === 'adherents') : ?>
            <?php // ----- Adhérents ----- ?>
            <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
                <?php
                stats_afficher_chiffre($mot_adherents, (string) $chiffres['total'], $chiffres['total'], $chiffres_precedents['total'] ?? null, $nom_precedent);
                stats_afficher_chiffre('Nouveaux adhérents', (string) $chiffres['nouveaux'], $chiffres['nouveaux'], $chiffres_precedents['nouveaux'] ?? null, $nom_precedent);
                stats_afficher_chiffre('Réadhésions', (string) $chiffres['readhesions'], $chiffres['readhesions'], $chiffres_precedents['readhesions'] ?? null, $nom_precedent);
                stats_afficher_chiffre('Âge moyen', $chiffres['age_moyen'] === null ? '—' : stats_decimal($chiffres['age_moyen']) . ' ans');
                ?>
            </div>
            <p class="text-body-secondary">
                <?= $chiffres['structures'] ?> structure<?= $chiffres['structures'] > 1 ? 's' : '' ?> en plus des personnes.
                Un nouvel adhérent n'a été inscrit pour aucune saison antérieure.
            </p>
            <?php if ($chiffres['total'] === 0) : ?>
                <div class="alert alert-info" role="alert">Aucune personne ne correspond.</div>
            <?php else : ?>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <?php stats_afficher_repartition('Sexe', stats_lignes($chiffres['sexe'], $chiffres_precedents['sexe'] ?? null), $chiffres['total'], 'doughnut', $nom_serie, $nom_precedent); ?>
                    </div>
                    <div class="col-lg-6">
                        <?php stats_afficher_repartition('Nationalité', stats_lignes($chiffres['nationalite'], $chiffres_precedents['nationalite'] ?? null), $chiffres['total'], 'doughnut', $nom_serie, $nom_precedent); ?>
                    </div>
                    <div class="col-12">
                        <?php stats_afficher_repartition('Tranches d\'âge', stats_lignes($chiffres['tranches'], $chiffres_precedents['tranches'] ?? null), $chiffres['total'], 'bar', $nom_serie, $nom_precedent); ?>
                    </div>
                    <div class="col-12">
                        <?php stats_afficher_repartition('Lieu de résidence', stats_lignes($chiffres['residence'], $chiffres_precedents['residence'] ?? null), $chiffres['total'], 'bar', $nom_serie, $nom_precedent); ?>
                    </div>
                    <div class="col-lg-6">
                        <?php stats_afficher_repartition('Quartier', stats_lignes($chiffres['quartier'], $chiffres_precedents['quartier'] ?? null), $chiffres['total'], '', $nom_serie, $nom_precedent); ?>
                    </div>
                    <div class="col-lg-6">
                        <?php
                        stats_afficher_repartition(
                            'Quartiers prioritaires',
                            stats_lignes(
                                ['En quartier prioritaire (QP)' => $chiffres['qp'], 'En quartier de la politique de la ville (QPV)' => $chiffres['qpv']],
                                $chiffres_precedents === null ? null : ['En quartier prioritaire (QP)' => $chiffres_precedents['qp'], 'En quartier de la politique de la ville (QPV)' => $chiffres_precedents['qpv']]
                            ),
                            $chiffres['total'],
                            '',
                            $nom_serie,
                            $nom_precedent
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($chiffres !== null) : ?>
            <?php // ----- Scolarité ----- ?>
            <?php $nb_scolarises = array_sum($chiffres['etablissement']); ?>
            <p class="text-body-secondary">
                <?= $nb_scolarises ?> personne<?= $nb_scolarises > 1 ? 's' : '' ?> avec un établissement scolaire renseigné, sur <?= $chiffres['total'] ?>.
                Les noms sont regroupés sans tenir compte des accents ni des majuscules.
            </p>
            <?php stats_afficher_repartition('Établissement scolaire', stats_lignes($chiffres['etablissement'], $chiffres_precedents['etablissement'] ?? null), $nb_scolarises, '', $nom_serie, $nom_precedent); ?>

        <?php elseif ($activites !== null) : ?>
            <?php // ----- Activités ----- ?>
            <?php
            $total_activites = $activites['total'];
            $total_precedent = $activites_precedentes['total'] ?? null;
            ?>
            <div class="row row-cols-2 row-cols-lg-4 g-3 mb-4">
                <?php
                stats_afficher_chiffre('Activités', (string) $total_activites['activites'], $total_activites['activites'], $total_precedent['activites'] ?? null, $nom_precedent);
                stats_afficher_chiffre('Participants différents', (string) $total_activites['participants'], $total_activites['participants'], $total_precedent['participants'] ?? null, $nom_precedent);
                stats_afficher_chiffre('Participations', (string) $total_activites['participations'], $total_activites['participations'], $total_precedent['participations'] ?? null, $nom_precedent);
                stats_afficher_chiffre('Familles concernées', (string) $total_activites['familles'], $total_activites['familles'], $total_precedent['familles'] ?? null, $nom_precedent);
                ?>
            </div>
            <p class="text-body-secondary">
                <?= $total_activites['adultes'] ?> adulte<?= $total_activites['adultes'] > 1 ? 's' : '' ?>,
                <?= $total_activites['enfants'] ?> enfant<?= $total_activites['enfants'] > 1 ? 's' : '' ?> (moins de <?= AGE_MAJORITE ?> ans),
                <?= $total_activites['structures'] ?> structure<?= $total_activites['structures'] > 1 ? 's' : '' ?> ;
                <?= $total_activites['attente'] ?> place<?= $total_activites['attente'] > 1 ? 's' : '' ?> en liste d'attente.
                Une participation est une place prise par un inscrit.
            </p>
            <?php if ($total_activites['activites'] === 0) : ?>
                <div class="alert alert-info" role="alert">Aucune activité ne correspond.</div>
            <?php else : ?>
                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <?php
                        stats_afficher_repartition(
                            'Participants différents par secteur',
                            stats_lignes(array_map(static fn (array $c): int => $c['participants'], $activites['secteurs']), $activites_precedentes === null ? null : array_map(static fn (array $c): int => $c['participants'], $activites_precedentes['secteurs'])),
                            $total_activites['participants'],
                            'bar',
                            $nom_serie,
                            $nom_precedent
                        );
                        ?>
                    </div>
                    <div class="col-lg-6">
                        <?php
                        stats_afficher_repartition(
                            'Participants différents par type',
                            stats_lignes(array_map(static fn (array $c): int => $c['participants'], $activites['types']), $activites_precedentes === null ? null : array_map(static fn (array $c): int => $c['participants'], $activites_precedentes['types'])),
                            $total_activites['participants'],
                            'bar',
                            $nom_serie,
                            $nom_precedent
                        );
                        ?>
                    </div>
                </div>
                <?php
                $tableaux = [
                    'Par secteur'  => array_map(static fn (string $nom, array $c): array => ['id' => 0, 'nom' => $nom, 'detail' => '', 'chiffres' => $c], array_keys($activites['secteurs']), array_values($activites['secteurs'])),
                    'Par type'     => array_map(static fn (string $nom, array $c): array => ['id' => 0, 'nom' => $nom, 'detail' => '', 'chiffres' => $c], array_keys($activites['types']), array_values($activites['types'])),
                    'Par activité' => array_map(static fn (array $a): array => ['id' => $a['id'], 'nom' => $a['nom'], 'detail' => $a['secteur'] . ' — ' . $a['type'], 'chiffres' => $a['chiffres']], $activites['activites']),
                ];
                ?>
                <?php foreach ($tableaux as $titre_tableau => $lignes_tableau) : ?>
                    <h2 class="h5 mt-4 mb-3"><?= e($titre_tableau) ?></h2>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><span class="visually-hidden"><?= e($titre_tableau) ?></span></th>
                                    <?php if ($titre_tableau !== 'Par activité') : ?>
                                        <th scope="col" class="text-end">Activités</th>
                                    <?php endif; ?>
                                    <th scope="col" class="text-end">Participants</th>
                                    <th scope="col" class="text-end">Participations</th>
                                    <th scope="col" class="text-end">Familles</th>
                                    <th scope="col" class="text-end">Adultes</th>
                                    <th scope="col" class="text-end">Enfants</th>
                                    <th scope="col" class="text-end">Structures</th>
                                    <th scope="col" class="text-end">Attente</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lignes_tableau as $ligne_tableau) : ?>
                                    <tr>
                                        <th scope="row" class="fw-normal">
                                            <?php if ($ligne_tableau['id'] > 0) : ?>
                                                <a href="<?= e(chemin_site('activite.php?id=' . $ligne_tableau['id'])) ?>"><?= e($ligne_tableau['nom']) ?></a>
                                                <span class="d-block small text-body-secondary"><?= e($ligne_tableau['detail']) ?></span>
                                            <?php else : ?>
                                                <?= e($ligne_tableau['nom']) ?>
                                            <?php endif; ?>
                                        </th>
                                        <?php foreach (STATS_ACTIVITES_CHIFFRES as $nom_chiffre) : ?>
                                            <?php if ($nom_chiffre !== 'activites' || $titre_tableau !== 'Par activité') : ?>
                                                <td class="text-end"><?= $ligne_tableau['chiffres'][$nom_chiffre] ?? 0 ?></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
