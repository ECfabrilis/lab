<?php

declare(strict_types=1);

// Tableau de bord : les choix de l'application, présentés en cards selon le rôle.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$moi = utilisateur_courant() ?? [];

$titre_page = 'Tableau de bord';
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

<?php afficher_cards(cards_tableau_de_bord()); ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
