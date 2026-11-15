<?php

declare(strict_types=1);

/**
 * Pied de page commun : ferme la page ouverte par header.php et affiche la
 * version de l'application (lien vers les notes de version).
 * Les valeurs saisies gardées pour le réaffichage sont oubliées ici.
 *
 * Une page peut définir avant l'inclusion :
 *   $scripts_page (list<string>, facultatif) scripts de public/js/ propres à
 *                 cette page, chargés après les scripts communs.
 */

ancien_oublier();
$scripts_page = isset($scripts_page) && is_array($scripts_page) ? $scripts_page : [];
// Pages sans connexion : pas de pied de page, seulement la version courte tout en bas.
$pied_public = isset($page_publique) && $page_publique === true;
?>
</main>
<?php if ($pied_public) : ?>
<p class="version-publique small text-body-secondary mb-0">Version <?= e(version_courte()) ?></p>
<?php else : ?>
<footer class="pied-page border-top mt-auto py-3">
    <div class="container d-flex flex-wrap justify-content-between gap-2 small text-body-secondary">
        <span><?= e(parametre('nom_association', 'Association')) ?></span>
        <a class="link-secondary" href="<?= e(chemin_site('notes_version.php')) ?>">Version <?= e(APP_VERSION) ?></a>
    </div>
</footer>
<?php endif; ?>
<script src="<?= e(chemin_site('js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(chemin_site('js/bddv3.js')) ?>"></script>
<script src="<?= e(chemin_site('js/adresse.js')) ?>"></script>
<script src="<?= e(chemin_site('js/inscription.js')) ?>"></script>
<script src="<?= e(chemin_site('js/recherche.js')) ?>"></script>
<script src="<?= e(chemin_site('js/activites.js')) ?>"></script>
<script src="<?= e(chemin_site('js/paiements.js')) ?>"></script>
<?php foreach ($scripts_page as $script_page) : ?>
<script src="<?= e(chemin_site('js/' . basename(texte($script_page)))) ?>"></script>
<?php endforeach; ?>
</body>
</html>
