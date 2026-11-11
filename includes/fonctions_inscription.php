<?php

declare(strict_types=1);

/**
 * Formulaire d'inscription en 4 étapes (cahier des charges, section 12.2).
 *
 * Les informations saisies sont gardées en session (le « brouillon ») entre
 * les étapes. Rien n'est écrit en base avant la validation finale, qui
 * enregistre tout en une seule opération. Un brouillon abandonné est oublié
 * à la fin de la session.
 */

/** Type de personne « Association / Structure » (les autres types sont les sexes H, F, A). */
const TYPE_STRUCTURE = 'S';

/**
 * Types de personne proposés au début du formulaire.
 *
 * @return array<string, string>
 */
function inscription_types(): array
{
    return libelles_sexes() + [TYPE_STRUCTURE => 'Association / Structure'];
}

/**
 * Convertit une valeur de session en tableau de textes.
 *
 * @return array<string, string>
 */
function tableau_textes(mixed $valeur): array
{
    $textes = [];
    if (is_array($valeur)) {
        foreach ($valeur as $cle => $element) {
            $textes[(string) $cle] = texte($element);
        }
    }
    return $textes;
}

/**
 * Brouillon vide.
 *
 * @return array{type: string, identite: array<string, string>, famille_choix: string, famille_id: int, famille_recherche: string, contact: array<string, string>, adresse: array<string, string|int|float|null>, etape_faite: int}
 */
function inscription_brouillon_vide(): array
{
    return [
        'type'              => '',
        'identite'          => ['est_benevole' => '0', 'nom' => '', 'prenom' => '', 'date_naissance' => '', 'nationalite_iso3' => '', 'code_postal' => '', 'ville' => ''],
        'famille_choix'     => '',
        'famille_id'        => 0,
        'famille_recherche' => '',
        'contact'           => [],
        'adresse'           => [],
        'etape_faite'       => 0,
    ];
}

/**
 * Brouillon en cours, lu dans la session et remis en forme.
 *
 * @return array{type: string, identite: array<string, string>, famille_choix: string, famille_id: int, famille_recherche: string, contact: array<string, string>, adresse: array<string, string|int|float|null>, etape_faite: int}
 */
function inscription_brouillon(): array
{
    $brouillon = inscription_brouillon_vide();
    $session = $_SESSION['inscription'] ?? null;
    if (!is_array($session)) {
        return $brouillon;
    }
    $type = texte($session['type'] ?? '');
    $brouillon['type'] = array_key_exists($type, inscription_types()) ? $type : '';
    $brouillon['identite'] = tableau_textes($session['identite'] ?? null) + $brouillon['identite'];
    $brouillon['famille_choix'] = in_array($session['famille_choix'] ?? '', ['nouvelle', 'existante'], true) ? texte($session['famille_choix']) : '';
    $brouillon['famille_id'] = entier($session['famille_id'] ?? 0);
    $brouillon['famille_recherche'] = texte($session['famille_recherche'] ?? '');
    $brouillon['contact'] = tableau_textes($session['contact'] ?? null);
    $brouillon['etape_faite'] = max(0, min(3, entier($session['etape_faite'] ?? 0)));
    if (is_array($session['adresse'] ?? null)) {
        foreach ($session['adresse'] as $cle => $valeur) {
            if (is_string($valeur) || is_int($valeur) || is_float($valeur) || $valeur === null) {
                $brouillon['adresse'][(string) $cle] = $valeur;
            }
        }
    }
    return $brouillon;
}

/**
 * Garde le brouillon en session.
 *
 * @param array{type: string, identite: array<string, string>, famille_choix: string, famille_id: int, famille_recherche: string, contact: array<string, string>, adresse: array<string, string|int|float|null>, etape_faite: int} $brouillon
 */
function inscription_garder(array $brouillon): void
{
    $_SESSION['inscription'] = $brouillon;
}

/** Oublie le brouillon en cours. */
function inscription_oublier(): void
{
    unset($_SESSION['inscription']);
}

/**
 * Commence un nouveau brouillon, éventuellement pour une famille existante
 * (formulaire prérempli avec la famille ; son adresse et son téléphone sont
 * proposés à l'étape 3).
 */
function inscription_commencer(int $famille_id = 0): void
{
    $brouillon = inscription_brouillon_vide();
    $famille = $famille_id > 0 ? famille_par_id($famille_id) : null;
    if ($famille !== null) {
        $brouillon['famille_choix'] = 'existante';
        $brouillon['famille_id'] = $famille_id;
        $brouillon['identite']['nom'] = texte($famille['nom']);
        $adresse = derniere_adresse_famille($famille_id);
        if ($adresse !== null) {
            $brouillon['identite']['code_postal'] = texte($adresse['code_postal']);
            $brouillon['identite']['ville'] = texte($adresse['ville']);
        }
    }
    inscription_garder($brouillon);
}

/** Vrai si le brouillon concerne une structure. */
function inscription_est_structure(string $type): bool
{
    return $type === TYPE_STRUCTURE;
}

/**
 * Titres des étapes à parcourir (une structure n'a pas d'étape « Famille »).
 *
 * @return array<int, string> numéro de l'étape => titre
 */
function inscription_etapes(string $type): array
{
    $etapes = [1 => 'Identité', 2 => 'Famille', 3 => 'Adresse et contact', 4 => 'Validation'];
    if (inscription_est_structure($type)) {
        unset($etapes[2]);
    }
    return $etapes;
}

/**
 * Fiche complète de l'adhérent, construite à partir du brouillon.
 *
 * @param array{type: string, identite: array<string, string>, contact: array<string, string>, ...} $brouillon
 * @return array<string, string>
 */
function inscription_fiche(array $brouillon): array
{
    $est_structure = inscription_est_structure($brouillon['type']);
    $fiche = adherent_saisie_vide($est_structure);
    $fiche['nom'] = $brouillon['identite']['nom'] ?? '';
    $fiche['mail'] = $brouillon['contact']['mail'] ?? '';
    $fiche['tel'] = $brouillon['contact']['tel'] ?? '';
    $fiche['a_consenti'] = ($brouillon['contact']['a_consenti'] ?? '') === '1' ? '1' : '0';
    if (!$est_structure) {
        $fiche['prenom'] = $brouillon['identite']['prenom'] ?? '';
        $fiche['sexe'] = $brouillon['type'];
        $fiche['date_naissance'] = $brouillon['identite']['date_naissance'] ?? '';
        $fiche['nationalite_iso3'] = $brouillon['identite']['nationalite_iso3'] ?? '';
        $fiche['est_benevole'] = ($brouillon['identite']['est_benevole'] ?? '') === '1' ? '1' : '0';
        // Le responsable n'est gardé que pour une personne mineure.
        if (est_mineur($fiche['date_naissance'])) {
            $fiche['responsable_nom'] = $brouillon['contact']['responsable_nom'] ?? '';
            $fiche['responsable_mail'] = $brouillon['contact']['responsable_mail'] ?? '';
            $fiche['responsable_tel'] = $brouillon['contact']['responsable_tel'] ?? '';
        }
    }
    return $fiche;
}

/**
 * Adresse et scolarité saisies à l'étape 3, au format attendu par erreurs_adresse().
 *
 * @param array<string, string> $contact
 * @return array<string, string>
 */
function inscription_adresse_saisie(array $contact, bool $scolarite): array
{
    return [
        'adresse'       => $contact['adresse'] ?? '',
        'complement'    => $contact['complement'] ?? '',
        'code_postal'   => $contact['code_postal'] ?? '',
        'ville'         => $contact['ville'] ?? '',
        'etablissement' => $scolarite ? ($contact['etablissement'] ?? '') : '',
        'niveau'        => $scolarite ? ($contact['niveau'] ?? '') : '',
    ];
}

/**
 * Vérifie l'étape 1 : type, identité, code postal et commune.
 *
 * @param array{type: string, identite: array<string, string>, contact: array<string, string>, ...} $brouillon
 * @return list<string>
 */
function inscription_erreurs_etape1(array $brouillon): array
{
    if ($brouillon['type'] === '') {
        return ['Choisissez d\'abord le type de personne.'];
    }
    $erreurs = erreurs_identite(inscription_fiche($brouillon));
    if (preg_match('/^\d{5}$/', $brouillon['identite']['code_postal'] ?? '') !== 1) {
        $erreurs[] = 'Le code postal est obligatoire (5 chiffres).';
    }
    $ville = $brouillon['identite']['ville'] ?? '';
    if ($ville === '' || mb_strlen($ville) > 100) {
        $erreurs[] = 'La commune est obligatoire.';
    }
    return $erreurs;
}

/**
 * Vérifie l'étape 2 : nouvelle famille, ou famille existante.
 *
 * @param array{type: string, famille_choix: string, famille_id: int, ...} $brouillon
 * @return list<string>
 */
function inscription_erreurs_etape2(array $brouillon): array
{
    if (inscription_est_structure($brouillon['type'])) {
        return [];
    }
    if ($brouillon['famille_choix'] === 'nouvelle') {
        return [];
    }
    if ($brouillon['famille_choix'] === 'existante' && famille_par_id($brouillon['famille_id']) !== null) {
        return [];
    }
    return ['Choisissez une famille, ou la création d\'une nouvelle famille.'];
}

/**
 * Vérifie l'étape 3 : adresse, contact, responsable d'un mineur, notes.
 *
 * @param array{type: string, identite: array<string, string>, contact: array<string, string>, ...} $brouillon
 * @return list<string>
 */
function inscription_erreurs_etape3(array $brouillon): array
{
    $fiche = inscription_fiche($brouillon);
    $erreurs = [
        ...erreurs_adresse(inscription_adresse_saisie($brouillon['contact'], est_mineur($fiche['date_naissance']))),
        ...erreurs_contact($fiche),
    ];
    if (mb_strlen($brouillon['contact']['notes'] ?? '') > 5000) {
        $erreurs[] = 'Les notes sont trop longues (5 000 caractères au maximum).';
    }
    return $erreurs;
}

/**
 * Valeurs proposées à l'étape 3 quand elle n'a pas encore été remplie :
 * le code postal de l'étape 1 et, pour une famille existante, son adresse,
 * son téléphone et les notes de son dossier de la saison.
 *
 * @param array{identite: array<string, string>, famille_choix: string, famille_id: int, ...} $brouillon
 * @return array<string, string>
 */
function inscription_contact_propose(array $brouillon, int $saison_id): array
{
    $propose = [
        'code_postal' => $brouillon['identite']['code_postal'] ?? '',
        'ville'       => $brouillon['identite']['ville'] ?? '',
    ];
    if ($brouillon['famille_choix'] !== 'existante' || $brouillon['famille_id'] <= 0) {
        return $propose;
    }
    $adresse = derniere_adresse_famille($brouillon['famille_id']);
    // L'adresse de la famille n'est proposée que si elle est dans le code postal saisi à l'étape 1.
    if ($adresse !== null && texte($adresse['code_postal']) === $propose['code_postal']) {
        $propose['adresse'] = texte($adresse['adresse']);
        $propose['complement'] = texte($adresse['complement']);
        $propose['ville'] = texte($adresse['ville']);
    }
    $propose['tel'] = telephone_famille($brouillon['famille_id']);
    $dossier_id = dossier_id_de_famille($brouillon['famille_id'], $saison_id);
    if ($dossier_id > 0) {
        $propose['notes'] = texte(db_valeur('SELECT notes FROM dossiers WHERE id = ?', [$dossier_id]));
    }
    return $propose;
}

/**
 * Validation finale : enregistre en une seule opération la famille (si elle
 * est nouvelle), l'adhérent, le dossier de la saison, l'adresse et les notes.
 *
 * @param array{type: string, identite: array<string, string>, famille_choix: string, famille_id: int, famille_recherche: string, contact: array<string, string>, adresse: array<string, string|int|float|null>, etape_faite: int} $brouillon brouillon déjà vérifié
 * @return int identifiant du nouvel adhérent
 */
function inscription_enregistrer(array $brouillon, int $saison_id): int
{
    $fiche = inscription_fiche($brouillon);
    $est_structure = inscription_est_structure($brouillon['type']);
    $notes = $brouillon['contact']['notes'] ?? '';

    db()->beginTransaction();
    try {
        $famille_id = null;
        if (!$est_structure) {
            $famille_id = $brouillon['famille_choix'] === 'existante' ? $brouillon['famille_id'] : creer_famille($fiche['nom']);
        }
        $adherent_id = creer_adherent($famille_id, $fiche);
        $dossier_id = dossier_obtenir_pour_adherent(
            ['id' => $adherent_id, 'est_structure' => $est_structure ? 1 : 0, 'famille_id' => $famille_id],
            $saison_id
        );
        adresse_ecrire($dossier_id, $adherent_id, $brouillon['adresse']);

        // Les notes appartiennent au dossier (famille et saison), pas à la personne.
        $notes_actuelles = texte(db_valeur('SELECT notes FROM dossiers WHERE id = ?', [$dossier_id]));
        if ($notes !== $notes_actuelles) {
            db_executer(
                'UPDATE dossiers SET notes = ?, modification_par = ? WHERE id = ?',
                [nul_si_vide($notes), entier($_SESSION['utilisateur_id'] ?? 0), $dossier_id]
            );
            journaliser('modification', 'dossiers', $dossier_id, 'Modification des notes du dossier', ['notes' => $notes_actuelles], ['notes' => $notes]);
        }
        db()->commit();
    } catch (Throwable $erreur) {
        db()->rollBack();
        throw $erreur;
    }
    return $adherent_id;
}
