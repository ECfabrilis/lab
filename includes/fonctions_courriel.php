<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as ExceptionCourriel;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi des courriels avec PHPMailer (SMTP).
 * En local, le serveur SMTP est Mailpit ; en production, celui de l'hébergeur.
 */

/**
 * Réglages SMTP lus dans config.php.
 *
 * @return array{hote: string, port: int, chiffrement: string, utilisateur: string, mdp: string, expediteur: string, expediteur_nom: string}
 */
function reglages_smtp(): array
{
    return [
        'hote'           => texte(config('SMTP_HOTE')),
        'port'           => entier(config('SMTP_PORT')),
        'chiffrement'    => texte(config('SMTP_CHIFFREMENT')),
        'utilisateur'    => texte(config('SMTP_UTILISATEUR')),
        'mdp'            => texte(config('SMTP_MDP')),
        'expediteur'     => texte(config('MAIL_EXPEDITEUR')),
        'expediteur_nom' => texte(config('MAIL_EXPEDITEUR_NOM')),
    ];
}

/**
 * Envoie un courriel avec des réglages SMTP donnés.
 *
 * @param array{hote: string, port: int, chiffrement: string, utilisateur: string, mdp: string, expediteur: string, expediteur_nom: string} $smtp
 * @return string|null message d'erreur technique, ou null si le courriel est parti
 */
function envoyer_courriel_smtp(array $smtp, string $destinataire, string $sujet, string $texte): ?string
{
    if (!class_exists(PHPMailer::class)) {
        return 'PHPMailer est absent (dossier vendor/ manquant : lancer « composer install --no-dev »).';
    }
    $courriel = new PHPMailer(true);
    try {
        $courriel->isSMTP();
        $courriel->Host = $smtp['hote'];
        $courriel->Port = $smtp['port'];
        $courriel->Timeout = 10;
        $courriel->CharSet = PHPMailer::CHARSET_UTF8;
        if ($smtp['chiffrement'] === 'tls') {
            $courriel->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtp['chiffrement'] === 'ssl') {
            $courriel->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $courriel->SMTPSecure = '';
            $courriel->SMTPAutoTLS = false;
        }
        if ($smtp['utilisateur'] !== '') {
            $courriel->SMTPAuth = true;
            $courriel->Username = $smtp['utilisateur'];
            $courriel->Password = $smtp['mdp'];
        }
        $courriel->setFrom($smtp['expediteur'], $smtp['expediteur_nom']);
        $courriel->addAddress($destinataire);
        $courriel->Subject = $sujet;
        $courriel->isHTML(true);
        $courriel->Body = '<div style="font-family: Arial, sans-serif; font-size: 15px;">' . nl2br(e($texte)) . '</div>';
        $courriel->AltBody = $texte;
        $courriel->send();
        return null;
    } catch (ExceptionCourriel $exception) {
        return $courriel->ErrorInfo !== '' ? $courriel->ErrorInfo : $exception->getMessage();
    }
}

/** Envoie un courriel avec les réglages de l'application. Les échecs sont notés dans logs/courriel.log. */
function envoyer_courriel(string $destinataire, string $sujet, string $texte): bool
{
    $erreur = envoyer_courriel_smtp(reglages_smtp(), $destinataire, $sujet, $texte);
    if ($erreur !== null) {
        journal_fichier('courriel.log', 'Échec de l\'envoi « ' . $sujet . ' » : ' . $erreur);
        return false;
    }
    return true;
}

/** Courriel d'invitation : le nouvel utilisateur choisit son mot de passe (lien valable 7 jours). */
function envoyer_invitation(int $utilisateur_id, string $prenom, string $mail): bool
{
    $jeton = creer_jeton($utilisateur_id, JETON_DUREE_INVITATION);
    $nom_association = parametre('nom_association', 'l\'association');
    $texte = "Bonjour $prenom,\n\n"
        . "Un compte vient d'être créé pour vous dans l'application de gestion de $nom_association.\n\n"
        . "Pour choisir votre mot de passe, ouvrez ce lien (valable 7 jours) :\n"
        . chemin_site('choix_mdp.php?jeton=' . $jeton) . "\n\n"
        . "Votre identifiant est votre adresse e-mail : $mail\n\n"
        . "Si vous n'attendiez pas ce message, vous pouvez l'ignorer.";
    return envoyer_courriel($mail, 'Votre compte — ' . $nom_association, $texte);
}

/**
 * Adresses qui reçoivent la confirmation d'inscription : celle de l'adhérent
 * et celle de son responsable (personne mineure), quand elles sont renseignées.
 *
 * @param array<string, mixed> $fiche fiche de l'adhérent
 * @return list<string> adresses sans doublon
 */
function destinataires_confirmation(array $fiche): array
{
    $adresses = [];
    foreach ([texte($fiche['mail'] ?? ''), texte($fiche['responsable_mail'] ?? '')] as $adresse) {
        if ($adresse !== '' && !in_array(mb_strtolower($adresse), array_map('mb_strtolower', $adresses), true)) {
            $adresses[] = $adresse;
        }
    }
    return $adresses;
}

/**
 * Courriel de confirmation d'inscription pour une saison.
 * L'adhérent est salué par son prénom ; son responsable (ou le contact d'une
 * structure) reçoit un message qui nomme la personne inscrite.
 *
 * @param array<string, mixed> $fiche fiche de l'adhérent inscrit
 */
function envoyer_confirmation_inscription(string $destinataire, array $fiche, string $saison_nom): bool
{
    $nom_association = parametre('nom_association', 'l\'association');
    $prenom = texte($fiche['prenom'] ?? '');
    $pour_adherent = $prenom !== '' && mb_strtolower($destinataire) === mb_strtolower(texte($fiche['mail'] ?? ''));
    $texte = ($pour_adherent
            ? "Bonjour $prenom,\n\nVotre inscription à $nom_association pour la saison $saison_nom est enregistrée."
            : "Bonjour,\n\nL'inscription de " . nom_adherent($fiche) . " à $nom_association pour la saison $saison_nom est enregistrée.")
        . "\n\nÀ bientôt,\n$nom_association";
    return envoyer_courriel($destinataire, 'Confirmation d\'inscription — ' . $nom_association, $texte);
}

/** Courriel « mot de passe oublié » (lien valable 1 heure). */
function envoyer_lien_mdp_oublie(int $utilisateur_id, string $prenom, string $mail): bool
{
    $jeton = creer_jeton($utilisateur_id, JETON_DUREE_OUBLI);
    $nom_association = parametre('nom_association', 'l\'association');
    $texte = "Bonjour $prenom,\n\n"
        . "Vous avez demandé à choisir un nouveau mot de passe.\n\n"
        . "Ouvrez ce lien (valable 1 heure) :\n"
        . chemin_site('choix_mdp.php?jeton=' . $jeton) . "\n\n"
        . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.";
    return envoyer_courriel($mail, 'Nouveau mot de passe — ' . $nom_association, $texte);
}
