<?php

declare(strict_types=1);

// Paramètres (administrateurs seulement) : les réglages de l'application,
// présentés en cards. La liste des cards est dans includes/fonctions_navigation.php.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$titre_page = 'Paramètres';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paramètres</h1>
<?php afficher_cards(cards_parametres()); ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
