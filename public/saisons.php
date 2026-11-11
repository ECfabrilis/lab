<?php

declare(strict_types=1);

// Liste des saisons (administrateurs seulement).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$liste_saisons = saisons_liste();

$titre_page = 'Saisons';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Saisons</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-primary" href="<?= e(chemin_site('ajout_saison.php')) ?>">Ajouter une saison</a>
        </div>
        <ul class="text-body-secondary small mt-3 mb-0 ps-3">
            <li>Deux saisons ne peuvent pas se chevaucher.</li>
            <li>Une seule saison à venir à la fois.</li>
            <li>Une saison dure 2 ans au maximum.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">Début</th>
                        <th scope="col">Fin</th>
                        <th scope="col">État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($liste_saisons as $ligne_saison) : ?>
                        <?php $etat_ligne = etat_saison(texte($ligne_saison['date_debut']), texte($ligne_saison['date_fin'])); ?>
                        <tr>
                            <td><a href="<?= e(chemin_site('saison.php?id=' . entier($ligne_saison['id']))) ?>"><?= e($ligne_saison['nom']) ?></a></td>
                            <td><?= e(date_fr($ligne_saison['date_debut'])) ?></td>
                            <td><?= e(date_fr($ligne_saison['date_fin'])) ?></td>
                            <td>
                                <span class="badge <?= $etat_ligne === 'en_cours' ? 'text-bg-success' : ($etat_ligne === 'a_venir' ? 'text-bg-info' : 'text-bg-secondary') ?>"><?= e(libelle_etat_saison($etat_ligne)) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
