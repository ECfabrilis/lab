<?php

declare(strict_types=1);

/**
 * Mise à jour de la base : suivi des migrations (table migrations), détection
 * d'une base en retard sur le code, application des fichiers manquants.
 *
 * Après un dépôt de fichiers, si sql/migrations/ contient un fichier qui n'a
 * pas été passé, l'application est fermée à tous sauf aux administrateurs,
 * qui sont renvoyés vers mise_a_jour.php (voir mise_a_jour_controler()).
 */

require_once DOSSIER_PRIVE . '/includes/fonctions_installation.php';

/** Migration qui crée la table de suivi : passée d'office quand la table n'existe pas. */
const MIGRATION_TABLE_SUIVI = '2026-11-25_migrations.sql';

/**
 * Dernière migration passée à la main, avant l'existence du suivi. Sur une
 * base sans table de suivi, les fichiers jusqu'à celui-ci sont inscrits comme
 * déjà passés, sans être rejoués.
 */
const MIGRATIONS_REPRISES_JUSQUA = '2026-11-17_partenaire_activites.sql';

/** Pages qui restent ouvertes à tous pendant qu'une mise à jour est attendue. */
const MISE_A_JOUR_PAGES_OUVERTES = ['mise_a_jour.php', 'connexion.php', 'deconnexion.php', 'mdp_oublie.php', 'choix_mdp.php'];

/**
 * Fichiers de sql/migrations/, du plus ancien au plus récent (le nom commence
 * par la date : l'ordre alphabétique est l'ordre d'application).
 *
 * @return list<string>
 */
function migrations_fichiers(): array
{
    $fichiers = [];
    $noms = scandir(DOSSIER_PRIVE . '/sql/migrations');
    foreach ($noms === false ? [] : $noms as $nom) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}_[a-z0-9_]+\.sql$/', $nom) === 1) {
            $fichiers[] = $nom;
        }
    }
    sort($fichiers);
    return $fichiers;
}

/**
 * Vrai si la base est en retard sur le code : la dernière migration du dossier
 * n'est pas celle que la base a notée (réglage « derniere_migration », lu avec
 * les autres réglages : aucune requête de plus par page).
 */
function base_en_retard(): bool
{
    $fichiers = migrations_fichiers();
    return $fichiers !== [] && parametre('derniere_migration') !== $fichiers[count($fichiers) - 1];
}

/** Vrai si la table de suivi existe. */
function migrations_table_existe(PDO $pdo): bool
{
    $requete = $pdo->query("SHOW TABLES LIKE 'migrations'");
    return $requete !== false && $requete->fetchColumn() !== false;
}

/**
 * Migrations déjà inscrites dans la table de suivi, de la plus récente à la plus ancienne.
 *
 * @return list<array{fichier: string, appliquee_le: string, prenom: string, est_reprise: bool}>
 */
function migrations_passees(): array
{
    if (!migrations_table_existe(db())) {
        return [];
    }
    $passees = [];
    $lignes = db_lignes(
        'SELECT m.fichier, m.appliquee_le, m.est_reprise, u.prenom
           FROM migrations m
           LEFT JOIN utilisateurs u ON u.id = m.appliquee_par
          ORDER BY m.fichier DESC'
    );
    foreach ($lignes as $ligne) {
        $passees[] = [
            'fichier'      => texte($ligne['fichier']),
            'appliquee_le' => texte($ligne['appliquee_le']),
            'prenom'       => texte($ligne['prenom'] ?? ''),
            'est_reprise'  => entier($ligne['est_reprise']) === 1,
        ];
    }
    return $passees;
}

/**
 * Migrations à passer, dans l'ordre. Sans table de suivi : celles qui suivent
 * la dernière migration passée à la main.
 *
 * @return list<string>
 */
function migrations_en_attente(): array
{
    $fichiers = migrations_fichiers();
    if (!migrations_table_existe(db())) {
        return array_values(array_filter($fichiers, static fn (string $fichier): bool => $fichier > MIGRATIONS_REPRISES_JUSQUA));
    }
    $passees = array_column(migrations_passees(), 'fichier');
    return array_values(array_diff($fichiers, $passees));
}

/**
 * Inscrit des migrations dans la table de suivi (sans les exécuter).
 *
 * @param list<string> $fichiers
 * @param bool $reprise true : inscrites sans avoir été jouées par l'application
 */
function migrations_inscrire(PDO $pdo, array $fichiers, bool $reprise, ?int $utilisateur_id = null): void
{
    $requete = $pdo->prepare('INSERT IGNORE INTO migrations (fichier, appliquee_par, est_reprise) VALUES (?, ?, ?)');
    foreach ($fichiers as $fichier) {
        $requete->execute([$fichier, $utilisateur_id, $reprise ? 1 : 0]);
    }
}

/** Note dans les réglages la dernière migration du dossier : la base est à jour. */
function migrations_noter_a_jour(PDO $pdo): void
{
    $fichiers = migrations_fichiers();
    if ($fichiers === []) {
        return;
    }
    $pdo->prepare('INSERT INTO parametres (cle, valeur) VALUES (\'derniere_migration\', ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
        ->execute([$fichiers[count($fichiers) - 1]]);
}

/**
 * Installation (install.php, étape 3) : sql/structure.sql contient déjà toutes
 * les migrations, elles sont donc inscrites comme passées.
 */
function migrations_inscrire_toutes(PDO $pdo): void
{
    migrations_inscrire($pdo, migrations_fichiers(), true);
    migrations_noter_a_jour($pdo);
}

/**
 * Applique les migrations en attente, dans l'ordre, et s'arrête à la première
 * erreur. Un changement de structure ne s'annule pas : après une erreur, on
 * corrige puis on relance (les migrations sont relançables), ou on restaure la
 * sauvegarde faite juste avant.
 *
 * @return array{appliquees: list<string>, fichier_en_erreur: string, erreur: string} erreur : '' si tout est passé
 */
function mise_a_jour_appliquer(int $utilisateur_id): array
{
    $pdo = db();
    $appliquees = [];
    $courant = '';
    try {
        if (!migrations_table_existe($pdo)) {
            // Base d'avant le suivi : création de la table, puis reprise des migrations passées à la main.
            $courant = MIGRATION_TABLE_SUIVI;
            sql_executer_fichier($pdo, DOSSIER_PRIVE . '/sql/migrations/' . MIGRATION_TABLE_SUIVI);
            migrations_inscrire($pdo, array_values(array_filter(
                migrations_fichiers(),
                static fn (string $fichier): bool => $fichier <= MIGRATIONS_REPRISES_JUSQUA
            )), true, $utilisateur_id);
            migrations_inscrire($pdo, [MIGRATION_TABLE_SUIVI], false, $utilisateur_id);
            journaliser('mise_a_jour', 'migrations', null, 'Migration ' . MIGRATION_TABLE_SUIVI . ' appliquée');
            $appliquees[] = MIGRATION_TABLE_SUIVI;
        }
        foreach (migrations_en_attente() as $fichier) {
            $courant = $fichier;
            sql_executer_fichier($pdo, DOSSIER_PRIVE . '/sql/migrations/' . $fichier);
            migrations_inscrire($pdo, [$fichier], false, $utilisateur_id);
            journaliser('mise_a_jour', 'migrations', null, 'Migration ' . $fichier . ' appliquée');
            $appliquees[] = $fichier;
        }
        migrations_noter_a_jour($pdo);
        parametres_tous(true);
    } catch (Throwable $erreur) {
        journal_fichier('mise_a_jour.log', 'Migration ' . $courant . ' : ' . $erreur->getMessage());
        return ['appliquees' => $appliquees, 'fichier_en_erreur' => $courant, 'erreur' => $erreur->getMessage()];
    }
    return ['appliquees' => $appliquees, 'fichier_en_erreur' => '', 'erreur' => ''];
}

/**
 * Appelée par init.php sur chaque page. Tant que la base est en retard sur le
 * code : un administrateur est renvoyé vers mise_a_jour.php (il garde l'accès
 * à sauvegarde.php) ; les autres voient une page d'attente. La connexion reste
 * possible, pour qu'un administrateur puisse entrer.
 */
function mise_a_jour_controler(): void
{
    if (!base_en_retard()) {
        return;
    }
    $page = page_courante();
    if (in_array($page, MISE_A_JOUR_PAGES_OUVERTES, true)) {
        return;
    }
    if (utilisateur_courant() !== null && role_au_moins('admin')) {
        if ($page === 'sauvegarde.php') {
            return;
        }
        rediriger('mise_a_jour.php');
    }
    http_response_code(503);
    header('Retry-After: 600');
    if (str_starts_with($page, 'api_')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erreur' => 'Une mise à jour est en cours.']);
        exit;
    }
    // Page écrite ici, sans header.php : il lit des tables que la mise à jour peut modifier.
    // La déconnexion se fait par un formulaire (POST), comme dans le menu.
    $sortie = utilisateur_courant() !== null
        ? '<form method="post" action="' . e(chemin_site('deconnexion.php')) . '">' . csrf_champ()
            . '<button class="btn btn-outline-secondary" type="submit">Se déconnecter</button></form>'
        : '<p><a class="btn btn-outline-secondary" href="' . e(chemin_site('connexion.php')) . '">Se connecter</a></p>';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>Mise à jour en cours</title>'
        . '<link rel="stylesheet" href="' . e(chemin_site('css/bddv3.css')) . '"></head>'
        . '<body class="bg-body-tertiary"><main class="container py-5 text-center">'
        . '<h1 class="h3 mb-3">Mise à jour en cours</h1>'
        . '<p>L\'application est en cours de mise à jour. Elle sera de nouveau disponible dans quelques minutes.</p>'
        . '<p class="text-body-secondary">Un administrateur doit terminer la mise à jour.</p>'
        . $sortie
        . '</main></body></html>';
    exit;
}
