<?php

declare(strict_types=1);

/**
 * Statistiques (cahier des charges, section 10) et leurs réglages
 * (Paramètres > Options > Statistiques).
 *
 * Règles :
 *   - un adhérent est une personne inscrite pour la saison ; les structures
 *     sont exclues des chiffres par personne et comptées à part ;
 *   - sur une période libre : les personnes inscrites pour une saison qui
 *     recoupe la période, si leur inscription a été faite avant la fin de la
 *     période ; chacune est comptée une fois, avec son adresse la plus récente ;
 *   - l'âge est calculé au 31 décembre de l'année où commence la saison (ou la
 *     période), jamais stocké ;
 *   - un participant a une inscription en cours ou inactive à une activité,
 *     hors liste d'attente et hors annulations (une inscription inactive a
 *     libéré sa place, mais la personne a participé : elle reste comptée) ;
 *   - aucun montant n'apparaît : les statistiques sont ouvertes à tous les rôles.
 */

// ---------------------------------------------------------------------------
// Réglage : tranches d'âge
// ---------------------------------------------------------------------------

/** Âges de début de tranche proposés par défaut : 0-6, 7-11, 12-17, 18-25, 26-59, 60 et +. */
const STATS_TRANCHES_DEFAUT = '7,12,18,26,60';

/** Nombre maximal d'âges de début de tranche. */
const STATS_TRANCHES_MAX = 12;

/**
 * Lit une saisie d'âges de début de tranche (« 7, 12, 18 »).
 *
 * @return list<int>|null les âges, du plus petit au plus grand ; null si la saisie n'est pas valable
 */
function stats_tranches_lire(string $saisie): ?array
{
    $ages = [];
    foreach (preg_split('/[\s,;]+/', trim($saisie)) ?: [] as $morceau) {
        if ($morceau === '') {
            continue;
        }
        if (preg_match('/^\d{1,3}$/', $morceau) !== 1) {
            return null;
        }
        $age = (int) $morceau;
        if ($age < 1 || $age > 120) {
            return null;
        }
        $ages[$age] = $age;
    }
    if ($ages === [] || count($ages) > STATS_TRANCHES_MAX) {
        return null;
    }
    sort($ages);
    return $ages;
}

/**
 * Âges de début de tranche en vigueur (réglage « stats_tranches_age »).
 *
 * @return list<int>
 */
function stats_tranches_debuts(): array
{
    return stats_tranches_lire(parametre('stats_tranches_age', STATS_TRANCHES_DEFAUT))
        ?? stats_tranches_lire(STATS_TRANCHES_DEFAUT)
        ?? [];
}

/**
 * Tranches d'âge déduites des âges de début : elles se suivent sans trou ni
 * chevauchement, de 0 an à « et + ».
 *
 * @param list<int>|null $debuts âges de début (ceux du réglage par défaut)
 * @return list<array{libelle: string, min: int, max: int|null}>
 */
function stats_tranches_age(?array $debuts = null): array
{
    $debuts ??= stats_tranches_debuts();
    $tranches = [];
    $min = 0;
    foreach ($debuts as $debut) {
        $tranches[] = ['libelle' => $min . '-' . ($debut - 1) . ' ans', 'min' => $min, 'max' => $debut - 1];
        $min = $debut;
    }
    $tranches[] = ['libelle' => $min . ' ans et +', 'min' => $min, 'max' => null];
    return $tranches;
}

// ---------------------------------------------------------------------------
// Réglage : groupes de lieu de résidence
// ---------------------------------------------------------------------------

/** Groupe des adresses qui ne correspondent à aucun code (il n'est pas en base). */
const RESIDENCE_AUTRES = 'Autres';

/** Nombre maximal de codes postaux dans un groupe. */
const RESIDENCE_CODES_MAX = 100;

/**
 * Groupes de lieu de résidence, dans l'ordre d'affichage, avec leurs codes.
 *
 * @return list<array{id: int, nom: string, ordre: int, codes: list<string>}>
 */
function groupes_residence_liste(): array
{
    $groupes = [];
    foreach (db_lignes('SELECT id, nom, ordre FROM groupes_residence ORDER BY ordre, nom') as $ligne) {
        $groupes[entier($ligne['id'])] = [
            'id'    => entier($ligne['id']),
            'nom'   => texte($ligne['nom']),
            'ordre' => entier($ligne['ordre']),
            'codes' => [],
        ];
    }
    foreach (db_lignes('SELECT code, groupe_id FROM groupes_residence_codes ORDER BY code') as $ligne) {
        $groupe_id = entier($ligne['groupe_id']);
        if (isset($groupes[$groupe_id])) {
            $groupes[$groupe_id]['codes'][] = texte($ligne['code']);
        }
    }
    return array_values($groupes);
}

/**
 * Un groupe de lieu de résidence par son identifiant, ou null.
 *
 * @return array{id: int, nom: string, ordre: int, codes: list<string>}|null
 */
function groupe_residence_par_id(int $id): ?array
{
    foreach (groupes_residence_liste() as $groupe) {
        if ($groupe['id'] === $id) {
            return $groupe;
        }
    }
    return null;
}

/**
 * Lit une saisie de codes postaux (« 75008, 75009, 75 »).
 *
 * @return list<string>|null les codes, sans doublon ; null si l'un d'eux n'est pas valable
 */
function residence_codes_lire(string $saisie): ?array
{
    $codes = [];
    foreach (preg_split('/[\s,;]+/', trim($saisie)) ?: [] as $morceau) {
        if ($morceau === '') {
            continue;
        }
        // Un code postal complet (5 chiffres) ou un début de code (2 à 4 chiffres).
        if (preg_match('/^\d{2,5}$/', $morceau) !== 1) {
            return null;
        }
        $codes[$morceau] = $morceau;
    }
    sort($codes, SORT_STRING);
    return $codes;
}

/**
 * Vérifie un groupe de lieu de résidence avant création ou modification.
 *
 * @param int $sauf_id identifiant du groupe modifié (0 pour une création)
 * @return list<string> messages d'erreur (vide si tout est correct)
 */
function erreurs_groupe_residence(string $nom, string $ordre, string $codes_saisis, int $sauf_id = 0): array
{
    $erreurs = [];
    if ($nom === '' || mb_strlen($nom) > 100) {
        $erreurs[] = 'Le nom du groupe est obligatoire (100 caractères au maximum).';
    } elseif (texte_simplifie($nom) === texte_simplifie(RESIDENCE_AUTRES)) {
        $erreurs[] = 'Le groupe « ' . RESIDENCE_AUTRES . ' » existe déjà : il réunit les adresses qui ne sont dans aucun groupe.';
    } elseif (entier(db_valeur('SELECT COUNT(*) FROM groupes_residence WHERE nom = ? AND id <> ?', [$nom, $sauf_id])) > 0) {
        $erreurs[] = 'Un groupe porte déjà ce nom.';
    }
    if (preg_match('/^\d{1,3}$/', $ordre) !== 1) {
        $erreurs[] = 'Le rang est un nombre de 0 à 999.';
    }
    $codes = residence_codes_lire($codes_saisis);
    if ($codes === null) {
        $erreurs[] = 'Les codes postaux s\'écrivent en chiffres, séparés par des virgules : un code complet (75017) ou un début de code (75).';
    } elseif ($codes === []) {
        $erreurs[] = 'Indiquez au moins un code postal.';
    } elseif (count($codes) > RESIDENCE_CODES_MAX) {
        $erreurs[] = 'Un groupe contient ' . RESIDENCE_CODES_MAX . ' codes postaux au maximum.';
    } else {
        foreach (groupes_residence_liste() as $autre) {
            $communs = $autre['id'] === $sauf_id ? [] : array_intersect($codes, $autre['codes']);
            if ($communs !== []) {
                $erreurs[] = 'Déjà dans le groupe « ' . $autre['nom'] . ' » : ' . implode(', ', $communs) . '. Un code n\'appartient qu\'à un seul groupe.';
            }
        }
    }
    return $erreurs;
}

/**
 * Remplace les codes d'un groupe (à appeler dans une transaction).
 *
 * @param list<string> $codes
 */
function groupe_residence_codes_enregistrer(int $groupe_id, array $codes): void
{
    db_executer('DELETE FROM groupes_residence_codes WHERE groupe_id = ?', [$groupe_id]);
    foreach ($codes as $code) {
        db_executer('INSERT INTO groupes_residence_codes (code, groupe_id) VALUES (?, ?)', [$code, $groupe_id]);
    }
}

/**
 * Crée un groupe de lieu de résidence (données déjà vérifiées).
 *
 * @param list<string> $codes
 */
function groupe_residence_creer(string $nom, int $ordre, array $codes): int
{
    db()->beginTransaction();
    try {
        db_executer('INSERT INTO groupes_residence (nom, ordre) VALUES (?, ?)', [$nom, $ordre]);
        $id = entier(db()->lastInsertId());
        groupe_residence_codes_enregistrer($id, $codes);
        journaliser('creation', 'groupes_residence', $id, 'Groupe de lieu de résidence « ' . $nom . ' » créé', null, [
            'nom' => $nom, 'ordre' => $ordre, 'codes' => implode(', ', $codes),
        ]);
        db()->commit();
    } catch (Throwable $erreur) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $erreur;
    }
    return $id;
}

/**
 * Modifie un groupe de lieu de résidence (données déjà vérifiées).
 *
 * @param array{id: int, nom: string, ordre: int, codes: list<string>} $actuel
 * @param list<string>                                                  $codes
 * @return bool false si rien n'a changé
 */
function groupe_residence_modifier(array $actuel, string $nom, int $ordre, array $codes): bool
{
    if ($actuel['nom'] === $nom && $actuel['ordre'] === $ordre && $actuel['codes'] === $codes) {
        return false;
    }
    db()->beginTransaction();
    try {
        db_executer('UPDATE groupes_residence SET nom = ?, ordre = ? WHERE id = ?', [$nom, $ordre, $actuel['id']]);
        groupe_residence_codes_enregistrer($actuel['id'], $codes);
        journaliser(
            'modification',
            'groupes_residence',
            $actuel['id'],
            'Groupe de lieu de résidence « ' . $nom . ' » modifié',
            ['nom' => $actuel['nom'], 'ordre' => $actuel['ordre'], 'codes' => implode(', ', $actuel['codes'])],
            ['nom' => $nom, 'ordre' => $ordre, 'codes' => implode(', ', $codes)]
        );
        db()->commit();
    } catch (Throwable $erreur) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $erreur;
    }
    return true;
}

/**
 * Supprime un groupe de lieu de résidence et ses codes : ses adresses seront
 * comptées dans un autre groupe ou dans « Autres ».
 *
 * @param array{id: int, nom: string, ordre: int, codes: list<string>} $groupe
 */
function groupe_residence_supprimer(array $groupe): void
{
    db_executer('DELETE FROM groupes_residence WHERE id = ?', [$groupe['id']]);
    journaliser('suppression', 'groupes_residence', $groupe['id'], 'Groupe de lieu de résidence « ' . $groupe['nom'] . ' » supprimé', [
        'nom' => $groupe['nom'], 'ordre' => $groupe['ordre'], 'codes' => implode(', ', $groupe['codes']),
    ]);
}

/**
 * Groupe de lieu de résidence d'un code postal : celui du code le plus précis
 * qui lui correspond, sinon « Autres ».
 *
 * @param array<string, string> $noms_par_code nom du groupe pour chaque code
 */
function residence_groupe(string $code_postal, array $noms_par_code): string
{
    for ($longueur = strlen($code_postal); $longueur >= 2; $longueur--) {
        $debut = substr($code_postal, 0, $longueur);
        if (isset($noms_par_code[$debut])) {
            return $noms_par_code[$debut];
        }
    }
    return RESIDENCE_AUTRES;
}

// ---------------------------------------------------------------------------
// Réglage : couleurs des graphiques
// ---------------------------------------------------------------------------

/** Nombre de couleurs d'une palette (l'anneau le plus découpé compte 7 parts). */
const STATS_PALETTE_TAILLE = 8;

/** Palette utilisée tant qu'aucune n'a été choisie. */
const STATS_PALETTE_DEFAUT = 'defaut';

/** Nom de la palette composée par l'administrateur (réglage « stats_palette_perso »). */
const STATS_PALETTE_PERSO = 'perso';

/**
 * Palettes prédéfinies. La première couleur sert aux histogrammes ; les
 * suivantes aux parts des anneaux, dans l'ordre. Deux palettes partent de la
 * couleur principale de l'application (Options d'affichage).
 *
 * @return array<string, array{nom: string, couleurs: list<string>}>
 */
function stats_palettes(): array
{
    $principale = strtolower(parametre('couleur', COULEUR_DEFAUT));
    if (!couleur_valide($principale)) {
        $principale = COULEUR_DEFAUT;
    }
    return [
        'defaut' => [
            'nom'      => 'Par défaut : couleur principale et teintes vives',
            'couleurs' => [$principale, '#fd7e14', '#198754', '#6f42c1', '#ffc107', '#20c997', '#dc3545', '#6c757d'],
        ],
        'degrade' => [
            'nom'      => 'Dégradé de la couleur principale',
            // Teintes claires et foncées alternées, pour que deux parts voisines se distinguent.
            'couleurs' => [
                $principale,
                couleur_eclaircir($principale, 0.5),
                couleur_assombrir($principale, 0.35),
                couleur_eclaircir($principale, 0.75),
                couleur_assombrir($principale, 0.6),
                couleur_eclaircir($principale, 0.25),
                couleur_assombrir($principale, 0.2),
                couleur_eclaircir($principale, 0.88),
            ],
        ],
        'daltoniens' => [
            'nom'      => 'Adaptée aux daltoniens',
            // Palette d'Okabe et Ito, lisible avec les principales formes de daltonisme.
            'couleurs' => ['#0072b2', '#e69f00', '#009e73', '#cc79a7', '#56b4e9', '#d55e00', '#f0e442', '#000000'],
        ],
        'gris' => [
            'nom'      => 'Nuances de gris (impression en noir et blanc)',
            'couleurs' => ['#212529', '#ced4da', '#495057', '#e9ecef', '#6c757d', '#dee2e6', '#343a40', '#868e96'],
        ],
    ];
}

/**
 * Lit les couleurs saisies d'une palette personnalisée.
 *
 * @param list<string> $saisies
 * @return list<string>|null les couleurs en minuscules ; null s'il n'y en a pas exactement STATS_PALETTE_TAILLE valables
 */
function stats_palette_lire(array $saisies): ?array
{
    $couleurs = [];
    foreach ($saisies as $saisie) {
        if (!couleur_valide($saisie)) {
            return null;
        }
        $couleurs[] = strtolower($saisie);
    }
    return count($couleurs) === STATS_PALETTE_TAILLE ? $couleurs : null;
}

/**
 * Couleurs de la palette personnalisée enregistrée, ou null s'il n'y en a pas.
 *
 * @return list<string>|null
 */
function stats_palette_perso(): ?array
{
    $reglage = parametre('stats_palette_perso');
    return $reglage === '' ? null : stats_palette_lire(explode(',', $reglage));
}

/** Palette en vigueur : le nom d'une palette prédéfinie, ou STATS_PALETTE_PERSO. */
function stats_palette_choisie(): string
{
    $choisie = parametre('stats_palette', STATS_PALETTE_DEFAUT);
    if ($choisie === STATS_PALETTE_PERSO) {
        return stats_palette_perso() === null ? STATS_PALETTE_DEFAUT : STATS_PALETTE_PERSO;
    }
    return array_key_exists($choisie, stats_palettes()) ? $choisie : STATS_PALETTE_DEFAUT;
}

/**
 * Couleurs des graphiques, selon la palette en vigueur.
 *
 * @return list<string>
 */
function stats_palette_couleurs(): array
{
    $choisie = stats_palette_choisie();
    if ($choisie === STATS_PALETTE_PERSO) {
        return stats_palette_perso() ?? stats_palettes()[STATS_PALETTE_DEFAUT]['couleurs'];
    }
    return stats_palettes()[$choisie]['couleurs'];
}

/**
 * Affiche les pastilles d'une palette. C'est un dessin SVG : la politique de
 * sécurité du contenu interdit les styles écrits dans la page.
 *
 * @param list<string> $couleurs
 */
function stats_afficher_pastilles(array $couleurs): void
{
    $cote = 16;
    ?>
    <svg class="align-middle" width="<?= count($couleurs) * ($cote + 2) ?>" height="<?= $cote ?>" role="img" aria-label="<?= e('Couleurs : ' . implode(', ', $couleurs)) ?>">
        <?php foreach ($couleurs as $rang => $couleur) : ?>
            <rect x="<?= $rang * ($cote + 2) ?>" y="0" width="<?= $cote ?>" height="<?= $cote ?>" rx="3" fill="<?= e($couleur) ?>" stroke="#adb5bd" stroke-width="0.5"></rect>
        <?php endforeach; ?>
    </svg>
    <?php
}

// ---------------------------------------------------------------------------
// Périmètre des statistiques : une saison ou une période, un secteur, un type,
// une récurrence
// ---------------------------------------------------------------------------

/** Date à laquelle l'âge est calculé : le 31 décembre de l'année de début. */
function stats_date_age(string $date_debut): string
{
    return substr($date_debut, 0, 4) . '-12-31';
}

/**
 * Périmètre « une saison ».
 *
 * @param array<string, mixed> $saison
 * @return array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string}
 */
function stats_perimetre_saison(array $saison, int $secteur_id = 0, int $type_id = 0, int $recurrence_id = 0): array
{
    $debut = texte($saison['date_debut'] ?? '');
    return [
        'mode'          => 'saison',
        'saison_id'     => entier($saison['id'] ?? 0),
        'debut'         => $debut,
        'fin'           => texte($saison['date_fin'] ?? ''),
        'secteur_id'    => $secteur_id,
        'type_id'       => $type_id,
        'recurrence_id' => $recurrence_id,
        'date_age'      => stats_date_age($debut),
        'libelle'       => 'saison ' . texte($saison['nom'] ?? ''),
    ];
}

/**
 * Périmètre « une période libre » (dates AAAA-MM-JJ déjà vérifiées).
 *
 * @return array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string}
 */
function stats_perimetre_periode(string $debut, string $fin, int $secteur_id = 0, int $type_id = 0, int $recurrence_id = 0): array
{
    return [
        'mode'          => 'periode',
        'saison_id'     => 0,
        'debut'         => $debut,
        'fin'           => $fin,
        'secteur_id'    => $secteur_id,
        'type_id'       => $type_id,
        'recurrence_id' => $recurrence_id,
        'date_age'      => stats_date_age($debut),
        'libelle'       => 'du ' . date_fr($debut) . ' au ' . date_fr($fin),
    ];
}

/**
 * Périmètre de comparaison : la saison précédente, ou la même période un an
 * plus tôt. Null s'il n'existe pas de saison précédente.
 *
 * @param array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string} $perimetre
 * @return array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string}|null
 */
function stats_perimetre_precedent(array $perimetre): ?array
{
    if ($perimetre['mode'] === 'saison') {
        $precedente = db_ligne(
            'SELECT id, nom, date_debut, date_fin FROM saisons WHERE date_debut < ? ORDER BY date_debut DESC LIMIT 1',
            [$perimetre['debut']]
        );
        return $precedente === null
            ? null
            : stats_perimetre_saison($precedente, $perimetre['secteur_id'], $perimetre['type_id'], $perimetre['recurrence_id']);
    }
    return stats_perimetre_periode(
        (new DateTimeImmutable($perimetre['debut']))->modify('-1 year')->format('Y-m-d'),
        (new DateTimeImmutable($perimetre['fin']))->modify('-1 year')->format('Y-m-d'),
        $perimetre['secteur_id'],
        $perimetre['type_id'],
        $perimetre['recurrence_id']
    );
}

/**
 * Condition SQL qui retient les activités du périmètre (alias « ac »).
 *
 * @param array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string} $perimetre
 * @return array{sql: string, parametres: list<string|int>}
 */
function stats_condition_activites(array $perimetre): array
{
    if ($perimetre['mode'] === 'saison') {
        $sql = 'ac.saison_id = ?';
        $parametres = [$perimetre['saison_id']];
    } else {
        // Activités qui ont lieu, au moins en partie, pendant la période.
        $sql = 'ac.date_debut <= ? AND COALESCE(ac.date_fin, ac.date_debut) >= ?';
        $parametres = [$perimetre['fin'], $perimetre['debut']];
    }
    if ($perimetre['secteur_id'] > 0) {
        $sql .= ' AND ac.secteur_id = ?';
        $parametres[] = $perimetre['secteur_id'];
    }
    if ($perimetre['type_id'] > 0) {
        $sql .= ' AND ac.type_id = ?';
        $parametres[] = $perimetre['type_id'];
    }
    if ($perimetre['recurrence_id'] > 0) {
        $sql .= ' AND ac.recurrence_id = ?';
        $parametres[] = $perimetre['recurrence_id'];
    }
    return ['sql' => $sql, 'parametres' => $parametres];
}

// ---------------------------------------------------------------------------
// Adhérents
// ---------------------------------------------------------------------------

/**
 * Adhérents (personnes et structures) du périmètre : une ligne par adhérent,
 * avec l'adresse de sa saison la plus récente dans le périmètre.
 * Si un secteur, un type ou une récurrence est choisi, seuls les participants aux activités
 * correspondantes sont retenus (le « public » de ces activités).
 *
 * @param array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string} $perimetre
 * @return list<array<string, mixed>>
 */
function stats_adherents_lignes(array $perimetre): array
{
    if ($perimetre['mode'] === 'saison') {
        $condition = 'd.saison_id = ?';
        $parametres = [$perimetre['saison_id']];
    } else {
        $condition = 's.date_debut <= ? AND s.date_fin >= ? AND da.date_crea < ?';
        $parametres = [
            $perimetre['fin'],
            $perimetre['debut'],
            (new DateTimeImmutable($perimetre['fin']))->modify('+1 day')->format('Y-m-d'),
        ];
    }
    if ($perimetre['secteur_id'] > 0 || $perimetre['type_id'] > 0 || $perimetre['recurrence_id'] > 0) {
        $activites = stats_condition_activites($perimetre);
        $condition .= ' AND EXISTS (
                SELECT 1 FROM inscriptions i
                  JOIN activites ac ON ac.id = i.activite_id
                 WHERE i.adherent_id = a.id AND i.est_annulee = 0 AND i.statut IN (\'inscrit\', \'inactif\') AND ' . $activites['sql'] . ')';
        $parametres = [...$parametres, ...$activites['parametres']];
    }
    $lignes = db_lignes(
        'SELECT a.id, a.est_structure, a.famille_id, a.sexe, a.date_naissance, a.nationalite_iso3, p.continent,
                da.code_postal, da.quartier, da.est_qp, da.est_qpv, da.etablissement,
                (SELECT COUNT(*) FROM dossiers_adherents da2
                   JOIN dossiers d2 ON d2.id = da2.dossier_id
                   JOIN saisons s2 ON s2.id = d2.saison_id
                  WHERE da2.adherent_id = a.id AND s2.date_debut < s.date_debut) AS nb_saisons_avant
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN saisons s ON s.id = d.saison_id
           JOIN adherents a ON a.id = da.adherent_id
           LEFT JOIN pays p ON p.iso3 = a.nationalite_iso3
          WHERE ' . $condition . '
          ORDER BY a.id, s.date_debut DESC',
        $parametres
    );
    // Sur une période qui touche deux saisons, un adhérent a deux lignes : la plus récente est gardée.
    $adherents = [];
    foreach ($lignes as $ligne) {
        $adherents[entier($ligne['id'])] ??= $ligne;
    }
    return array_values($adherents);
}

/**
 * Chiffres des adhérents à partir des lignes de stats_adherents_lignes().
 * Chaque répartition est un tableau « libellé => nombre de personnes ».
 *
 * @param list<array<string, mixed>> $lignes
 * @return array{total: int, structures: int, nouveaux: int, readhesions: int, age_moyen: float|null, qp: int, qpv: int,
 *               sexe: array<string, int>, tranches: array<string, int>, nationalite: array<string, int>,
 *               residence: array<string, int>, quartier: array<string, int>, etablissement: array<string, int>}
 */
function stats_adherents_calculer(array $lignes, string $date_age): array
{
    $tranches_age = stats_tranches_age();
    $noms_par_code = [];
    $residence = [];
    foreach (groupes_residence_liste() as $groupe) {
        $residence[$groupe['nom']] = 0;
        foreach ($groupe['codes'] as $code) {
            $noms_par_code[$code] = $groupe['nom'];
        }
    }
    $residence[RESIDENCE_AUTRES] = 0;

    $sexe = array_fill_keys(array_values(libelles_sexes()), 0);
    $tranches = array_fill_keys(array_column($tranches_age, 'libelle'), 0);
    $nationalite = ['France' => 0, 'Autres pays d\'Europe' => 0, 'Afrique' => 0, 'Asie' => 0, 'Amériques' => 0, 'Océanie' => 0, 'Non renseignée' => 0];
    $quartier = [];
    $etablissement = [];
    $etablissement_noms = [];
    $total = 0;
    $structures = 0;
    $nouveaux = 0;
    $qp = 0;
    $qpv = 0;
    $somme_ages = 0;

    foreach ($lignes as $ligne) {
        if (entier($ligne['est_structure']) === 1) {
            $structures++;
            continue;
        }
        $total++;
        if (entier($ligne['nb_saisons_avant']) === 0) {
            $nouveaux++;
        }
        $libelle_sexe = libelles_sexes()[texte($ligne['sexe'])] ?? 'Autre';
        $sexe[$libelle_sexe] = ($sexe[$libelle_sexe] ?? 0) + 1;

        // Une personne née après la date de calcul a 0 an.
        $naissance = texte($ligne['date_naissance']);
        $age = $naissance > $date_age ? 0 : age_ans($naissance, $date_age);
        $somme_ages += $age;
        foreach ($tranches_age as $tranche) {
            if ($age >= $tranche['min'] && ($tranche['max'] === null || $age <= $tranche['max'])) {
                $tranches[$tranche['libelle']]++;
                break;
            }
        }

        $groupe_nationalite = match (true) {
            texte($ligne['nationalite_iso3']) === '' => 'Non renseignée',
            texte($ligne['nationalite_iso3']) === 'FRA' => 'France',
            default => match (texte($ligne['continent'])) {
                'Europe'   => 'Autres pays d\'Europe',
                'Afrique'  => 'Afrique',
                'Asie'     => 'Asie',
                'Amérique' => 'Amériques',
                'Océanie'  => 'Océanie',
                default    => 'Non renseignée',
            },
        };
        $nationalite[$groupe_nationalite]++;

        $residence[residence_groupe(texte($ligne['code_postal']), $noms_par_code)]++;

        $nom_quartier = texte($ligne['quartier']) !== '' ? texte($ligne['quartier']) : 'Sans quartier';
        $quartier[$nom_quartier] = ($quartier[$nom_quartier] ?? 0) + 1;
        $qp += entier($ligne['est_qp']) === 1 ? 1 : 0;
        $qpv += entier($ligne['est_qpv']) === 1 ? 1 : 0;

        // Établissement : texte libre, regroupé sans tenir compte des accents ni des majuscules.
        $nom_etablissement = trim(texte($ligne['etablissement']));
        if ($nom_etablissement !== '') {
            $cle = texte_simplifie($nom_etablissement);
            $etablissement_noms[$cle] ??= $nom_etablissement;
            $etablissement[$cle] = ($etablissement[$cle] ?? 0) + 1;
        }
    }

    // Quartiers et établissements : du plus représenté au moins représenté, « Sans quartier » en dernier.
    $sans_quartier = $quartier['Sans quartier'] ?? 0;
    unset($quartier['Sans quartier']);
    arsort($quartier);
    if ($sans_quartier > 0) {
        $quartier['Sans quartier'] = $sans_quartier;
    }
    arsort($etablissement);
    $etablissements = [];
    foreach ($etablissement as $cle => $nombre) {
        $etablissements[$etablissement_noms[$cle]] = $nombre;
    }
    // « Océanie » et « Non renseignée » ne s'affichent que si quelqu'un y figure.
    $nationalite = array_filter(
        $nationalite,
        static fn (int $nombre, string $libelle): bool => $nombre > 0 || !in_array($libelle, ['Océanie', 'Non renseignée'], true),
        ARRAY_FILTER_USE_BOTH
    );

    return [
        'total'         => $total,
        'structures'    => $structures,
        'nouveaux'      => $nouveaux,
        'readhesions'   => $total - $nouveaux,
        'age_moyen'     => $total > 0 ? $somme_ages / $total : null,
        'qp'            => $qp,
        'qpv'           => $qpv,
        'sexe'          => $sexe,
        'tranches'      => $tranches,
        'nationalite'   => $nationalite,
        'residence'     => $residence,
        'quartier'      => $quartier,
        'etablissement' => $etablissements,
    ];
}

// ---------------------------------------------------------------------------
// Activités
// ---------------------------------------------------------------------------

/** Chiffres suivis pour une activité, un secteur ou un type. */
const STATS_ACTIVITES_CHIFFRES = ['activites', 'participants', 'participations', 'familles', 'adultes', 'enfants', 'structures', 'attente'];

/**
 * Chiffres des activités du périmètre : total, par secteur, par type et par activité.
 *   activites      : nombre d'activités
 *   participants   : personnes différentes inscrites, inscriptions inactives comprises (hors structures)
 *   participations : places des inscrits et des inscriptions inactives (structures comprises)
 *   familles       : familles différentes concernées
 *   adultes / enfants : participants de 18 ans et plus / de moins de 18 ans, à la date de calcul de l'âge
 *   structures     : structures différentes inscrites
 *   attente        : places demandées en liste d'attente
 *
 * @param array{mode: 'saison'|'periode', saison_id: int, debut: string, fin: string, secteur_id: int, type_id: int, recurrence_id: int, date_age: string, libelle: string} $perimetre
 * @return array{total: array<string, int>, secteurs: array<string, array<string, int>>, types: array<string, array<string, int>>,
 *               activites: list<array{id: int, nom: string, secteur: string, type: string, chiffres: array<string, int>}>}
 */
function stats_activites_calculer(array $perimetre): array
{
    $condition = stats_condition_activites($perimetre);
    $lignes = db_lignes(
        'SELECT ac.id AS activite_id, ac.nom, se.nom AS secteur_nom, t.nom AS type_nom,
                i.adherent_id, i.nb_places, i.statut, a.est_structure, a.famille_id, a.date_naissance
           FROM activites ac
           JOIN secteurs se ON se.id = ac.secteur_id
           JOIN types_activite t ON t.id = ac.type_id
           LEFT JOIN inscriptions i ON i.activite_id = ac.id AND i.est_annulee = 0
           LEFT JOIN adherents a ON a.id = i.adherent_id
          WHERE ' . $condition['sql'] . '
          ORDER BY ac.nom, ac.date_debut, ac.id',
        $condition['parametres']
    );

    $vide = ['activites' => [], 'personnes' => [], 'familles' => [], 'adultes' => [], 'enfants' => [], 'structures' => [], 'participations' => 0, 'attente' => 0];
    /** @var array<string, array{activites: array<int, true>, personnes: array<int, true>, familles: array<int, true>, adultes: array<int, true>, enfants: array<int, true>, structures: array<int, true>, participations: int, attente: int}> $groupes */
    $groupes = ['total' => $vide];
    /** @var array<int, array{id: int, nom: string, secteur: string, type: string}> $activites */
    $activites = [];
    $secteurs = [];
    $types = [];

    foreach ($lignes as $ligne) {
        $activite_id = entier($ligne['activite_id']);
        $secteur = texte($ligne['secteur_nom']);
        $type = texte($ligne['type_nom']);
        $activites[$activite_id] ??= ['id' => $activite_id, 'nom' => texte($ligne['nom']), 'secteur' => $secteur, 'type' => $type];
        $secteurs[$secteur] = true;
        $types[$type] = true;

        foreach (['total', 'secteur:' . $secteur, 'type:' . $type, 'activite:' . $activite_id] as $cle) {
            $groupes[$cle] ??= $vide;
            $groupes[$cle]['activites'][$activite_id] = true;
            if ($ligne['adherent_id'] === null) {
                continue;
            }
            $places = entier($ligne['nb_places']);
            if (texte($ligne['statut']) === 'attente') {
                $groupes[$cle]['attente'] += $places;
                continue;
            }
            $adherent_id = entier($ligne['adherent_id']);
            $groupes[$cle]['participations'] += $places;
            if (entier($ligne['est_structure']) === 1) {
                $groupes[$cle]['structures'][$adherent_id] = true;
                continue;
            }
            $groupes[$cle]['personnes'][$adherent_id] = true;
            $groupes[$cle]['familles'][entier($ligne['famille_id'])] = true;
            $naissance = texte($ligne['date_naissance']);
            $age = $naissance > $perimetre['date_age'] ? 0 : age_ans($naissance, $perimetre['date_age']);
            $groupes[$cle][$age >= AGE_MAJORITE ? 'adultes' : 'enfants'][$adherent_id] = true;
        }
    }

    $chiffres = static function (string $cle) use ($groupes, $vide): array {
        $groupe = $groupes[$cle] ?? $vide;
        return [
            'activites'      => count($groupe['activites']),
            'participants'   => count($groupe['personnes']),
            'participations' => $groupe['participations'],
            'familles'       => count($groupe['familles']),
            'adultes'        => count($groupe['adultes']),
            'enfants'        => count($groupe['enfants']),
            'structures'     => count($groupe['structures']),
            'attente'        => $groupe['attente'],
        ];
    };

    ksort($secteurs, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($types, SORT_NATURAL | SORT_FLAG_CASE);
    $par_secteur = [];
    foreach (array_keys($secteurs) as $secteur) {
        $par_secteur[$secteur] = $chiffres('secteur:' . $secteur);
    }
    $par_type = [];
    foreach (array_keys($types) as $type) {
        $par_type[$type] = $chiffres('type:' . $type);
    }
    $par_activite = [];
    foreach ($activites as $activite) {
        $par_activite[] = $activite + ['chiffres' => $chiffres('activite:' . $activite['id'])];
    }

    return ['total' => $chiffres('total'), 'secteurs' => $par_secteur, 'types' => $par_type, 'activites' => $par_activite];
}

// ---------------------------------------------------------------------------
// Affichage
// ---------------------------------------------------------------------------

/** Nombre au format français, avec une décimale : « 12,5 ». */
function stats_decimal(float $nombre): string
{
    return number_format($nombre, 1, ',', "\u{202F}");
}

/** Part d'un nombre dans un total : « 12,5 % » ('' si le total est nul). */
function stats_part(int $nombre, int $total): string
{
    return $total > 0 ? stats_decimal($nombre * 100 / $total) . ' %' : '';
}

/** Évolution entre deux nombres : « +12,5 % » ('' si la comparaison est impossible). */
function stats_evolution(int $nombre, ?int $precedent): string
{
    if ($precedent === null || $precedent === 0) {
        return '';
    }
    $evolution = ($nombre - $precedent) * 100 / $precedent;
    return ($evolution > 0 ? '+' : '') . stats_decimal($evolution) . ' %';
}

/**
 * Réunit une répartition et celle du périmètre de comparaison.
 *
 * @param array<string, int>      $courant
 * @param array<string, int>|null $precedent null s'il n'y a pas de comparaison
 * @return list<array{libelle: string, nombre: int, precedent: int|null}>
 */
function stats_lignes(array $courant, ?array $precedent): array
{
    $lignes = [];
    foreach ($courant as $libelle => $nombre) {
        $lignes[] = ['libelle' => (string) $libelle, 'nombre' => $nombre, 'precedent' => $precedent === null ? null : ($precedent[$libelle] ?? 0)];
    }
    foreach ($precedent ?? [] as $libelle => $nombre) {
        if (!array_key_exists($libelle, $courant) && $nombre > 0) {
            $lignes[] = ['libelle' => (string) $libelle, 'nombre' => 0, 'precedent' => $nombre];
        }
    }
    return $lignes;
}

/**
 * Description d'un graphique, lue par public/js/statistiques.js
 * (attribut data-graphique d'un <canvas>).
 *
 * @param 'bar'|'doughnut'                                                    $type
 * @param list<array{libelle: string, nombre: int, precedent: int|null}> $lignes
 */
function stats_graphique_json(string $type, array $lignes, string $nom_serie, string $nom_precedent = ''): string
{
    $series = [['nom' => $nom_serie, 'valeurs' => array_column($lignes, 'nombre')]];
    // La comparaison ne se dessine que sur un histogramme (deux barres côte à côte).
    if ($type === 'bar' && $nom_precedent !== '' && $lignes !== [] && $lignes[0]['precedent'] !== null) {
        $series[] = ['nom' => $nom_precedent, 'valeurs' => array_map(static fn (array $ligne): int => $ligne['precedent'] ?? 0, $lignes)];
    }
    $json = json_encode(
        ['type' => $type, 'libelles' => array_column($lignes, 'libelle'), 'series' => $series],
        JSON_UNESCAPED_UNICODE
    );
    return $json === false ? '{}' : $json;
}

/**
 * Affiche une répartition : un graphique et, dessous, le tableau des chiffres.
 *
 * @param list<array{libelle: string, nombre: int, precedent: int|null}> $lignes
 * @param ''|'bar'|'doughnut'                                               $type          '' = pas de graphique
 * @param int                                                               $total         base des pourcentages
 * @param string                                                            $nom_precedent libellé de la comparaison ('' = aucune)
 */
function stats_afficher_repartition(string $titre, array $lignes, int $total, string $type, string $nom_serie, string $nom_precedent = ''): void
{
    $avec_precedent = $nom_precedent !== '';
    ?>
    <section class="stats-bloc border rounded p-3 h-100">
        <h3 class="h6 mb-3"><?= e($titre) ?></h3>
        <?php if ($lignes === []) : ?>
            <p class="text-body-secondary mb-0">Aucune donnée.</p>
        <?php else : ?>
            <?php if ($type !== '' && $total > 0) : ?>
                <div class="stats-graphique mb-3">
                    <canvas role="img" aria-label="<?= e('Graphique : ' . $titre . ' (chiffres dans le tableau qui suit)') ?>" data-graphique="<?= e(stats_graphique_json($type, $lignes, $nom_serie, $nom_precedent)) ?>"></canvas>
                </div>
            <?php endif; ?>
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden"><?= e($titre) ?></span></th>
                        <th scope="col" class="text-end">Nombre</th>
                        <th scope="col" class="text-end">Part</th>
                        <?php if ($avec_precedent) : ?>
                            <th scope="col" class="text-end text-nowrap"><?= e($nom_precedent) ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne) : ?>
                        <tr>
                            <th scope="row" class="fw-normal"><?= e($ligne['libelle']) ?></th>
                            <td class="text-end"><?= $ligne['nombre'] ?></td>
                            <td class="text-end text-nowrap"><?= e(stats_part($ligne['nombre'], $total)) ?></td>
                            <?php if ($avec_precedent) : ?>
                                <td class="text-end text-body-secondary"><?= $ligne['precedent'] ?? 0 ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Affiche un chiffre clé, avec son évolution par rapport à la comparaison.
 *
 * @param string   $valeur    chiffre déjà mis en forme
 * @param int|null $nombre    chiffre brut, pour calculer l'évolution (null = pas d'évolution)
 * @param int|null $precedent chiffre de la comparaison (null = pas de comparaison)
 */
function stats_afficher_chiffre(string $libelle, string $valeur, ?int $nombre = null, ?int $precedent = null, string $nom_precedent = ''): void
{
    ?>
    <div class="col">
        <div class="stats-bloc border rounded p-3 h-100">
            <div class="small text-body-secondary"><?= e($libelle) ?></div>
            <div class="fs-3 fw-semibold"><?= e($valeur) ?></div>
            <?php if ($nombre !== null && $precedent !== null && $nom_precedent !== '') : ?>
                <div class="small text-body-secondary">
                    <?= e($nom_precedent) ?> : <?= $precedent ?>
                    <?php if (stats_evolution($nombre, $precedent) !== '') : ?>
                        (<?= e(stats_evolution($nombre, $precedent)) ?>)
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
