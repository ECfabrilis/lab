<?php

declare(strict_types=1);

// Paramètres (administrateurs seulement) : les réglages de l'application,
// présentés en cards. La liste des cards est dans includes/fonctions_navigation.php.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

// Aucun quartier pris en compte : les adresses sont enregistrées sans quartier.
$rappel_quartiers = quartiers_aucun_actif();

$titre_page = 'Paramètres';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paramètres</h1>

<?php if ($rappel_quartiers) : ?>
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
        <span>Aucun quartier n'est pris en compte : les adresses sont enregistrées sans quartier.</span>
        <a class="btn btn-sm btn-warning" href="<?= e(chemin_site('quartiers.php')) ?>">Choisir les quartiers</a>
    </div>
<?php endif; ?>
<?php afficher_cards(cards_parametres()); ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
