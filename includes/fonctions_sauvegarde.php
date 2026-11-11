<?php

declare(strict_types=1);

/**
 * Sauvegarde de la base : export SQL complet écrit en PHP (pas besoin de
 * mysqldump, absent des hébergements mutualisés), rangé dans sauvegardes/.
 */

/** Nombre de jours au-delà duquel on rappelle de faire une sauvegarde. */
const SAUVEGARDE_RAPPEL_JOURS = 7;

/** Dossier privé des sauvegardes. */
function sauvegarde_dossier(): string
{
    return DOSSIER_PRIVE . '/sauvegardes';
}

/**
 * Sauvegardes présentes, de la plus récente à la plus ancienne.
 *
 * @return list<array{nom: string, taille: int, date: int}>
 */
function sauvegardes_liste(): array
{
    $liste = [];
    $fichiers = glob(sauvegarde_dossier() . '/bddv3_*.sql');
    foreach ($fichiers === false ? [] : $fichiers as $chemin) {
        $liste[] = [
            'nom'    => basename($chemin),
            'taille' => (int) filesize($chemin),
            'date'   => (int) filemtime($chemin),
        ];
    }
    usort($liste, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
    return $liste;
}

/** Chemin complet d'une sauvegarde à partir de son nom, ou null si le nom n'est pas celui d'une sauvegarde. */
function sauvegarde_chemin(string $nom): ?string
{
    if (preg_match('/^bddv3_\d{4}-\d{2}-\d{2}_\d{6}\.sql$/', $nom) !== 1) {
        return null;
    }
    $chemin = sauvegarde_dossier() . '/' . $nom;
    return is_file($chemin) ? $chemin : null;
}

/** Vrai s'il faut rappeler de faire une sauvegarde (aucune, ou la dernière a plus de 7 jours). */
function sauvegarde_en_retard(): bool
{
    $derniere = parametre('derniere_sauvegarde');
    if ($derniere === '') {
        return true;
    }
    $horodatage = strtotime($derniere);
    return $horodatage === false || time() - $horodatage > SAUVEGARDE_RAPPEL_JOURS * 86400;
}

/**
 * Crée une sauvegarde complète de la base (structure et données).
 *
 * @return string nom du fichier créé
 */
function sauvegarde_creer(): string
{
    $pdo = db();
    $nom = 'bddv3_' . date('Y-m-d_His') . '.sql';
    $fichier = fopen(sauvegarde_dossier() . '/' . $nom, 'w');
    if ($fichier === false) {
        throw new RuntimeException('Le dossier sauvegardes/ n\'est pas accessible en écriture.');
    }

    fwrite($fichier, "-- Sauvegarde bddv3 du " . date('d/m/Y à H:i') . "\n");
    fwrite($fichier, "-- Pour restaurer : importer ce fichier dans une base vide (phpMyAdmin).\n\n");
    fwrite($fichier, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

    $tables = [];
    $requete = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
    if ($requete !== false) {
        foreach ($requete->fetchAll(PDO::FETCH_NUM) as $ligne) {
            $tables[] = is_array($ligne) ? texte($ligne[0] ?? '') : '';
        }
    }

    foreach ($tables as $table) {
        if ($table === '') {
            continue;
        }
        $nom_table = '`' . str_replace('`', '``', $table) . '`';

        // Structure
        $creation = $pdo->query('SHOW CREATE TABLE ' . $nom_table);
        $ligne = $creation === false ? null : $creation->fetch(PDO::FETCH_NUM);
        if (!is_array($ligne)) {
            continue;
        }
        fwrite($fichier, "DROP TABLE IF EXISTS $nom_table;\n" . texte($ligne[1] ?? '') . ";\n\n");

        // Colonnes binaires (géométries, blobs) : écrites en hexadécimal
        $binaires = [];
        $colonnes = $pdo->query('SHOW COLUMNS FROM ' . $nom_table);
        if ($colonnes !== false) {
            foreach ($colonnes->fetchAll() as $colonne) {
                if (is_array($colonne) && preg_match('/geometry|point|polygon|linestring|blob|binary/i', texte($colonne['Type'] ?? '')) === 1) {
                    $binaires[texte($colonne['Field'] ?? '')] = true;
                }
            }
        }

        // Données, par paquets d'environ 500 Ko
        $donnees = $pdo->query('SELECT * FROM ' . $nom_table);
        if ($donnees === false) {
            continue;
        }
        $paquet = [];
        $taille = 0;
        $ecrire = static function (array $paquet) use ($fichier, $nom_table): void {
            if ($paquet !== []) {
                fwrite($fichier, "INSERT INTO $nom_table VALUES\n" . implode(",\n", $paquet) . ";\n");
            }
        };
        while (is_array($ligne = $donnees->fetch(PDO::FETCH_ASSOC))) {
            $valeurs = [];
            foreach ($ligne as $colonne => $valeur) {
                if ($valeur === null) {
                    $valeurs[] = 'NULL';
                } elseif (is_int($valeur) || is_float($valeur)) {
                    $valeurs[] = (string) $valeur;
                } elseif (is_bool($valeur)) {
                    $valeurs[] = $valeur ? '1' : '0';
                } elseif (isset($binaires[(string) $colonne])) {
                    $valeurs[] = texte($valeur) === '' ? "''" : '0x' . bin2hex(texte($valeur));
                } else {
                    $valeurs[] = $pdo->quote(texte($valeur));
                }
            }
            $texte_ligne = '(' . implode(', ', $valeurs) . ')';
            $paquet[] = $texte_ligne;
            $taille += strlen($texte_ligne);
            if ($taille > 500000) {
                $ecrire($paquet);
                $paquet = [];
                $taille = 0;
            }
        }
        $ecrire($paquet);
        fwrite($fichier, "\n");
    }

    fwrite($fichier, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($fichier);
    return $nom;
}
