<?php

declare(strict_types=1);

// Choix du mot de passe à partir d'un lien reçu par courriel
// (invitation d'un nouvel utilisateur ou mot de passe oublié).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

// Le lien contient un jeton : il ne doit pas partir vers un autre site.
header('Referrer-Policy: no-referrer');

$jeton_lien = est_post() ? post_brut('jeton') : texte($_GET['jeton'] ?? '');
$compte = utilisateur_par_jeton($jeton_lien);

if ($compte === null) {
    flash('erreur', 'Ce lien n\'est plus valable. Demandez-en un nouveau.');
    rediriger('mdp_oublie.php');
}

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger('choix_mdp.php?jeton=' . $jeton_lien);
    }
    $erreur = erreur_mdp(post_brut('mdp'), post_brut('mdp_confirmation'));
    if ($erreur !== null) {
        flash('erreur', $erreur);
        rediriger('choix_mdp.php?jeton=' . $jeton_lien);
    }
    definir_mdp(entier($compte['id']), post_brut('mdp'));
    journaliser('mdp_choisi', 'utilisateurs', entier($compte['id']), 'Mot de passe choisi à partir d\'un lien reçu par courriel');
    deconnecter();
    flash('succes', 'Votre mot de passe est enregistré. Vous pouvez vous connecter.');
    rediriger('connexion.php');
}

$titre_page = 'Choix du mot de passe';
$page_publique = true;
require DOSSIER_PRIVE . '/includes/header.php';
?>
<form class="form-mdpmodif mx-auto" method="post" action="<?= e(chemin_site('choix_mdp.php')) ?>">
    <?= csrf_champ() ?>
    <input type="hidden" name="jeton" value="<?= e($jeton_lien) ?>">
    <h1 class="h4 mb-3 fw-normal">Choix du mot de passe</h1>
    <p class="text-body-secondary">Compte : <?= e($compte['mail']) ?><br><?= MDP_LONGUEUR_MIN ?> caractères au minimum.</p>
    <div class="form-floating">
        <input class="form-control" type="password" id="mdpmodif" name="mdp" placeholder="Mot de passe" minlength="<?= MDP_LONGUEUR_MIN ?>" autocomplete="new-password" required autofocus>
        <label for="mdpmodif">Nouveau mot de passe</label>
    </div>
    <div class="form-floating mdpverif">
        <input class="form-control" type="password" id="mdp_confirmation" name="mdp_confirmation" placeholder="Mot de passe" minlength="<?= MDP_LONGUEUR_MIN ?>" autocomplete="new-password" required>
        <label for="mdp_confirmation">Une seconde fois</label>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Enregistrer</button>
</form>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
