<?php

declare(strict_types=1);

/**
 * Familles et dossiers.
 *
 * Modèle : famille → dossier (un par saison) → adhérents du dossier.
 * Une famille peut être une personne seule.
 */

/**
 * Une famille par son identifiant, ou null.
 *
 * @return array<string, mixed>|null
 */
function famille_par_id(int $id): ?array
{
    return db_ligne('SELECT id, nom, anonymise_le, date_crea FROM familles WHERE id = ?', [$id]);
}

/** Crée une famille et renvoie son identifiant. */
function creer_famille(string $nom): int
{
    db_executer(
        'INSERT INTO familles (nom, creation_par) VALUES (?, ?)',
        [$nom, entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    $famille_id = entier(db()->lastInsertId());
    journaliser('creation', 'familles', $famille_id, 'Création de la famille ' . $nom, null, ['nom' => $nom]);
    return $famille_id;
}

/**
 * Membres d'une famille, avec leur présence dans le dossier de la saison indiquée.
 *
 * @return list<array<string, mixed>>
 */
function famille_membres(int $famille_id, int $saison_id = 0): array
{
    return db_lignes(
        'SELECT a.id, a.nom, a.prenom, a.sexe, a.date_naissance, a.mail, a.tel,
                (da.adherent_id IS NOT NULL) AS dans_dossier
           FROM adherents a
           LEFT JOIN dossiers d ON d.famille_id = a.famille_id AND d.saison_id = ?
           LEFT JOIN dossiers_adherents da ON da.dossier_id = d.id AND da.adherent_id = a.id
          WHERE a.famille_id = ?
          ORDER BY a.date_naissance, a.prenom',
        [$saison_id, $famille_id]
    );
}

/**
 * Un dossier par son identifiant, avec le nom de la famille et de la saison, ou null.
 *
 * @return array<string, mixed>|null
 */
function dossier_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT d.id, d.famille_id, d.saison_id, d.date_dossier, d.notes, d.date_crea, d.date_modif,
                f.nom AS famille_nom, s.nom AS saison_nom
           FROM dossiers d
           JOIN familles f ON f.id = d.famille_id
           JOIN saisons s ON s.id = d.saison_id
          WHERE d.id = ?',
        [$id]
    );
}

/** Identifiant du dossier d'une famille pour une saison (0 s'il n'existe pas). */
function dossier_id_de_famille(int $famille_id, int $saison_id): int
{
    return entier(db_valeur(
        'SELECT id FROM dossiers WHERE famille_id = ? AND saison_id = ?',
        [$famille_id, $saison_id]
    ));
}

/**
 * Dossiers d'une famille, du plus récent au plus ancien, avec le nombre d'adhérents.
 *
 * @return list<array<string, mixed>>
 */
function dossiers_de_famille(int $famille_id): array
{
    return db_lignes(
        'SELECT d.id, d.saison_id, d.date_dossier, s.nom AS saison_nom,
                (SELECT COUNT(*) FROM dossiers_adherents da WHERE da.dossier_id = d.id) AS nb_adherents
           FROM dossiers d
           JOIN saisons s ON s.id = d.saison_id
          WHERE d.famille_id = ?
          ORDER BY s.date_debut DESC',
        [$famille_id]
    );
}

/**
 * Renvoie le dossier de la famille pour la saison ; le crée s'il n'existe pas.
 *
 * @return int identifiant du dossier
 */
function dossier_obtenir(int $famille_id, int $saison_id): int
{
    $dossier_id = dossier_id_de_famille($famille_id, $saison_id);
    if ($dossier_id > 0) {
        return $dossier_id;
    }
    db_executer(
        'INSERT INTO dossiers (famille_id, saison_id, date_dossier, creation_par) VALUES (?, ?, ?, ?)',
        [$famille_id, $saison_id, date('Y-m-d'), entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    $dossier_id = entier(db()->lastInsertId());
    journaliser('creation', 'dossiers', $dossier_id, 'Ouverture d\'un dossier', null, [
        'famille_id' => $famille_id,
        'saison_id'  => $saison_id,
    ]);
    return $dossier_id;
}

/**
 * Adhérents d'un dossier, avec leur adresse et leur scolarité de la saison.
 *
 * @return list<array<string, mixed>>
 */
function dossier_membres(int $dossier_id): array
{
    return db_lignes(
        'SELECT a.id, a.nom, a.prenom, a.sexe, a.date_naissance, a.mail, a.tel,
                da.adresse, da.complement, da.code_postal, da.ville, da.est_validee,
                da.quartier, da.est_qp, da.qp_nom, da.est_qpv, da.qpv_nom,
                da.etablissement, da.niveau
           FROM dossiers_adherents da
           JOIN adherents a ON a.id = da.adherent_id
          WHERE da.dossier_id = ?
          ORDER BY a.date_naissance, a.prenom',
        [$dossier_id]
    );
}
