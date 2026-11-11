<?php

declare(strict_types=1);

/**
 * Connexion, déconnexion, contrôle des rôles, mots de passe et jetons
 * (invitation d'un nouvel utilisateur, mot de passe oublié).
 */

/** Nombre d'échecs de connexion consécutifs avant le blocage du compte. */
const AUTH_ECHECS_MAX = 5;
/** Durée du blocage du compte, en minutes. */
const AUTH_BLOCAGE_MINUTES = 15;
/** Durée d'inactivité avant déconnexion automatique, en secondes (2 heures). */
const AUTH_INACTIVITE_SECONDES = 7200;
/** Longueur minimale d'un mot de passe. */
const MDP_LONGUEUR_MIN = 8;
/** Validité du lien d'invitation : 7 jours. */
const JETON_DUREE_INVITATION = 604800;
/** Validité du lien « mot de passe oublié » : 1 heure. */
const JETON_DUREE_OUBLI = 3600;

/**
 * Utilisateur connecté (ligne de la table utilisateurs, sans mot de passe ni jeton), ou null.
 * Relu dans la base à chaque page : une désactivation ou un changement de rôle s'applique tout de suite.
 *
 * @return array<string, mixed>|null
 */
function utilisateur_courant(): ?array
{
    /** @var array<string, mixed>|false|null $cache */
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    $id = entier($_SESSION['utilisateur_id'] ?? 0);
    if ($id > 0) {
        $cache = db_ligne(
            'SELECT id, prenom, mail, role, est_actif, derniere_connexion FROM utilisateurs WHERE id = ? AND est_actif = 1',
            [$id]
        );
        if ($cache === null) {
            unset($_SESSION['utilisateur_id']);
        }
    }
    return $cache;
}

/** Rang d'un rôle : lecteur (1) < standard (2) < admin (3). */
function rang_role(string $role): int
{
    return match ($role) {
        'admin'    => 3,
        'standard' => 2,
        'lecteur'  => 1,
        default    => 0,
    };
}

/** Vrai si l'utilisateur connecté a au moins le rôle demandé. */
function role_au_moins(string $role): bool
{
    $utilisateur = utilisateur_courant();
    return $utilisateur !== null && rang_role(texte($utilisateur['role'])) >= rang_role($role);
}

/** Réserve la page aux utilisateurs connectés ; sinon, renvoi vers la connexion. */
function exiger_connexion(): void
{
    $derniere = entier($_SESSION['derniere_activite'] ?? 0);
    if (utilisateur_courant() !== null && $derniere > 0 && time() - $derniere > AUTH_INACTIVITE_SECONDES) {
        deconnecter();
        flash('info', 'Vous avez été déconnecté après une longue période d\'inactivité.');
        rediriger('connexion.php');
    }
    if (utilisateur_courant() === null) {
        rediriger('connexion.php');
    }
    $_SESSION['derniere_activite'] = time();
}

/** Réserve la page à un rôle minimal (vérification côté serveur). */
function exiger_role(string $role): void
{
    exiger_connexion();
    if (!role_au_moins($role)) {
        flash('erreur', 'Vous n\'avez pas les droits nécessaires pour cette page.');
        rediriger('index.php');
    }
}

/**
 * Tente une connexion.
 *
 * @return 'ok'|'echec'|'bloque'
 */
function tenter_connexion(string $mail, string $mdp): string
{
    $utilisateur = db_ligne('SELECT * FROM utilisateurs WHERE mail = ?', [$mail]);

    if ($utilisateur === null) {
        // Même durée de calcul que pour un compte existant (on ne révèle pas quels comptes existent).
        password_verify($mdp, '$2y$12$59gNDzACFhyQOGJr0jzUfuFeK2i.PPS8kr38tFZ.uJXTnZhkwlsn.');
        journaliser('connexion_echec', 'utilisateurs', null, 'Connexion refusée : adresse inconnue');
        return 'echec';
    }

    $id = entier($utilisateur['id']);
    $bloque_jusqua = texte($utilisateur['verrouille_jusqua']);
    if ($bloque_jusqua !== '' && strtotime($bloque_jusqua) > time()) {
        return 'bloque';
    }

    if (entier($utilisateur['est_actif']) !== 1 || !password_verify($mdp, texte($utilisateur['mdp']))) {
        $echecs = entier($utilisateur['tentatives_echec']) + 1;
        if ($echecs >= AUTH_ECHECS_MAX) {
            db_executer(
                'UPDATE utilisateurs SET tentatives_echec = 0, verrouille_jusqua = ? WHERE id = ?',
                [date('Y-m-d H:i:s', time() + AUTH_BLOCAGE_MINUTES * 60), $id]
            );
            journaliser('blocage', 'utilisateurs', $id, 'Compte bloqué ' . AUTH_BLOCAGE_MINUTES . ' minutes après ' . AUTH_ECHECS_MAX . ' échecs');
            return 'bloque';
        }
        db_executer('UPDATE utilisateurs SET tentatives_echec = ? WHERE id = ?', [$echecs, $id]);
        journaliser('connexion_echec', 'utilisateurs', $id, 'Connexion refusée');
        return 'echec';
    }

    // Connexion réussie : nouvel identifiant de session (contre la fixation de session).
    session_regenerate_id(true);
    $_SESSION['utilisateur_id'] = $id;
    $_SESSION['derniere_activite'] = time();
    if (password_needs_rehash(texte($utilisateur['mdp']), PASSWORD_DEFAULT)) {
        db_executer('UPDATE utilisateurs SET mdp = ? WHERE id = ?', [password_hash($mdp, PASSWORD_DEFAULT), $id]);
    }
    db_executer(
        'UPDATE utilisateurs SET tentatives_echec = 0, verrouille_jusqua = NULL, derniere_connexion = ? WHERE id = ?',
        [date('Y-m-d H:i:s'), $id]
    );
    journaliser('connexion', 'utilisateurs', $id, 'Connexion');
    return 'ok';
}

/** Déconnecte l'utilisateur et repart d'une session vide. */
function deconnecter(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Vérifie un nouveau mot de passe et sa confirmation.
 *
 * @return string|null message d'erreur, ou null si tout va bien
 */
function erreur_mdp(string $mdp, string $confirmation): ?string
{
    if (mb_strlen($mdp) < MDP_LONGUEUR_MIN) {
        return 'Le mot de passe doit contenir au moins ' . MDP_LONGUEUR_MIN . ' caractères.';
    }
    if (strlen($mdp) > 72) {
        return 'Le mot de passe est trop long (72 caractères au maximum).';
    }
    if ($mdp !== $confirmation) {
        return 'Les deux mots de passe ne sont pas identiques.';
    }
    return null;
}

/** Enregistre un nouveau mot de passe ; le jeton et le blocage éventuels sont effacés. */
function definir_mdp(int $utilisateur_id, string $mdp): void
{
    db_executer(
        'UPDATE utilisateurs
            SET mdp = ?, token = NULL, date_expiration_token = NULL, tentatives_echec = 0, verrouille_jusqua = NULL
          WHERE id = ?',
        [password_hash($mdp, PASSWORD_DEFAULT), $utilisateur_id]
    );
}

/**
 * Crée un jeton pour un utilisateur et renvoie sa valeur en clair (à mettre dans le lien).
 * Seul le hash SHA-256 est conservé dans la base.
 */
function creer_jeton(int $utilisateur_id, int $duree_secondes): string
{
    $jeton = bin2hex(random_bytes(32));
    db_executer(
        'UPDATE utilisateurs SET token = ?, date_expiration_token = ? WHERE id = ?',
        [hash('sha256', $jeton), date('Y-m-d H:i:s', time() + $duree_secondes), $utilisateur_id]
    );
    return $jeton;
}

/**
 * Utilisateur actif correspondant à un jeton encore valable, ou null.
 *
 * @return array<string, mixed>|null
 */
function utilisateur_par_jeton(string $jeton): ?array
{
    if (preg_match('/^[0-9a-f]{64}$/', $jeton) !== 1) {
        return null;
    }
    return db_ligne(
        'SELECT id, prenom, mail, role FROM utilisateurs
          WHERE token = ? AND date_expiration_token > ? AND est_actif = 1',
        [hash('sha256', $jeton), date('Y-m-d H:i:s')]
    );
}
