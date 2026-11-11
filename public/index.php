<?php

declare(strict_types=1);

// Accueil après connexion.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$moi = utilisateur_courant() ?? [];
$saison_affichee = saison_courante();

$titre_page = 'Accueil';
require DOSSIER_PRIVE . '/includes/header.php';

$rappel_sauvegarde = role_au_moins('admin') && sauvegarde_en_retard();
?>
<h1 class="h3 mb-4">Bonjour <?= e($moi['prenom'] ?? '') ?></h1>

<?php if ($rappel_sauvegarde) : ?>
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
        <span>La dernière sauvegarde de la base date de plus de <?= SAUVEGARDE_RAPPEL_JOURS ?> jours (ou n'a jamais été faite).</span>
        <a class="btn btn-sm btn-warning" href="<?= e(chemin_site('sauvegarde.php')) ?>">Faire une sauvegarde</a>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h2 class="h5">Saison affichée</h2>
            <?php if ($saison_affichee !== null) : ?>
                <p class="mb-1 fs-5"><?= e($saison_affichee['nom']) ?>
                    <span class="badge text-bg-secondary"><?= e(libelle_etat_saison(etat_saison(texte($saison_affichee['date_debut']), texte($saison_affichee['date_fin'])))) ?></span>
                </p>
                <p class="mb-0 text-body-secondary">Du <?= e(date_fr($saison_affichee['date_debut'])) ?> au <?= e(date_fr($saison_affichee['date_fin'])) ?></p>
            <?php else : ?>
                <p class="mb-0">Aucune saison n'est créée.</p>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <h2 class="h5">Adhérents</h2>
            <p class="text-body-secondary">Familles, adhérents et dossiers de la saison. Les activités, les inscriptions et les paiements arriveront aux étapes suivantes.</p>
            <a class="btn btn-outline-primary" href="<?= e(chemin_site('adherents.php')) ?>">Voir les adhérents</a>
        </div>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
