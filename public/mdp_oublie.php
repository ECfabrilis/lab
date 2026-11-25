<?php

declare(strict_types=1);

// Mot de passe oublié : envoi d'un lien par courriel (valable 1 heure).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

if (est_post()) {
    csrf_verifier();
    $mail_saisi = post('mail');
    // Trop de demandes depuis cette adresse IP : elles sont ignorées, sans le dire.
    if (!ip_bloquee()) {
        ip_noter_echec();
        $compte = db_ligne(
            'SELECT id, prenom, mail, derniere_connexion, date_expiration_token FROM utilisateurs WHERE mail = ? AND est_actif = 1',
            [$mail_saisi]
        );
        if ($compte === null) {
            journaliser('mdp_oublie', 'utilisateurs', null, 'Demande de nouveau mot de passe pour une adresse inconnue ou désactivée');
        } else {
            // Un compte invité qui n'a jamais choisi son mot de passe garde un lien de 7 jours.
            $jamais_connecte = texte($compte['derniere_connexion']) === '';
            $duree_lien = $jamais_connecte ? JETON_DUREE_INVITATION : JETON_DUREE_OUBLI;
            // Un lien vient d'être envoyé à ce compte : pas de second courriel avant quelques minutes.
            $expiration = strtotime(texte($compte['date_expiration_token']));
            $lien_recent = $expiration !== false && $expiration - $duree_lien > time() - MDP_OUBLIE_DELAI_MINUTES * 60;
            if (!$lien_recent) {
                if ($jamais_connecte) {
                    envoyer_invitation(entier($compte['id']), texte($compte['prenom']), texte($compte['mail']));
                } else {
                    envoyer_lien_mdp_oublie(entier($compte['id']), texte($compte['prenom']), texte($compte['mail']));
                }
                journaliser('mdp_oublie', 'utilisateurs', entier($compte['id']), 'Demande de nouveau mot de passe');
            }
        }
    }
    // Même message dans tous les cas : on ne révèle pas quels comptes existent.
    flash('info', 'Si un compte actif correspond à cette adresse, un courriel vient de lui être envoyé.');
    rediriger('connexion.php');
}

$titre_page = 'Mot de passe oublié';
$page_publique = true;
require DOSSIER_PRIVE . '/includes/header.php';
?>
<form class="form-signin mx-auto" method="post" action="<?= e(chemin_site('mdp_oublie.php')) ?>">
    <?= csrf_champ() ?>
    <h1 class="h4 mb-3 fw-normal">Mot de passe oublié</h1>
    <p class="text-body-secondary">Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
    <div class="form-floating mb-3">
        <input class="form-control" type="email" id="mail" name="mail" placeholder="nom@exemple.org" autocomplete="username" required autofocus>
        <label for="mail">Adresse e-mail</label>
    </div>
    <button class="btn btn-primary w-100 py-2" type="submit">Recevoir le lien</button>
    <p class="mt-3 mb-0"><a href="<?= e(chemin_site('connexion.php')) ?>">Retour à la connexion</a></p>
</form>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
