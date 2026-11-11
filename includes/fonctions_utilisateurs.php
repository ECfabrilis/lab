<?php

declare(strict_types=1);

/**
 * Gestion des comptes utilisateurs de l'application.
 */

/**
 * Libellés des rôles, du moins élevé au plus élevé.
 *
 * @return array<string, string>
 */
function libelles_roles(): array
{
    return [
        'lecteur'  => 'Lecteur',
        'standard' => 'Standard',
        'admin'    => 'Administrateur',
    ];
}

/**
 * Tous les utilisateurs, actifs d'abord, par prénom.
 *
 * @return list<array<string, mixed>>
 */
function utilisateurs_liste(): array
{
    return db_lignes(
        'SELECT id, prenom, mail, role, est_actif, derniere_connexion, date_crea,
                (token IS NOT NULL AND derniere_connexion IS NULL) AS invitation_en_attente
           FROM utilisateurs
          ORDER BY est_actif DESC, prenom, mail'
    );
}

/**
 * Un utilisateur par son identifiant (sans mot de passe ni jeton), ou null.
 *
 * @return array<string, mixed>|null
 */
function utilisateur_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT id, prenom, mail, role, est_actif, verrouille_jusqua, derniere_connexion, date_crea
           FROM utilisateurs WHERE id = ?',
        [$id]
    );
}

/** Vrai si l'adresse e-mail est déjà utilisée par un autre compte. */
function mail_utilisateur_pris(string $mail, int $sauf_id = 0): bool
{
    return db_valeur('SELECT COUNT(*) FROM utilisateurs WHERE mail = ? AND id <> ?', [$mail, $sauf_id]) > 0;
}

/** Nombre d'administrateurs actifs. */
function nb_admins_actifs(): int
{
    return entier(db_valeur("SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin' AND est_actif = 1"));
}

/**
 * Vérifie le prénom, l'adresse e-mail et le rôle d'un compte.
 *
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_utilisateur(string $prenom, string $mail, string $role, int $sauf_id = 0): array
{
    $erreurs = [];
    if ($prenom === '' || mb_strlen($prenom) > 100) {
        $erreurs[] = 'Le prénom est obligatoire (100 caractères au maximum).';
    }
    if (filter_var($mail, FILTER_VALIDATE_EMAIL) === false || mb_strlen($mail) > 150) {
        $erreurs[] = 'L\'adresse e-mail n\'est pas valide.';
    } elseif (mail_utilisateur_pris($mail, $sauf_id)) {
        $erreurs[] = 'Cette adresse e-mail est déjà utilisée par un autre compte.';
    }
    if (!array_key_exists($role, libelles_roles())) {
        $erreurs[] = 'Le rôle choisi n\'existe pas.';
    }
    return $erreurs;
}

/**
 * Crée un compte. Aucun mot de passe utilisable n'est attribué : l'utilisateur
 * le choisit grâce au lien reçu par courriel (ou $mdp est fourni par install.php).
 *
 * @return int identifiant du nouveau compte
 */
function creer_utilisateur(string $prenom, string $mail, string $role, ?string $mdp = null): int
{
    // Sans mot de passe fourni : hash d'une valeur aléatoire que personne ne connaît.
    $hash = password_hash($mdp ?? bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    db_executer(
        'INSERT INTO utilisateurs (prenom, mail, mdp, role) VALUES (?, ?, ?, ?)',
        [$prenom, $mail, $hash, $role]
    );
    return entier(db()->lastInsertId());
}
