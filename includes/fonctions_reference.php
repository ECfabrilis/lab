<?php

declare(strict_types=1);

/**
 * Données de référence : pays, quartiers, quartiers prioritaires (QP), QPV,
 * codes postaux. Consultation et mise à jour depuis Paramètres > Base de données.
 *
 * Mettre à jour une table de quartiers ne change pas les adresses déjà saisies :
 * elles gardent le quartier calculé au moment de leur saisie (instantané).
 */

/** Taille maximale d'un fichier de mise à jour : 30 Mo. */
const REFERENCE_TAILLE_MAX = 31457280;

/**
 * Tables de quartiers qui se mettent à jour par un fichier GeoJSON.
 * Pour chaque colonne : le nom de la propriété GeoJSON proposé par défaut.
 *
 * @return array<string, array{libelle: string, colonnes: array<string, string>}>
 */
function reference_tables_quartiers(): array
{
    return [
        'quartiers' => [
            'libelle'  => 'Quartiers',
            'colonnes' => ['nom' => 'l_qu', 'commune' => '', 'arrondissement' => 'c_ar'],
        ],
        'quartiers_prioritaires' => [
            'libelle'  => 'Quartiers prioritaires (QP)',
            'colonnes' => ['code_quartier' => 'code_qp', 'nom' => 'nom_qp', 'commune' => 'commune_qp', 'arrondissement' => ''],
        ],
        'quartiers_qpv' => [
            'libelle'  => 'Quartiers de la politique de la ville (QPV)',
            'colonnes' => ['code_quartier' => 'code_qp', 'nom' => 'lib_qp', 'commune' => 'lib_com'],
        ],
    ];
}

/** Libellé d'une colonne dans les formulaires de mise à jour. */
function reference_libelle_colonne(string $colonne): string
{
    return match ($colonne) {
        'code_quartier'  => 'Code du quartier',
        'nom'            => 'Nom du quartier',
        'commune'        => 'Commune',
        'arrondissement' => 'Arrondissement',
        default          => $colonne,
    };
}

/**
 * Nombre de lignes de chaque table de référence.
 *
 * @return array<string, int>
 */
function reference_compter(): array
{
    return [
        'pays'                   => entier(db_valeur('SELECT COUNT(*) FROM pays')),
        'quartiers'              => entier(db_valeur('SELECT COUNT(*) FROM quartiers')),
        'quartiers_prioritaires' => entier(db_valeur('SELECT COUNT(*) FROM quartiers_prioritaires')),
        'quartiers_qpv'          => entier(db_valeur('SELECT COUNT(*) FROM quartiers_qpv')),
        'codes_postaux'          => entier(db_valeur('SELECT COUNT(*) FROM codes_postaux')),
    ];
}

/**
 * Remplace le contenu d'une table de quartiers par celui d'un fichier GeoJSON
 * (FeatureCollection de polygones, coordonnées en longitude / latitude), ou
 * l'ajoute à la table (quartiers seulement).
 *
 * Ajout : un quartier déjà présent (même commune, même arrondissement, même
 * nom) n'est pas créé une deuxième fois ; son contour est mis à jour. Le choix
 * « pris en compte » se fait par zone (commune et arrondissement) : un nouveau
 * quartier suit celui de sa zone ; une nouvelle zone est prise en compte.
 *
 * @param array<string, string> $proprietes colonne de la table => nom de la propriété GeoJSON ('' = colonne laissée vide)
 * @param bool $ajouter true : ajouter à la table au lieu de la remplacer
 * @return array{ajoutes: int, mis_a_jour: int} nombre de quartiers créés et de quartiers dont le contour est mis à jour
 * @throws RuntimeException avec un message destiné à l'utilisateur si le fichier ne convient pas
 */
function reference_importer_geojson(string $table, string $chemin, array $proprietes, bool $ajouter = false): array
{
    $definition = reference_tables_quartiers()[$table] ?? null;
    if ($definition === null || ($ajouter && $table !== 'quartiers')) {
        throw new RuntimeException('Table inconnue.');
    }
    $contenu = file_get_contents($chemin);
    $donnees = $contenu === false ? null : json_decode($contenu, true);
    if (!is_array($donnees) || ($donnees['type'] ?? '') !== 'FeatureCollection' || !is_array($donnees['features'] ?? null)) {
        throw new RuntimeException('Ce fichier n\'est pas un fichier GeoJSON (une « FeatureCollection » est attendue).');
    }
    if ($donnees['features'] === []) {
        throw new RuntimeException('Le fichier ne contient aucun quartier.');
    }

    $colonnes = array_keys($definition['colonnes']);
    $lignes = [];
    $codes_vus = [];
    foreach ($donnees['features'] as $numero => $element) {
        $rang = entier($numero) + 1;
        $geometrie = is_array($element) ? ($element['geometry'] ?? null) : null;
        $valeurs_fichier = is_array($element) && is_array($element['properties'] ?? null) ? $element['properties'] : [];
        if (!is_array($geometrie) || !in_array($geometrie['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
            throw new RuntimeException('Le quartier n° ' . $rang . ' n\'a pas de contour (polygone).');
        }

        $ligne = [];
        foreach ($colonnes as $colonne) {
            $propriete = $proprietes[$colonne] ?? '';
            if ($propriete !== '' && !array_key_exists($propriete, $valeurs_fichier)) {
                throw new RuntimeException('La propriété « ' . $propriete . ' » est absente du fichier. Propriétés disponibles : '
                    . implode(', ', array_map(strval(...), array_keys($valeurs_fichier))) . '.');
            }
            $valeur = $propriete === '' ? '' : trim(texte($valeurs_fichier[$propriete]));
            $ligne[$colonne] = $colonne === 'arrondissement' ? entier($valeur) % 100 : mb_substr($valeur, 0, $colonne === 'code_quartier' ? 50 : 150);
        }
        if ($ligne['nom'] === '') {
            throw new RuntimeException('Le quartier n° ' . $rang . ' n\'a pas de nom : vérifiez la propriété indiquée pour le nom.');
        }
        if (array_key_exists('code_quartier', $ligne)) {
            $code = texte($ligne['code_quartier']);
            if ($code === '' || isset($codes_vus[$code])) {
                throw new RuntimeException('Le code du quartier n° ' . $rang . ' est vide ou en double : vérifiez la propriété indiquée pour le code.');
            }
            $codes_vus[$code] = true;
        }
        $json = json_encode($geometrie);
        if ($json === false) {
            throw new RuntimeException('Le contour du quartier n° ' . $rang . ' est illisible.');
        }
        $ligne['geom'] = $json;
        $lignes[] = $ligne;
    }

    // Le passage par le texte donne aux contours le même format que ceux d'origine.
    $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $colonnes) . ', geom) VALUES ('
        . str_repeat('?, ', count($colonnes)) . 'ST_GeomFromText(ST_AsText(ST_GeomFromGeoJSON(?))))';

    $ajoutes = 0;
    $mis_a_jour = 0;
    db()->beginTransaction();
    try {
        if (!$ajouter) {
            db_executer('DELETE FROM `' . $table . '`');
        }
        foreach ($lignes as $ligne) {
            $existant_id = $ajouter ? entier(db_valeur(
                'SELECT id FROM quartiers WHERE commune = ? AND arrondissement = ? AND nom = ? ORDER BY id LIMIT 1',
                [$ligne['commune'] ?? '', $ligne['arrondissement'] ?? 0, $ligne['nom']]
            )) : 0;
            if ($existant_id > 0) {
                db_executer('UPDATE quartiers SET geom = ST_GeomFromText(ST_AsText(ST_GeomFromGeoJSON(?))) WHERE id = ?', [$ligne['geom'], $existant_id]);
                $mis_a_jour++;
            } else {
                db_executer($sql, array_values($ligne));
                $ajoutes++;
                if ($ajouter) {
                    // Le choix « pris en compte » se fait par zone (commune et arrondissement) :
                    // un quartier ajouté à une zone qui ne l'est pas ne l'est pas non plus.
                    $nouveau_id = entier(db()->lastInsertId());
                    $etat_zone = db_valeur(
                        'SELECT MAX(est_actif) FROM quartiers WHERE commune = ? AND arrondissement = ? AND id <> ?',
                        [$ligne['commune'] ?? '', $ligne['arrondissement'] ?? 0, $nouveau_id]
                    );
                    if ($etat_zone !== null && entier($etat_zone) === 0) {
                        db_executer('UPDATE quartiers SET est_actif = 0 WHERE id = ?', [$nouveau_id]);
                    }
                }
            }
        }
        db()->commit();
    } catch (Throwable $erreur) {
        db()->rollBack();
        journal_fichier('reference.log', 'Import GeoJSON refusé pour ' . $table . ' : ' . $erreur->getMessage());
        throw new RuntimeException('La base a refusé un contour du fichier ; rien n\'a été modifié.');
    }
    return ['ajoutes' => $ajoutes, 'mis_a_jour' => $mis_a_jour];
}

// ---------------------------------------------------------------------------
// Quartiers pris en compte (Paramètres > Options > Quartiers)
// ---------------------------------------------------------------------------

/**
 * Zones de quartiers : une zone regroupe les quartiers d'une commune et d'un
 * arrondissement (« Paris 17ème »), ou d'une commune entière quand
 * l'arrondissement n'est pas renseigné. Le choix « pris en compte » se fait
 * par zone, jamais quartier par quartier.
 *
 * @return list<array{commune: string, arrondissement: int, libelle: string, cle: string, nb_quartiers: int, est_actif: bool}>
 *         cle : valeur de la case à cocher (« 17|Paris ») ; est_actif : au moins un quartier de la zone est pris en compte
 */
function quartiers_zones(): array
{
    $zones = [];
    $lignes = db_lignes(
        'SELECT commune, arrondissement, COUNT(*) AS nb_quartiers, MAX(est_actif) AS est_actif
           FROM quartiers
          GROUP BY commune, arrondissement
          ORDER BY commune, arrondissement'
    );
    foreach ($lignes as $ligne) {
        $commune = texte($ligne['commune']);
        $arrondissement = entier($ligne['arrondissement']);
        $libelle = $commune !== '' ? $commune : 'Commune non renseignée';
        if ($arrondissement > 0) {
            $libelle .= ' ' . $arrondissement . ($arrondissement === 1 ? 'er' : 'ème');
        }
        $zones[] = [
            'commune'        => $commune,
            'arrondissement' => $arrondissement,
            'libelle'        => $libelle,
            'cle'            => $arrondissement . '|' . $commune,
            'nb_quartiers'   => entier($ligne['nb_quartiers']),
            'est_actif'      => entier($ligne['est_actif']) === 1,
        ];
    }
    return $zones;
}

/**
 * Enregistre le choix des zones prises en compte : tous les quartiers des
 * zones cochées le sont, tous les autres ne le sont plus. Les adresses déjà
 * saisies ne changent pas.
 *
 * @param list<string> $cles clés des zones cochées (voir quartiers_zones())
 * @return array{modifie: bool, libelles: list<string>} modifie : false si rien n'a changé ; libelles : zones prises en compte
 */
function quartiers_choisir(array $cles): array
{
    $zones = quartiers_zones();
    $avant = [];
    $apres = [];
    foreach ($zones as $zone) {
        if ($zone['est_actif']) {
            $avant[] = $zone['libelle'];
        }
        if (in_array($zone['cle'], $cles, true)) {
            $apres[] = $zone['libelle'];
        }
    }
    // Zone en partie prise en compte (cas anormal) : l'enregistrement la remet d'aplomb.
    $partielle = entier(db_valeur(
        'SELECT COUNT(*) FROM (SELECT commune, arrondissement FROM quartiers
                                GROUP BY commune, arrondissement HAVING MIN(est_actif) <> MAX(est_actif)) z'
    )) > 0;
    if ($apres === $avant && !$partielle) {
        return ['modifie' => false, 'libelles' => $apres];
    }
    db()->beginTransaction();
    try {
        db_executer('UPDATE quartiers SET est_actif = 0');
        foreach ($zones as $zone) {
            if (in_array($zone['cle'], $cles, true)) {
                db_executer('UPDATE quartiers SET est_actif = 1 WHERE commune = ? AND arrondissement = ?', [$zone['commune'], $zone['arrondissement']]);
            }
        }
        journaliser('modification', 'quartiers', null, 'Choix des quartiers pris en compte', ['zones' => implode(', ', $avant)], ['zones' => implode(', ', $apres)]);
        db()->commit();
    } catch (Throwable $erreur) {
        db()->rollBack();
        throw $erreur;
    }
    return ['modifie' => true, 'libelles' => $apres];
}

/**
 * Rétablit une table de quartiers à partir du fichier fourni avec l'application
 * (sql/donnees_reference.sql).
 *
 * @return int nombre de lignes après l'import
 */
function reference_retablir(string $table): int
{
    if (!array_key_exists($table, reference_tables_quartiers())) {
        throw new RuntimeException('Table inconnue.');
    }
    $sql = file_get_contents(DOSSIER_PRIVE . '/sql/donnees_reference.sql');
    if ($sql === false) {
        throw new RuntimeException('Le fichier sql/donnees_reference.sql est illisible.');
    }
    $debut = 'INSERT INTO `' . $table . '`';
    $requetes = array_values(array_filter(
        sql_decouper($sql),
        static fn (string $requete): bool => str_starts_with($requete, $debut)
    ));
    if ($requetes === []) {
        throw new RuntimeException('Le fichier d\'origine ne contient rien pour cette table.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM `' . $table . '`');
        foreach ($requetes as $requete) {
            $pdo->exec($requete);
        }
        $pdo->commit();
    } catch (Throwable $erreur) {
        $pdo->rollBack();
        throw $erreur;
    }
    return entier(db_valeur('SELECT COUNT(*) FROM `' . $table . '`'));
}

/**
 * Fichier envoyé par un formulaire : renvoie son chemin temporaire.
 *
 * @throws RuntimeException avec un message destiné à l'utilisateur
 */
function fichier_envoye(string $champ): string
{
    $fichier = $_FILES[$champ] ?? null;
    if (!is_array($fichier) || entier($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choisissez un fichier.');
    }
    $chemin = texte($fichier['tmp_name'] ?? '');
    if (entier($fichier['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($chemin)) {
        throw new RuntimeException('Le fichier n\'a pas pu être reçu (il dépasse peut-être la taille acceptée par le serveur).');
    }
    if (entier($fichier['size'] ?? 0) > REFERENCE_TAILLE_MAX) {
        throw new RuntimeException('Le fichier dépasse 30 Mo.');
    }
    return $chemin;
}
