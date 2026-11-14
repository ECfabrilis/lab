<?php

declare(strict_types=1);

// Liste des secteurs d'activité (administrateurs seulement).
// Les types d'activité et les récurrences, gérés de la même façon, sont sur options_activites.php.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$liste_choix = 'secteurs';
$definition = choix_definition($liste_choix);
$lignes_choix = choix_liste($liste_choix);

$titre_page = $definition['titre'];
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e($definition['titre']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-primary" href="<?= e(chemin_site($definition['page_ajout'])) ?>">Ajouter <?= e($definition['un']) ?></a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>La liste est commune à toutes les saisons.</li>
            <li>Un choix inactif n'est plus proposé pour les nouvelles activités ; celles qui l'utilisent le gardent.</li>
            <li>Un choix ne se supprime que si aucune activité ne l'utilise.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <?php if ($lignes_choix === []) : ?>
            <div class="alert alert-info" role="alert">La liste est vide. Il faut au moins un choix pour créer une activité.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Activités</th>
                            <th scope="col">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes_choix as $ligne_choix) : ?>
                            <tr>
                                <td><a href="<?= e(chemin_site($definition['page_fiche'] . '?id=' . entier($ligne_choix['id']))) ?>"><?= e($ligne_choix['nom']) ?></a>
                                    <?php if (texte($ligne_choix['description']) !== '') : ?>
                                        <span class="d-block small text-body-secondary"><?= e($ligne_choix['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= entier($ligne_choix['nb_activites']) ?></td>
                                <td>
                                    <?php if (entier($ligne_choix['est_actif']) === 1) : ?>
                                        <span class="badge text-bg-success">Actif</span>
                                    <?php else : ?>
                                        <span class="badge text-bg-secondary">Inactif</span>
                                    <?php endif; ?>
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
