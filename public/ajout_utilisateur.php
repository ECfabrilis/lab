<?php

declare(strict_types=1);

// Création d'un utilisateur (administrateurs seulement).
// Aucun mot de passe n'est attribué : l'utilisateur reçoit un lien par courriel.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

if (est_post()) {
    csrf_verifier();
    if (!jeton_unique_consommer('ajout_utilisateur')) {
        flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste avant de recommencer.');
        rediriger('utilisateurs.php');
    }

    $prenom_saisi = post('prenom');
    $mail_saisi = post('mail');
    $role_saisi = post('role');

    $erreurs = erreurs_utilisateur($prenom_saisi, $mail_saisi, $role_saisi);
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger('ajout_utilisateur.php');
    }

    $nouvel_id = creer_utilisateur($prenom_saisi, $mail_saisi, $role_saisi);
    journaliser('creation', 'utilisateurs', $nouvel_id, 'Création du compte de ' . $prenom_saisi, null, [
        'prenom' => $prenom_saisi,
        'mail'   => $mail_saisi,
        'role'   => $role_saisi,
    ]);

    if (envoyer_invitation($nouvel_id, $prenom_saisi, $mail_saisi)) {
        flash('succes', 'Le compte est créé. Un courriel d\'invitation est parti à ' . $mail_saisi . '.');
    } else {
        flash('avertissement', 'Le compte est créé, mais le courriel d\'invitation n\'a pas pu être envoyé. Renvoyez-le depuis cette fiche.');
    }
    rediriger('utilisateur.php?id=' . $nouvel_id);
}

$titre_page = 'Ajouter un utilisateur';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Ajouter un utilisateur</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('utilisateurs.php')) ?>">Retour à la liste</a>
        </div>
        <ul class="text-body-secondary small mt-3 mb-0 ps-3">
            <li><strong>Lecteur</strong> : consultation seulement.</li>
            <li><strong>Standard</strong> : saisie (inscriptions, paiements).</li>
            <li><strong>Administrateur</strong> : paramétrage et utilisateurs.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('ajout_utilisateur.php')) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ('ajout_utilisateur') ?>
            <div class="mb-3">
                <label class="form-label" for="prenom">Prénom</label>
                <input class="form-control" type="text" id="prenom" name="prenom" maxlength="100" value="<?= e(ancien('prenom')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mail">Adresse e-mail (identifiant de connexion)</label>
                <input class="form-control" type="email" id="mail" name="mail" maxlength="150" value="<?= e(ancien('mail')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="role">Rôle</label>
                <select class="form-select" id="role" name="role">
                    <?php foreach (libelles_roles() as $code_role => $libelle_role) : ?>
                        <option value="<?= e($code_role) ?>" <?= ancien('role', 'lecteur') === $code_role ? 'selected' : '' ?>><?= e($libelle_role) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Créer le compte et envoyer l'invitation</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
