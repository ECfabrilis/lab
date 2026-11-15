<?php

declare(strict_types=1);

// Notes de version : ce qui a changé dans l'application, version par version.
// Le contenu est dans includes/fonctions_version.php.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$versions = notes_version();

$titre_page = 'Notes de version';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Notes de version</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <p class="text-body-secondary">Version installée : <strong><?= e(APP_VERSION) ?></strong></p>
        <div class="d-grid">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('index.php')) ?>">Retour au tableau de bord</a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
        <?php foreach ($versions as $note) : ?>
            <h2 class="h5">Version <?= e($note['version']) ?> <span class="text-body-secondary fw-normal small">— <?= e(date_fr($note['date'])) ?></span></h2>
            <ul class="mb-4">
                <?php foreach ($note['changements'] as $changement) : ?>
                    <li><?= e($changement) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
