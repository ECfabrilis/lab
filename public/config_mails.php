<?php

declare(strict_types=1);

// Paramètres des mails (administrateurs seulement) : serveur d'envoi (SMTP),
// expéditeur, essai d'envoi. Ces réglages sont enregistrés dans config.php,
// que cette page réécrit. Le mot de passe n'est jamais réaffiché ni journalisé.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_installation.php';

exiger_role('admin');

$actuels = reglages_smtp();

if (est_post()) {
    csrf_verifier();
    $action = post('action');

    $saisis = [
        'hote'           => post('smtp_hote'),
        'port'           => entier(post('smtp_port')),
        'chiffrement'    => post('smtp_chiffrement'),
        'utilisateur'    => post('smtp_utilisateur'),
        // Champ laissé vide : on garde le mot de passe déjà enregistré.
        'mdp'            => post_brut('smtp_mdp') !== '' ? post_brut('smtp_mdp') : $actuels['mdp'],
        'expediteur'     => post('mail_expediteur'),
        'expediteur_nom' => post('mail_expediteur_nom'),
    ];
    $mail_essai = post('mail_essai');

    $erreurs = [];
    if ($saisis['hote'] === '' || $saisis['port'] < 1 || $saisis['port'] > 65535) {
        $erreurs[] = 'Le serveur SMTP et son port sont obligatoires.';
    }
    if (!in_array($saisis['chiffrement'], ['aucun', 'tls', 'ssl'], true)) {
        $erreurs[] = 'Le chiffrement choisi n\'existe pas.';
    }
    if (filter_var($saisis['expediteur'], FILTER_VALIDATE_EMAIL) === false) {
        $erreurs[] = 'L\'adresse de l\'expéditeur n\'est pas valide.';
    }
    if ($saisis['expediteur_nom'] === '') {
        $erreurs[] = 'Le nom de l\'expéditeur est obligatoire.';
    }
    if ($action === 'essai' && filter_var($mail_essai, FILTER_VALIDATE_EMAIL) === false) {
        $erreurs[] = 'Indiquez une adresse valide pour recevoir le courriel d\'essai.';
    }
    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger('config_mails.php');
    }

    // --- Essai d'envoi avec les réglages saisis, sans les enregistrer --------
    if ($action === 'essai') {
        $erreur_envoi = envoyer_courriel_smtp(
            $saisis,
            $mail_essai,
            'Essai d\'envoi — ' . parametre('nom_association', 'application'),
            "Ce message confirme que les réglages d'envoi des courriels fonctionnent."
        );
        if ($erreur_envoi === null) {
            flash('succes', 'Courriel d\'essai envoyé à ' . $mail_essai . '. Les réglages ne sont pas encore enregistrés.');
        } else {
            flash('erreur', 'L\'envoi a échoué : ' . $erreur_envoi);
        }
        ancien_memoriser();
        rediriger('config_mails.php');
    }

    // --- Enregistrement dans config.php ---------------------------------------
    if ($action === 'enregistrer') {
        $configuration = config_lire();
        if ($configuration === null) {
            flash('erreur', 'Le fichier de configuration est illisible.');
            rediriger('config_mails.php');
        }
        $configuration['SMTP_HOTE'] = $saisis['hote'];
        $configuration['SMTP_PORT'] = $saisis['port'];
        $configuration['SMTP_CHIFFREMENT'] = $saisis['chiffrement'];
        $configuration['SMTP_UTILISATEUR'] = $saisis['utilisateur'];
        $configuration['SMTP_MDP'] = $saisis['mdp'];
        $configuration['MAIL_EXPEDITEUR'] = $saisis['expediteur'];
        $configuration['MAIL_EXPEDITEUR_NOM'] = $saisis['expediteur_nom'];

        if (!installation_ecrire_config($configuration)) {
            flash('erreur', 'Le fichier de configuration n\'a pas pu être écrit (droits d\'écriture sur le dossier privé).');
            ancien_memoriser();
            rediriger('config_mails.php');
        }
        $sans_mdp = static function (array $reglages): array {
            unset($reglages['mdp']);
            return $reglages;
        };
        journaliser('modification', 'configuration', null, 'Modification des paramètres des mails', $sans_mdp($actuels), $sans_mdp($saisis)
            + ['mot_de_passe_change' => post_brut('smtp_mdp') !== '']);
        flash('succes', 'Les paramètres des mails sont enregistrés.');
        rediriger('config_mails.php');
    }

    rediriger('config_mails.php');
}

$titre_page = 'Paramètres des mails';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paramètres des mails</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Ces réglages servent aux courriels d'invitation et de mot de passe oublié.</li>
            <li>« Envoyer un essai » teste les valeurs saisies sans les enregistrer.</li>
            <li>En local avec Mailpit : serveur <code>localhost</code>, port <code>1025</code>, sans chiffrement, sans identifiant.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('config_mails.php')) ?>" autocomplete="off">
            <?= csrf_champ() ?>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="smtp_hote">Serveur SMTP</label>
                    <input class="form-control" type="text" id="smtp_hote" name="smtp_hote" maxlength="150" value="<?= e(ancien('smtp_hote', $actuels['hote'])) ?>" required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label" for="smtp_port">Port</label>
                    <input class="form-control" type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?= e(ancien('smtp_port', $actuels['port'])) ?>" required>
                </div>
                <div class="col-sm-3">
                    <label class="form-label" for="smtp_chiffrement">Chiffrement</label>
                    <select class="form-select" id="smtp_chiffrement" name="smtp_chiffrement">
                        <?php foreach (['ssl' => 'SSL', 'tls' => 'STARTTLS', 'aucun' => 'Aucun'] as $valeur_option => $libelle_option) : ?>
                            <option value="<?= e($valeur_option) ?>" <?= ancien('smtp_chiffrement', $actuels['chiffrement']) === $valeur_option ? 'selected' : '' ?>><?= e($libelle_option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="smtp_utilisateur">Identifiant SMTP</label>
                    <input class="form-control" type="text" id="smtp_utilisateur" name="smtp_utilisateur" maxlength="150" value="<?= e(ancien('smtp_utilisateur', $actuels['utilisateur'])) ?>">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="smtp_mdp">Mot de passe SMTP</label>
                    <input class="form-control" type="password" id="smtp_mdp" name="smtp_mdp" autocomplete="new-password" aria-describedby="aide_smtp_mdp">
                    <div class="form-text" id="aide_smtp_mdp">Laissez vide pour conserver le mot de passe actuel.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="mail_expediteur">Adresse de l'expéditeur</label>
                    <input class="form-control" type="email" id="mail_expediteur" name="mail_expediteur" maxlength="150" value="<?= e(ancien('mail_expediteur', $actuels['expediteur'])) ?>" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="mail_expediteur_nom">Nom de l'expéditeur</label>
                    <input class="form-control" type="text" id="mail_expediteur_nom" name="mail_expediteur_nom" maxlength="100" value="<?= e(ancien('mail_expediteur_nom', $actuels['expediteur_nom'])) ?>" required>
                </div>
            </div>

            <hr class="my-4">
            <div class="row g-3 align-items-end">
                <div class="col-sm-8">
                    <label class="form-label" for="mail_essai">Adresse pour le courriel d'essai</label>
                    <input class="form-control" type="email" id="mail_essai" name="mail_essai" maxlength="150" value="<?= e(ancien('mail_essai')) ?>">
                </div>
                <div class="col-sm-4">
                    <button class="btn btn-outline-primary w-100" type="submit" name="action" value="essai">Envoyer un essai</button>
                </div>
            </div>
            <button class="btn btn-primary mt-4" type="submit" name="action" value="enregistrer">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
