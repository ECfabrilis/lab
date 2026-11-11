<?php

declare(strict_types=1);

// Page de connexion.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

if (utilisateur_courant() !== null) {
    rediriger('index.php');
}

if (est_post()) {
    csrf_verifier();
    $resultat = tenter_connexion(post('mail'), post_brut('mdp'));
    if ($resultat === 'ok') {
        rediriger('index.php');
    }
    flash('erreur', $resultat === 'bloque'
        ? 'Trop d\'essais : ce compte est bloqué pendant ' . AUTH_BLOCAGE_MINUTES . ' minutes.'
        : 'Adresse e-mail ou mot de passe incorrect.');
    ancien_memoriser();
    rediriger('connexion.php');
}

$titre_page = 'Connexion';
$page_publique = true;
require DOSSIER_PRIVE . '/includes/header.php';
?>
<form class="form-signin mx-auto" method="post" action="<?= e(chemin_site('connexion.php')) ?>">
    <?= csrf_champ() ?>
    <h1 class="h4 mb-3 fw-normal">Connexion</h1>
    <div class="form-floating">
        <input class="form-control" type="email" id="mail" name="mail" placeholder="nom@exemple.org" value="<?= e(ancien('mail')) ?>" autocomplete="username" required autofocus>
        <label for="mail">Adresse e-mail</label>
    </div>
    <div class="form-floating">
        <input class="form-control" type="password" id="mdp" name="mdp" placeholder="Mot de passe" autocomplete="current-password" required>
        <label for="mdp">Mot de passe</label>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Se connecter</button>
    <p class="mt-3 mb-0"><a href="<?= e(chemin_site('mdp_oublie.php')) ?>">Mot de passe oublié ?</a></p>
</form>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
