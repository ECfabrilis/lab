<?php

declare(strict_types=1);

/**
 * Familles et dossiers.
 *
 * Modèle : famille → dossier (un par saison) → adhérents du dossier.
 * Une famille peut être une personne seule. Une structure n'a pas de famille :
 * elle a son propre dossier (dossiers.structure_id).
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

/** Nombre de familles affichées par page dans la liste. */
const FAMILLES_PAR_PAGE = 50;

/**
 * Recherche de familles par leur nom ou celui d'un de leurs membres.
 *
 * @return array{total: int, lignes: list<array<string, mixed>>}
 */
function familles_rechercher(string $recherche, int $saison_id, int $page = 1): array
{
    $conditions = ['f.anonymise_le IS NULL'];
    $parametres = [];
    $mots = preg_split('/\s+/', $recherche, -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($mots === false ? [] : $mots, 0, 5) as $mot) {
        // % et _ sont des caractères spéciaux de LIKE : on les neutralise.
        $motif = '%' . addcslashes($mot, '%_\\') . '%';
        $conditions[] = '(f.nom LIKE ? OR EXISTS (SELECT 1 FROM adherents m WHERE m.famille_id = f.id AND (m.nom LIKE ? OR m.prenom LIKE ?)))';
        array_push($parametres, $motif, $motif, $motif);
    }
    $ou = ' FROM familles f WHERE ' . implode(' AND ', $conditions);

    $total = entier(db_valeur('SELECT COUNT(*)' . $ou, $parametres));
    $lignes = db_lignes(
        'SELECT f.id, f.nom,
                (SELECT GROUP_CONCAT(a.prenom ORDER BY a.date_naissance SEPARATOR \', \') FROM adherents a WHERE a.famille_id = f.id) AS membres,
                (SELECT d.id FROM dossiers d WHERE d.famille_id = f.id AND d.saison_id = ?) AS dossier_id'
        . $ou . ' ORDER BY f.nom, f.id LIMIT ? OFFSET ?',
        [$saison_id, ...$parametres, FAMILLES_PAR_PAGE, (max(1, $page) - 1) * FAMILLES_PAR_PAGE]
    );
    return ['total' => $total, 'lignes' => $lignes];
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
 * Un dossier par son identifiant, avec le nom de la famille (ou de la structure) et de la saison, ou null.
 *
 * @return array<string, mixed>|null
 */
function dossier_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT d.id, d.famille_id, d.structure_id, d.saison_id, d.date_dossier, d.notes, d.date_crea, d.date_modif,
                f.nom AS famille_nom, st.nom AS structure_nom, s.nom AS saison_nom
           FROM dossiers d
           LEFT JOIN familles f ON f.id = d.famille_id
           LEFT JOIN adherents st ON st.id = d.structure_id
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
 * Identifiant du dossier d'un adhérent pour une saison (0 s'il n'existe pas) :
 * celui de sa famille, ou le sien si c'est une structure.
 *
 * @param array<string, mixed> $adherent
 */
function dossier_id_de_adherent(array $adherent, int $saison_id): int
{
    if (entier($adherent['est_structure'] ?? 0) === 1) {
        return entier(db_valeur(
            'SELECT id FROM dossiers WHERE structure_id = ? AND saison_id = ?',
            [entier($adherent['id'] ?? 0), $saison_id]
        ));
    }
    return dossier_id_de_famille(entier($adherent['famille_id'] ?? 0), $saison_id);
}

/**
 * Renvoie le dossier d'un adhérent pour la saison ; le crée s'il n'existe pas.
 *
 * @param array<string, mixed> $adherent
 * @return int identifiant du dossier
 */
function dossier_obtenir_pour_adherent(array $adherent, int $saison_id): int
{
    if (entier($adherent['est_structure'] ?? 0) !== 1) {
        return dossier_obtenir(entier($adherent['famille_id'] ?? 0), $saison_id);
    }
    $dossier_id = dossier_id_de_adherent($adherent, $saison_id);
    if ($dossier_id > 0) {
        return $dossier_id;
    }
    $structure_id = entier($adherent['id'] ?? 0);
    db_executer(
        'INSERT INTO dossiers (structure_id, saison_id, date_dossier, creation_par) VALUES (?, ?, ?, ?)',
        [$structure_id, $saison_id, date('Y-m-d'), entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    $dossier_id = entier(db()->lastInsertId());
    journaliser('creation', 'dossiers', $dossier_id, 'Ouverture du dossier d\'une structure', null, [
        'structure_id' => $structure_id,
        'saison_id'    => $saison_id,
    ]);
    return $dossier_id;
}

/**
 * Dossiers d'une structure, du plus récent au plus ancien.
 *
 * @return list<array<string, mixed>>
 */
function dossiers_de_structure(int $structure_id): array
{
    return db_lignes(
        'SELECT d.id, d.saison_id, d.date_dossier, s.nom AS saison_nom
           FROM dossiers d
           JOIN saisons s ON s.id = d.saison_id
          WHERE d.structure_id = ?
          ORDER BY s.date_debut DESC',
        [$structure_id]
    );
}

/**
 * Familles dont le nom est presque identique à un nom donné (sans tenir compte
 * des accents ni des majuscules, à deux lettres près), avec leurs membres.
 *
 * @return list<array{id: int, nom: string, membres: string}>
 */
function familles_proches(string $nom, int $maximum = 5): array
{
    $nom_simple = texte_simplifie($nom);
    if (mb_strlen($nom_simple) < 2) {
        return [];
    }
    $proches = [];
    foreach (familles_avec_membres('', []) as $famille) {
        $ecart = ecart_noms($nom_simple, texte_simplifie($famille['nom']));
        if ($ecart <= ecart_tolere($nom_simple)) {
            $proches[] = ['ecart' => $ecart] + $famille;
        }
    }
    usort($proches, static fn (array $a, array $b): int => $a['ecart'] <=> $b['ecart']);
    $resultats = [];
    foreach (array_slice($proches, 0, $maximum) as $proche) {
        $resultats[] = ['id' => $proche['id'], 'nom' => $proche['nom'], 'membres' => $proche['membres']];
    }
    return $resultats;
}

/**
 * Familles dont le nom, ou celui d'un membre, contient le texte cherché.
 *
 * @return list<array{id: int, nom: string, membres: string}>
 */
function familles_par_texte(string $recherche, int $maximum = 10): array
{
    $recherche = trim($recherche);
    if (mb_strlen($recherche) < 2) {
        return [];
    }
    $motif = '%' . addcslashes($recherche, '%_\\') . '%';
    return array_slice(familles_avec_membres(
        ' AND (f.nom LIKE ? OR EXISTS (SELECT 1 FROM adherents m WHERE m.famille_id = f.id AND (m.nom LIKE ? OR m.prenom LIKE ?)))',
        [$motif, $motif, $motif]
    ), 0, $maximum);
}

/**
 * Familles (non anonymisées) avec le prénom de leurs membres.
 *
 * @param string       $condition  complément de la clause WHERE, commençant par ' AND'
 * @param list<string> $parametres valeurs de ce complément
 * @return list<array{id: int, nom: string, membres: string}>
 */
function familles_avec_membres(string $condition, array $parametres): array
{
    $familles = [];
    $lignes = db_lignes(
        'SELECT f.id, f.nom,
                (SELECT GROUP_CONCAT(a.prenom ORDER BY a.date_naissance SEPARATOR \', \') FROM adherents a WHERE a.famille_id = f.id) AS membres
           FROM familles f
          WHERE f.anonymise_le IS NULL' . $condition . '
          ORDER BY f.nom, f.id',
        $parametres
    );
    foreach ($lignes as $ligne) {
        $familles[] = ['id' => entier($ligne['id']), 'nom' => texte($ligne['nom']), 'membres' => texte($ligne['membres'])];
    }
    return $familles;
}

/** Dernier téléphone connu dans une famille (proposé pour un nouveau membre), ou ''. */
function telephone_famille(int $famille_id): string
{
    return texte(db_valeur(
        'SELECT tel FROM adherents WHERE famille_id = ? AND tel IS NOT NULL ORDER BY date_crea DESC LIMIT 1',
        [$famille_id]
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
