<?php

declare(strict_types=1);

// Liste et recherche des familles.
// Comme pour les adhérents, la recherche est envoyée par formulaire (POST) et
// gardée en session : aucun nom n'apparaît dans l'adresse de la page.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

if (est_post()) {
    csrf_verifier();
    if (post('action') === 'effacer') {
        unset($_SESSION['recherche_familles']);
    } else {
        $_SESSION['recherche_familles'] = mb_substr(post('recherche'), 0, 100);
    }
    rediriger('familles.php');
}

$recherche = texte($_SESSION['recherche_familles'] ?? '');
$saison_affichee = saison_courante();
$numero_page = max(1, entier($_GET['page'] ?? 1));

$resultat = familles_rechercher($recherche, entier($saison_affichee['id'] ?? 0), $numero_page);
$nb_pages = max(1, (int) ceil($resultat['total'] / FAMILLES_PAR_PAGE));

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
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
                <?php if ($recherche !== '') : ?>
                    <button class="btn btn-outline-secondary" type="submit" name="action" value="effacer">Effacer</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-8 order-md-1">
        <p class="text-body-secondary">
            <?= $resultat['total'] ?> famille<?= $resultat['total'] > 1 ? 's' : '' ?>
            <?= $recherche !== '' ? '— recherche : « ' . e($recherche) . ' »' : '' ?>
        </p>
        <?php if ($resultat['lignes'] === []) : ?>
            <div class="alert alert-info" role="alert">Aucune famille ne correspond.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Famille</th>
                            <th scope="col">Membres</th>
                            <th scope="col">Saison <?= e($saison_affichee['nom'] ?? '') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultat['lignes'] as $ligne) : ?>
                            <tr>
                                <td><a href="<?= e(chemin_site('famille.php?id=' . entier($ligne['id']))) ?>"><?= e($ligne['nom']) ?></a></td>
                                <td class="small"><?= e($ligne['membres']) ?></td>
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
