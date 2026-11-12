<?php

declare(strict_types=1);

/**
 * Assistant d'installation, à lancer une seule fois par environnement.
 *
 * 1. Connexion à la base            (gardée en session)
 * 2. Courriel et adresse du site    → écriture de config.php (installation « en cours »)
 * 3. Création de la structure       (sql/structure.sql)
 * 4. Tables de référence            (pays, quartiers, QP, QPV, codes postaux)
 * 5. Premier administrateur et première saison → installation « terminée »
 *
 * Verrouillage : dès que config.php indique une installation terminée (ou s'il
 * existe un config.php d'un autre format), l'assistant refuse de s'exécuter.
 */

require __DIR__ . '/_chemins.php';
require_once DOSSIER_PRIVE . '/includes/mes_fonctions.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_auth.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_utilisateurs.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_saisons.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_courriel.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_installation.php';
if (is_file(DOSSIER_PRIVE . '/vendor/autoload.php')) {
    require_once DOSSIER_PRIVE . '/vendor/autoload.php';
}

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', DOSSIER_PRIVE . '/logs/php_erreurs.log');

demarrer_session();
envoyer_entetes_securite();

/** Retour à l'assistant après un traitement (Post/Redirect/Get). */
function install_rediriger(string $page = 'install.php'): never
{
    header('Location: ' . $page, true, 303);
    exit;
}

// ---------------------------------------------------------------------------
// État de l'installation
// ---------------------------------------------------------------------------
$config_install = config_lire();
$verrouille = is_file(config_chemin()) && ($config_install === null || $config_install['INSTALLATION_TERMINEE'] === true);

$pdo_install = null;
$erreur_base = '';
$tables_attendues = installation_tables_attendues();
$tables_presentes = [];
$tables_manquantes = [];
$tables_etrangeres = [];

if (!$verrouille && $config_install !== null) {
    try {
        $pdo_install = db();
        $tables_base = installation_tables_existantes($pdo_install);
        $tables_presentes = array_values(array_intersect($tables_attendues, $tables_base));
        $tables_manquantes = array_values(array_diff($tables_attendues, $tables_presentes));
        // Tables qui ne font pas partie de l'application (restes d'une ancienne version, autre logiciel).
        $tables_etrangeres = array_values(array_diff($tables_base, $tables_attendues));
    } catch (PDOException $exception) {
        $erreur_base = $exception->getMessage();
    }
}

// Étape affichée
if ($config_install === null) {
    $etape = is_array($_SESSION['install_base'] ?? null) ? 2 : 1;
} elseif ($pdo_install === null || $tables_manquantes !== []) {
    $etape = 3;
} else {
    $etape = entier($_SESSION['install_etape'] ?? 0);
    if ($etape < 3 || $etape > 5) {
        $etape = 4;
        foreach (TABLES_REFERENCE_SQL as $table_reference) {
            if (installation_compter($pdo_install, $table_reference) > 0) {
                $etape = 5;
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Traitement des formulaires
// ---------------------------------------------------------------------------
if (est_post()) {
    if ($verrouille) {
        install_rediriger();
    }
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        install_rediriger();
    }
    $action = post('action');

    // --- Étape 1 : connexion à la base ------------------------------------
    if ($action === 'base' && $config_install === null) {
        $base = [
            'hote'        => post('db_hote'),
            'port'        => entier(post('db_port')),
            'nom'         => post('db_nom'),
            'utilisateur' => post('db_utilisateur'),
            'mdp'         => post_brut('db_mdp'),
        ];
        if ($base['hote'] === '' || $base['nom'] === '' || $base['utilisateur'] === '' || $base['port'] < 1 || $base['port'] > 65535) {
            flash('erreur', 'Le serveur, le port, le nom de la base et l\'utilisateur sont obligatoires.');
            ancien_memoriser();
            install_rediriger();
        }
        try {
            db_connecter($base['hote'], $base['port'], $base['nom'], $base['utilisateur'], $base['mdp']);
        } catch (PDOException $exception) {
            flash('erreur', 'Connexion impossible : ' . $exception->getMessage());
            ancien_memoriser();
            install_rediriger();
        }
        $_SESSION['install_base'] = $base;
        flash('succes', 'La connexion à la base fonctionne.');
        install_rediriger();
    }

    // --- Retour à l'étape 1 -------------------------------------------------
    if ($action === 'retour_base' && $config_install === null) {
        unset($_SESSION['install_base'], $_SESSION['install_courriel'], $_SESSION['install_smtp_mdp']);
        install_rediriger();
    }

    // --- Étape 2 : courriel et adresse du site ------------------------------
    if (($action === 'courriel_essai' || $action === 'courriel_enregistrer') && $config_install === null && $etape === 2) {
        $courriel = [
            'smtp_hote'           => post('smtp_hote'),
            'smtp_port'           => post('smtp_port'),
            'smtp_chiffrement'    => post('smtp_chiffrement'),
            'smtp_utilisateur'    => post('smtp_utilisateur'),
            'mail_expediteur'     => post('mail_expediteur'),
            'mail_expediteur_nom' => post('mail_expediteur_nom'),
            'app_url'             => rtrim(post('app_url'), '/'),
            'mail_essai'          => post('mail_essai'),
        ];
        // Le mot de passe SMTP n'est jamais réaffiché : s'il est laissé vide, on garde celui déjà saisi.
        if (post_brut('smtp_mdp') !== '') {
            $_SESSION['install_smtp_mdp'] = post_brut('smtp_mdp');
        }
        $_SESSION['install_courriel'] = $courriel;

        $erreurs = [];
        $port = entier($courriel['smtp_port']);
        if ($courriel['smtp_hote'] === '' || $port < 1 || $port > 65535) {
            $erreurs[] = 'Le serveur SMTP et son port sont obligatoires.';
        }
        if (!in_array($courriel['smtp_chiffrement'], ['aucun', 'tls', 'ssl'], true)) {
            $erreurs[] = 'Le chiffrement choisi n\'existe pas.';
        }
        if (filter_var($courriel['mail_expediteur'], FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'L\'adresse d\'expéditeur n\'est pas valide.';
        }
        if ($courriel['mail_expediteur_nom'] === '') {
            $erreurs[] = 'Le nom d\'expéditeur est obligatoire.';
        }
        if (preg_match('#^https?://[^\s/]+(/[^\s]*)?$#', $courriel['app_url']) !== 1) {
            $erreurs[] = 'L\'adresse du site doit commencer par http:// ou https://.';
        }
        if ($action === 'courriel_essai' && filter_var($courriel['mail_essai'], FILTER_VALIDATE_EMAIL) === false) {
            $erreurs[] = 'Indiquez une adresse valide pour recevoir le courriel d\'essai.';
        }
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            install_rediriger();
        }

        $smtp = [
            'hote'           => $courriel['smtp_hote'],
            'port'           => $port,
            'chiffrement'    => $courriel['smtp_chiffrement'],
            'utilisateur'    => $courriel['smtp_utilisateur'],
            'mdp'            => texte($_SESSION['install_smtp_mdp'] ?? ''),
            'expediteur'     => $courriel['mail_expediteur'],
            'expediteur_nom' => $courriel['mail_expediteur_nom'],
        ];

        if ($action === 'courriel_essai') {
            $erreur = envoyer_courriel_smtp(
                $smtp,
                $courriel['mail_essai'],
                'Essai d\'envoi — installation',
                "Bonjour,\n\nCe courriel confirme que les réglages d'envoi de l'application fonctionnent.\n\nAdresse du site : " . $courriel['app_url']
            );
            if ($erreur === null) {
                flash('succes', 'Courriel d\'essai envoyé à ' . $courriel['mail_essai'] . '.');
            } else {
                flash('erreur', 'L\'envoi a échoué : ' . $erreur);
            }
            install_rediriger();
        }

        $base = is_array($_SESSION['install_base'] ?? null) ? $_SESSION['install_base'] : [];
        $hote_site = texte(parse_url($courriel['app_url'], PHP_URL_HOST));
        $ecrit = installation_ecrire_config([
            'INSTALLATION_TERMINEE' => false,
            'AFFICHER_ERREURS'      => in_array($hote_site, ['localhost', '127.0.0.1'], true) || str_ends_with($hote_site, '.test'),
            'APP_URL'               => $courriel['app_url'],
            'DB_HOTE'               => texte($base['hote'] ?? ''),
            'DB_PORT'               => entier($base['port'] ?? 3306),
            'DB_NOM'                => texte($base['nom'] ?? ''),
            'DB_UTILISATEUR'        => texte($base['utilisateur'] ?? ''),
            'DB_MDP'                => texte($base['mdp'] ?? ''),
            'SMTP_HOTE'             => $smtp['hote'],
            'SMTP_PORT'             => $smtp['port'],
            'SMTP_CHIFFREMENT'      => $smtp['chiffrement'],
            'SMTP_UTILISATEUR'      => $smtp['utilisateur'],
            'SMTP_MDP'              => $smtp['mdp'],
            'MAIL_EXPEDITEUR'       => $smtp['expediteur'],
            'MAIL_EXPEDITEUR_NOM'   => $smtp['expediteur_nom'],
        ]);
        if (!$ecrit) {
            flash('erreur', 'Impossible d\'écrire config.php : le dossier privé n\'est pas accessible en écriture.');
            install_rediriger();
        }
        // Les mots de passe ne restent pas en session une fois config.php écrit.
        unset($_SESSION['install_base'], $_SESSION['install_courriel'], $_SESSION['install_smtp_mdp']);
        flash('succes', 'Le fichier config.php est écrit dans la partie privée.');
        install_rediriger();
    }

    // --- Navigation entre les étapes 3 à 5 ----------------------------------
    if ($action === 'aller' && $pdo_install !== null) {
        $_SESSION['install_etape'] = max(3, min(5, entier(post('etape'))));
        install_rediriger();
    }

    // --- Étape 3 : structure ------------------------------------------------
    if ($action === 'structure' && $pdo_install !== null) {
        try {
            if ($tables_presentes !== [] && post('recreer') === '1') {
                // Suppression demandée et confirmée explicitement par la case à cocher.
                installation_supprimer_tables($pdo_install, $tables_attendues);
                flash('avertissement', 'Les tables existantes ont été supprimées puis recréées.');
            }
            sql_executer_fichier($pdo_install, DOSSIER_PRIVE . '/sql/structure.sql');
            flash('succes', 'La structure de la base est en place (' . count($tables_attendues) . ' tables).');
            $_SESSION['install_etape'] = 4;
        } catch (Throwable $exception) {
            flash('erreur', 'La création de la structure a échoué : ' . $exception->getMessage());
        }
        install_rediriger();
    }

    // --- Étape 4 : tables de référence --------------------------------------
    if ($action === 'reference' && $pdo_install !== null && $tables_manquantes === []) {
        set_time_limit(300);
        $remplacer = post('remplacer') === '1';
        try {
            // Pays, quartiers, QP, QPV : un seul fichier, donc tout ou rien.
            $deja = 0;
            foreach (TABLES_REFERENCE_SQL as $table_reference) {
                $deja += installation_compter($pdo_install, $table_reference);
            }
            if ($deja === 0 || $remplacer) {
                foreach (TABLES_REFERENCE_SQL as $table_reference) {
                    $pdo_install->exec('DELETE FROM `' . $table_reference . '`');
                }
                installation_importer_reference($pdo_install);
                flash('succes', 'Pays, quartiers, quartiers prioritaires et QPV sont remplis.');
            } else {
                flash('info', 'Pays et quartiers déjà remplis : ils n\'ont pas été modifiés.');
            }

            if (installation_compter($pdo_install, 'codes_postaux') === 0 || $remplacer) {
                $pdo_install->exec('DELETE FROM `codes_postaux`');
                $nombre = installation_importer_codes_postaux($pdo_install);
                flash('succes', 'Codes postaux remplis (' . $nombre . ' lignes lues).');
            } else {
                flash('info', 'Codes postaux déjà remplis : ils n\'ont pas été modifiés.');
            }
            $_SESSION['install_etape'] = 5;
        } catch (Throwable $exception) {
            flash('erreur', 'Le remplissage a échoué : ' . $exception->getMessage());
        }
        install_rediriger();
    }

    // --- Étape 5 : premier administrateur et première saison ----------------
    if ($action === 'final' && $pdo_install !== null && $tables_manquantes === []) {
        $faut_admin = installation_compter($pdo_install, 'utilisateurs') === 0;
        $faut_saison = installation_compter($pdo_install, 'saisons') === 0;
        $erreurs = [];

        $nom_association = post('nom_association');
        if ($nom_association === '' || mb_strlen($nom_association) > 255) {
            $erreurs[] = 'Le nom de l\'association est obligatoire.';
        }
        if ($faut_admin) {
            $erreurs = array_merge($erreurs, erreurs_utilisateur(post('prenom'), post('mail'), 'admin'));
            $erreur_mdp = erreur_mdp(post_brut('mdp'), post_brut('mdp_confirmation'));
            if ($erreur_mdp !== null) {
                $erreurs[] = $erreur_mdp;
            }
        }
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            ancien_memoriser();
            install_rediriger();
        }

        try {
            $pdo_install->beginTransaction();
            if ($faut_admin) {
                $admin_id = creer_utilisateur(post('prenom'), post('mail'), 'admin', post_brut('mdp'));
                journaliser('creation', 'utilisateurs', $admin_id, 'Installation : premier administrateur', null, ['prenom' => post('prenom'), 'mail' => post('mail'), 'role' => 'admin']);
            }
            if ($faut_saison) {
                // La saison en cours est créée sans rien demander (1er septembre → 31 août).
                $premiere_saison = saison_habituelle();
                db_executer('INSERT INTO saisons (nom, date_debut, date_fin) VALUES (?, ?, ?)', [$premiere_saison['nom'], $premiere_saison['date_debut'], $premiere_saison['date_fin']]);
                journaliser('creation', 'saisons', entier($pdo_install->lastInsertId()), 'Installation : première saison « ' . $premiere_saison['nom'] . ' »', null, $premiere_saison);
            }
            parametre_enregistrer('nom_association', $nom_association);
            db_executer("INSERT IGNORE INTO parametres (cle, valeur) VALUES ('adresse_association', ''), ('logo', 'img/logo-cefia75.png')");
            $pdo_install->commit();
        } catch (Throwable $exception) {
            if ($pdo_install->inTransaction()) {
                $pdo_install->rollBack();
            }
            flash('erreur', 'L\'enregistrement a échoué : ' . $exception->getMessage());
            ancien_memoriser();
            install_rediriger();
        }

        $config_finale = $config_install;
        $config_finale['INSTALLATION_TERMINEE'] = true;
        if (!installation_ecrire_config($config_finale)) {
            flash('erreur', 'Impossible de mettre à jour config.php. Relancez cette étape.');
            install_rediriger();
        }
        unset($_SESSION['install_etape']);
        // L'assistant ne sert plus : il se supprime lui-même si le serveur le permet.
        // (Même s'il reste, il est verrouillé par INSTALLATION_TERMINEE.)
        if (@unlink(__FILE__)) {
            flash('succes', 'Installation terminée. Vous pouvez vous connecter. Le fichier install.php a été supprimé du serveur.');
        } else {
            flash('succes', 'Installation terminée. Vous pouvez vous connecter.');
            flash('avertissement', 'Le fichier install.php n\'a pas pu être supprimé automatiquement : pensez à le supprimer du serveur.');
        }
        install_rediriger('connexion.php');
    }

    install_rediriger();
}

// ---------------------------------------------------------------------------
// Affichage
// ---------------------------------------------------------------------------
$messages_install = flash_lire();
$titres_etapes = [
    1 => 'Base de données',
    2 => 'Courriel et adresse du site',
    3 => 'Structure',
    4 => 'Tables de référence',
    5 => 'Administrateur et saison',
];
$courriel_saisi = is_array($_SESSION['install_courriel'] ?? null) ? $_SESSION['install_courriel'] : [];
$mdp_smtp_garde = texte($_SESSION['install_smtp_mdp'] ?? '') !== '';

if ($verrouille) {
    http_response_code(403);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Installation</title>
    <link rel="icon" href="img/favicon.png">
    <link rel="stylesheet" href="css/bddv3.css">
</head>
<body class="bg-body-tertiary">
<main class="container py-4 install">
    <h1 class="h3 mb-4">Installation de l'application</h1>

    <?php foreach ($messages_install as $message_install) : ?>
        <div class="alert alert-<?= e($message_install['classe']) ?>" role="alert"><?= e($message_install['message']) ?></div>
    <?php endforeach; ?>

<?php if ($verrouille) : ?>
    <div class="alert alert-warning" role="alert">
        <h2 class="h5">L'assistant est verrouillé</h2>
        <?php if ($config_install === null) : ?>
            <p class="mb-0">Un fichier <code>config.php</code> existe déjà dans la partie privée, mais il n'a pas été écrit par cet assistant. Supprimez-le (ou renommez-le) pour lancer l'installation.</p>
        <?php else : ?>
            <p>L'installation est déjà faite. Pour des raisons de sécurité, supprimez <code>install.php</code> du serveur.</p>
            <a class="btn btn-primary" href="connexion.php">Aller à la connexion</a>
        <?php endif; ?>
    </div>
<?php else : ?>

    <ol class="list-group list-group-horizontal-md mb-4">
        <?php foreach ($titres_etapes as $numero => $titre_etape) : ?>
            <li class="list-group-item flex-fill <?= $numero === $etape ? 'active' : ($numero < $etape ? 'list-group-item-success' : 'text-body-secondary') ?>">
                <?= $numero ?>. <?= e($titre_etape) ?>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="border rounded bg-body p-4">
        <div>

<?php if ($etape === 1) : ?>
            <h2 class="h5 card-title">1. Connexion à la base de données</h2>
            <p class="text-body-secondary">La base doit déjà exister (créée dans phpMyAdmin ou dans le panneau de l'hébergeur). La connexion est testée avant de continuer.</p>
            <form method="post" action="install.php" autocomplete="off">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="base">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="db_hote">Serveur</label>
                        <input class="form-control" type="text" id="db_hote" name="db_hote" value="<?= e(ancien('db_hote', 'localhost')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="db_port">Port</label>
                        <input class="form-control" type="number" id="db_port" name="db_port" min="1" max="65535" value="<?= e(ancien('db_port', '3306')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="db_nom">Nom de la base</label>
                        <input class="form-control" type="text" id="db_nom" name="db_nom" value="<?= e(ancien('db_nom')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="db_utilisateur">Utilisateur</label>
                        <input class="form-control" type="text" id="db_utilisateur" name="db_utilisateur" value="<?= e(ancien('db_utilisateur')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="db_mdp">Mot de passe</label>
                        <input class="form-control" type="password" id="db_mdp" name="db_mdp" autocomplete="new-password">
                    </div>
                </div>
                <button class="btn btn-primary mt-4" type="submit">Tester la connexion et continuer</button>
            </form>

<?php elseif ($etape === 2) : ?>
            <h2 class="h5 card-title">2. Courriel et adresse du site</h2>
            <p class="text-body-secondary">Les courriels (invitation, mot de passe oublié) sont envoyés par SMTP. En local avec Mailpit : serveur <code>localhost</code>, port <code>1025</code>, sans chiffrement, sans identifiant.</p>
            <form method="post" action="install.php" autocomplete="off">
                <?= csrf_champ() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_hote">Serveur SMTP</label>
                        <input class="form-control" type="text" id="smtp_hote" name="smtp_hote" value="<?= e($courriel_saisi['smtp_hote'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="smtp_port">Port</label>
                        <input class="form-control" type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?= e($courriel_saisi['smtp_port'] ?? '465') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="smtp_chiffrement">Chiffrement</label>
                        <select class="form-select" id="smtp_chiffrement" name="smtp_chiffrement">
                            <?php foreach (['ssl' => 'SSL / TLS (port 465)', 'tls' => 'STARTTLS (port 587)', 'aucun' => 'Aucun (Mailpit)'] as $valeur_option => $libelle_option) : ?>
                                <option value="<?= e($valeur_option) ?>" <?= texte($courriel_saisi['smtp_chiffrement'] ?? 'ssl') === $valeur_option ? 'selected' : '' ?>><?= e($libelle_option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_utilisateur">Identifiant SMTP</label>
                        <input class="form-control" type="text" id="smtp_utilisateur" name="smtp_utilisateur" value="<?= e($courriel_saisi['smtp_utilisateur'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_mdp">Mot de passe SMTP</label>
                        <input class="form-control" type="password" id="smtp_mdp" name="smtp_mdp" autocomplete="new-password" aria-describedby="aide_smtp_mdp">
                        <?php if ($mdp_smtp_garde) : ?>
                            <div class="form-text" id="aide_smtp_mdp">Un mot de passe est déjà saisi. Laissez vide pour le conserver.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mail_expediteur">Adresse d'expéditeur</label>
                        <input class="form-control" type="email" id="mail_expediteur" name="mail_expediteur" value="<?= e($courriel_saisi['mail_expediteur'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="mail_expediteur_nom">Nom d'expéditeur</label>
                        <input class="form-control" type="text" id="mail_expediteur_nom" name="mail_expediteur_nom" value="<?= e($courriel_saisi['mail_expediteur_nom'] ?? '') ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="app_url">Adresse publique du site</label>
                        <input class="form-control" type="url" id="app_url" name="app_url" value="<?= e($courriel_saisi['app_url'] ?? installation_url_detectee()) ?>" required aria-describedby="aide_app_url">
                        <div class="form-text" id="aide_app_url">Adresse du dossier qui contient les pages, sans barre oblique finale. Elle sert à tous les liens, y compris dans les courriels.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="mail_essai">Adresse pour le courriel d'essai (facultatif)</label>
                        <input class="form-control" type="email" id="mail_essai" name="mail_essai" value="<?= e($courriel_saisi['mail_essai'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-outline-primary w-100" type="submit" name="action" value="courriel_essai">Envoyer un essai</button>
                    </div>
                </div>
                <button class="btn btn-primary mt-4" type="submit" name="action" value="courriel_enregistrer">Enregistrer et continuer</button>
            </form>
            <form method="post" action="install.php" class="mt-2">
                <?= csrf_champ() ?>
                <button class="btn btn-link px-0" type="submit" name="action" value="retour_base">Revenir à l'étape 1</button>
            </form>

<?php elseif ($pdo_install === null) : ?>
            <h2 class="h5 card-title">La connexion à la base ne fonctionne plus</h2>
            <div class="alert alert-danger" role="alert"><?= e($erreur_base) ?></div>
            <p class="mb-0">Vérifiez que le serveur de base de données est démarré. Si les identifiants ont changé, supprimez <code>config.php</code> dans la partie privée, puis rechargez cette page pour reprendre à l'étape 1.</p>

<?php elseif ($etape === 3) : ?>
            <h2 class="h5 card-title">3. Création de la structure</h2>
            <p class="text-body-secondary">Les tables sont créées à partir de <code>sql/structure.sql</code>. Une table déjà présente n'est jamais modifiée, sauf si vous demandez explicitement à tout recréer.</p>
            <?php if ($tables_presentes !== [] || $tables_etrangeres !== []) : ?>
                <div class="alert alert-warning" role="alert">
                    <p class="mb-1"><strong>Cette base n'est pas vide.</strong> Une table déjà présente est gardée telle quelle : si elle vient d'une ancienne version, ses colonnes peuvent différer et la suite de l'installation peut échouer.</p>
                    <p class="mb-0">Le plus sûr est d'installer dans une base vide. Sinon, cochez la case ci-dessous pour recréer les tables de l'application.
                        <?php if ($tables_etrangeres !== []) : ?>
                            Tables étrangères à l'application, qui ne seront pas touchées : <?= e(implode(', ', $tables_etrangeres)) ?>.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            <ul class="list-group mb-3">
                <?php foreach ($tables_attendues as $table_attendue) : ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <code><?= e($table_attendue) ?></code>
                        <?php if (in_array($table_attendue, $tables_presentes, true)) : ?>
                            <span class="badge text-bg-success">présente</span>
                        <?php else : ?>
                            <span class="badge text-bg-secondary">à créer</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="install.php">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="structure">
                <?php if ($tables_presentes !== []) : ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="recreer" name="recreer" value="1">
                        <label class="form-check-label text-danger" for="recreer">Supprimer les tables présentes et tout recréer : <strong>toutes leurs données seront perdues</strong>.</label>
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit"><?= $tables_manquantes === [] ? 'Appliquer' : 'Créer les tables manquantes' ?></button>
            </form>
            <?php if ($tables_manquantes === []) : ?>
                <form method="post" action="install.php" class="mt-2">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="action" value="aller">
                    <button class="btn btn-link px-0" type="submit" name="etape" value="4">Continuer sans rien modifier</button>
                </form>
            <?php endif; ?>

<?php elseif ($etape === 4) : ?>
            <h2 class="h5 card-title">4. Tables de référence</h2>
            <p class="text-body-secondary">Pays, quartiers, quartiers prioritaires (QP) et QPV viennent de <code>sql/donnees_reference.sql</code> ; les codes postaux, du fichier de La Poste <code>sql/019HexaSmal.csv</code>. Le remplissage peut prendre une minute.</p>
            <ul class="list-group mb-3">
                <?php foreach ([...TABLES_REFERENCE_SQL, 'codes_postaux'] as $table_reference) : ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <code><?= e($table_reference) ?></code>
                        <span><?= installation_compter($pdo_install, $table_reference) ?> ligne(s)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="install.php">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="reference">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remplacer" name="remplacer" value="1">
                    <label class="form-check-label" for="remplacer">Vider les tables déjà remplies et les remplir de nouveau.</label>
                </div>
                <button class="btn btn-primary" type="submit">Remplir les tables de référence</button>
            </form>
            <form method="post" action="install.php" class="mt-2">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="aller">
                <button class="btn btn-link px-0" type="submit" name="etape" value="5">Continuer sans rien modifier</button>
                <button class="btn btn-link" type="submit" name="etape" value="3">Revenir à l'étape 3</button>
            </form>

<?php else : ?>
            <?php
            $faut_admin_affichage = installation_compter($pdo_install, 'utilisateurs') === 0;
            $faut_saison_affichage = installation_compter($pdo_install, 'saisons') === 0;
            ?>
            <h2 class="h5 card-title">5. Premier administrateur et première saison</h2>
            <form method="post" action="install.php" autocomplete="off">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="final">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="nom_association">Nom de l'association</label>
                        <input class="form-control" type="text" id="nom_association" name="nom_association" maxlength="255" value="<?= e(ancien('nom_association', 'CEFIA')) ?>" required>
                    </div>

                    <?php if ($faut_admin_affichage) : ?>
                        <div class="col-12"><h3 class="h6 mt-2 mb-0">Premier administrateur</h3></div>
                        <div class="col-md-6">
                            <label class="form-label" for="prenom">Prénom</label>
                            <input class="form-control" type="text" id="prenom" name="prenom" maxlength="100" value="<?= e(ancien('prenom')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mail">Adresse e-mail (identifiant)</label>
                            <input class="form-control" type="email" id="mail" name="mail" maxlength="150" value="<?= e(ancien('mail')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mdp">Mot de passe (8 caractères au minimum)</label>
                            <input class="form-control" type="password" id="mdp" name="mdp" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mdp_confirmation">Mot de passe, une seconde fois</label>
                            <input class="form-control" type="password" id="mdp_confirmation" name="mdp_confirmation" minlength="8" autocomplete="new-password" required>
                        </div>
                    <?php else : ?>
                        <div class="col-12"><div class="alert alert-info mb-0">Un utilisateur existe déjà : aucun administrateur ne sera créé.</div></div>
                    <?php endif; ?>

                    <?php if ($faut_saison_affichage) : ?>
                        <?php $saison_prevue = saison_habituelle(); ?>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                La saison en cours, « <?= e($saison_prevue['nom']) ?> » (du <?= e(date_fr($saison_prevue['date_debut'])) ?> au <?= e(date_fr($saison_prevue['date_fin'])) ?>), sera créée automatiquement.
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="col-12"><div class="alert alert-info mb-0">Une saison existe déjà : aucune saison ne sera créée.</div></div>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary mt-4" type="submit">Terminer l'installation</button>
            </form>
            <form method="post" action="install.php" class="mt-2">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="aller">
                <button class="btn btn-link px-0" type="submit" name="etape" value="4">Revenir à l'étape 4</button>
            </form>
<?php endif; ?>

        </div>
    </div>
<?php endif; ?>
</main>
</body>
</html>
<?php ancien_oublier(); ?>
