<?php

declare(strict_types=1);

// Mon compte : informations du compte connecté et changement de mot de passe.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();
$moi_id = entier($_SESSION['utilisateur_id'] ?? 0);
$moi = utilisateur_courant() ?? [];

if (est_post()) {
    csrf_verifier();
    $hash_actuel = texte(db_valeur('SELECT mdp FROM utilisateurs WHERE id = ?', [$moi_id]));
    if (!password_verify(post_brut('mdp_actuel'), $hash_actuel)) {
        flash('erreur', 'Le mot de passe actuel est incorrect.');
        rediriger('mon_compte.php');
    }
    $erreur = erreur_mdp(post_brut('mdp'), post_brut('mdp_confirmation'));
    if ($erreur !== null) {
        flash('erreur', $erreur);
        rediriger('mon_compte.php');
    }
    definir_mdp($moi_id, post_brut('mdp'));
    session_regenerate_id(true);
    journaliser('modification', 'utilisateurs', $moi_id, 'Changement de son propre mot de passe');
    flash('succes', 'Votre mot de passe est modifié.');
    rediriger('mon_compte.php');
}

$titre_page = 'Mon compte';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Mon compte</h1>
<div class="row g-4">
    <div class="col-md-6">
        <h2 class="h5">Mes informations</h2>
        <dl class="row mb-0">
            <dt class="col-sm-5">Prénom</dt>
            <dd class="col-sm-7"><?= e($moi['prenom'] ?? '') ?></dd>
            <dt class="col-sm-5">Adresse e-mail</dt>
            <dd class="col-sm-7"><?= e($moi['mail'] ?? '') ?></dd>
            <dt class="col-sm-5">Rôle</dt>
            <dd class="col-sm-7"><?= e(libelles_roles()[texte($moi['role'] ?? '')] ?? '') ?></dd>
            <dt class="col-sm-5">Dernière connexion</dt>
            <dd class="col-sm-7"><?= e(date_heure_fr($moi['derniere_connexion'] ?? '')) ?></dd>
        </dl>
        <p class="text-body-secondary mt-3 mb-0">Le prénom, l'adresse e-mail et le rôle sont modifiés par un administrateur.</p>
    </div>
    <div class="col-md-6">
        <h2 class="h5">Changer mon mot de passe</h2>
        <form method="post" action="<?= e(chemin_site('mon_compte.php')) ?>">
            <?= csrf_champ() ?>
            <div class="mb-3">
                <label class="form-label" for="mdp_actuel">Mot de passe actuel</label>
                <input class="form-control" type="password" id="mdp_actuel" name="mdp_actuel" autocomplete="current-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mdp">Nouveau mot de passe (<?= MDP_LONGUEUR_MIN ?> caractères au minimum)</label>
                <input class="form-control" type="password" id="mdp" name="mdp" minlength="<?= MDP_LONGUEUR_MIN ?>" autocomplete="new-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mdp_confirmation">Nouveau mot de passe, une seconde fois</label>
                <input class="form-control" type="password" id="mdp_confirmation" name="mdp_confirmation" minlength="<?= MDP_LONGUEUR_MIN ?>" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
