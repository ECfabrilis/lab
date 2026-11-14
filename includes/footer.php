<?php

declare(strict_types=1);

/**
 * Pied de page commun : ferme la page ouverte par header.php.
 * Les valeurs saisies gardées pour le réaffichage sont oubliées ici.
 */

ancien_oublier();
?>
</main>
<script src="<?= e(chemin_site('js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(chemin_site('js/bddv3.js')) ?>"></script>
<script src="<?= e(chemin_site('js/adresse.js')) ?>"></script>
<script src="<?= e(chemin_site('js/inscription.js')) ?>"></script>
<script src="<?= e(chemin_site('js/recherche.js')) ?>"></script>
<script src="<?= e(chemin_site('js/activites.js')) ?>"></script>
<script src="<?= e(chemin_site('js/paiements.js')) ?>"></script>
</body>
</html>
