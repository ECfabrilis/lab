<?php

declare(strict_types=1);

/**
 * Adresses : codes postaux, API Adresse (Base Adresse Nationale), quartiers.
 *
 * L'adresse est enregistrée par adhérent et par dossier (donc par saison).
 * Le quartier, le QP et le QPV sont calculés au moment de la saisie puis
 * conservés en texte (instantané) : ils ne changent plus ensuite.
 */

/** Service de géocodage de la Géoplateforme (IGN), qui remplace api-adresse.data.gouv.fr. */
const API_ADRESSE_URL = 'https://data.geopf.fr/geocodage/search';

/** Note minimale (de 0 à 1) pour accepter le résultat de l'API comme étant l'adresse saisie. */
const API_ADRESSE_SCORE_MIN = 0.7;

/**
 * Codes postaux qui commencent par les chiffres saisis.
 *
 * @return list<array{code_postal: string, ville: string}>
 */
function codes_postaux_chercher(string $debut): array
{
    if (preg_match('/^\d{2,5}$/', $debut) !== 1) {
        return [];
    }
    $resultats = [];
    $lignes = db_lignes(
        'SELECT DISTINCT code_postal, libelle_acheminement
           FROM codes_postaux
          WHERE code_postal LIKE ?
          ORDER BY code_postal, libelle_acheminement
          LIMIT 12',
        [$debut . '%']
    );
    foreach ($lignes as $ligne) {
        $resultats[] = ['code_postal' => texte($ligne['code_postal']), 'ville' => texte($ligne['libelle_acheminement'])];
    }
    return $resultats;
}

/**
 * Interroge l'API Adresse.
 *
 * @return list<array{libelle: string, adresse: string, code_postal: string, ville: string, type: string, score: float, latitude: float, longitude: float}>|null
 *         null si le service ne répond pas
 */
function adresse_api_chercher(string $recherche, string $code_postal = '', int $limite = 5): ?array
{
    $recherche = mb_substr(trim($recherche), 0, 200);
    if (mb_strlen($recherche) < 3) {
        return [];
    }
    $parametres = ['q' => $recherche, 'limit' => $limite, 'index' => 'address'];
    if (preg_match('/^\d{5}$/', $code_postal) === 1) {
        $parametres['postcode'] = $code_postal;
    }

    $reponse = http_lire(API_ADRESSE_URL . '?' . http_build_query($parametres));
    $donnees = $reponse === null ? null : json_decode($reponse, true);
    if (!is_array($donnees) || !is_array($donnees['features'] ?? null)) {
        journal_fichier('api_adresse.log', 'Pas de réponse exploitable de l\'API Adresse.');
        return null;
    }

    $resultats = [];
    foreach ($donnees['features'] as $element) {
        if (!is_array($element) || !is_array($element['properties'] ?? null) || !is_array($element['geometry'] ?? null)) {
            continue;
        }
        $proprietes = $element['properties'];
        $coordonnees = $element['geometry']['coordinates'] ?? null;
        if (!is_array($coordonnees) || !is_numeric($coordonnees[0] ?? null) || !is_numeric($coordonnees[1] ?? null)) {
            continue;
        }
        $resultats[] = [
            'libelle'     => texte($proprietes['label'] ?? ''),
            'adresse'     => texte($proprietes['name'] ?? ''),
            'code_postal' => texte($proprietes['postcode'] ?? ''),
            'ville'       => texte($proprietes['city'] ?? ''),
            'type'        => texte($proprietes['type'] ?? ''),
            'score'       => is_numeric($proprietes['score'] ?? null) ? (float) $proprietes['score'] : 0.0,
            'latitude'    => (float) $coordonnees[1],
            'longitude'   => (float) $coordonnees[0],
        ];
    }
    return $resultats;
}

/** Lit une adresse web (GET, 5 secondes au plus). Renvoie null en cas d'échec. */
function http_lire(string $url): ?string
{
    if (!function_exists('curl_init')) {
        $contexte = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => false]]);
        $reponse = @file_get_contents($url, false, $contexte);
        return $reponse === false ? null : $reponse;
    }
    $requete = curl_init($url);
    if ($requete === false) {
        return null;
    }
    curl_setopt($requete, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($requete, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($requete, CURLOPT_TIMEOUT, 5);
    curl_setopt($requete, CURLOPT_USERAGENT, 'bddv3');
    // Sous Windows (poste local), on utilise les certificats du système :
    // un antivirus qui inspecte le HTTPS y a installé le sien.
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($requete, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    $reponse = curl_exec($requete);
    $code = curl_getinfo($requete, CURLINFO_RESPONSE_CODE);
    if (!is_string($reponse) || $code !== 200) {
        journal_fichier('api_adresse.log', 'Échec de l\'appel (code ' . $code . ') : ' . curl_error($requete));
        return null;
    }
    return $reponse;
}

/**
 * Quartier, QP et QPV d'un point (tables de référence, calcul dans la base).
 *
 * @return array{quartier: ?string, est_qp: int, qp_code: ?string, qp_nom: ?string, est_qpv: int, qpv_code: ?string, qpv_nom: ?string}
 */
function quartiers_du_point(float $latitude, float $longitude): array
{
    // Les géométries sont enregistrées en longitude puis latitude.
    $point = sprintf('POINT(%.6F %.6F)', $longitude, $latitude);
    $quartier = db_valeur('SELECT nom FROM quartiers WHERE ST_Contains(geom, ST_GeomFromText(?)) LIMIT 1', [$point]);
    $qp = db_ligne('SELECT code_quartier, nom FROM quartiers_prioritaires WHERE ST_Contains(geom, ST_GeomFromText(?)) LIMIT 1', [$point]);
    $qpv = db_ligne('SELECT code_quartier, nom FROM quartiers_qpv WHERE ST_Contains(geom, ST_GeomFromText(?)) LIMIT 1', [$point]);
    return [
        'quartier' => $quartier === null ? null : texte($quartier),
        'est_qp'   => $qp === null ? 0 : 1,
        'qp_code'  => $qp === null ? null : texte($qp['code_quartier']),
        'qp_nom'   => $qp === null ? null : texte($qp['nom']),
        'est_qpv'  => $qpv === null ? 0 : 1,
        'qpv_code' => $qpv === null ? null : texte($qpv['code_quartier']),
        'qpv_nom'  => $qpv === null ? null : texte($qpv['nom']),
    ];
}

/**
 * Champs d'adresse et de scolarité lus dans le formulaire envoyé.
 *
 * @return array<string, string>
 */
function adresse_saisie(): array
{
    return [
        'adresse'       => post('adresse'),
        'complement'    => post('complement'),
        'code_postal'   => post('code_postal'),
        'ville'         => post('ville'),
        'etablissement' => post('etablissement'),
        'niveau'        => post('niveau'),
    ];
}

/**
 * Vérifie l'adresse et la scolarité saisies.
 *
 * @param array<string, string> $saisie
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_adresse(array $saisie): array
{
    $erreurs = [];
    if ($saisie['adresse'] === '' || mb_strlen($saisie['adresse']) > 255) {
        $erreurs[] = 'L\'adresse (numéro et voie) est obligatoire.';
    }
    if (mb_strlen($saisie['complement']) > 255) {
        $erreurs[] = 'Le complément d\'adresse est trop long (255 caractères au maximum).';
    }
    if (preg_match('/^\d{5}$/', $saisie['code_postal']) !== 1) {
        $erreurs[] = 'Le code postal est obligatoire (5 chiffres).';
    }
    if ($saisie['ville'] === '' || mb_strlen($saisie['ville']) > 100) {
        $erreurs[] = 'La ville est obligatoire.';
    }
    if (mb_strlen($saisie['etablissement']) > 150 || mb_strlen($saisie['niveau']) > 50) {
        $erreurs[] = 'L\'établissement scolaire ou le niveau est trop long.';
    }
    return $erreurs;
}

/**
 * Cherche l'adresse officielle et ses coordonnées.
 * Une adresse déjà validée dans la base est réutilisée sans nouvel appel à l'API.
 *
 * @return array{adresse: string, ville: string, est_validee: int, latitude: ?float, longitude: ?float}
 */
function adresse_resoudre(string $adresse, string $code_postal, string $ville): array
{
    $connue = db_ligne(
        'SELECT adresse, ville, latitude, longitude
           FROM dossiers_adherents
          WHERE adresse = ? AND code_postal = ? AND ville = ? AND est_validee = 1 AND latitude IS NOT NULL
          LIMIT 1',
        [$adresse, $code_postal, $ville]
    );
    if ($connue !== null) {
        return [
            'adresse'     => texte($connue['adresse']),
            'ville'       => texte($connue['ville']),
            'est_validee' => 1,
            'latitude'    => (float) texte($connue['latitude']),
            'longitude'   => (float) texte($connue['longitude']),
        ];
    }

    $resultats = adresse_api_chercher($adresse . ' ' . $code_postal . ' ' . $ville, $code_postal, 1);
    $premier = $resultats[0] ?? null;
    // On n'accepte qu'une adresse trouvée au numéro près, dans le bon code postal.
    if (
        $premier !== null && $premier['type'] === 'housenumber'
        && $premier['score'] >= API_ADRESSE_SCORE_MIN && $premier['code_postal'] === $code_postal
    ) {
        return [
            'adresse'     => $premier['adresse'],
            'ville'       => $premier['ville'],
            'est_validee' => 1,
            'latitude'    => $premier['latitude'],
            'longitude'   => $premier['longitude'],
        ];
    }
    return ['adresse' => $adresse, 'ville' => $ville, 'est_validee' => 0, 'latitude' => null, 'longitude' => null];
}

/**
 * Enregistre l'adresse et la scolarité d'un adhérent dans un dossier
 * (l'adhérent est ajouté au dossier s'il n'y était pas).
 * L'adresse est vérifiée auprès de l'API et les quartiers sont calculés.
 *
 * @param array<string, string> $saisie champs déjà vérifiés par erreurs_adresse()
 * @return bool true si l'adresse a été reconnue au numéro près
 */
function adresse_enregistrer(int $dossier_id, int $adherent_id, array $saisie): bool
{
    $ancienne = adresse_du_dossier($dossier_id, $adherent_id);
    $resolue = adresse_resoudre($saisie['adresse'], $saisie['code_postal'], $saisie['ville']);

    $quartiers = ['quartier' => null, 'est_qp' => 0, 'qp_code' => null, 'qp_nom' => null, 'est_qpv' => 0, 'qpv_code' => null, 'qpv_nom' => null];
    $quartiers_le = null;
    if ($resolue['latitude'] !== null && $resolue['longitude'] !== null) {
        $quartiers = quartiers_du_point($resolue['latitude'], $resolue['longitude']);
        $quartiers_le = date('Y-m-d H:i:s');
    }

    $valeurs = [
        'adresse'       => $resolue['adresse'],
        'complement'    => nul_si_vide($saisie['complement']),
        'code_postal'   => $saisie['code_postal'],
        'ville'         => $resolue['ville'],
        'est_validee'   => $resolue['est_validee'],
        'latitude'      => $resolue['latitude'],
        'longitude'     => $resolue['longitude'],
        'quartier'      => $quartiers['quartier'],
        'est_qp'        => $quartiers['est_qp'],
        'qp_code'       => $quartiers['qp_code'],
        'qp_nom'        => $quartiers['qp_nom'],
        'est_qpv'       => $quartiers['est_qpv'],
        'qpv_code'      => $quartiers['qpv_code'],
        'qpv_nom'       => $quartiers['qpv_nom'],
        'quartiers_le'  => $quartiers_le,
        'etablissement' => nul_si_vide($saisie['etablissement']),
        'niveau'        => nul_si_vide($saisie['niveau']),
    ];

    $colonnes = array_keys($valeurs);
    db_executer(
        'INSERT INTO dossiers_adherents (dossier_id, adherent_id, ' . implode(', ', $colonnes) . ')
         VALUES (?, ?' . str_repeat(', ?', count($colonnes)) . ')
         ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $colonnes)),
        [$dossier_id, $adherent_id, ...array_values($valeurs)]
    );

    journaliser(
        $ancienne === null ? 'creation' : 'modification',
        'dossiers_adherents',
        $adherent_id,
        ($ancienne === null ? 'Ajout de l\'adhérent au dossier ' : 'Modification de l\'adresse dans le dossier ') . $dossier_id,
        $ancienne,
        ['dossier_id' => $dossier_id] + $valeurs
    );
    return $resolue['est_validee'] === 1;
}

/**
 * Adresse et scolarité d'un adhérent dans un dossier, ou null s'il n'en fait pas partie.
 *
 * @return array<string, mixed>|null
 */
function adresse_du_dossier(int $dossier_id, int $adherent_id): ?array
{
    return db_ligne(
        'SELECT dossier_id, adresse, complement, code_postal, ville, est_validee, latitude, longitude,
                quartier, est_qp, qp_code, qp_nom, est_qpv, qpv_code, qpv_nom, quartiers_le, etablissement, niveau
           FROM dossiers_adherents
          WHERE dossier_id = ? AND adherent_id = ?',
        [$dossier_id, $adherent_id]
    );
}

/**
 * Dernière adresse connue d'un adhérent (saison la plus récente), ou null.
 *
 * @return array<string, mixed>|null
 */
function derniere_adresse_adherent(int $adherent_id): ?array
{
    return db_ligne(
        'SELECT da.adresse, da.complement, da.code_postal, da.ville, da.etablissement, da.niveau
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN saisons s ON s.id = d.saison_id
          WHERE da.adherent_id = ?
          ORDER BY s.date_debut DESC
          LIMIT 1',
        [$adherent_id]
    );
}

/**
 * Dernière adresse connue dans une famille (proposée pour un nouveau membre), ou null.
 *
 * @return array<string, mixed>|null
 */
function derniere_adresse_famille(int $famille_id): ?array
{
    return db_ligne(
        'SELECT da.adresse, da.complement, da.code_postal, da.ville
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN saisons s ON s.id = d.saison_id
          WHERE d.famille_id = ?
          ORDER BY s.date_debut DESC, da.date_crea DESC
          LIMIT 1',
        [$famille_id]
    );
}

/**
 * Affiche les champs d'adresse et de scolarité d'une saison.
 * Les propositions (code postal, adresse) sont ajoutées par js/adresse.js.
 * Le code postal est placé avant l'adresse : il limite les propositions d'adresses à la commune.
 *
 * @param array<string, mixed> $defauts valeurs affichées quand rien n'a été saisi
 */
function afficher_champs_adresse(array $defauts): void
{
    ?>
    <div class="row g-3 mb-3">
        <div class="col-sm-4 position-relative">
            <label class="form-label" for="code_postal">Code postal</label>
            <input class="form-control" type="text" id="code_postal" name="code_postal" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" autocomplete="off"
                   data-suggestions="code_postal" data-url="<?= e(chemin_site('api_codes_postaux.php')) ?>"
                   value="<?= e(ancien('code_postal', $defauts['code_postal'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-8">
            <label class="form-label" for="ville">Ville</label>
            <input class="form-control" type="text" id="ville" name="ville" maxlength="100" value="<?= e(ancien('ville', $defauts['ville'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-12 position-relative">
            <label class="form-label" for="adresse">Adresse (numéro et voie)</label>
            <input class="form-control" type="text" id="adresse" name="adresse" maxlength="255" autocomplete="off"
                   data-suggestions="adresse" data-url="<?= e(chemin_site('api_adresse.php')) ?>"
                   value="<?= e(ancien('adresse', $defauts['adresse'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-12">
            <label class="form-label" for="complement">Complément <span class="text-body-secondary">(bâtiment, étage, chez… — facultatif)</span></label>
            <input class="form-control" type="text" id="complement" name="complement" maxlength="255" value="<?= e(ancien('complement', $defauts['complement'] ?? '')) ?>">
        </div>
        <div class="col-sm-8">
            <label class="form-label" for="etablissement">Établissement scolaire <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="text" id="etablissement" name="etablissement" maxlength="150" value="<?= e(ancien('etablissement', $defauts['etablissement'] ?? '')) ?>">
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="niveau">Niveau <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="text" id="niveau" name="niveau" maxlength="50" value="<?= e(ancien('niveau', $defauts['niveau'] ?? '')) ?>">
        </div>
    </div>
    <?php
}
