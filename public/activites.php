<?php

declare(strict_types=1);

// Liste des activités de la saison affichée, avec leurs places : d'abord celles
// en cours ou à venir, puis les activités passées, repliées et classées par mois.
// Les filtres (secteur, type, nom d'activité cherché, avec partenaire) sont dans l'adresse de
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
// Interrupteur « Avec partenaire » : seulement les activités qui ont un partenaire.
$filtre_partenaire = texte($_GET['partenaire'] ?? '') === '1';

$liste_activites = activites_liste($saison_affichee_id, $filtre_secteur, $filtre_type, $recherche_nom, $filtre_partenaire);
$nb_activites = count($liste_activites);
// Les activités passées sont affichées à part, classées par mois de début.
$activites_separees = activites_separer_passees($liste_activites);
$activites_en_cours = $activites_separees['en_cours'];
$activites_passees = $activites_separees['passees'];
$nb_en_cours = count($activites_en_cours);
$nb_passees = $activites_separees['nb_passees'];
$peut_creer = role_au_moins('standard');

$titre_page = 'Activités';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Activités</h1>
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
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="partenaire" name="partenaire" value="1" <?= $filtre_partenaire ? 'checked' : '' ?> data-envoi-auto>
                <label class="form-check-label" for="partenaire">Avec partenaire</label>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
                <?php if ($filtre_secteur > 0 || $filtre_type > 0 || $recherche_nom !== '' || $filtre_partenaire) : ?>
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
            <?= $filtre_partenaire ? '— avec partenaire' : '' ?>
        </p>
        <?php if ($saison_affichee === null) : ?>
            <div class="alert alert-info" role="alert">Aucune saison n'existe encore.</div>
        <?php elseif ($liste_activites === []) : ?>
            <div class="alert alert-info" role="alert">Aucune activité ne correspond pour cette saison.</div>
        <?php else : ?>
            <h2 class="h5">Activités en cours et à venir <span class="badge text-bg-secondary"><?= $nb_en_cours ?></span></h2>
            <?php if ($activites_en_cours === []) : ?>
                <p class="text-body-secondary">Aucune activité en cours ou à venir.</p>
            <?php else : ?>
                <?php afficher_tableau_activites($activites_en_cours); ?>
            <?php endif; ?>
            <?php if ($nb_passees === 0) : ?>
                <h2 class="h5 mt-4">Activités passées <span class="badge text-bg-secondary">0</span></h2>
                <p class="text-body-secondary">Aucune activité passée.</p>
            <?php else : ?>
                <details class="activites-passees mt-4" <?= $nb_en_cours === 0 ? 'open' : '' ?>>
                    <summary class="h5">Activités passées <span class="badge text-bg-secondary"><?= $nb_passees ?></span></summary>
                    <?php foreach ($activites_passees as $mois_passe => $activites_du_mois) : ?>
                        <h3 class="h6 mt-3"><?= e(mois_fr($mois_passe . '-01')) ?></h3>
                        <?php afficher_tableau_activites($activites_du_mois); ?>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
