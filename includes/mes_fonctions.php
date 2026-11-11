<?php

declare(strict_types=1);

/**
 * Fonctions réutilisables par toute l'application :
 * configuration, base de données, affichage, redirections, messages,
 * formulaires (CSRF, jeton à usage unique, valeurs saisies), paramètres, journal.
 */

// ---------------------------------------------------------------------------
// Conversions sûres (les données de la base, de la session et des formulaires
// arrivent sans type garanti)
// ---------------------------------------------------------------------------

/** Convertit une valeur quelconque en texte ('' si ce n'est pas une valeur simple). */
function texte(mixed $valeur): string
{
    return is_scalar($valeur) ? (string) $valeur : '';
}

/** Convertit une valeur quelconque en entier (0 si ce n'est pas un nombre). */
function entier(mixed $valeur): int
{
    return is_numeric($valeur) ? (int) $valeur : 0;
}

/**
 * Texte ramené à sa forme la plus simple pour comparer deux noms :
 * minuscules, sans accents, sans ponctuation ni espaces en double.
 */
function texte_simplifie(string $texte): string
{
    $texte = mb_strtolower(trim($texte));
    $texte = strtr($texte, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ñ' => 'n',
        'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ý' => 'y',
        'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
    ]);
    $texte = preg_replace('/[^a-z0-9]+/', ' ', $texte) ?? '';
    return trim($texte);
}

/** Échappe un texte avant de l'écrire dans la page. */
function e(mixed $valeur): string
{
    return htmlspecialchars(texte($valeur), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------------------
// Configuration (config.php, écrit par install.php)
// ---------------------------------------------------------------------------

/** Chemin du fichier de configuration, dans la partie privée. */
function config_chemin(): string
{
    return DOSSIER_PRIVE . '/config.php';
}

/**
 * Lit config.php sans mise en cache.
 *
 * @return array<string, string|int|bool>|null null si le fichier est absent ou n'a pas le format attendu
 */
function config_lire(): ?array
{
    $chemin = config_chemin();
    if (!is_file($chemin)) {
        return null;
    }
    // Le fichier vient d'être réécrit par install.php : on force sa relecture.
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($chemin, true);
    }
    $contenu = require $chemin;
    if (!is_array($contenu)) {
        return null;
    }
    $valeurs = [];
    foreach ($contenu as $cle => $valeur) {
        if (is_string($cle) && (is_string($valeur) || is_int($valeur) || is_bool($valeur))) {
            $valeurs[$cle] = $valeur;
        }
    }
    return array_key_exists('INSTALLATION_TERMINEE', $valeurs) ? $valeurs : null;
}

/** Renvoie un réglage de config.php ('' s'il n'existe pas). */
function config(string $cle): string|int|bool
{
    /** @var array<string, string|int|bool>|null $valeurs */
    static $valeurs = null;
    if ($valeurs === null) {
        $valeurs = config_lire() ?? [];
    }
    return $valeurs[$cle] ?? '';
}

// ---------------------------------------------------------------------------
// Base de données
// ---------------------------------------------------------------------------

/** Ouvre une connexion PDO à MariaDB. Lève une PDOException en cas d'échec. */
function db_connecter(string $hote, int $port, string $base, string $utilisateur, string $mdp): PDO
{
    $dsn = 'mysql:host=' . $hote . ';port=' . $port . ';dbname=' . $base . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $utilisateur, $mdp, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Même fuseau horaire que PHP, pour que NOW() et date() donnent la même heure.
    $requete = $pdo->prepare('SET time_zone = ?');
    $requete->execute([date('P')]);
    return $pdo;
}

/** Connexion à la base de l'application (une seule par requête HTTP). */
function db(): PDO
{
    /** @var PDO|null $pdo */
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_connecter(
            texte(config('DB_HOTE')),
            entier(config('DB_PORT')),
            texte(config('DB_NOM')),
            texte(config('DB_UTILISATEUR')),
            texte(config('DB_MDP'))
        );
    }
    return $pdo;
}

/**
 * Prépare et exécute une requête.
 *
 * @param array<int|string, string|int|float|null> $parametres
 */
function db_executer(string $sql, array $parametres = []): PDOStatement
{
    $requete = db()->prepare($sql);
    $requete->execute($parametres);
    return $requete;
}

/**
 * Première ligne du résultat, ou null.
 *
 * @param array<int|string, string|int|float|null> $parametres
 * @return array<string, mixed>|null
 */
function db_ligne(string $sql, array $parametres = []): ?array
{
    $ligne = db_executer($sql, $parametres)->fetch();
    if (!is_array($ligne)) {
        return null;
    }
    /** @var array<string, mixed> $ligne */
    return $ligne;
}

/**
 * Toutes les lignes du résultat.
 *
 * @param array<int|string, string|int|float|null> $parametres
 * @return list<array<string, mixed>>
 */
function db_lignes(string $sql, array $parametres = []): array
{
    /** @var list<array<string, mixed>> $lignes */
    $lignes = db_executer($sql, $parametres)->fetchAll();
    return $lignes;
}

/**
 * Première colonne de la première ligne (null s'il n'y a pas de ligne).
 *
 * @param array<int|string, string|int|float|null> $parametres
 */
function db_valeur(string $sql, array $parametres = []): mixed
{
    $valeur = db_executer($sql, $parametres)->fetchColumn();
    return $valeur === false ? null : $valeur;
}

// ---------------------------------------------------------------------------
// Session et en-têtes de sécurité
// ---------------------------------------------------------------------------

/** Indique si la page est servie en HTTPS. */
function est_https(): bool
{
    $https = texte($_SERVER['HTTPS'] ?? '');
    return ($https !== '' && $https !== 'off') || texte($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Démarre une session sécurisée (cookie HttpOnly, SameSite, Secure en HTTPS). */
function demarrer_session(string $chemin_cookie = '/'): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bddv3');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $chemin_cookie,
        'secure'   => est_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Envoie les en-têtes HTTP de sécurité communs à toutes les pages. */
function envoyer_entetes_securite(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
        . "script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    // Les pages contiennent des données personnelles : pas de copie en cache.
    header('Cache-Control: no-store, private');
    if (est_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

// ---------------------------------------------------------------------------
// Liens et redirections
// ---------------------------------------------------------------------------

/** Nom du fichier de la page en cours (exemple : 'saisons.php'). */
function page_courante(): string
{
    return basename(texte($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

/**
 * Adresse complète d'une page ou d'une ressource du site.
 * Le site est dans un sous-dossier : on part toujours de APP_URL.
 */
function chemin_site(string $chemin = ''): string
{
    return rtrim(texte(config('APP_URL')), '/') . '/' . ltrim($chemin, '/');
}

/** Redirige vers une page du site (303 : Post/Redirect/Get) et arrête le script. */
function rediriger(string $chemin): never
{
    header('Location: ' . chemin_site($chemin), true, 303);
    exit;
}

// ---------------------------------------------------------------------------
// Messages à l'utilisateur
// ---------------------------------------------------------------------------

/** Enregistre un message affiché sur la page suivante. Types : succes, avertissement, erreur, info. */
function flash(string $type, string $message): void
{
    $liste = $_SESSION['flash'] ?? [];
    if (!is_array($liste)) {
        $liste = [];
    }
    $liste[] = ['type' => $type, 'message' => $message];
    $_SESSION['flash'] = $liste;
}

/**
 * Renvoie les messages en attente et les efface.
 *
 * @return list<array{type: string, classe: string, message: string}>
 */
function flash_lire(): array
{
    $liste = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    $messages = [];
    if (is_array($liste)) {
        foreach ($liste as $element) {
            if (!is_array($element)) {
                continue;
            }
            $type = texte($element['type'] ?? 'info');
            $messages[] = [
                'type'    => $type,
                'classe'  => match ($type) {
                    'succes'        => 'success',
                    'avertissement' => 'warning',
                    'erreur'        => 'danger',
                    default         => 'info',
                },
                'message' => texte($element['message'] ?? ''),
            ];
        }
    }
    return $messages;
}

// ---------------------------------------------------------------------------
// Formulaires
// ---------------------------------------------------------------------------

/** Vrai si la page est appelée par l'envoi d'un formulaire. */
function est_post(): bool
{
    return texte($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Valeur d'un champ envoyé, sans espaces autour ('' si absent). */
function post(string $cle): string
{
    $valeur = $_POST[$cle] ?? '';
    // mb_scrub retire les octets qui ne sont pas de l'UTF-8 valide (la base les refuserait).
    return is_string($valeur) ? trim(mb_scrub($valeur, 'UTF-8')) : '';
}

/** Valeur d'un champ envoyé, telle quelle (pour les mots de passe). */
function post_brut(string $cle): string
{
    $valeur = $_POST[$cle] ?? '';
    return is_string($valeur) ? $valeur : '';
}

/** Jeton CSRF de la session (créé au premier appel). */
function csrf_jeton(): string
{
    $jeton = texte($_SESSION['csrf'] ?? '');
    if ($jeton === '') {
        $jeton = bin2hex(random_bytes(32));
        $_SESSION['csrf'] = $jeton;
    }
    return $jeton;
}

/** Champ caché à placer dans chaque formulaire. */
function csrf_champ(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_jeton()) . '">';
}

/** Vrai si le jeton CSRF envoyé est celui de la session. */
function csrf_valide(): bool
{
    return hash_equals(csrf_jeton(), post_brut('csrf'));
}

/** Vérifie le jeton CSRF ; en cas d'échec, message d'erreur et retour à la page. */
function csrf_verifier(): void
{
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger(page_courante());
    }
}

/**
 * Champ caché anti-double envoi pour les formulaires de création.
 * Chaque affichage du formulaire reçoit un jeton utilisable une seule fois.
 */
function jeton_unique_champ(string $nom): string
{
    $tous = $_SESSION['jetons_uniques'] ?? [];
    if (!is_array($tous)) {
        $tous = [];
    }
    $jetons = $tous[$nom] ?? [];
    if (!is_array($jetons)) {
        $jetons = [];
    }
    $jeton = bin2hex(random_bytes(16));
    $jetons[$jeton] = time();
    // On ne garde que les 20 derniers (plusieurs onglets ouverts).
    $tous[$nom] = array_slice($jetons, -20, null, true);
    $_SESSION['jetons_uniques'] = $tous;
    return '<input type="hidden" name="jeton_unique" value="' . e($jeton) . '">';
}

/** Vrai si le jeton à usage unique est valable ; il est alors détruit. */
function jeton_unique_consommer(string $nom): bool
{
    $jeton = post_brut('jeton_unique');
    $tous = $_SESSION['jetons_uniques'] ?? [];
    if ($jeton === '' || !is_array($tous) || !is_array($tous[$nom] ?? null) || !isset($tous[$nom][$jeton])) {
        return false;
    }
    unset($tous[$nom][$jeton]);
    $_SESSION['jetons_uniques'] = $tous;
    return true;
}

/**
 * Mémorise les valeurs saisies pour les réafficher après la redirection.
 * Les mots de passe et les jetons ne sont jamais mémorisés.
 */
function ancien_memoriser(): void
{
    $valeurs = [];
    foreach ($_POST as $cle => $valeur) {
        $cle = (string) $cle;
        if (!is_string($valeur) || str_contains($cle, 'mdp') || $cle === 'csrf' || $cle === 'jeton_unique' || $cle === 'jeton') {
            continue;
        }
        $valeurs[$cle] = trim($valeur);
    }
    $_SESSION['ancien'] = ['page' => page_courante(), 'valeurs' => $valeurs];
}

/** Valeur saisie lors du dernier envoi de ce formulaire, sinon la valeur par défaut. */
function ancien(string $cle, mixed $defaut = ''): string
{
    $ancien = $_SESSION['ancien'] ?? null;
    if (is_array($ancien) && ($ancien['page'] ?? '') === page_courante() && is_array($ancien['valeurs'] ?? null)) {
        if (array_key_exists($cle, $ancien['valeurs'])) {
            return texte($ancien['valeurs'][$cle]);
        }
    }
    return texte($defaut);
}

/** Vrai si des valeurs saisies sont en attente de réaffichage pour cette page. */
function ancien_existe(): bool
{
    $ancien = $_SESSION['ancien'] ?? null;
    return is_array($ancien) && ($ancien['page'] ?? '') === page_courante();
}

/** Oublie les valeurs saisies (appelé en bas de chaque page affichée). */
function ancien_oublier(): void
{
    unset($_SESSION['ancien']);
}

// ---------------------------------------------------------------------------
// Dates
// ---------------------------------------------------------------------------

/** Date de la base (AAAA-MM-JJ ou AAAA-MM-JJ HH:MM:SS) au format JJ/MM/AAAA. */
function date_fr(mixed $date): string
{
    $date = texte($date);
    if ($date === '') {
        return '';
    }
    $horodatage = strtotime($date);
    return $horodatage === false ? '' : date('d/m/Y', $horodatage);
}

/** Date et heure de la base au format JJ/MM/AAAA à HH:MM. */
function date_heure_fr(mixed $date): string
{
    $date = texte($date);
    if ($date === '') {
        return '';
    }
    $horodatage = strtotime($date);
    return $horodatage === false ? '' : date('d/m/Y \à H:i', $horodatage);
}

/** Vrai si le texte est une date AAAA-MM-JJ qui existe. */
function date_valide(string $date): bool
{
    $objet = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $objet !== false && $objet->format('Y-m-d') === $date;
}

// ---------------------------------------------------------------------------
// Paramètres de l'application (table parametres)
// ---------------------------------------------------------------------------

/**
 * Tous les paramètres, lus une seule fois par requête.
 *
 * @return array<string, string>
 */
function parametres_tous(bool $relire = false): array
{
    /** @var array<string, string>|null $cache */
    static $cache = null;
    if ($cache === null || $relire) {
        $cache = [];
        foreach (db_lignes('SELECT cle, valeur FROM parametres') as $ligne) {
            $cache[texte($ligne['cle'])] = texte($ligne['valeur']);
        }
    }
    return $cache;
}

/** Valeur d'un paramètre, ou la valeur par défaut s'il est absent ou vide. */
function parametre(string $cle, string $defaut = ''): string
{
    $valeur = parametres_tous()[$cle] ?? '';
    return $valeur !== '' ? $valeur : $defaut;
}

/** Enregistre un paramètre (création ou modification). */
function parametre_enregistrer(string $cle, string $valeur, ?int $utilisateur_id = null): void
{
    db_executer(
        'INSERT INTO parametres (cle, valeur, modification_par) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), modification_par = VALUES(modification_par)',
        [$cle, $valeur, $utilisateur_id]
    );
    parametres_tous(true);
}

// ---------------------------------------------------------------------------
// Journal des actions (table logs_bdd)
// ---------------------------------------------------------------------------

/**
 * Note une action dans le journal. Les mots de passe et jetons sont retirés.
 *
 * @param array<string, mixed>|null $ancien  valeurs avant l'action
 * @param array<string, mixed>|null $nouveau valeurs après l'action
 */
function journaliser(
    string $type_action,
    string $nom_table,
    ?int $id_enregistrement,
    string $description,
    ?array $ancien = null,
    ?array $nouveau = null
): void {
    $nettoyer = static function (?array $donnees): ?string {
        if ($donnees === null) {
            return null;
        }
        unset($donnees['mdp'], $donnees['token'], $donnees['date_expiration_token']);
        $json = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? null : $json;
    };
    $utilisateur_id = entier($_SESSION['utilisateur_id'] ?? 0);
    db_executer(
        'INSERT INTO logs_bdd (utilisateur_id, type_action, nom_table, id_enregistrement, description, donnee_ancienne, donnee_nouvelle)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $utilisateur_id > 0 ? $utilisateur_id : null,
            $type_action,
            $nom_table,
            $id_enregistrement,
            mb_substr($description, 0, 255),
            $nettoyer($ancien),
            $nettoyer($nouveau),
        ]
    );
}

/** Écrit une ligne dans un fichier du dossier logs/ (erreurs techniques, sans secret). */
function journal_fichier(string $fichier, string $message): void
{
    $ligne = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
    @file_put_contents(DOSSIER_PRIVE . '/logs/' . basename($fichier), $ligne, FILE_APPEND | LOCK_EX);
}
