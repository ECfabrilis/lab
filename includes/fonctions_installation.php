<?php

declare(strict_types=1);

/**
 * Fonctions de l'assistant d'installation (public/install.php) :
 * écriture de config.php, création de la structure, tables de référence.
 */

/** Tables de référence remplies à partir de sql/donnees_reference.sql. */
const TABLES_REFERENCE_SQL = ['pays', 'quartiers', 'quartiers_prioritaires', 'quartiers_qpv'];

/**
 * Écrit config.php dans la partie privée, avec des droits restreints.
 *
 * @param array<string, string|int|bool> $valeurs
 */
function installation_ecrire_config(array $valeurs): bool
{
    $contenu = "<?php\n\ndeclare(strict_types=1);\n\n"
        . "// Fichier écrit par install.php le " . date('d/m/Y à H:i') . ".\n"
        . "// Il contient des mots de passe : ne jamais le versionner ni le placer dans public/.\n"
        . "// Voir config.exemple.php pour la signification de chaque réglage.\n\n"
        . 'return ' . var_export($valeurs, true) . ";\n";

    $chemin = config_chemin();
    $temporaire = $chemin . '.tmp';
    if (@file_put_contents($temporaire, $contenu, LOCK_EX) === false) {
        return false;
    }
    @chmod($temporaire, 0600);
    if (!@rename($temporaire, $chemin)) {
        @unlink($temporaire);
        return false;
    }
    return true;
}

/** Adresse publique du site devinée à partir de la requête en cours (proposée à l'étape 2). */
function installation_url_detectee(): string
{
    $hote = texte($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dossier = str_replace('\\', '/', dirname(texte($_SERVER['SCRIPT_NAME'] ?? '/')));
    return (est_https() ? 'https' : 'http') . '://' . $hote . rtrim($dossier, '/');
}

/**
 * Découpe un script SQL en requêtes, en respectant les chaînes et les commentaires.
 *
 * @return list<string>
 */
function sql_decouper(string $sql): array
{
    $requetes = [];
    $courante = '';
    $longueur = strlen($sql);
    $i = 0;
    while ($i < $longueur) {
        // On saute directement au prochain caractère qui peut changer l'état.
        $saut = strcspn($sql, "'\"`;-#/", $i);
        $courante .= substr($sql, $i, $saut);
        $i += $saut;
        if ($i >= $longueur) {
            break;
        }
        $caractere = $sql[$i];
        $suivant = $i + 1 < $longueur ? $sql[$i + 1] : '';

        if ($caractere === ';') {
            if (trim($courante) !== '') {
                $requetes[] = trim($courante);
            }
            $courante = '';
            $i++;
        } elseif ($caractere === "'" || $caractere === '"' || $caractere === '`') {
            // Chaîne ou nom entre guillemets : on va jusqu'au guillemet fermant.
            $fin = $i + 1;
            while ($fin < $longueur) {
                if ($sql[$fin] === '\\' && $caractere !== '`') {
                    $fin += 2;
                } elseif ($sql[$fin] === $caractere) {
                    if ($fin + 1 < $longueur && $sql[$fin + 1] === $caractere) {
                        $fin += 2;
                    } else {
                        break;
                    }
                } else {
                    $fin++;
                }
            }
            $courante .= substr($sql, $i, $fin - $i + 1);
            $i = $fin + 1;
        } elseif ($caractere === '#' || ($caractere === '-' && $suivant === '-')) {
            // Commentaire jusqu'à la fin de la ligne.
            $fin = strpos($sql, "\n", $i);
            $i = $fin === false ? $longueur : $fin + 1;
        } elseif ($caractere === '/' && $suivant === '*') {
            $fin = strpos($sql, '*/', $i + 2);
            $i = $fin === false ? $longueur : $fin + 2;
        } else {
            $courante .= $caractere;
            $i++;
        }
    }
    if (trim($courante) !== '') {
        $requetes[] = trim($courante);
    }
    return $requetes;
}

/**
 * Exécute toutes les requêtes d'un fichier SQL.
 *
 * @return int nombre de requêtes exécutées
 */
function sql_executer_fichier(PDO $pdo, string $chemin): int
{
    $sql = file_get_contents($chemin);
    if ($sql === false) {
        throw new RuntimeException('Fichier illisible : ' . basename($chemin));
    }
    $requetes = sql_decouper($sql);
    foreach ($requetes as $requete) {
        $pdo->exec($requete);
    }
    return count($requetes);
}

/**
 * Noms des tables créées par sql/structure.sql, dans l'ordre du fichier.
 *
 * @return list<string>
 */
function installation_tables_attendues(): array
{
    $sql = file_get_contents(DOSSIER_PRIVE . '/sql/structure.sql');
    if ($sql === false) {
        return [];
    }
    preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_0-9]+)`/', $sql, $resultats);
    return $resultats[1];
}

/**
 * Noms des tables présentes dans la base.
 *
 * @return list<string>
 */
function installation_tables_existantes(PDO $pdo): array
{
    $tables = [];
    $requete = $pdo->query('SHOW TABLES');
    if ($requete !== false) {
        foreach ($requete->fetchAll(PDO::FETCH_COLUMN) as $nom) {
            $tables[] = texte($nom);
        }
    }
    return $tables;
}

/** Nombre de lignes d'une table (le nom vient toujours de la liste des tables attendues). */
function installation_compter(PDO $pdo, string $table): int
{
    $requete = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');
    return $requete === false ? 0 : entier($requete->fetchColumn());
}

/**
 * Supprime les tables de l'application (après confirmation explicite), dans l'ordre inverse de leur création.
 *
 * @param list<string> $tables
 */
function installation_supprimer_tables(PDO $pdo, array $tables): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (array_reverse($tables) as $table) {
        $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/**
 * Remplit pays, quartiers, QP et QPV à partir de sql/donnees_reference.sql.
 * Le fichier ouvre et valide lui-même une transaction.
 */
function installation_importer_reference(PDO $pdo): void
{
    try {
        sql_executer_fichier($pdo, DOSSIER_PRIVE . '/sql/donnees_reference.sql');
    } catch (Throwable $erreur) {
        $pdo->exec('ROLLBACK');
        throw $erreur;
    }
}

/**
 * Remplit codes_postaux à partir du fichier CSV de La Poste (sql/019HexaSmal.csv par défaut).
 * Colonnes : code INSEE ; commune ; code postal ; libellé d'acheminement ; ligne 5.
 *
 * @param string $chemin    autre fichier au même format (mise à jour depuis les paramètres)
 * @param bool   $remplacer true : la table est vidée avant l'import, dans la même transaction
 * @return int nombre de lignes lues
 */
function installation_importer_codes_postaux(PDO $pdo, string $chemin = '', bool $remplacer = false): int
{
    $fichier = fopen($chemin !== '' ? $chemin : DOSSIER_PRIVE . '/sql/019HexaSmal.csv', 'r');
    if ($fichier === false) {
        throw new RuntimeException('Fichier des codes postaux illisible.');
    }
    fgets($fichier); // ligne d'en-tête

    $nombre = 0;
    $lot = [];
    $inserer = static function (array $lot) use ($pdo): void {
        if ($lot === []) {
            return;
        }
        $requete = $pdo->prepare(
            'INSERT IGNORE INTO codes_postaux (code_insee, commune, code_postal, libelle_acheminement, ligne_5) VALUES '
            . implode(', ', array_fill(0, intdiv(count($lot), 5), '(?, ?, ?, ?, ?)'))
        );
        $requete->execute($lot);
    };

    $pdo->beginTransaction();
    try {
        if ($remplacer) {
            $pdo->exec('DELETE FROM codes_postaux');
        }
        while (($ligne = fgets($fichier)) !== false) {
            // Le fichier de La Poste est en ISO-8859-1.
            $colonnes = explode(';', trim(mb_convert_encoding($ligne, 'UTF-8', 'ISO-8859-1')));
            // Une ligne valable a un code INSEE et un code postal de 5 chiffres.
            if (count($colonnes) < 4 || $colonnes[0] === '' || preg_match('/^\d{5}$/', $colonnes[2]) !== 1) {
                continue;
            }
            array_push($lot, $colonnes[0], $colonnes[1], $colonnes[2], $colonnes[3], $colonnes[4] ?? '');
            $nombre++;
            if ($nombre % 500 === 0) {
                $inserer($lot);
                $lot = [];
            }
        }
        $inserer($lot);
        $pdo->commit();
    } catch (Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    } finally {
        fclose($fichier);
    }
    return $nombre;
}
