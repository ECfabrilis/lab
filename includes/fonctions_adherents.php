<?php

declare(strict_types=1);

/**
 * Adhérents : fiche, vérifications, doublons, recherche.
 *
 * Un adhérent est une personne (rattachée à une famille) ou une structure
 * (association, école… : sans famille, sans prénom, sexe ni date de naissance).
 */

/** Âge à partir duquel une personne n'a plus besoin d'un responsable. */
const AGE_MAJORITE = 18;

/** Nombre d'adhérents affichés par page dans la liste. */
const ADHERENTS_PAR_PAGE = 50;

/** Nombre maximal d'adhérents proches proposés pendant une inscription. */
const DOUBLONS_PROPOSES_MAX = 5;

/**
 * Libellés des sexes.
 *
 * @return array<string, string>
 */
function libelles_sexes(): array
{
    return ['H' => 'Homme', 'F' => 'Femme', 'A' => 'Autre'];
}

/** Âge en années révolues à une date donnée (aujourd'hui par défaut). L'âge n'est jamais stocké. */
function age_ans(string $date_naissance, ?string $a_la_date = null): int
{
    if (!date_valide($date_naissance)) {
        return 0;
    }
    $reference = new DateTimeImmutable($a_la_date ?? 'today');
    return (new DateTimeImmutable($date_naissance))->diff($reference)->y;
}

/** Vrai si la personne est mineure aujourd'hui. */
function est_mineur(string $date_naissance): bool
{
    return date_valide($date_naissance) && age_ans($date_naissance) < AGE_MAJORITE;
}

/**
 * Nom affiché d'un adhérent : « Prénom Nom », ou le nom seul pour une structure.
 *
 * @param array<string, mixed> $adherent
 */
function nom_adherent(array $adherent): string
{
    return trim(texte($adherent['prenom'] ?? '') . ' ' . texte($adherent['nom'] ?? ''));
}

/**
 * Pays proposés pour la nationalité : la France d'abord, puis l'ordre alphabétique.
 *
 * @return array<string, string> code du pays => nom
 */
function pays_liste(): array
{
    /** @var array<string, string>|null $pays */
    static $pays = null;
    if ($pays === null) {
        $pays = [];
        foreach (db_lignes("SELECT iso3, name_fr FROM pays ORDER BY (iso3 = 'FRA') DESC, name_fr") as $ligne) {
            $pays[texte($ligne['iso3'])] = texte($ligne['name_fr']);
        }
    }
    return $pays;
}

/**
 * Un adhérent par son identifiant, avec le nom de sa famille et de son pays, ou null.
 *
 * @return array<string, mixed>|null
 */
function adherent_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT a.*, f.nom AS famille_nom, p.name_fr AS nationalite_nom
           FROM adherents a
           LEFT JOIN familles f ON f.id = a.famille_id
           LEFT JOIN pays p ON p.iso3 = a.nationalite_iso3
          WHERE a.id = ?',
        [$id]
    );
}

/**
 * Fiche vide : toutes les clés d'une saisie, sans valeur.
 *
 * @return array<string, string>
 */
function adherent_saisie_vide(bool $est_structure = false): array
{
    return [
        'est_structure'    => $est_structure ? '1' : '0',
        'nom'              => '',
        'prenom'           => '',
        'sexe'             => '',
        'date_naissance'   => '',
        'nationalite_iso3' => '',
        'mail'             => '',
        'tel'              => '',
        'responsable_nom'  => '',
        'responsable_mail' => '',
        'responsable_tel'  => '',
        'est_benevole'     => '0',
        'a_consenti'       => '0',
    ];
}

/**
 * Champs de la fiche adhérent lus dans le formulaire envoyé.
 * Pour une structure, les champs propres aux personnes restent vides.
 *
 * @return array<string, string>
 */
function adherent_saisie(bool $est_structure = false): array
{
    $saisie = adherent_saisie_vide($est_structure);
    $saisie['nom'] = post('nom');
    $saisie['mail'] = post('mail');
    $saisie['tel'] = post('tel');
    $saisie['a_consenti'] = post('a_consenti') === '1' ? '1' : '0';
    if (!$est_structure) {
        $saisie['prenom'] = post('prenom');
        $saisie['sexe'] = post('sexe');
        $saisie['date_naissance'] = post('date_naissance');
        $saisie['nationalite_iso3'] = post('nationalite_iso3');
        $saisie['responsable_nom'] = post('responsable_nom');
        $saisie['responsable_mail'] = post('responsable_mail');
        $saisie['responsable_tel'] = post('responsable_tel');
        $saisie['est_benevole'] = post('est_benevole') === '1' ? '1' : '0';
    }
    return $saisie;
}

/** Vrai si le texte ressemble à un numéro de téléphone (chiffres, espaces, + . - et parenthèses). */
function telephone_valide(string $tel): bool
{
    return preg_match('/^[0-9+ .()\-]{6,20}$/', $tel) === 1;
}

/**
 * Vérifie l'identité : nom, et pour une personne prénom, sexe, date de naissance, nationalité.
 *
 * @param array<string, string> $saisie
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_identite(array $saisie): array
{
    $erreurs = [];
    if ($saisie['est_structure'] === '1') {
        if ($saisie['nom'] === '' || mb_strlen($saisie['nom']) > 100) {
            $erreurs[] = 'Le nom de la structure est obligatoire (100 caractères au maximum).';
        }
        return $erreurs;
    }

    if ($saisie['nom'] === '' || mb_strlen($saisie['nom']) > 100) {
        $erreurs[] = 'Le nom est obligatoire (100 caractères au maximum).';
    }
    if ($saisie['prenom'] === '' || mb_strlen($saisie['prenom']) > 100) {
        $erreurs[] = 'Le prénom est obligatoire (100 caractères au maximum).';
    }
    if (!array_key_exists($saisie['sexe'], libelles_sexes())) {
        $erreurs[] = 'Choisissez Homme, Femme ou Autre.';
    }
    if (!date_naissance_valide($saisie['date_naissance'])) {
        $erreurs[] = 'La date de naissance est obligatoire et ne peut pas être dans le futur.';
    }
    if ($saisie['nationalite_iso3'] !== '' && !array_key_exists($saisie['nationalite_iso3'], pays_liste())) {
        $erreurs[] = 'La nationalité choisie n\'existe pas dans la liste des pays.';
    }
    return $erreurs;
}

/** Vrai si la date de naissance existe, n'est pas dans le futur et n'est pas antérieure à 1900. */
function date_naissance_valide(string $date): bool
{
    return date_valide($date) && $date <= date('Y-m-d') && $date >= '1900-01-01';
}

/**
 * Vérifie le contact, et le responsable si la personne est mineure.
 *
 * @param array<string, string> $saisie
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_contact(array $saisie): array
{
    $erreurs = [];
    if ($saisie['mail'] !== '' && (filter_var($saisie['mail'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($saisie['mail']) > 150)) {
        $erreurs[] = 'L\'adresse e-mail n\'est pas valide.';
    }
    if ($saisie['tel'] !== '' && !telephone_valide($saisie['tel'])) {
        $erreurs[] = 'Le téléphone n\'est pas valide (chiffres, espaces et + seulement).';
    }
    if ($saisie['est_structure'] === '1') {
        return $erreurs;
    }

    if (mb_strlen($saisie['responsable_nom']) > 150) {
        $erreurs[] = 'Le nom du responsable est trop long (150 caractères au maximum).';
    }
    if ($saisie['responsable_mail'] !== '' && (filter_var($saisie['responsable_mail'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($saisie['responsable_mail']) > 150)) {
        $erreurs[] = 'L\'adresse e-mail du responsable n\'est pas valide.';
    }
    if ($saisie['responsable_tel'] !== '' && !telephone_valide($saisie['responsable_tel'])) {
        $erreurs[] = 'Le téléphone du responsable n\'est pas valide.';
    }
    // Responsable : obligatoire pour un mineur.
    if (est_mineur($saisie['date_naissance'])) {
        if ($saisie['responsable_nom'] === '') {
            $erreurs[] = 'Cette personne est mineure : le nom de son responsable adulte est obligatoire.';
        }
        if ($saisie['responsable_mail'] === '' && $saisie['responsable_tel'] === '') {
            $erreurs[] = 'Cette personne est mineure : indiquez le téléphone ou l\'adresse e-mail de son responsable.';
        }
    }
    return $erreurs;
}

/**
 * Vérifie la fiche complète d'un adhérent.
 *
 * @param array<string, string> $saisie
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_adherent(array $saisie): array
{
    return [...erreurs_identite($saisie), ...erreurs_contact($saisie)];
}

/**
 * Personnes qui portent déjà exactement ce nom, ce prénom et cette date de naissance.
 *
 * @return list<array<string, mixed>>
 */
function adherent_doublons(string $nom, string $prenom, string $date_naissance, int $sauf_id = 0): array
{
    return db_lignes(
        'SELECT a.id, a.nom, a.prenom, f.nom AS famille_nom
           FROM adherents a
           LEFT JOIN familles f ON f.id = a.famille_id
          WHERE a.nom = ? AND a.prenom = ? AND a.date_naissance = ? AND a.id <> ? AND a.anonymise_le IS NULL',
        [$nom, $prenom, $date_naissance, $sauf_id]
    );
}

/**
 * Adhérents « proches » d'une personne en cours de saisie : même date de
 * naissance, ou nom et prénom presque identiques (sans tenir compte des
 * accents ni des majuscules ; écart accepté sur chacun : voir ecart_tolere()).
 * Pour une structure : nom presque identique.
 *
 * @return list<array{id: int, nom: string, naissance: string, famille: string, saison: string}> les plus proches d'abord
 */
function adherents_proches(string $nom, string $prenom, string $date_naissance, bool $est_structure = false): array
{
    $nom_simple = texte_simplifie($nom);
    $prenom_simple = texte_simplifie($prenom);
    $date_utile = !$est_structure && date_valide($date_naissance);
    $noms_utiles = $est_structure ? mb_strlen($nom_simple) >= 3 : (mb_strlen($nom_simple) >= 2 && mb_strlen($prenom_simple) >= 2);
    if (!$date_utile && !$noms_utiles) {
        return [];
    }

    // La volumétrie (quelques milliers de fiches) permet de comparer en PHP.
    $candidats = db_lignes(
        'SELECT a.id, a.nom, a.prenom, a.date_naissance, f.nom AS famille_nom,
                (SELECT s.nom FROM dossiers_adherents da
                   JOIN dossiers d ON d.id = da.dossier_id
                   JOIN saisons s ON s.id = d.saison_id
                  WHERE da.adherent_id = a.id
                  ORDER BY s.date_debut DESC LIMIT 1) AS derniere_saison
           FROM adherents a
           LEFT JOIN familles f ON f.id = a.famille_id
          WHERE a.anonymise_le IS NULL AND a.est_structure = ?',
        [$est_structure ? 1 : 0]
    );

    $proches = [];
    foreach ($candidats as $candidat) {
        $meme_date = $date_utile && texte($candidat['date_naissance']) === $date_naissance;
        // Le nom et le prénom sont comparés séparément : « Dupond Michel » est
        // proche de « Dupont Michou » (1 lettre d'écart sur le nom, 2 sur le prénom).
        $ecart = 99;
        $noms_proches = false;
        if ($noms_utiles) {
            $nom_candidat = texte_simplifie(texte($candidat['nom']));
            $ecart = ecart_noms($nom_simple, $nom_candidat);
            $noms_proches = $ecart <= ecart_tolere($nom_simple);
            if ($est_structure) {
                // Structure : un nom contenu dans l'autre suffit (« Club Yoga » et « Club Yoga 17 »).
                if (str_contains($nom_candidat, $nom_simple) || str_contains($nom_simple, $nom_candidat)) {
                    $ecart = 0;
                    $noms_proches = true;
                }
            } else {
                $ecart_prenom = ecart_noms($prenom_simple, texte_simplifie(texte($candidat['prenom'])));
                $noms_proches = $noms_proches && $ecart_prenom <= ecart_tolere($prenom_simple);
                $ecart += $ecart_prenom;
            }
        }
        if (!$meme_date && !$noms_proches) {
            continue;
        }
        $proches[] = [
            // Classement : les noms les plus proches d'abord, la même date de naissance en plus.
            'rang'      => min($ecart, 10) * 2 - ($meme_date ? 1 : 0),
            'id'        => entier($candidat['id']),
            'nom'       => nom_adherent($candidat),
            'naissance' => date_fr($candidat['date_naissance']),
            'famille'   => texte($candidat['famille_nom']),
            'saison'    => texte($candidat['derniere_saison']),
        ];
    }
    usort($proches, static fn (array $a, array $b): int => $a['rang'] <=> $b['rang']);

    $resultats = [];
    foreach (array_slice($proches, 0, DOUBLONS_PROPOSES_MAX) as $proche) {
        unset($proche['rang']);
        $resultats[] = $proche;
    }
    return $resultats;
}

/**
 * Écart entre un nom en cours de saisie et un nom enregistré (tous deux
 * simplifiés) : nombre de lettres à changer, ou 0 si le nom enregistré
 * commence par ce qui est saisi (la saisie n'est peut-être pas finie).
 */
function ecart_noms(string $saisi, string $enregistre): int
{
    if ($saisi !== '' && str_starts_with($enregistre, $saisi)) {
        return 0;
    }
    return levenshtein($saisi, $enregistre);
}

/**
 * Écart accepté pour dire que deux noms sont « presque identiques » :
 * 1 lettre pour un nom court, 2 à partir de 5 lettres, 3 à partir de 8.
 */
function ecart_tolere(string $nom): int
{
    $longueur = mb_strlen($nom);
    return $longueur >= 8 ? 3 : ($longueur >= 5 ? 2 : 1);
}

/** Renvoie null pour un texte vide (colonnes facultatives de la base). */
function nul_si_vide(string $valeur): ?string
{
    return $valeur === '' ? null : $valeur;
}

/**
 * Crée un adhérent : une personne dans une famille, ou une structure (sans famille).
 *
 * @param int|null              $famille_id null pour une structure
 * @param array<string, string> $saisie     fiche déjà vérifiée
 * @return int identifiant du nouvel adhérent
 */
function creer_adherent(?int $famille_id, array $saisie): int
{
    $est_structure = $saisie['est_structure'] === '1';
    $consent = $saisie['a_consenti'] === '1';
    db_executer(
        'INSERT INTO adherents (est_structure, famille_id, nom, prenom, sexe, date_naissance, nationalite_iso3, mail, tel,
                                responsable_nom, responsable_mail, responsable_tel,
                                est_benevole, a_consenti, consentement_le, creation_par)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $est_structure ? 1 : 0,
            $est_structure ? null : $famille_id,
            $saisie['nom'],
            nul_si_vide($saisie['prenom']),
            nul_si_vide($saisie['sexe']),
            nul_si_vide($saisie['date_naissance']),
            nul_si_vide($saisie['nationalite_iso3']),
            nul_si_vide($saisie['mail']),
            nul_si_vide($saisie['tel']),
            nul_si_vide($saisie['responsable_nom']),
            nul_si_vide($saisie['responsable_mail']),
            nul_si_vide($saisie['responsable_tel']),
            (int) $saisie['est_benevole'],
            $consent ? 1 : 0,
            $consent ? date('Y-m-d H:i:s') : null,
            entier($_SESSION['utilisateur_id'] ?? 0),
        ]
    );
    $adherent_id = entier(db()->lastInsertId());
    journaliser(
        'creation',
        'adherents',
        $adherent_id,
        ($est_structure ? 'Création de la structure ' : 'Création de l\'adhérent ') . nom_adherent($saisie),
        null,
        $saisie
    );
    return $adherent_id;
}

/**
 * Modifie la fiche d'un adhérent (une personne reste une personne, une structure reste une structure).
 *
 * @param array<string, mixed>  $actuel fiche en base avant la modification
 * @param array<string, string> $saisie fiche déjà vérifiée
 * @return bool false s'il n'y avait rien à modifier
 */
function modifier_adherent(array $actuel, array $saisie): bool
{
    $ancien = [];
    foreach (array_keys($saisie) as $champ) {
        $ancien[$champ] = texte($actuel[$champ] ?? '');
    }
    if ($ancien === $saisie) {
        return false;
    }

    // La date du consentement est posée quand il est donné, effacée quand il est retiré.
    $consent = $saisie['a_consenti'] === '1';
    $consentement_le = null;
    if ($consent) {
        $consentement_le = $ancien['a_consenti'] === '1' && texte($actuel['consentement_le'] ?? '') !== ''
            ? texte($actuel['consentement_le'])
            : date('Y-m-d H:i:s');
    }

    $adherent_id = entier($actuel['id'] ?? 0);
    db_executer(
        'UPDATE adherents
            SET nom = ?, prenom = ?, sexe = ?, date_naissance = ?, nationalite_iso3 = ?, mail = ?, tel = ?,
                responsable_nom = ?, responsable_mail = ?, responsable_tel = ?,
                est_benevole = ?, a_consenti = ?, consentement_le = ?, modification_par = ?
          WHERE id = ?',
        [
            $saisie['nom'],
            nul_si_vide($saisie['prenom']),
            nul_si_vide($saisie['sexe']),
            nul_si_vide($saisie['date_naissance']),
            nul_si_vide($saisie['nationalite_iso3']),
            nul_si_vide($saisie['mail']),
            nul_si_vide($saisie['tel']),
            nul_si_vide($saisie['responsable_nom']),
            nul_si_vide($saisie['responsable_mail']),
            nul_si_vide($saisie['responsable_tel']),
            (int) $saisie['est_benevole'],
            $consent ? 1 : 0,
            $consentement_le,
            entier($_SESSION['utilisateur_id'] ?? 0),
            $adherent_id,
        ]
    );
    journaliser('modification', 'adherents', $adherent_id, 'Modification de la fiche de ' . nom_adherent($saisie), $ancien, $saisie);
    return true;
}

/**
 * Recherche d'adhérents (personnes et structures) par nom, prénom, famille, courriel ou téléphone.
 * Chaque mot saisi doit être trouvé dans l'un de ces champs.
 *
 * @param bool $saison_seule true : seulement les adhérents présents dans un dossier de la saison
 * @return array{total: int, lignes: list<array<string, mixed>>}
 */
function adherents_rechercher(string $recherche, int $saison_id, bool $saison_seule, int $page = 1): array
{
    $conditions = ['a.anonymise_le IS NULL'];
    $parametres = [];
    if ($saison_seule) {
        $conditions[] = 'da.adherent_id IS NOT NULL';
    }
    $mots = preg_split('/\s+/', $recherche, -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($mots === false ? [] : $mots, 0, 5) as $mot) {
        // % et _ sont des caractères spéciaux de LIKE : on les neutralise.
        $motif = '%' . addcslashes($mot, '%_\\') . '%';
        $conditions[] = '(a.nom LIKE ? OR a.prenom LIKE ? OR f.nom LIKE ? OR a.mail LIKE ? OR a.tel LIKE ?)';
        array_push($parametres, $motif, $motif, $motif, $motif, $motif);
    }

    $base = ' FROM adherents a
              LEFT JOIN familles f ON f.id = a.famille_id
              LEFT JOIN dossiers d ON d.saison_id = ? AND (d.famille_id = a.famille_id OR d.structure_id = a.id)
              LEFT JOIN dossiers_adherents da ON da.dossier_id = d.id AND da.adherent_id = a.id
             WHERE ' . implode(' AND ', $conditions);
    array_unshift($parametres, $saison_id);

    $total = entier(db_valeur('SELECT COUNT(*)' . $base, $parametres));

    array_push($parametres, ADHERENTS_PAR_PAGE, (max(1, $page) - 1) * ADHERENTS_PAR_PAGE);
    $lignes = db_lignes(
        'SELECT a.id, a.est_structure, a.nom, a.prenom, a.date_naissance, a.mail, a.tel, a.famille_id, f.nom AS famille_nom,
                d.id AS dossier_id, da.code_postal, da.ville, (da.adherent_id IS NOT NULL) AS dans_saison'
        . $base . ' ORDER BY a.nom, a.prenom, a.id LIMIT ? OFFSET ?',
        $parametres
    );
    return ['total' => $total, 'lignes' => $lignes];
}

/**
 * Propositions pendant la frappe dans la recherche d'adhérents : chaque mot
 * saisi doit commencer un mot du nom ou du prénom (8 propositions au maximum).
 *
 * @param bool $saison_seule true : seulement les adhérents présents dans un dossier de la saison
 * @return list<array{id: int, nom: string, detail: string}>
 */
function adherents_suggestions(string $recherche, int $saison_id, bool $saison_seule): array
{
    $recherche = trim($recherche);
    if (mb_strlen($recherche) < 3) {
        return [];
    }
    $conditions = ['a.anonymise_le IS NULL'];
    $parametres = [$saison_id];
    if ($saison_seule) {
        $conditions[] = 'da.adherent_id IS NOT NULL';
    }
    $mots = preg_split('/\s+/', $recherche, -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($mots === false ? [] : $mots, 0, 4) as $mot) {
        $mot = addcslashes($mot, '%_\\');
        // Début du nom ou du prénom, ou début d'un mot à l'intérieur (nom composé).
        $conditions[] = '(a.nom LIKE ? OR a.nom LIKE ? OR a.nom LIKE ? OR a.prenom LIKE ? OR a.prenom LIKE ? OR a.prenom LIKE ?)';
        array_push($parametres, $mot . '%', '% ' . $mot . '%', '%-' . $mot . '%', $mot . '%', '% ' . $mot . '%', '%-' . $mot . '%');
    }
    $lignes = db_lignes(
        'SELECT a.id, a.est_structure, a.nom, a.prenom, a.date_naissance, f.nom AS famille_nom
           FROM adherents a
           LEFT JOIN familles f ON f.id = a.famille_id
           LEFT JOIN dossiers d ON d.saison_id = ? AND (d.famille_id = a.famille_id OR d.structure_id = a.id)
           LEFT JOIN dossiers_adherents da ON da.dossier_id = d.id AND da.adherent_id = a.id
          WHERE ' . implode(' AND ', $conditions) . '
          ORDER BY a.nom, a.prenom, a.id
          LIMIT 8',
        $parametres
    );
    $propositions = [];
    foreach ($lignes as $ligne) {
        $propositions[] = [
            'id'     => entier($ligne['id']),
            'nom'    => trim(texte($ligne['nom']) . ' ' . texte($ligne['prenom'])),
            'detail' => entier($ligne['est_structure']) === 1
                ? 'Structure'
                : age_ans(texte($ligne['date_naissance'])) . ' ans — famille ' . texte($ligne['famille_nom']),
        ];
    }
    return $propositions;
}

/**
 * Autres saisons pour lesquelles un adhérent a un dossier (la plus récente d'abord).
 *
 * @return list<array<string, mixed>>
 */
function autres_saisons_adherent(int $adherent_id, int $sauf_saison_id): array
{
    return db_lignes(
        'SELECT s.id, s.nom
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN saisons s ON s.id = d.saison_id
          WHERE da.adherent_id = ? AND s.id <> ?
          ORDER BY s.date_debut DESC',
        [$adherent_id, $sauf_saison_id]
    );
}

/**
 * Affiche les champs de la fiche d'un adhérent existant (page adherent.php).
 * Le formulaire doit contenir <input type="hidden" name="formulaire" value="adherent">
 * pour que les cases à cocher soient réaffichées correctement après une erreur.
 *
 * @param array<string, mixed> $defauts valeurs affichées quand rien n'a été saisi
 */
function afficher_champs_adherent(array $defauts): void
{
    $est_structure = entier($defauts['est_structure'] ?? 0) === 1;
    $reaffichage = ancien('formulaire') === 'adherent';
    $coche = static function (string $champ) use ($reaffichage, $defauts): string {
        $oui = $reaffichage ? ancien($champ) === '1' : entier($defauts[$champ] ?? 0) === 1;
        return $oui ? 'checked' : '';
    };
    $sexe = ancien('sexe', $defauts['sexe'] ?? '');
    $nationalite = ancien('nationalite_iso3', $defauts['nationalite_iso3'] ?? '');
    ?>
    <div class="row g-3 mb-3">
        <?php if ($est_structure) : ?>
            <div class="col-sm-12">
                <label class="form-label" for="nom">Nom de la structure</label>
                <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e(ancien('nom', $defauts['nom'] ?? '')) ?>" required>
            </div>
        <?php else : ?>
            <div class="col-sm-6">
                <label class="form-label" for="nom">Nom</label>
                <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e(ancien('nom', $defauts['nom'] ?? '')) ?>" required>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="prenom">Prénom</label>
                <input class="form-control" type="text" id="prenom" name="prenom" maxlength="100" value="<?= e(ancien('prenom', $defauts['prenom'] ?? '')) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label" for="sexe">Sexe</label>
                <select class="form-select" id="sexe" name="sexe" required>
                    <?php foreach (libelles_sexes() as $code_sexe => $libelle_sexe) : ?>
                        <option value="<?= e($code_sexe) ?>" <?= $sexe === $code_sexe ? 'selected' : '' ?>><?= e($libelle_sexe) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-4">
                <label class="form-label" for="date_naissance">Date de naissance</label>
                <input class="form-control" type="date" id="date_naissance" name="date_naissance" max="<?= e(date('Y-m-d')) ?>" value="<?= e(ancien('date_naissance', $defauts['date_naissance'] ?? '')) ?>" required>
            </div>
            <div class="col-sm-4">
                <label class="form-label" for="nationalite_iso3">Nationalité <span class="text-body-secondary">(facultatif)</span></label>
                <select class="form-select" id="nationalite_iso3" name="nationalite_iso3">
                    <option value="">Non renseignée</option>
                    <?php foreach (pays_liste() as $code_pays => $nom_pays) : ?>
                        <option value="<?= e($code_pays) ?>" <?= $nationalite === $code_pays ? 'selected' : '' ?>><?= e($nom_pays) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-sm-6">
            <label class="form-label" for="mail">Adresse e-mail <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="email" id="mail" name="mail" maxlength="150" value="<?= e(ancien('mail', $defauts['mail'] ?? '')) ?>">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="tel">Téléphone <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="tel" id="tel" name="tel" maxlength="20" value="<?= e(ancien('tel', $defauts['tel'] ?? '')) ?>">
        </div>
    </div>
    <?php if (!$est_structure) : ?>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="est_benevole" name="est_benevole" value="1" <?= $coche('est_benevole') ?>>
            <label class="form-check-label" for="est_benevole">Est bénévole</label>
        </div>
    <?php endif; ?>
    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="a_consenti" name="a_consenti" value="1" <?= $coche('a_consenti') ?>>
        <label class="form-check-label" for="a_consenti">Accepte de recevoir les communications de l'association (programme, informations)</label>
    </div>
    <?php if (!$est_structure) : ?>
        <?php afficher_champs_responsable($defauts); ?>
    <?php endif; ?>
    <?php
}

/**
 * Affiche les champs du responsable adulte d'une personne mineure.
 *
 * @param array<string, mixed> $defauts valeurs affichées quand rien n'a été saisi
 */
function afficher_champs_responsable(array $defauts): void
{
    ?>
    <h2 class="h5">Responsable adulte</h2>
    <p class="text-body-secondary small">Obligatoire pour une personne de moins de <?= AGE_MAJORITE ?> ans : le nom, et le téléphone ou l'adresse e-mail.</p>
    <div class="row g-3 mb-4">
        <div class="col-sm-12">
            <label class="form-label" for="responsable_nom">Nom et prénom du responsable</label>
            <input class="form-control" type="text" id="responsable_nom" name="responsable_nom" maxlength="150" value="<?= e(ancien('responsable_nom', $defauts['responsable_nom'] ?? '')) ?>">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="responsable_mail">Adresse e-mail du responsable</label>
            <input class="form-control" type="email" id="responsable_mail" name="responsable_mail" maxlength="150" value="<?= e(ancien('responsable_mail', $defauts['responsable_mail'] ?? '')) ?>">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="responsable_tel">Téléphone du responsable</label>
            <input class="form-control" type="tel" id="responsable_tel" name="responsable_tel" maxlength="20" value="<?= e(ancien('responsable_tel', $defauts['responsable_tel'] ?? '')) ?>">
        </div>
    </div>
    <?php
}
