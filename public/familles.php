<?php

declare(strict_types=1);

// Liste et recherche des familles.
// Comme pour les adhérents, la recherche et les filtres sont envoyés par
// formulaire (POST) et gardés en session : aucun nom n'apparaît dans l'adresse
// de la page. Par défaut, seules les familles inscrites pour la saison sont listées.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

if (est_post()) {
    csrf_verifier();
    if (post('action') === 'effacer') {
        unset($_SESSION['recherche_familles']);
    } else {
        $_SESSION['recherche_familles'] = [
            'texte'         => mb_substr(post('recherche'), 0, 100),
            'non_inscrites' => post('non_inscrites') === '1',
            'quartier'      => mb_substr(post('quartier'), 0, 150),
            'qp'            => post('qp') === '1',
            'qpv'           => post('qpv') === '1',
            'enfants'       => entier(post('enfants')),
        ];
    }
    rediriger('familles.php');
}

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);
$numero_page = max(1, entier($_GET['page'] ?? 1));
$quartiers_saison = familles_quartiers($saison_affichee_id);

// Critères gardés en session.
$criteres = familles_criteres($saison_affichee_id);
$recherche = $criteres['texte'];
$filtres_liste = $criteres['filtres'];
$filtre_non_inscrites = $filtres_liste['non_inscrites'];
$filtre_quartier = $filtres_liste['quartier'];
$filtre_qp = $filtres_liste['qp'];
$filtre_qpv = $filtres_liste['qpv'];
$filtre_enfants = $filtres_liste['enfants'];

$resultat = familles_rechercher($recherche, $saison_affichee_id, $numero_page, $filtres_liste);
$nb_pages = max(1, (int) ceil($resultat['total'] / FAMILLES_PAR_PAGE));
if ($numero_page > $nb_pages) {
    // Page au-delà de la dernière (les filtres ont changé) : on affiche la dernière.
    $numero_page = $nb_pages;
    $resultat = familles_rechercher($recherche, $saison_affichee_id, $numero_page, $filtres_liste);
}

// Choix du filtre sur les enfants, et libellés des deux tranches d'âge.
$choix_enfants = [
    FAMILLES_AGE_PETITS => 'Avec un enfant de ' . FAMILLES_AGE_PETITS . ' ans ou moins',
    FAMILLES_AGE_MOYENS => 'Avec un enfant de ' . FAMILLES_AGE_MOYENS . ' ans ou moins',
];
$libelle_petits = FAMILLES_AGE_PETITS . ' ans ou moins';
$libelle_moyens = (FAMILLES_AGE_PETITS + 1) . ' à ' . FAMILLES_AGE_MOYENS . ' ans';

// Rappel des filtres en cours, au-dessus de la liste.
$rappel_filtres = [];
if ($recherche !== '') {
    $rappel_filtres[] = 'recherche : « ' . $recherche . ' »';
}
if ($filtre_quartier !== '') {
    $rappel_filtres[] = 'quartier ' . $filtre_quartier;
}
if ($filtre_qp) {
    $rappel_filtres[] = 'quartier prioritaire (QP)';
}
if ($filtre_qpv) {
    $rappel_filtres[] = 'QPV';
}
if (isset($choix_enfants[$filtre_enfants])) {
    $rappel_filtres[] = mb_strtolower($choix_enfants[$filtre_enfants]);
}
$criteres_actifs = $rappel_filtres !== [] || $filtre_non_inscrites;
// Quartier, QP et QPV viennent de l'adresse de la saison : seules les familles inscrites y répondent.
$filtre_adresse = $filtre_quartier !== '' || $filtre_qp || $filtre_qpv;

$titre_page = 'Familles';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Familles</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <?php if (role_au_moins('standard')) : ?>
            <div class="d-grid gap-2 mb-3">
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_adherent.php')) ?>">Nouvelle inscription</a>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(chemin_site('familles.php')) ?>">
            <?= csrf_champ() ?>
            <div class="mb-3">
                <label class="form-label" for="recherche">Rechercher une famille</label>
                <input class="form-control" type="search" id="recherche" name="recherche" maxlength="100" value="<?= e($recherche) ?>" placeholder="Nom de la famille ou d'un membre">
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="non_inscrites" name="non_inscrites" value="1" <?= $filtre_non_inscrites ? 'checked' : '' ?>>
                <label class="form-check-label" for="non_inscrites">Afficher aussi les familles non inscrites pour la saison <?= e($saison_affichee['nom'] ?? '') ?></label>
            </div>
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Filtrer</legend>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="qp" name="qp" value="1" <?= $filtre_qp ? 'checked' : '' ?>>
                    <label class="form-check-label" for="qp">En quartier prioritaire (QP)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="qpv" name="qpv" value="1" <?= $filtre_qpv ? 'checked' : '' ?>>
                    <label class="form-check-label" for="qpv">En quartier de la politique de la ville (QPV)</label>
                </div>
            </fieldset>
            <div class="mb-3">
                <label class="form-label" for="quartier">Quartier</label>
                <select class="form-select" id="quartier" name="quartier" aria-describedby="aide_quartier">
                    <option value="">Tous les quartiers</option>
                    <?php foreach ($quartiers_saison as $nom_quartier) : ?>
                        <option value="<?= e($nom_quartier) ?>" <?= $filtre_quartier === $nom_quartier ? 'selected' : '' ?>><?= e($nom_quartier) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="aide_quartier">Quartier, QP et QPV : au moins un membre de la famille y habite, d'après son adresse de la saison <?= e($saison_affichee['nom'] ?? '') ?>. Une famille non inscrite pour la saison n'y répond pas.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="enfants">Enfants</label>
                <select class="form-select" id="enfants" name="enfants" aria-describedby="aide_enfants">
                    <option value="0">Toutes les familles</option>
                    <?php foreach ($choix_enfants as $age_choix => $libelle_choix) : ?>
                        <option value="<?= $age_choix ?>" <?= $filtre_enfants === $age_choix ? 'selected' : '' ?>><?= e($libelle_choix) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="aide_enfants">Âge à la date du jour ; tous les membres de la famille comptent, inscrits pour la saison ou non.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
                <?php if ($criteres_actifs) : ?>
                    <button class="btn btn-outline-secondary" type="submit" name="action" value="effacer">Effacer</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-8 order-md-1">
        <p class="text-body-secondary">
            <?= $resultat['total'] ?> famille<?= $resultat['total'] > 1 ? 's' : '' ?>
            <?= $filtre_non_inscrites && !$filtre_adresse ? 'inscrite' . ($resultat['total'] > 1 ? 's' : '') . ' ou non pour la saison ' . e($saison_affichee['nom'] ?? '') : 'inscrite' . ($resultat['total'] > 1 ? 's' : '') . ' pour la saison ' . e($saison_affichee['nom'] ?? '') ?>
            <?= $rappel_filtres !== [] ? '— ' . e(implode(', ', $rappel_filtres)) : '' ?>
        </p>
        <?php if ($resultat['lignes'] === []) : ?>
            <div class="alert alert-info" role="alert">
                Aucune famille ne correspond.
                <?php if (!$filtre_non_inscrites && !$filtre_adresse) : ?>
                    Une famille venue une autre saison se retrouve en affichant aussi les familles non inscrites.
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Famille</th>
                            <th scope="col">Membres</th>
                            <?php // Nombres d'enfants : masqués sur les petits écrans (avant md). ?>
                            <th scope="col" class="d-none d-md-table-cell text-center">Enfants</th>
                            <th scope="col" class="d-none d-md-table-cell text-center"><?= e($libelle_petits) ?></th>
                            <th scope="col" class="d-none d-md-table-cell text-center"><?= e($libelle_moyens) ?></th>
                            <th scope="col">Saison <?= e($saison_affichee['nom'] ?? '') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultat['lignes'] as $ligne) : ?>
                            <tr>
                                <td><a href="<?= e(chemin_site('famille.php?id=' . entier($ligne['id']))) ?>"><?= e($ligne['nom']) ?></a></td>
                                <td class="small"><?= e($ligne['membres']) ?></td>
                                <?php foreach (['nb_enfants', 'nb_enfants_petits', 'nb_enfants_moyens'] as $colonne_enfants) : ?>
                                    <td class="d-none d-md-table-cell text-center"><?= entier($ligne[$colonne_enfants]) > 0 ? entier($ligne[$colonne_enfants]) : '—' ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <?php if (entier($ligne['nb_inscrits']) > 0) : ?>
                                        <span class="badge text-bg-success"><?= entier($ligne['nb_inscrits']) ?> inscrit<?= entier($ligne['nb_inscrits']) > 1 ? 's' : '' ?></span>
                                    <?php else : ?>
                                        <span class="badge text-bg-secondary">Non inscrite</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($nb_pages > 1) : ?>
                <nav aria-label="Pages de la liste">
                    <ul class="pagination flex-wrap">
                        <?php for ($i = 1; $i <= $nb_pages; $i++) : ?>
                            <li class="page-item <?= $i === $numero_page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(chemin_site('familles.php?page=' . $i)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
