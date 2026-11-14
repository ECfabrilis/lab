<?php

declare(strict_types=1);

// Options des paiements (administrateurs seulement) : les deux listes de choix
// proposées pour les tarifs et les paiements :
//   - les types de tarif (« Adhésion » et « Forfait », figés, et les types libres) ;
//   - les modes de paiement (espèces, chèque, carte bancaire…).
// Chaque choix s'ajoute et se modifie sur sa propre page (ajout_type_tarif.php,
// type_tarif.php, ajout_mode_paiement.php, mode_paiement.php), comme un type
// d'activité (options_activites.php).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$listes_affichees = [];
foreach (['types_tarif', 'modes_paiement'] as $liste_choix) {
    $listes_affichees[] = ['definition' => choix_definition($liste_choix), 'lignes' => choix_liste($liste_choix)];
}

$titre_page = 'Options des paiements';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Options des paiements</h1>
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
            <li>« Adhésion » et « Forfait » sont réservés : l'adhésion sert aux cotisations, et un forfait ne se paie qu'une fois. Ils ne se modifient pas.</li>
            <li>Un tarif d'un autre type est dû à chaque inscription.</li>
            <li>Un choix inactif n'est plus proposé pour un nouveau tarif ou un nouveau paiement ; ceux qui l'utilisent le gardent.</li>
            <li>Un choix ne se supprime que si aucun tarif ou aucun paiement ne l'utilise.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <?php foreach ($listes_affichees as $liste_affichee) : ?>
            <?php $definition = $liste_affichee['definition']; ?>
            <h2 class="h5"><?= e($definition['titre']) ?></h2>
            <?php if ($liste_affichee['lignes'] === []) : ?>
                <div class="alert alert-info" role="alert">La liste est vide. Ajoutez au moins un choix.</div>
            <?php else : ?>
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Nom</th>
                                <th scope="col"><?= e($definition['usage_titre']) ?></th>
                                <th scope="col">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liste_affichee['lignes'] as $ligne_choix) : ?>
                                <tr>
                                    <td>
                                        <?php if (choix_est_fige($ligne_choix)) : ?>
                                            <?= e($ligne_choix['nom']) ?>
                                        <?php else : ?>
                                            <a href="<?= e(chemin_site($definition['page_fiche'] . '?id=' . entier($ligne_choix['id']))) ?>"><?= e($ligne_choix['nom']) ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= entier($ligne_choix['nb_activites']) ?></td>
                                    <td>
                                        <?php if (choix_est_fige($ligne_choix)) : ?>
                                            <span class="badge text-bg-light border">Réservé</span>
                                        <?php elseif (entier($ligne_choix['est_actif']) === 1) : ?>
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
