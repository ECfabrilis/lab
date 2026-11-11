<?php

declare(strict_types=1);

/**
 * Modèle de config.php (réglages techniques et secrets).
 *
 * config.php est écrit par l'assistant d'installation (public/install.php) :
 * ce modèle sert de documentation, ou à le recréer à la main.
 * config.php n'est jamais versionné et reste dans la partie privée.
 */
return [
    // Passe à true à la fin de l'assistant : install.php refuse alors de s'exécuter.
    'INSTALLATION_TERMINEE' => true,

    // true en local seulement : affiche le détail des erreurs PHP dans la page.
    'AFFICHER_ERREURS' => false,

    // Adresse publique du site, sans barre oblique finale. Tous les liens en partent.
    'APP_URL' => 'https://exemple.org/bddv3',

    // Base de données MariaDB
    'DB_HOTE'        => 'localhost',
    'DB_PORT'        => 3306,
    'DB_NOM'         => 'bddv3',
    'DB_UTILISATEUR' => 'utilisateur',
    'DB_MDP'         => 'mot-de-passe',

    // Courriel (PHPMailer, SMTP). En local avec Mailpit : hôte localhost, port 1025, chiffrement « aucun ».
    'SMTP_HOTE'           => 'smtp.exemple.org',
    'SMTP_PORT'           => 465,
    'SMTP_CHIFFREMENT'    => 'ssl', // 'ssl', 'tls' ou 'aucun'
    'SMTP_UTILISATEUR'    => 'ne-pas-repondre@exemple.org',
    'SMTP_MDP'            => 'mot-de-passe',
    'MAIL_EXPEDITEUR'     => 'ne-pas-repondre@exemple.org',
    'MAIL_EXPEDITEUR_NOM' => 'Association',
];
