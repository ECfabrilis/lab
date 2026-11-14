<?php

declare(strict_types=1);

// Tarifs de la saison affichée.
// Consultation et ajout : rôle standard. Modification et suppression : administrateurs.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_affichee = saison_courante();
$liste_tarifs = tarifs_liste(entier($saison_affichee['id'] ?? 0));
$est_admin = role_au_moins('admin');

$titre_page = 'Tarifs';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Tarifs — saison <?= e($saison_affichee['nom'] ?? '') ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-primary" href="<?= e(chemin_site('ajout_tarif.php')) ?>">Ajouter un tarif</a>
        </div>
        <ul class="text-body-secondary small mt-3 mb-0 ps-3">
            <li>Un tarif appartient à une saison ; plusieurs activités peuvent le partager.</li>
            <li>Les tarifs « Adhésion » sont réservés aux cotisations : une activité choisit un tarif « Forfait » ou « Autre ».</li>
            <li>Un « Forfait » ne se paie qu'une fois, comme une cotisation : il compte une fois par personne (une fois par famille au montant familial, qui couvre alors tous ses membres), quel que soit le nombre d'activités qui l'utilisent. Un tarif « Autre » est dû à chaque inscription.</li>
            <li>Le montant familial, facultatif, vaut pour toute la famille (exemple : 5 € par enfant, ou 10 € pour tous les enfants).</li>
            <li>Le montant est copié sur chaque inscription au moment où elle est faite : modifier un tarif ne change pas les inscriptions déjà enregistrées.</li>
            <li>La modification et la suppression sont réservées aux administrateurs.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <?php if ($liste_tarifs === []) : ?>
            <div class="alert alert-info" role="alert">Aucun tarif pour cette saison. Sans tarif, une activité est gratuite.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Type</th>
                            <th scope="col" class="text-end">Individuel</th>
                            <th scope="col" class="text-end">Familial</th>
                            <th scope="col" class="text-end">Activités</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liste_tarifs as $ligne_tarif) : ?>
                            <tr>
                                <td>
                                    <?php if ($est_admin) : ?>
                                        <a href="<?= e(chemin_site('tarif.php?id=' . entier($ligne_tarif['id']))) ?>"><?= e($ligne_tarif['nom']) ?></a>
                                    <?php else : ?>
                                        <?= e($ligne_tarif['nom']) ?>
                                    <?php endif; ?>
                                    <?php if (texte($ligne_tarif['description']) !== '') : ?>
                                        <span class="d-block small text-body-secondary"><?= e($ligne_tarif['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(libelles_types_tarif()[texte($ligne_tarif['type'])] ?? '') ?></td>
                                <td class="text-end"><?= e(montant_fr($ligne_tarif['montant_individuel'])) ?></td>
                                <td class="text-end"><?= $ligne_tarif['montant_familial'] === null ? '—' : e(montant_fr($ligne_tarif['montant_familial'])) ?></td>
                                <td class="text-end"><?= entier($ligne_tarif['nb_activites']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
