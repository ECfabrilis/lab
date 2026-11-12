<?php

declare(strict_types=1);

// Liste des activités de la saison affichée, avec leurs places.
// Les filtres (secteur, type, nom d'activité cherché) sont dans l'adresse de
// la page : aucune donnée personnelle n'y figure.
// Consultation : tout utilisateur connecté. Création : rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);

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

$recherche_nom = mb_substr(trim(mb_scrub(texte($_GET['q'] ?? ''), 'UTF-8')), 0, 100);

$liste_activites = activites_liste($saison_affichee_id, $filtre_secteur, $filtre_type, $recherche_nom);
$nb_activites = count($liste_activites);
$peut_creer = role_au_moins('standard');

$titre_page = 'Activités';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Activités — saison <?= e($saison_affichee['nom'] ?? '') ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <?php if ($peut_creer) : ?>
            <div class="d-grid gap-2 mb-3">
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_activite.php')) ?>">Nouvelle activité</a>
            </div>
        <?php endif; ?>
        <form method="get" action="<?= e(chemin_site('activites.php')) ?>">
            <div class="mb-3">
                <label class="form-label" for="q">Rechercher</label>
                <input class="form-control" type="search" id="q" name="q" maxlength="100" value="<?= e($recherche_nom) ?>" placeholder="Nom de l'activité">
            </div>
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
                <label class="form-label" for="type">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="0">Tous les types</option>
                    <?php foreach ($types_filtre as $choix_id => $choix_nom) : ?>
                        <option value="<?= $choix_id ?>" <?= $filtre_type === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
                <?php if ($filtre_secteur > 0 || $filtre_type > 0 || $recherche_nom !== '') : ?>
                    <a class="btn btn-outline-secondary" href="<?= e(chemin_site('activites.php')) ?>">Effacer</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-8 order-md-1">
        <p class="text-body-secondary">
            <?= $nb_activites ?> activité<?= $nb_activites > 1 ? 's' : '' ?>
            <?= $recherche_nom !== '' ? '— recherche : « ' . e($recherche_nom) . ' »' : '' ?>
            <?= $filtre_secteur > 0 ? '— secteur ' . e($secteurs_filtre[$filtre_secteur]) : '' ?>
            <?= $filtre_type > 0 ? '— type ' . e($types_filtre[$filtre_type]) : '' ?>
        </p>
        <?php if ($saison_affichee === null) : ?>
            <div class="alert alert-info" role="alert">Aucune saison n'existe encore.</div>
        <?php elseif ($liste_activites === []) : ?>
            <div class="alert alert-info" role="alert">Aucune activité ne correspond pour cette saison.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Activité</th>
                            <th scope="col">Dates</th>
                            <th scope="col">Tarif</th>
                            <th scope="col">Places</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liste_activites as $ligne_activite) : ?>
                            <?php
                            $restantes = places_restantes($ligne_activite);
                            $en_attente = entier($ligne_activite['places_attente']);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= e(chemin_site('activite.php?id=' . entier($ligne_activite['id']))) ?>"><?= e($ligne_activite['nom']) ?></a>
                                    <span class="d-block small text-body-secondary"><?= e($ligne_activite['secteur_nom']) ?> — <?= e($ligne_activite['type_nom']) ?> — réf. <?= e($ligne_activite['referent']) ?></span>
                                </td>
                                <td class="small"><?= e(activite_dates($ligne_activite)) ?></td>
                                <td><?= e(activite_prix($ligne_activite)) ?></td>
                                <td>
                                    <?= entier($ligne_activite['places_prises']) ?><?= $restantes === null ? '' : ' / ' . entier($ligne_activite['nb_places']) ?>
                                    <?php if ($restantes === 0) : ?><span class="badge text-bg-danger">Complet</span><?php endif; ?>
                                    <?php if ($en_attente > 0) : ?><span class="badge text-bg-warning"><?= $en_attente ?> en attente</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
