<?php

declare(strict_types=1);

// Liste et recherche des adhérents.
// La recherche est envoyée par formulaire (POST) et gardée en session :
// aucun nom n'apparaît dans l'adresse de la page.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

if (est_post()) {
    csrf_verifier();
    if (post('action') === 'effacer') {
        unset($_SESSION['recherche_adherents']);
    } else {
        $_SESSION['recherche_adherents'] = [
            'texte'     => mb_substr(post('recherche'), 0, 100),
            'perimetre' => post('perimetre') === 'tous' ? 'tous' : 'saison',
        ];
    }
    rediriger('adherents.php');
}

$criteres = $_SESSION['recherche_adherents'] ?? [];
$recherche = is_array($criteres) ? texte($criteres['texte'] ?? '') : '';
$perimetre = is_array($criteres) && ($criteres['perimetre'] ?? '') === 'tous' ? 'tous' : 'saison';

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);
$numero_page = max(1, entier($_GET['page'] ?? 1));

$resultat = adherents_rechercher($recherche, $saison_affichee_id, $perimetre === 'saison', $numero_page);
$nb_pages = max(1, (int) ceil($resultat['total'] / ADHERENTS_PAR_PAGE));

$titre_page = 'Adhérents';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Adhérents</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <?php if (role_au_moins('standard')) : ?>
            <div class="d-grid gap-2 mb-3">
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_adherent.php')) ?>">Nouvel adhérent</a>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e(chemin_site('adherents.php')) ?>">
            <?= csrf_champ() ?>
            <div class="mb-3">
                <label class="form-label" for="recherche">Rechercher</label>
                <input class="form-control" type="search" id="recherche" name="recherche" maxlength="100" value="<?= e($recherche) ?>" placeholder="Nom, prénom, courriel, téléphone">
            </div>
            <div class="mb-3">
                <label class="form-label" for="perimetre">Parmi</label>
                <select class="form-select" id="perimetre" name="perimetre">
                    <option value="saison" <?= $perimetre === 'saison' ? 'selected' : '' ?>>Les adhérents de la saison <?= e($saison_affichee['nom'] ?? '') ?></option>
                    <option value="tous" <?= $perimetre === 'tous' ? 'selected' : '' ?>>Tous les adhérents, toutes saisons</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Rechercher</button>
                <?php if ($recherche !== '' || $perimetre !== 'saison') : ?>
                    <button class="btn btn-outline-secondary" type="submit" name="action" value="effacer">Effacer</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-md-8 order-md-1">
        <p class="text-body-secondary">
            <?= $resultat['total'] ?> adhérent<?= $resultat['total'] > 1 ? 's' : '' ?>
            <?= $perimetre === 'saison' ? 'pour la saison ' . e($saison_affichee['nom'] ?? '') : 'toutes saisons confondues' ?>
            <?= $recherche !== '' ? '— recherche : « ' . e($recherche) . ' »' : '' ?>
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
                                    <a href="<?= e(chemin_site('adherent.php?id=' . entier($ligne['id']))) ?>"><?= e($ligne['nom']) ?> <?= e($ligne['prenom']) ?></a>
                                    <?php if ($perimetre === 'tous' && entier($ligne['dans_saison']) !== 1) : ?>
                                        <span class="badge text-bg-secondary">Hors saison</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= age_ans(texte($ligne['date_naissance'])) ?> ans</td>
                                <td><a href="<?= e(chemin_site('famille.php?id=' . entier($ligne['famille_id']))) ?>"><?= e($ligne['famille_nom']) ?></a></td>
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
                    <ul class="pagination">
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
