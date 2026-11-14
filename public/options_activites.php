<?php

declare(strict_types=1);

// Options des activités (administrateurs seulement) : les deux listes de choix
// proposées dans le formulaire d'une activité, hors secteurs :
//   - les types d'activité (atelier, sortie, évènement, séjour…) ;
//   - les récurrences (ponctuel, régulier, vacances…).
// Chaque choix s'ajoute et se modifie sur sa propre page (ajout_type_activite.php,
// type_activite.php, ajout_recurrence.php, recurrence.php), comme un secteur.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$listes_affichees = [];
foreach (['types_activite', 'recurrences'] as $liste_choix) {
    $listes_affichees[] = ['definition' => choix_definition($liste_choix), 'lignes' => choix_liste($liste_choix)];
}

$titre_page = 'Options des activités';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Options des activités</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php foreach ($listes_affichees as $liste_affichee) : ?>
                <a class="btn btn-primary" href="<?= e(chemin_site($liste_affichee['definition']['page_ajout'])) ?>">Ajouter <?= e($liste_affichee['definition']['un']) ?></a>
            <?php endforeach; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Les deux listes sont communes à toutes les saisons.</li>
            <li>Un choix inactif n'est plus proposé pour les nouvelles activités ; celles qui l'utilisent le gardent.</li>
            <li>Un choix ne se supprime que si aucune activité ne l'utilise.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <?php foreach ($listes_affichees as $liste_affichee) : ?>
            <?php $definition = $liste_affichee['definition']; ?>
            <h2 class="h5"><?= e($definition['titre']) ?></h2>
            <?php if ($liste_affichee['lignes'] === []) : ?>
                <div class="alert alert-info" role="alert">La liste est vide. Il faut au moins un choix pour créer une activité.</div>
            <?php else : ?>
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Nom</th>
                                <th scope="col">Activités</th>
                                <th scope="col">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste_affichee['lignes'] as $ligne_choix) : ?>
                                <tr>
                                    <td><a href="<?= e(chemin_site($definition['page_fiche'] . '?id=' . entier($ligne_choix['id']))) ?>"><?= e($ligne_choix['nom']) ?></a></td>
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
        <?php endforeach; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
