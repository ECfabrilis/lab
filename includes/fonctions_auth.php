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
/** Longueur minimale d'un nouveau mot de passe (les mots de passe déjà choisis restent valables). */
const MDP_LONGUEUR_MIN = 12;
/** Nombre d'échecs venant d'une même adresse IP, sur la durée ci-dessous, avant de refuser ses essais. */
const AUTH_IP_ECHECS_MAX = 10;
/** Durée sur laquelle les échecs d'une adresse IP sont comptés, et durée du refus, en minutes. */
const AUTH_IP_MINUTES = 15;
/** Durée de conservation des échecs par adresse IP, en heures. */
const AUTH_IP_CONSERVATION_HEURES = 24;
/** Délai minimal entre deux courriels « mot de passe oublié » pour un même compte, en minutes. */
const MDP_OUBLIE_DELAI_MINUTES = 10;
/** Validité du lien d'invitation : 7 jours. */
const JETON_DUREE_INVITATION = 604800;
/** Validité du lien « mot de passe oublié » : 1 heure. */
const JETON_DUREE_OUBLI = 3600;

/**
 * Utilisateur connecté (ligne de la table utilisateurs, sans mot de passe ni jeton), ou null.
 * Relu dans la base à chaque page : une désactivation, un changement de rôle ou un changement de
 * mot de passe s'applique tout de suite (un nouveau mot de passe ferme les autres sessions).
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
            'SELECT id, prenom, mail, role, est_actif, derniere_connexion, SHA2(mdp, 256) AS empreinte_mdp
               FROM utilisateurs WHERE id = ? AND est_actif = 1',
            [$id]
        );
        // Le mot de passe a changé depuis l'ouverture de cette session (changement
        // ou réinitialisation faits ailleurs) : la session n'est plus valable.
        if ($cache !== null && !hash_equals(texte($cache['empreinte_mdp']), texte($_SESSION['empreinte_mdp'] ?? ''))) {
            $cache = null;
        }
        if ($cache === null) {
            unset($_SESSION['utilisateur_id'], $_SESSION['empreinte_mdp']);
        } else {
            unset($cache['empreinte_mdp']);
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
    // Trop d'échecs depuis cette adresse IP : l'essai est refusé sans être examiné.
    if (ip_bloquee()) {
        return 'bloque';
    }
    $utilisateur = db_ligne('SELECT * FROM utilisateurs WHERE mail = ?', [$mail]);

    if ($utilisateur === null) {
        // Même durée de calcul que pour un compte existant (on ne révèle pas quels comptes existent).
        password_verify($mdp, '$2y$12$59gNDzACFhyQOGJr0jzUfuFeK2i.PPS8kr38tFZ.uJXTnZhkwlsn.');
        ip_noter_echec();
        journaliser('connexion_echec', 'utilisateurs', null, 'Connexion refusée : adresse inconnue');
        return 'echec';
    }

    $id = entier($utilisateur['id']);
    $bloque_jusqua = texte($utilisateur['verrouille_jusqua']);
    if ($bloque_jusqua !== '' && strtotime($bloque_jusqua) > time()) {
        ip_noter_echec();
        return 'bloque';
    }

    if (entier($utilisateur['est_actif']) !== 1 || !password_verify($mdp, texte($utilisateur['mdp']))) {
        ip_noter_echec();
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
    session_noter_mdp($id);
    ip_purger_echecs();
    db_executer(
        'UPDATE utilisateurs SET tentatives_echec = 0, verrouille_jusqua = NULL, derniere_connexion = ? WHERE id = ?',
        [date('Y-m-d H:i:s'), $id]
    );
    journaliser('connexion', 'utilisateurs', $id, 'Connexion');
    journal_purger_saisons_passees();
    return 'ok';
}

/**
 * Note dans la session l'empreinte du mot de passe de l'utilisateur. Elle est
 * comparée à chaque page (utilisateur_courant()) : si le mot de passe change
 * ailleurs, les sessions ouvertes avec l'ancien se ferment. À appeler à la
 * connexion, et quand l'utilisateur change lui-même son mot de passe.
 */
function session_noter_mdp(int $utilisateur_id): void
{
    $_SESSION['empreinte_mdp'] = texte(db_valeur('SELECT SHA2(mdp, 256) FROM utilisateurs WHERE id = ?', [$utilisateur_id]));
}

/** Adresse IP du visiteur, telle que le serveur la voit (aucun en-tête fourni par le visiteur n'est cru). */
function adresse_ip(): string
{
    return mb_substr(texte($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Vrai si cette adresse IP a fait trop d'essais refusés (connexion, mot de
 * passe oublié) dans les dernières minutes : ses essais sont alors ignorés.
 */
function ip_bloquee(): bool
{
    $ip = adresse_ip();
    if ($ip === '') {
        return false;
    }
    $depuis = date('Y-m-d H:i:s', time() - AUTH_IP_MINUTES * 60);
    try {
        return entier(db_valeur('SELECT COUNT(*) FROM connexions_echecs WHERE adresse_ip = ? AND date_echec > ?', [$ip, $depuis])) >= AUTH_IP_ECHECS_MAX;
    } catch (PDOException) {
        // Table absente : la migration qui la crée n'est pas encore passée. Il faut
        // pouvoir se connecter pour la passer : la limite ne s'applique pas encore.
        return false;
    }
}

/** Note un essai refusé pour l'adresse IP du visiteur. */
function ip_noter_echec(): void
{
    $ip = adresse_ip();
    if ($ip === '') {
        return;
    }
    try {
        db_executer('INSERT INTO connexions_echecs (adresse_ip, date_echec) VALUES (?, ?)', [$ip, date('Y-m-d H:i:s')]);
    } catch (PDOException) {
        // Table absente avant la migration (voir ip_bloquee()).
    }
}

/** Efface les essais refusés trop anciens : une adresse IP n'est gardée que 24 heures. */
function ip_purger_echecs(): void
{
    try {
        db_executer('DELETE FROM connexions_echecs WHERE date_echec < ?', [date('Y-m-d H:i:s', time() - AUTH_IP_CONSERVATION_HEURES * 3600)]);
    } catch (PDOException) {
        // Table absente avant la migration (voir ip_bloquee()).
    }
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
 * @param string $prenom prénom de l'utilisateur : le mot de passe ne doit pas le contenir
 * @param string $mail   son adresse e-mail : le mot de passe ne doit pas en contenir la partie avant « @ »
 * @return string|null message d'erreur, ou null si tout va bien
 */
function erreur_mdp(string $mdp, string $confirmation, string $prenom = '', string $mail = ''): ?string
{
    if (mb_strlen($mdp) < MDP_LONGUEUR_MIN) {
        return 'Le mot de passe doit contenir au moins ' . MDP_LONGUEUR_MIN . ' caractères.';
    }
    if (strlen($mdp) > 72) {
        return 'Le mot de passe est trop long (72 caractères au maximum).';
    }
    // Un mot de passe bâti sur le prénom ou l'adresse est le premier qu'on essaie.
    foreach ([$prenom, explode('@', $mail)[0]] as $interdit) {
        if (mb_strlen($interdit) >= 3 && mb_stripos($mdp, $interdit) !== false) {
            return 'Le mot de passe ne doit contenir ni votre prénom ni votre adresse e-mail.';
        }
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

/**
 * Protège une petite page d'aide à la saisie (réponse JSON à un envoi POST) :
 * utilisateur connecté, rôle minimal, jeton CSRF. Sinon, réponse 403 et arrêt.
 * Ces pages ne suivent pas Post/Redirect/Get : elles n'affichent rien, elles
 * répondent à une demande faite par le JavaScript d'un formulaire.
 */
function api_exiger(string $role = 'standard'): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (utilisateur_courant() === null || !role_au_moins($role) || !est_post() || !csrf_valide()) {
        http_response_code(403);
        echo json_encode(['erreur' => 'Accès refusé.']);
        exit;
    }
}
