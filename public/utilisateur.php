<?php

declare(strict_types=1);

// Fiche d'un utilisateur : modification, activation, envoi d'un lien (administrateurs seulement).
//
// Règles vérifiées ici, côté serveur : un administrateur ne peut pas se désactiver
// ni changer son propre rôle ; le dernier administrateur actif ne peut pas être retiré.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$fiche_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$fiche = utilisateur_par_id($fiche_id);
if ($fiche === null) {
    flash('erreur', 'Cet utilisateur n\'existe pas.');
    rediriger('utilisateurs.php');
}
$est_moi = $fiche_id === entier($_SESSION['utilisateur_id'] ?? 0);
$page_fiche = 'utilisateur.php?id=' . $fiche_id;

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Envoi d'un lien pour choisir un mot de passe ---------------------
    if ($action === 'lien') {
        if (entier($fiche['est_actif']) !== 1) {
            flash('erreur', 'Ce compte est désactivé : réactivez-le avant d\'envoyer un lien.');
        } elseif (envoyer_invitation($fiche_id, texte($fiche['prenom']), texte($fiche['mail']))) {
            journaliser('invitation', 'utilisateurs', $fiche_id, 'Envoi d\'un lien de choix du mot de passe');
            flash('succes', 'Un lien valable 7 jours est parti à ' . texte($fiche['mail']) . '.');
        } else {
            flash('erreur', 'Le courriel n\'a pas pu être envoyé. Vérifiez les réglages d\'envoi.');
        }
        rediriger($page_fiche);
    }

    // --- Modification -------------------------------------------------------
    if ($action === 'modifier') {
        $prenom_saisi = post('prenom');
        $mail_saisi = post('mail');
        // Sur son propre compte, le rôle et l'état ne sont pas modifiables : on garde les valeurs de la base.
        $role_saisi = $est_moi ? texte($fiche['role']) : post('role');
        $actif_saisi = $est_moi ? entier($fiche['est_actif']) : (post('est_actif') === '1' ? 1 : 0);

        $erreurs = erreurs_utilisateur($prenom_saisi, $mail_saisi, $role_saisi, $fiche_id);

        $perd_admin = texte($fiche['role']) === 'admin' && entier($fiche['est_actif']) === 1
            && ($role_saisi !== 'admin' || $actif_saisi !== 1);
        if ($perd_admin && nb_admins_actifs() <= 1) {
            $erreurs[] = 'Impossible : il doit rester au moins un administrateur actif.';
        }

        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            rediriger($page_fiche);
        }

        $nouveau = ['prenom' => $prenom_saisi, 'mail' => $mail_saisi, 'role' => $role_saisi, 'est_actif' => $actif_saisi];
        $ancien = ['prenom' => $fiche['prenom'], 'mail' => $fiche['mail'], 'role' => $fiche['role'], 'est_actif' => entier($fiche['est_actif'])];

        if ($nouveau == $ancien) {
            flash('info', 'Aucune modification à enregistrer.');
            rediriger($page_fiche);
        }

        db_executer(
            'UPDATE utilisateurs SET prenom = ?, mail = ?, role = ?, est_actif = ? WHERE id = ?',
            [$prenom_saisi, $mail_saisi, $role_saisi, $actif_saisi, $fiche_id]
        );
        if ($actif_saisi !== 1) {
            // Un compte désactivé ne garde ni lien en attente ni blocage.
            db_executer('UPDATE utilisateurs SET token = NULL, date_expiration_token = NULL WHERE id = ?', [$fiche_id]);
        }
        journaliser('modification', 'utilisateurs', $fiche_id, 'Modification du compte de ' . $prenom_saisi, $ancien, $nouveau);
        flash('succes', 'Le compte est modifié.');
        rediriger($page_fiche);
    }

    // --- Déblocage après trop d'échecs de connexion --------------------------
    if ($action === 'debloquer') {
        db_executer('UPDATE utilisateurs SET tentatives_echec = 0, verrouille_jusqua = NULL WHERE id = ?', [$fiche_id]);
        journaliser('deblocage', 'utilisateurs', $fiche_id, 'Déblocage du compte');
        flash('succes', 'Le compte est débloqué.');
        rediriger($page_fiche);
    }

    rediriger($page_fiche);
}

$est_bloque = texte($fiche['verrouille_jusqua']) !== '' && strtotime(texte($fiche['verrouille_jusqua'])) > time();

$titre_page = 'Utilisateur';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4"><?= e($fiche['prenom']) ?>
    <?php if (entier($fiche['est_actif']) !== 1) : ?><span class="badge text-bg-secondary">Désactivé</span><?php endif; ?>
</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <form class="d-grid" method="post" action="<?= e(chemin_site('utilisateur.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="id" value="<?= $fiche_id ?>">
                <button class="btn btn-outline-primary" type="submit" name="action" value="lien">Envoyer un lien pour choisir un mot de passe</button>
            </form>
            <?php if ($est_bloque) : ?>
                <form class="d-grid" method="post" action="<?= e(chemin_site('utilisateur.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $fiche_id ?>">
                    <button class="btn btn-outline-warning" type="submit" name="action" value="debloquer">Débloquer le compte</button>
                </form>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('utilisateurs.php')) ?>">Retour à la liste</a>
        </div>
        <dl class="small text-body-secondary mt-3 mb-0">
            <dt>Compte créé</dt>
            <dd><?= e(date_heure_fr($fiche['date_crea'])) ?></dd>
            <dt>Dernière connexion</dt>
            <dd><?= e(date_heure_fr($fiche['derniere_connexion']) ?: 'Jamais') ?></dd>
            <?php if ($est_bloque) : ?>
                <dt>Bloqué jusqu'au</dt>
                <dd><?= e(date_heure_fr($fiche['verrouille_jusqua'])) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('utilisateur.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="id" value="<?= $fiche_id ?>">
            <input type="hidden" name="action" value="modifier">
            <div class="mb-3">
                <label class="form-label" for="prenom">Prénom</label>
                <input class="form-control" type="text" id="prenom" name="prenom" maxlength="100" value="<?= e(ancien('prenom', $fiche['prenom'])) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mail">Adresse e-mail (identifiant de connexion)</label>
                <input class="form-control" type="email" id="mail" name="mail" maxlength="150" value="<?= e(ancien('mail', $fiche['mail'])) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="role">Rôle</label>
                <select class="form-select" id="role" name="role" <?= $est_moi ? 'disabled' : '' ?> aria-describedby="aide_role">
                    <?php foreach (libelles_roles() as $code_role => $libelle_role) : ?>
                        <option value="<?= e($code_role) ?>" <?= ancien('role', $fiche['role']) === $code_role ? 'selected' : '' ?>><?= e($libelle_role) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($est_moi) : ?>
                    <div class="form-text" id="aide_role">Vous ne pouvez pas changer votre propre rôle ni désactiver votre compte.</div>
                <?php endif; ?>
            </div>
            <div class="form-check mb-3">
                <?php $actif_affiche = ancien_existe() ? ancien('est_actif') === '1' : entier($fiche['est_actif']) === 1; ?>
                <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" value="1" <?= $actif_affiche || $est_moi ? 'checked' : '' ?> <?= $est_moi ? 'disabled' : '' ?>>
                <label class="form-check-label" for="est_actif">Compte actif (un compte désactivé ne peut plus se connecter)</label>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
