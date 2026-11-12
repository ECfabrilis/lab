<?php

declare(strict_types=1);

// Liste et recherche des adhérents.
// La recherche et les filtres sont envoyés par formulaire (POST) et gardés en
// session : aucun nom n'apparaît dans l'adresse de la page.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

if (est_post()) {
    csrf_verifier();
    $criteres_gardes = $_SESSION['recherche_adherents'] ?? [];
    $criteres_gardes = is_array($criteres_gardes) ? $criteres_gardes : [];
    $action_liste = post('action');
    if ($action_liste === 'effacer') {
        unset($_SESSION['recherche_adherents']);
    } elseif ($action_liste === 'lettre') {
        // La rangée de lettres ne change que la lettre : le reste est conservé.
        $criteres_gardes['lettre'] = preg_match('/^[A-Z]$/', post('lettre')) === 1 ? post('lettre') : '';
        $_SESSION['recherche_adherents'] = $criteres_gardes;
    } else {
        $par_page_saisi = entier(post('par_page'));
        $_SESSION['recherche_adherents'] = [
            'texte'     => mb_substr(post('recherche'), 0, 100),
            'perimetre' => post('perimetre') === 'tous' ? 'tous' : 'saison',
            'lettre'    => texte($criteres_gardes['lettre'] ?? ''),
            'benevoles' => post('benevoles') === '1',
            'quartier'  => mb_substr(post('quartier'), 0, 150),
            'qp'        => post('qp') === '1',
            'qpv'       => post('qpv') === '1',
            'par_page'  => in_array($par_page_saisi, ADHERENTS_PAR_PAGE_CHOIX, true) ? $par_page_saisi : ADHERENTS_PAR_PAGE,
        ];
    }
    rediriger('adherents.php');
}

$criteres = $_SESSION['recherche_adherents'] ?? [];
$criteres = is_array($criteres) ? $criteres : [];
$recherche = texte($criteres['texte'] ?? '');
$perimetre = ($criteres['perimetre'] ?? '') === 'tous' ? 'tous' : 'saison';
$filtre_lettre = preg_match('/^[A-Z]$/', texte($criteres['lettre'] ?? '')) === 1 ? texte($criteres['lettre']) : '';
$filtre_benevoles = ($criteres['benevoles'] ?? false) === true;
$filtre_quartier = texte($criteres['quartier'] ?? '');
$filtre_qp = ($criteres['qp'] ?? false) === true;
$filtre_qpv = ($criteres['qpv'] ?? false) === true;
$par_page = in_array($criteres['par_page'] ?? 0, ADHERENTS_PAR_PAGE_CHOIX, true) ? entier($criteres['par_page']) : ADHERENTS_PAR_PAGE;

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);
$numero_page = max(1, entier($_GET['page'] ?? 1));

$quartiers_saison = adherents_quartiers($saison_affichee_id);
if ($filtre_quartier !== '' && !in_array($filtre_quartier, $quartiers_saison, true)) {
    // Quartier d'une autre saison (la saison affichée a changé) : le filtre tombe.
    $filtre_quartier = '';
}

$filtres_liste = ['lettre' => $filtre_lettre, 'benevoles' => $filtre_benevoles, 'quartier' => $filtre_quartier, 'qp' => $filtre_qp, 'qpv' => $filtre_qpv];
$resultat = adherents_rechercher($recherche, $saison_affichee_id, $perimetre === 'saison', $numero_page, $filtres_liste, $par_page);
$nb_pages = max(1, (int) ceil($resultat['total'] / $par_page));
if ($numero_page > $nb_pages) {
    // Page au-delà de la dernière (le nombre par page a changé) : on affiche la dernière.
    $numero_page = $nb_pages;
    $resultat = adherents_rechercher($recherche, $saison_affichee_id, $perimetre === 'saison', $numero_page, $filtres_liste, $par_page);
}

// Rappel des filtres en cours, au-dessus de la liste.
$rappel_filtres = [];
if ($recherche !== '') {
    $rappel_filtres[] = 'recherche : « ' . $recherche . ' »';
}
if ($filtre_lettre !== '') {
    $rappel_filtres[] = 'nom commençant par ' . $filtre_lettre;
}
if ($filtre_benevoles) {
    $rappel_filtres[] = 'bénévoles';
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
$criteres_actifs = $rappel_filtres !== [] || $perimetre !== 'saison' || $par_page !== ADHERENTS_PAR_PAGE;

$titre_page = 'Adhérents';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Adhérents</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <?php if (role_au_moins('standard')) : ?>
            <div class="d-grid gap-2 mb-3">
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_adherent.php')) ?>">Nouvelle inscription</a>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(chemin_site('adherents.php')) ?>">
            <?= csrf_champ() ?>
            <div class="mb-3">
                <label class="form-label" for="recherche">Rechercher</label>
                <input class="form-control" type="search" id="recherche" name="recherche" maxlength="100" autocomplete="off"
                       data-recherche-url="<?= e(chemin_site('api_adherents.php')) ?>" data-recherche-fiche="<?= e(chemin_site('adherent.php?id=')) ?>"
                       value="<?= e($recherche) ?>" placeholder="Nom, prénom, courriel, téléphone" aria-describedby="aide_recherche">
                <div class="form-text" id="aide_recherche">Dès 3 lettres, n'importe où dans le nom ou le prénom, des propositions s'affichent.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="perimetre">Parmi</label>
                <select class="form-select" id="perimetre" name="perimetre">
                    <option value="saison" <?= $perimetre === 'saison' ? 'selected' : '' ?>>Les adhérents de la saison <?= e($saison_affichee['nom'] ?? '') ?></option>
                    <option value="tous" <?= $perimetre === 'tous' ? 'selected' : '' ?>>Tous les adhérents, toutes saisons</option>
                </select>
            </div>
            <fieldset class="mb-3">
                <legend class="form-label fs-6">Filtrer</legend>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="benevoles" name="benevoles" value="1" <?= $filtre_benevoles ? 'checked' : '' ?>>
                    <label class="form-check-label" for="benevoles">Les bénévoles uniquement</label>
                </div>
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
                <div class="form-text" id="aide_quartier">Quartier, QP et QPV sont ceux de l'adresse de la saison <?= e($saison_affichee['nom'] ?? '') ?>.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="par_page">Nombre par page</label>
                <select class="form-select" id="par_page" name="par_page">
                    <?php foreach (ADHERENTS_PAR_PAGE_CHOIX as $choix_par_page) : ?>
                        <option value="<?= $choix_par_page ?>" <?= $par_page === $choix_par_page ? 'selected' : '' ?>><?= $choix_par_page ?></option>
                    <?php endforeach; ?>
                </select>
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
        <form method="post" action="<?= e(chemin_site('adherents.php')) ?>" class="mb-3" aria-label="Filtrer par première lettre du nom">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="lettre">
            <div class="d-flex flex-wrap gap-1">
                <button class="btn btn-sm <?= $filtre_lettre === '' ? 'btn-primary' : 'btn-outline-secondary' ?>" type="submit" name="lettre" value="">Tous</button>
                <?php foreach (range('A', 'Z') as $lettre_alphabet) : ?>
                    <button class="btn btn-sm <?= $filtre_lettre === $lettre_alphabet ? 'btn-primary' : 'btn-outline-secondary' ?>" type="submit" name="lettre" value="<?= $lettre_alphabet ?>"
                            <?= $filtre_lettre === $lettre_alphabet ? 'aria-pressed="true"' : '' ?>><?= $lettre_alphabet ?></button>
                <?php endforeach; ?>
            </div>
        </form>
        <p class="text-body-secondary">
            <?= $resultat['total'] ?> adhérent<?= $resultat['total'] > 1 ? 's' : '' ?>
            <?= $perimetre === 'saison' ? 'pour la saison ' . e($saison_affichee['nom'] ?? '') : 'toutes saisons confondues' ?>
            <?= $rappel_filtres !== [] ? '— ' . e(implode(', ', $rappel_filtres)) : '' ?>
        </p>
        <?php if ($resultat['lignes'] === []) : ?>
            <div class="alert alert-info" role="alert">
                Aucun adhérent ne correspond.
                <?php if ($perimetre === 'saison') : ?>
                    Une personne déjà venue une autre saison se retrouve en cherchant parmi « Tous les adhérents ».
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Âge</th>
                            <th scope="col">Famille</th>
                            <th scope="col">Ville</th>
                            <th scope="col">Contact</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultat['lignes'] as $ligne) : ?>
                            <tr>
                                <td>
                                    <a href="<?= e(chemin_site('adherent.php?id=' . entier($ligne['id']))) ?>"><?= e(trim(texte($ligne['nom']) . ' ' . texte($ligne['prenom']))) ?></a>
                                    <?php if ($perimetre === 'tous' && entier($ligne['dans_saison']) !== 1) : ?>
                                        <span class="badge text-bg-secondary">Hors saison</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (entier($ligne['est_structure']) === 1) : ?>
                                    <td>—</td>
                                    <td><span class="badge text-bg-secondary">Structure</span></td>
                                <?php else : ?>
                                    <td><?= age_ans(texte($ligne['date_naissance'])) ?> ans</td>
                                    <td><a href="<?= e(chemin_site('famille.php?id=' . entier($ligne['famille_id']))) ?>"><?= e($ligne['famille_nom']) ?></a></td>
                                <?php endif; ?>
                                <td><?= e(trim(texte($ligne['code_postal']) . ' ' . texte($ligne['ville']))) ?></td>
                                <td class="small">
                                    <?= e($ligne['tel']) ?>
                                    <?php if (texte($ligne['tel']) !== '' && texte($ligne['mail']) !== '') : ?><br><?php endif; ?>
                                    <?= e($ligne['mail']) ?>
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
                                <a class="page-link" href="<?= e(chemin_site('adherents.php?page=' . $i)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
