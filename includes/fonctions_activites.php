<?php

declare(strict_types=1);

/**
 * Activités et inscriptions aux activités.
 *
 *  - Secteurs, types d'activité et récurrences : trois listes de choix communes
 *    à toutes les saisons (fonctions choix_*). Un choix utilisé par une activité ne se
 *    supprime pas : il se rend inactif.
 *  - Activités : rattachées à une saison, avec un type, un secteur, un tarif
 *    (ou gratuites), des places limitées ou non, une liste d'attente ou non.
 *  - Inscriptions : un adhérent inscrit pour la saison prend une ou plusieurs
 *    places ; l'inscription est « inscrit » ou « en attente », et s'annule
 *    sans jamais être supprimée.
 */

// ---------------------------------------------------------------------------
// Listes de choix : secteurs, types d'activité et récurrences
// ---------------------------------------------------------------------------

/**
 * Description d'une liste de choix. Le nom de la table vient toujours du code
 * (jamais d'une saisie) : il peut être écrit dans les requêtes.
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @return array{table: string, colonne: string, a_description: bool, un: string, le: string, titre: string, page_liste: string, page_fiche: string, page_ajout: string}
 */
function choix_definition(string $liste): array
{
    return match ($liste) {
        'secteurs' => [
            'table'      => 'secteurs',
            'colonne'    => 'secteur_id',
            'a_description' => true,
            'un'         => 'un secteur',
            'le'         => 'le secteur',
            'titre'      => 'Secteurs d\'activité',
            'page_liste' => 'secteurs.php',
            'page_fiche' => 'secteur.php',
            'page_ajout' => 'ajout_secteur.php',
        ],
        'types_activite' => [
            'table'      => 'types_activite',
            'colonne'    => 'type_id',
            'a_description' => false,
            'un'         => 'un type d\'activité',
            'le'         => 'le type d\'activité',
            'titre'      => 'Types d\'activité',
            'page_liste' => 'options_activites.php',
            'page_fiche' => 'type_activite.php',
            'page_ajout' => 'ajout_type_activite.php',
        ],
        'recurrences' => [
            'table'      => 'recurrences',
            'colonne'    => 'recurrence_id',
            'a_description' => false,
            'un'         => 'une récurrence',
            'le'         => 'la récurrence',
            'titre'      => 'Récurrences',
            'page_liste' => 'options_activites.php',
            'page_fiche' => 'recurrence.php',
            'page_ajout' => 'ajout_recurrence.php',
        ],
    };
}

/**
 * Tous les choix d'une liste, avec le nombre d'activités qui les utilisent (toutes saisons).
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @return list<array<string, mixed>>
 */
function choix_liste(string $liste): array
{
    $definition = choix_definition($liste);
    return db_lignes(
        'SELECT c.id, c.nom, c.est_actif, ' . ($definition['a_description'] ? 'c.description' : 'NULL AS description') . ',
                (SELECT COUNT(*) FROM activites ac WHERE ac.' . $definition['colonne'] . ' = c.id) AS nb_activites
           FROM ' . $definition['table'] . ' c
          ORDER BY c.est_actif DESC, c.nom'
    );
}

/**
 * Choix proposés dans le formulaire d'une activité : les choix actifs, plus
 * celui que l'activité utilise déjà (même s'il a été rendu inactif depuis).
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @return array<int, string> identifiant => nom
 */
function choix_proposes(string $liste, int $courant_id = 0): array
{
    $definition = choix_definition($liste);
    $proposes = [];
    $lignes = db_lignes(
        'SELECT id, nom FROM ' . $definition['table'] . ' WHERE est_actif = 1 OR id = ? ORDER BY nom',
        [$courant_id]
    );
    foreach ($lignes as $ligne) {
        $proposes[entier($ligne['id'])] = texte($ligne['nom']);
    }
    return $proposes;
}

/**
 * Un choix par son identifiant, avec le nombre d'activités qui l'utilisent, ou null.
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @return array<string, mixed>|null
 */
function choix_par_id(string $liste, int $id): ?array
{
    $definition = choix_definition($liste);
    return db_ligne(
        'SELECT c.id, c.nom, c.est_actif, ' . ($definition['a_description'] ? 'c.description' : 'NULL AS description') . ',
                (SELECT COUNT(*) FROM activites ac WHERE ac.' . $definition['colonne'] . ' = c.id) AS nb_activites
           FROM ' . $definition['table'] . ' c
          WHERE c.id = ?',
        [$id]
    );
}

/**
 * Vérifie le nom d'un choix avant création ou modification.
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @param int $sauf_id identifiant du choix modifié (0 pour une création)
 * @return string|null message d'erreur, ou null si tout va bien
 */
function choix_erreur_nom(string $liste, string $nom, int $sauf_id = 0): ?string
{
    $definition = choix_definition($liste);
    if ($nom === '' || mb_strlen($nom) > 100) {
        return 'Le nom est obligatoire (100 caractères au maximum).';
    }
    if (entier(db_valeur('SELECT COUNT(*) FROM ' . $definition['table'] . ' WHERE nom = ? AND id <> ?', [$nom, $sauf_id])) > 0) {
        return 'Ce nom existe déjà dans la liste.';
    }
    return null;
}

/** Vérifie la description d'un choix : message d'erreur, ou null si tout va bien. */
function choix_erreur_description(string $description): ?string
{
    return mb_strlen($description) > 2000 ? 'La description est trop longue (2 000 caractères au maximum).' : null;
}

/**
 * Ajoute un choix (nom et description déjà vérifiés) et renvoie son identifiant.
 * La description n'est enregistrée que pour les listes qui en ont une (secteurs).
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 */
function choix_creer(string $liste, string $nom, string $description = ''): int
{
    $definition = choix_definition($liste);
    $nouveau = ['nom' => $nom];
    if ($definition['a_description']) {
        $nouveau['description'] = nul_si_vide($description);
        db_executer('INSERT INTO ' . $definition['table'] . ' (nom, description) VALUES (?, ?)', [$nom, $nouveau['description']]);
    } else {
        db_executer('INSERT INTO ' . $definition['table'] . ' (nom) VALUES (?)', [$nom]);
    }
    $choix_id = entier(db()->lastInsertId());
    journaliser('creation', $definition['table'], $choix_id, 'Ajout : ' . $definition['le'] . ' « ' . $nom . ' »', null, $nouveau);
    return $choix_id;
}

/**
 * Modifie le nom d'un choix, sa description (secteurs), ou le rend actif ou inactif (valeurs déjà vérifiées).
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @param array<string, mixed> $actuel choix en base avant la modification
 * @return bool false s'il n'y avait rien à modifier
 */
function choix_modifier(string $liste, array $actuel, string $nom, bool $est_actif, string $description = ''): bool
{
    $definition = choix_definition($liste);
    $ancien = ['nom' => texte($actuel['nom'] ?? ''), 'est_actif' => entier($actuel['est_actif'] ?? 0)];
    $nouveau = ['nom' => $nom, 'est_actif' => $est_actif ? 1 : 0];
    if ($definition['a_description']) {
        $ancien['description'] = texte($actuel['description'] ?? '');
        $nouveau['description'] = $description;
    }
    if ($ancien === $nouveau) {
        return false;
    }
    $choix_id = entier($actuel['id'] ?? 0);
    db_executer('UPDATE ' . $definition['table'] . ' SET nom = ?, est_actif = ? WHERE id = ?', [$nom, $nouveau['est_actif'], $choix_id]);
    if ($definition['a_description']) {
        db_executer('UPDATE ' . $definition['table'] . ' SET description = ? WHERE id = ?', [nul_si_vide($description), $choix_id]);
    }
    journaliser('modification', $definition['table'], $choix_id, 'Modification : ' . $definition['le'] . ' « ' . $nom . ' »', $ancien, $nouveau);
    return true;
}

/**
 * Supprime un choix qu'aucune activité n'utilise.
 *
 * @param 'secteurs'|'types_activite'|'recurrences' $liste
 * @param array<string, mixed> $choix
 * @return bool false si une activité l'utilise (il faut alors le rendre inactif)
 */
function choix_supprimer(string $liste, array $choix): bool
{
    $definition = choix_definition($liste);
    $choix_id = entier($choix['id'] ?? 0);
    if (entier(db_valeur('SELECT COUNT(*) FROM activites WHERE ' . $definition['colonne'] . ' = ?', [$choix_id])) > 0) {
        return false;
    }
    db_executer('DELETE FROM ' . $definition['table'] . ' WHERE id = ?', [$choix_id]);
    journaliser('suppression', $definition['table'], $choix_id, 'Suppression : ' . $definition['le'] . ' « ' . texte($choix['nom'] ?? '') . ' »', [
        'nom'       => $choix['nom'] ?? null,
        'est_actif' => $choix['est_actif'] ?? null,
    ]);
    return true;
}

// ---------------------------------------------------------------------------
// Activités
// ---------------------------------------------------------------------------

/** Début commun des requêtes qui lisent une activité avec ses libellés et ses places. */
const ACTIVITE_SELECT = 'SELECT ac.*, t.nom AS type_nom, se.nom AS secteur_nom, r.nom AS recurrence_nom,
            ta.nom AS tarif_nom, ta.montant_individuel AS tarif_montant, ta.montant_familial AS tarif_montant_familial,
            s.nom AS saison_nom, s.date_debut AS saison_debut, s.date_fin AS saison_fin,
            (SELECT COALESCE(SUM(i.nb_places), 0) FROM inscriptions i
              WHERE i.activite_id = ac.id AND i.est_annulee = 0 AND i.statut = \'inscrit\') AS places_prises,
            (SELECT COALESCE(SUM(i.nb_places), 0) FROM inscriptions i
              WHERE i.activite_id = ac.id AND i.est_annulee = 0 AND i.statut = \'attente\') AS places_attente,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.activite_id = ac.id) AS nb_inscriptions
       FROM activites ac
       JOIN types_activite t ON t.id = ac.type_id
       JOIN secteurs se ON se.id = ac.secteur_id
       JOIN recurrences r ON r.id = ac.recurrence_id
       LEFT JOIN tarifs ta ON ta.id = ac.tarif_id
       JOIN saisons s ON s.id = ac.saison_id';

/**
 * Une activité par son identifiant, avec ses libellés et ses places, ou null.
 *
 * @return array<string, mixed>|null
 */
function activite_par_id(int $id): ?array
{
    return db_ligne(ACTIVITE_SELECT . ' WHERE ac.id = ?', [$id]);
}

/**
 * Activités d'une saison, par date de début ; filtres facultatifs sur le
 * secteur, le type et le nom (chaque mot cherché doit se trouver dans le nom,
 * à n'importe quel endroit).
 *
 * @return list<array<string, mixed>>
 */
function activites_liste(int $saison_id, int $secteur_id = 0, int $type_id = 0, string $recherche = ''): array
{
    $conditions = ['ac.saison_id = ?'];
    $parametres = [$saison_id];
    $mots = preg_split('/\s+/', $recherche, -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($mots === false ? [] : $mots, 0, 5) as $mot) {
        // % et _ sont des caractères spéciaux de LIKE : on les neutralise.
        $conditions[] = 'ac.nom LIKE ?';
        $parametres[] = '%' . addcslashes($mot, '%_\\') . '%';
    }
    if ($secteur_id > 0) {
        $conditions[] = 'ac.secteur_id = ?';
        $parametres[] = $secteur_id;
    }
    if ($type_id > 0) {
        $conditions[] = 'ac.type_id = ?';
        $parametres[] = $type_id;
    }
    return db_lignes(
        ACTIVITE_SELECT . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY ac.date_debut, ac.nom, ac.id',
        $parametres
    );
}

/**
 * Places encore libres d'une activité ; null si les places ne sont pas limitées.
 *
 * @param array<string, mixed> $activite ligne lue par activite_par_id() ou activites_liste()
 */
function places_restantes(array $activite): ?int
{
    if (($activite['nb_places'] ?? null) === null) {
        return null;
    }
    return max(0, entier($activite['nb_places']) - entier($activite['places_prises'] ?? 0));
}

/**
 * Dates d'une activité : « le 12/03/2027 » ou « du 12/03/2027 au 14/03/2027 ».
 *
 * @param array<string, mixed> $activite
 */
function activite_dates(array $activite): string
{
    $debut = date_fr($activite['date_debut'] ?? '');
    $fin = date_fr($activite['date_fin'] ?? '');
    return $fin === '' || $fin === $debut ? 'le ' . $debut : 'du ' . $debut . ' au ' . $fin;
}

/**
 * Prix d'une activité : les montants de son tarif (individuel, et familial s'il y en a un), ou « Gratuit ».
 *
 * @param array<string, mixed> $activite
 */
function activite_prix(array $activite): string
{
    return ($activite['tarif_id'] ?? null) === null
        ? 'Gratuit'
        : tarif_montants($activite['tarif_montant'] ?? 0, $activite['tarif_montant_familial'] ?? null);
}

/** Écart maximal, en jours, entre deux dates de début pour dire que deux activités ont des dates similaires. */
const ACTIVITES_SIMILAIRES_JOURS = 7;

/**
 * Activités de la saison qui ressemblent à celle que l'on s'apprête à créer :
 * un nom presque identique (sans tenir compte des accents ni des majuscules,
 * avec le même écart toléré que pour les doublons d'adhérents) et des dates
 * similaires (périodes qui se chevauchent, ou débuts à 7 jours d'écart au plus).
 *
 * @param string $date_fin '' si l'activité n'a pas de date de fin
 * @return list<array<string, mixed>>
 */
function activites_similaires(int $saison_id, string $nom, string $date_debut, string $date_fin = ''): array
{
    $nom_simple = texte_simplifie($nom);
    if ($nom_simple === '' || !date_valide($date_debut)) {
        return [];
    }
    $fin = date_valide($date_fin) ? $date_fin : $date_debut;
    $debut_jour = new DateTimeImmutable($date_debut);

    $similaires = [];
    foreach (activites_liste($saison_id) as $activite) {
        $autre_simple = texte_simplifie(texte($activite['nom']));
        $noms_proches = ecart_noms($nom_simple, $autre_simple) <= ecart_tolere($nom_simple)
            || ecart_noms($autre_simple, $nom_simple) <= ecart_tolere($autre_simple);
        if (!$noms_proches) {
            continue;
        }
        $autre_debut = texte($activite['date_debut']);
        $autre_fin = texte($activite['date_fin']) !== '' ? texte($activite['date_fin']) : $autre_debut;
        $se_chevauchent = $autre_debut <= $fin && $autre_fin >= $date_debut;
        $debuts_proches = $debut_jour->diff(new DateTimeImmutable($autre_debut))->days <= ACTIVITES_SIMILAIRES_JOURS;
        if ($se_chevauchent || $debuts_proches) {
            $similaires[] = $activite;
        }
    }
    return $similaires;
}

/**
 * Champs d'une activité lus dans le formulaire envoyé.
 *
 * @return array<string, string>
 */
function activite_saisie(): array
{
    return [
        'nom'                => post('nom'),
        'type_id'            => post('type_id'),
        'secteur_id'         => post('secteur_id'),
        'referent'           => post('referent'),
        'recurrence_id'      => post('recurrence_id'),
        'tarif_id'           => post('tarif_id'),
        'date_debut'         => post('date_debut'),
        'date_fin'           => post('date_fin'),
        'nb_places'          => post('nb_places'),
        'a_liste_attente'    => post('a_liste_attente') === '1' ? '1' : '0',
        'nb_accompagnateurs' => post('nb_accompagnateurs'),
        'description'        => post('description'),
        'est_sans_adhesion'  => post('est_sans_adhesion') === '1' ? '1' : '0',
    ];
}

/**
 * Vérifie une activité avant création ou modification.
 *
 * @param array<string, string>     $saisie
 * @param array<string, mixed>      $saison   saison de l'activité
 * @param array<string, mixed>|null $actuelle activité avant modification (null pour une création)
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_activite(array $saisie, array $saison, ?array $actuelle = null): array
{
    $erreurs = [];
    $saison_id = entier($saison['id'] ?? 0);

    if ($saisie['nom'] === '' || mb_strlen($saisie['nom']) > 150) {
        $erreurs[] = 'Le nom de l\'activité est obligatoire (150 caractères au maximum).';
    }
    if (!array_key_exists(entier($saisie['type_id']), choix_proposes('types_activite', entier($actuelle['type_id'] ?? 0)))) {
        $erreurs[] = 'Choisissez un type d\'activité dans la liste.';
    }
    if (!array_key_exists(entier($saisie['secteur_id']), choix_proposes('secteurs', entier($actuelle['secteur_id'] ?? 0)))) {
        $erreurs[] = 'Choisissez un secteur dans la liste.';
    }
    if ($saisie['referent'] === '' || mb_strlen($saisie['referent']) > 150) {
        $erreurs[] = 'Le référent est obligatoire (150 caractères au maximum).';
    }
    if (!array_key_exists(entier($saisie['recurrence_id']), choix_proposes('recurrences', entier($actuelle['recurrence_id'] ?? 0)))) {
        $erreurs[] = 'Choisissez une récurrence dans la liste.';
    }
    if ($saisie['tarif_id'] !== '') {
        $tarif = tarif_par_id(entier($saisie['tarif_id']));
        if ($tarif === null || entier($tarif['saison_id']) !== $saison_id) {
            $erreurs[] = 'Le tarif choisi n\'existe pas pour cette saison.';
        } elseif (texte($tarif['type']) === 'adhesion') {
            $erreurs[] = 'Un tarif « adhésion » est réservé aux cotisations : une activité qui ne demande que l\'adhésion est gratuite.';
        }
    }

    if (!date_valide($saisie['date_debut'])) {
        $erreurs[] = 'La date de début est obligatoire.';
    } else {
        if ($saisie['date_debut'] < texte($saison['date_debut'] ?? '') || $saisie['date_debut'] > texte($saison['date_fin'] ?? '')) {
            $erreurs[] = 'La date de début doit être dans la saison ' . texte($saison['nom'] ?? '')
                . ' (du ' . date_fr($saison['date_debut'] ?? '') . ' au ' . date_fr($saison['date_fin'] ?? '') . ').';
        }
        if ($saisie['date_fin'] !== '' && (!date_valide($saisie['date_fin']) || $saisie['date_fin'] < $saisie['date_debut'])) {
            $erreurs[] = 'La date de fin ne peut pas être avant la date de début.';
        }
    }

    if ($saisie['nb_places'] !== '') {
        $places = entier($saisie['nb_places']);
        if (preg_match('/^\d{1,4}$/', $saisie['nb_places']) !== 1 || $places < 1) {
            $erreurs[] = 'Le nombre de places est un nombre entre 1 et 9999 (laissez vide si les places ne sont pas limitées).';
        } elseif ($actuelle !== null && $places < entier($actuelle['places_prises'] ?? 0)) {
            $erreurs[] = 'Le nombre de places ne peut pas être inférieur aux places déjà prises (' . entier($actuelle['places_prises'] ?? 0) . ').';
        }
    }
    if ($saisie['nb_accompagnateurs'] !== '' && preg_match('/^\d{1,3}$/', $saisie['nb_accompagnateurs']) !== 1) {
        $erreurs[] = 'Le nombre d\'accompagnateurs est un nombre entre 0 et 999.';
    }
    if (mb_strlen($saisie['description']) > 5000) {
        $erreurs[] = 'La description est trop longue (5 000 caractères au maximum).';
    }
    return $erreurs;
}

/**
 * Valeurs d'une activité prêtes pour la base, dans l'ordre des colonnes.
 *
 * @param array<string, string> $saisie activité déjà vérifiée
 * @return array<string, string|int|null>
 */
function activite_valeurs(array $saisie): array
{
    return [
        'nom'                => $saisie['nom'],
        'type_id'            => entier($saisie['type_id']),
        'secteur_id'         => entier($saisie['secteur_id']),
        'referent'           => $saisie['referent'],
        'recurrence_id'      => entier($saisie['recurrence_id']),
        'tarif_id'           => $saisie['tarif_id'] === '' ? null : entier($saisie['tarif_id']),
        'date_debut'         => $saisie['date_debut'],
        'date_fin'           => nul_si_vide($saisie['date_fin']),
        'nb_places'          => $saisie['nb_places'] === '' ? null : entier($saisie['nb_places']),
        'a_liste_attente'    => entier($saisie['a_liste_attente']),
        'nb_accompagnateurs' => $saisie['nb_accompagnateurs'] === '' ? null : entier($saisie['nb_accompagnateurs']),
        'description'        => nul_si_vide($saisie['description']),
        'est_sans_adhesion'  => entier($saisie['est_sans_adhesion']),
    ];
}

/**
 * Crée une activité (déjà vérifiée) dans une saison et renvoie son identifiant.
 *
 * @param array<string, string> $saisie
 */
function creer_activite(int $saison_id, array $saisie): int
{
    $valeurs = activite_valeurs($saisie);
    db_executer(
        'INSERT INTO activites (nom, type_id, secteur_id, referent, recurrence_id, tarif_id, date_debut, date_fin, nb_places,
                                a_liste_attente, nb_accompagnateurs, description, est_sans_adhesion, saison_id, creation_par)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [...array_values($valeurs), $saison_id, entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    $activite_id = entier(db()->lastInsertId());
    journaliser('creation', 'activites', $activite_id, 'Création de l\'activité « ' . $saisie['nom'] . ' »', null, $valeurs + ['saison_id' => $saison_id]);
    return $activite_id;
}

/**
 * Modifie une activité (déjà vérifiée). Elle reste dans sa saison.
 *
 * @param array<string, mixed>  $actuelle activité en base avant la modification
 * @param array<string, string> $saisie
 * @return bool false s'il n'y avait rien à modifier
 */
function modifier_activite(array $actuelle, array $saisie): bool
{
    $valeurs = activite_valeurs($saisie);
    $ancien = [];
    foreach (array_keys($valeurs) as $champ) {
        $valeur = $actuelle[$champ] ?? null;
        $ancien[$champ] = is_string($valeurs[$champ]) || $valeur === null ? ($valeur === null ? null : texte($valeur)) : entier($valeur);
    }
    if ($ancien === $valeurs) {
        return false;
    }
    $activite_id = entier($actuelle['id'] ?? 0);
    db_executer(
        'UPDATE activites
            SET nom = ?, type_id = ?, secteur_id = ?, referent = ?, recurrence_id = ?, tarif_id = ?, date_debut = ?, date_fin = ?,
                nb_places = ?, a_liste_attente = ?, nb_accompagnateurs = ?, description = ?, est_sans_adhesion = ?, modification_par = ?
          WHERE id = ?',
        [...array_values($valeurs), entier($_SESSION['utilisateur_id'] ?? 0), $activite_id]
    );
    journaliser('modification', 'activites', $activite_id, 'Modification de l\'activité « ' . $saisie['nom'] . ' »', $ancien, $valeurs);
    return true;
}

/**
 * Supprime une activité vide : sans aucune inscription, même annulée.
 *
 * @param array<string, mixed> $activite
 * @return bool false si elle a des inscriptions
 */
function supprimer_activite(array $activite): bool
{
    $activite_id = entier($activite['id'] ?? 0);
    if (entier(db_valeur('SELECT COUNT(*) FROM inscriptions WHERE activite_id = ?', [$activite_id])) > 0) {
        return false;
    }
    $ancien = [];
    foreach (['saison_id', 'nom', 'type_id', 'secteur_id', 'referent', 'recurrence_id', 'tarif_id', 'date_debut', 'date_fin', 'nb_places', 'a_liste_attente', 'nb_accompagnateurs', 'description', 'est_sans_adhesion'] as $champ) {
        $ancien[$champ] = $activite[$champ] ?? null;
    }
    db_executer('DELETE FROM activites WHERE id = ?', [$activite_id]);
    journaliser('suppression', 'activites', $activite_id, 'Suppression de l\'activité « ' . texte($activite['nom'] ?? '') . ' »', $ancien);
    return true;
}

/**
 * Affiche les champs du formulaire d'une activité (création et modification).
 * Le formulaire doit contenir <input type="hidden" name="formulaire" value="activite">
 * pour que les cases à cocher soient réaffichées correctement après une erreur.
 *
 * @param array<string, mixed> $defauts   valeurs affichées quand rien n'a été saisi
 * @param array<string, mixed> $saison    saison de l'activité
 */
function afficher_champs_activite(array $defauts, array $saison): void
{
    $reaffichage = ancien('formulaire') === 'activite';
    $coche = static function (string $champ) use ($reaffichage, $defauts): string {
        $oui = $reaffichage ? ancien($champ) === '1' : entier($defauts[$champ] ?? 0) === 1;
        return $oui ? 'checked' : '';
    };
    $type_choisi = entier(ancien('type_id', $defauts['type_id'] ?? ''));
    $secteur_choisi = entier(ancien('secteur_id', $defauts['secteur_id'] ?? ''));
    $tarif_choisi = entier(ancien('tarif_id', $defauts['tarif_id'] ?? ''));
    $recurrence_choisie = entier(ancien('recurrence_id', $defauts['recurrence_id'] ?? ''));
    ?>
    <div class="row g-3 mb-3">
        <div class="col-sm-12">
            <label class="form-label" for="nom">Nom</label>
            <input class="form-control" type="text" id="nom" name="nom" maxlength="150" value="<?= e(ancien('nom', $defauts['nom'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="secteur_id">Secteur</label>
            <select class="form-select" id="secteur_id" name="secteur_id" required>
                <option value="">Choisir…</option>
                <?php foreach (choix_proposes('secteurs', entier($defauts['secteur_id'] ?? 0)) as $choix_id => $choix_nom) : ?>
                    <option value="<?= $choix_id ?>" <?= $secteur_choisi === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="type_id">Type</label>
            <select class="form-select" id="type_id" name="type_id" required>
                <option value="">Choisir…</option>
                <?php foreach (choix_proposes('types_activite', entier($defauts['type_id'] ?? 0)) as $choix_id => $choix_nom) : ?>
                    <option value="<?= $choix_id ?>" <?= $type_choisi === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="referent">Référent</label>
            <input class="form-control" type="text" id="referent" name="referent" maxlength="150" value="<?= e(ancien('referent', $defauts['referent'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="recurrence_id">Récurrence</label>
            <select class="form-select" id="recurrence_id" name="recurrence_id" required>
                <option value="">Choisir…</option>
                <?php foreach (choix_proposes('recurrences', entier($defauts['recurrence_id'] ?? 0)) as $choix_id => $choix_nom) : ?>
                    <option value="<?= $choix_id ?>" <?= $recurrence_choisie === $choix_id ? 'selected' : '' ?>><?= e($choix_nom) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="date_debut">Date de début</label>
            <input class="form-control" type="date" id="date_debut" name="date_debut" min="<?= e($saison['date_debut'] ?? '') ?>" max="<?= e($saison['date_fin'] ?? '') ?>"
                   value="<?= e(ancien('date_debut', $defauts['date_debut'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="date_fin">Date de fin <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="date" id="date_fin" name="date_fin" value="<?= e(ancien('date_fin', $defauts['date_fin'] ?? '')) ?>">
        </div>
        <div class="col-sm-12">
            <label class="form-label" for="tarif_id">Tarif</label>
            <select class="form-select" id="tarif_id" name="tarif_id" aria-describedby="aide_tarif">
                <option value="">Gratuit</option>
                <?php foreach (tarifs_liste(entier($saison['id'] ?? 0), true) as $tarif) : ?>
                    <option value="<?= entier($tarif['id']) ?>" <?= $tarif_choisi === entier($tarif['id']) ? 'selected' : '' ?>><?= e(texte($tarif['nom']) . ' — ' . tarif_montants($tarif['montant_individuel'], $tarif['montant_familial'])) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text" id="aide_tarif">Tarifs « forfait » et « autre » de la saison <?= e($saison['nom'] ?? '') ?>. Une activité qui ne demande que l'adhésion est gratuite. Le montant est copié sur chaque inscription au moment où elle est faite.</div>
        </div>
        <div class="col-sm-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="est_sans_adhesion" name="est_sans_adhesion" value="1" <?= $coche('est_sans_adhesion') ?>>
                <label class="form-check-label" for="est_sans_adhesion">Activité sans adhésion</label>
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="nb_places">Nombre de places <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="number" id="nb_places" name="nb_places" min="1" max="9999" step="1" value="<?= e(ancien('nb_places', $defauts['nb_places'] ?? '')) ?>" aria-describedby="aide_places">
            <div class="form-text" id="aide_places">Vide : les places ne sont pas limitées.</div>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="nb_accompagnateurs">Accompagnateurs <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="number" id="nb_accompagnateurs" name="nb_accompagnateurs" min="0" max="999" step="1" value="<?= e(ancien('nb_accompagnateurs', $defauts['nb_accompagnateurs'] ?? '')) ?>" aria-describedby="aide_accompagnateurs">
            <div class="form-text" id="aide_accompagnateurs">Simple information : ils ne prennent pas de places.</div>
        </div>
    </div>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="a_liste_attente" name="a_liste_attente" value="1" <?= $coche('a_liste_attente') ?>>
        <label class="form-check-label" for="a_liste_attente">Liste d'attente</label>
    </div>
    <div class="mb-3">
        <label class="form-label" for="description">Description <span class="text-body-secondary">(facultatif)</span></label>
        <textarea class="form-control" id="description" name="description" rows="3" maxlength="5000"><?= e(ancien('description', $defauts['description'] ?? '')) ?></textarea>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Inscriptions aux activités
// ---------------------------------------------------------------------------

/**
 * Inscriptions d'une activité : les inscrits, puis la liste d'attente, puis
 * les annulées. Les inscrits sont dans l'ordre de leur inscription, la liste
 * d'attente dans l'ordre d'entrée dans cette liste.
 *
 * @return list<array<string, mixed>>
 */
function inscriptions_activite(int $activite_id): array
{
    return db_lignes(
        'SELECT i.id, i.adherent_id, i.nb_places, i.montant, i.est_tarif_familial, i.statut, i.attente_le, i.est_annulee, i.annulee_le, i.date_crea,
                a.nom, a.prenom, a.est_structure, a.date_naissance, a.mail, a.tel,
                a.responsable_nom, a.responsable_tel
           FROM inscriptions i
           JOIN adherents a ON a.id = i.adherent_id
          WHERE i.activite_id = ?
          ORDER BY i.est_annulee, i.statut, COALESCE(i.attente_le, i.date_crea), i.id',
        [$activite_id]
    );
}

/**
 * Inscriptions d'un adhérent aux activités d'une saison (les annulées en dernier).
 *
 * @return list<array<string, mixed>>
 */
function inscriptions_adherent(int $adherent_id, int $saison_id): array
{
    return db_lignes(
        'SELECT i.id, i.activite_id, i.nb_places, i.montant, i.est_tarif_familial, i.statut, i.est_annulee, i.annulee_le, i.date_crea,
                ac.nom AS activite_nom, ac.date_debut, ac.date_fin
           FROM inscriptions i
           JOIN activites ac ON ac.id = i.activite_id
          WHERE i.adherent_id = ? AND ac.saison_id = ?
          ORDER BY i.est_annulee, ac.date_debut, ac.nom, i.id',
        [$adherent_id, $saison_id]
    );
}

/**
 * Inscriptions des membres d'une famille aux activités d'une saison, classées
 * par membre (du plus âgé au plus jeune), les annulées en dernier.
 *
 * @return list<array<string, mixed>>
 */
function inscriptions_famille(int $famille_id, int $saison_id): array
{
    return db_lignes(
        'SELECT i.id, i.activite_id, i.adherent_id, i.nb_places, i.montant, i.est_tarif_familial, i.statut, i.est_annulee, i.annulee_le,
                ac.nom AS activite_nom, ac.date_debut, ac.date_fin
           FROM inscriptions i
           JOIN activites ac ON ac.id = i.activite_id
           JOIN adherents a ON a.id = i.adherent_id
          WHERE a.famille_id = ? AND ac.saison_id = ?
          ORDER BY a.date_naissance, a.prenom, a.id, i.est_annulee, ac.date_debut, ac.nom, i.id',
        [$famille_id, $saison_id]
    );
}

/**
 * Affiche une liste d'inscriptions à des activités (fiche adhérent, fiche famille).
 * Les montants ne sont montrés qu'au rôle standard au minimum.
 *
 * @param list<array<string, mixed>> $inscriptions lignes de inscriptions_adherent() ou inscriptions_famille()
 */
function afficher_inscriptions_activites(array $inscriptions): void
{
    ?>
    <ul class="list-unstyled mb-0">
        <?php foreach ($inscriptions as $ligne_inscription) : ?>
            <?php $ligne_annulee = entier($ligne_inscription['est_annulee']) === 1; ?>
            <li class="<?= $ligne_annulee ? 'text-body-secondary' : '' ?>">
                <a href="<?= e(chemin_site('activite.php?id=' . entier($ligne_inscription['activite_id']))) ?>"><?= e($ligne_inscription['activite_nom']) ?></a>
                <span class="text-body-secondary">— <?= e(activite_dates($ligne_inscription)) ?></span>
                <?php if (entier($ligne_inscription['nb_places']) > 1) : ?>
                    <span class="text-body-secondary">— <?= entier($ligne_inscription['nb_places']) ?> places</span>
                <?php endif; ?>
                <?php if (role_au_moins('standard')) : ?>
                    <span class="text-body-secondary">— <?= e(montant_fr($ligne_inscription['montant'])) ?><?= entier($ligne_inscription['est_tarif_familial']) === 1 ? ' (tarif familial)' : '' ?></span>
                <?php endif; ?>
                <?php if ($ligne_annulee) : ?>
                    <span class="badge text-bg-secondary">Annulée le <?= e(date_fr($ligne_inscription['annulee_le'])) ?></span>
                <?php elseif (texte($ligne_inscription['statut']) === 'attente') : ?>
                    <span class="badge text-bg-warning">Liste d'attente</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

/**
 * Une inscription par son identifiant, ou null.
 *
 * @return array<string, mixed>|null
 */
function inscription_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT i.*, a.nom, a.prenom, a.est_structure
           FROM inscriptions i
           JOIN adherents a ON a.id = i.adherent_id
          WHERE i.id = ?',
        [$id]
    );
}

/** Nombre d'inscriptions non annulées d'un adhérent dans un dossier (elles empêchent de le retirer de la saison). */
function inscriptions_actives_nb(int $dossier_id, int $adherent_id): int
{
    return entier(db_valeur(
        'SELECT COUNT(*) FROM inscriptions WHERE dossier_id = ? AND adherent_id = ? AND est_annulee = 0',
        [$dossier_id, $adherent_id]
    ));
}

/** Vrai si la famille (son dossier) a déjà une inscription non annulée au tarif familial à cette activité. */
function inscription_familiale_existe(int $activite_id, int $dossier_id): bool
{
    return entier(db_valeur(
        'SELECT COUNT(*) FROM inscriptions WHERE activite_id = ? AND dossier_id = ? AND est_tarif_familial = 1 AND est_annulee = 0',
        [$activite_id, $dossier_id]
    )) > 0;
}

/**
 * Vrai si l'adhérent a déjà une inscription non annulée (inscrit ou en attente) à cette activité.
 */
function inscription_existe(int $activite_id, int $adherent_id): bool
{
    return entier(db_valeur(
        'SELECT COUNT(*) FROM inscriptions WHERE activite_id = ? AND adherent_id = ? AND est_annulee = 0',
        [$activite_id, $adherent_id]
    )) > 0;
}

/**
 * Verrouille une activité le temps d'une transaction et renvoie ses places :
 * deux inscriptions simultanées ne peuvent pas prendre la même dernière place.
 *
 * @return array{nom: string, saison_id: int, nb_places: int|null, a_liste_attente: bool, tarif_montant: string, tarif_montant_familial: string|null, places_prises: int}|null
 */
function activite_verrouiller(int $activite_id): ?array
{
    $activite = db_ligne(
        'SELECT ac.nom, ac.saison_id, ac.nb_places, ac.a_liste_attente, ac.tarif_id FROM activites ac WHERE ac.id = ? FOR UPDATE',
        [$activite_id]
    );
    if ($activite === null) {
        return null;
    }
    $tarif = $activite['tarif_id'] === null
        ? null
        : db_ligne('SELECT montant_individuel, montant_familial FROM tarifs WHERE id = ?', [entier($activite['tarif_id'])]);
    $montant = texte($tarif['montant_individuel'] ?? '');
    return [
        'nom'             => texte($activite['nom']),
        'saison_id'       => entier($activite['saison_id']),
        'nb_places'       => $activite['nb_places'] === null ? null : entier($activite['nb_places']),
        'a_liste_attente' => entier($activite['a_liste_attente']) === 1,
        'tarif_montant'   => $montant !== '' ? $montant : '0.00',
        'tarif_montant_familial' => ($tarif['montant_familial'] ?? null) === null ? null : texte($tarif['montant_familial']),
        'places_prises'   => entier(db_valeur(
            'SELECT COALESCE(SUM(nb_places), 0) FROM inscriptions WHERE activite_id = ? AND est_annulee = 0 AND statut = \'inscrit\'',
            [$activite_id]
        )),
    ];
}

/**
 * Inscrit un adhérent à une activité.
 *
 * Règles : l'adhérent doit être inscrit pour la saison de l'activité ; une
 * personne n'a qu'une inscription en cours par activité (une structure peut en
 * avoir plusieurs) ; si les places demandées ne tiennent pas en entier,
 * l'inscription passe entièrement en liste d'attente quand l'activité en a
 * une, et elle est refusée sinon ; on peut aussi demander directement la liste
 * d'attente, même s'il reste des places.
 *
 * Tarif familial : quand le tarif de l'activité a un montant familial, une
 * personne peut être inscrite « au tarif familial ». Chaque inscription de ce
 * genre porte le montant familial, qui ne compte qu'une seule fois par famille
 * et par activité (calcul du montant dû, étape 3).
 *
 * @param array<string, mixed> $adherent       fiche de l'adhérent
 * @param int                  $nb_places      1 pour une personne
 * @param string|null          $montant        montant négocié (structure), au format de la base ; null : tarif de l'activité
 * @param bool                 $en_attente     true : directement en liste d'attente
 * @param bool                 $familial       true : au tarif familial de l'activité
 * @return array{erreur: string, statut: string, id: int} erreur vide si l'inscription est faite
 */
function inscription_creer(int $activite_id, array $adherent, int $nb_places, ?string $montant, bool $en_attente, bool $familial = false): array
{
    $adherent_id = entier($adherent['id'] ?? 0);
    $est_structure = entier($adherent['est_structure'] ?? 0) === 1;
    $echec = static fn (string $message): array => ['erreur' => $message, 'statut' => '', 'id' => 0];

    db()->beginTransaction();
    try {
        $activite = activite_verrouiller($activite_id);
        if ($activite === null) {
            db()->rollBack();
            return $echec('Cette activité n\'existe pas.');
        }
        $dossier_id = dossier_id_de_adherent($adherent, $activite['saison_id']);
        $dans_saison = $dossier_id > 0 && entier(db_valeur(
            'SELECT COUNT(*) FROM dossiers_adherents WHERE dossier_id = ? AND adherent_id = ?',
            [$dossier_id, $adherent_id]
        )) > 0;

        $erreur = '';
        $statut = 'inscrit';
        if (texte($adherent['anonymise_le'] ?? '') !== '') {
            $erreur = 'Cette fiche est anonymisée : elle ne peut plus être inscrite.';
        } elseif (!$dans_saison) {
            $erreur = nom_adherent($adherent) . ' n\'est pas inscrit(e) pour la saison de cette activité : faites-le d\'abord sur sa fiche.';
        } elseif (!$est_structure && inscription_existe($activite_id, $adherent_id)) {
            $erreur = nom_adherent($adherent) . ' a déjà une inscription en cours à cette activité.';
        } elseif ($nb_places < 1 || $nb_places > 9999 || (!$est_structure && $nb_places !== 1)) {
            $erreur = 'Le nombre de places n\'est pas valide.';
        } elseif ($familial && ($est_structure || $activite['tarif_montant_familial'] === null)) {
            $erreur = 'Le tarif de cette activité n\'a pas de montant familial.';
        } elseif ($en_attente) {
            if (!$activite['a_liste_attente']) {
                $erreur = 'Cette activité n\'a pas de liste d\'attente.';
            }
            $statut = 'attente';
        } elseif ($activite['nb_places'] !== null && $activite['places_prises'] + $nb_places > $activite['nb_places']) {
            $restantes = max(0, $activite['nb_places'] - $activite['places_prises']);
            if ($activite['a_liste_attente']) {
                $statut = 'attente';
            } else {
                $erreur = $restantes === 0
                    ? 'Cette activité est complète et n\'a pas de liste d\'attente.'
                    : 'Il ne reste que ' . $restantes . ' place' . ($restantes > 1 ? 's' : '') . ' et cette activité n\'a pas de liste d\'attente.';
            }
        }
        if ($erreur !== '') {
            db()->rollBack();
            return $echec($erreur);
        }

        $montant_retenu = $activite['tarif_montant'];
        if ($est_structure && $montant !== null) {
            $montant_retenu = $montant;
        } elseif ($familial && $activite['tarif_montant_familial'] !== null) {
            $montant_retenu = $activite['tarif_montant_familial'];
        }
        db_executer(
            'INSERT INTO inscriptions (activite_id, dossier_id, adherent_id, nb_places, montant, est_tarif_familial, statut, attente_le, creation_par)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$activite_id, $dossier_id, $adherent_id, $nb_places, $montant_retenu, $familial ? 1 : 0, $statut, $statut === 'attente' ? date('Y-m-d H:i:s') : null, entier($_SESSION['utilisateur_id'] ?? 0)]
        );
        $inscription_id = entier(db()->lastInsertId());
        journaliser(
            'creation',
            'inscriptions',
            $inscription_id,
            ($statut === 'attente' ? 'Liste d\'attente : ' : 'Inscription : ') . nom_adherent($adherent) . ' — ' . $activite['nom'],
            null,
            ['activite_id' => $activite_id, 'adherent_id' => $adherent_id, 'nb_places' => $nb_places, 'montant' => $montant_retenu, 'est_tarif_familial' => $familial ? 1 : 0, 'statut' => $statut]
        );
        db()->commit();
        return ['erreur' => '', 'statut' => $statut, 'id' => $inscription_id];
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $exception;
    }
}

/**
 * Annule une inscription (elle reste en base, pour l'historique) : ses places sont libérées.
 *
 * @param array<string, mixed> $inscription ligne lue par inscription_par_id()
 * @return bool false si elle était déjà annulée
 */
function inscription_annuler(array $inscription): bool
{
    if (entier($inscription['est_annulee'] ?? 0) === 1) {
        return false;
    }
    $inscription_id = entier($inscription['id'] ?? 0);
    $utilisateur_id = entier($_SESSION['utilisateur_id'] ?? 0);
    db_executer(
        'UPDATE inscriptions SET est_annulee = 1, annulee_le = ?, annulation_par = ?, modification_par = ? WHERE id = ? AND est_annulee = 0',
        [date('Y-m-d H:i:s'), $utilisateur_id, $utilisateur_id, $inscription_id]
    );
    journaliser(
        'modification',
        'inscriptions',
        $inscription_id,
        'Annulation de l\'inscription de ' . nom_adherent($inscription),
        ['est_annulee' => 0, 'statut' => $inscription['statut'] ?? null],
        ['est_annulee' => 1]
    );
    return true;
}

/**
 * Fait passer une inscription de la liste d'attente aux inscrits, si ses
 * places tiennent dans les places libres.
 *
 * @param array<string, mixed> $inscription ligne lue par inscription_par_id()
 * @return string message d'erreur, ou '' si l'inscription est faite
 */
function inscription_sortir_attente(array $inscription): string
{
    $inscription_id = entier($inscription['id'] ?? 0);
    db()->beginTransaction();
    try {
        $activite = activite_verrouiller(entier($inscription['activite_id'] ?? 0));
        // Relue après le verrou : un autre utilisateur a pu la modifier entre-temps.
        $actuelle = db_ligne('SELECT nb_places, statut, est_annulee FROM inscriptions WHERE id = ?', [$inscription_id]);
        $erreur = '';
        if ($activite === null || $actuelle === null || entier($actuelle['est_annulee']) === 1 || texte($actuelle['statut']) !== 'attente') {
            $erreur = 'Cette inscription n\'est plus en liste d\'attente.';
        } elseif ($activite['nb_places'] !== null && $activite['places_prises'] + entier($actuelle['nb_places']) > $activite['nb_places']) {
            $restantes = max(0, $activite['nb_places'] - $activite['places_prises']);
            $erreur = $restantes === 0
                ? 'Aucune place n\'est libre pour le moment.'
                : 'Cette inscription demande ' . entier($actuelle['nb_places']) . ' places et il n\'en reste que ' . $restantes . '.';
        }
        if ($erreur !== '') {
            db()->rollBack();
            return $erreur;
        }
        db_executer(
            'UPDATE inscriptions SET statut = \'inscrit\', attente_le = NULL, modification_par = ? WHERE id = ?',
            [entier($_SESSION['utilisateur_id'] ?? 0), $inscription_id]
        );
        journaliser(
            'modification',
            'inscriptions',
            $inscription_id,
            'Sortie de la liste d\'attente : ' . nom_adherent($inscription),
            ['statut' => 'attente'],
            ['statut' => 'inscrit']
        );
        db()->commit();
        return '';
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $exception;
    }
}

/**
 * Fait passer une inscription des inscrits à la liste d'attente : ses places
 * sont libérées, et elle prend la dernière position de la liste d'attente.
 * Refusé si l'activité n'a pas de liste d'attente.
 *
 * @param array<string, mixed> $inscription ligne lue par inscription_par_id()
 * @return string message d'erreur, ou '' si l'inscription est en liste d'attente
 */
function inscription_mettre_en_attente(array $inscription): string
{
    $inscription_id = entier($inscription['id'] ?? 0);
    db()->beginTransaction();
    try {
        $activite = activite_verrouiller(entier($inscription['activite_id'] ?? 0));
        // Relue après le verrou : un autre utilisateur a pu la modifier entre-temps.
        $actuelle = db_ligne('SELECT statut, est_annulee FROM inscriptions WHERE id = ?', [$inscription_id]);
        $erreur = '';
        if ($activite === null || $actuelle === null || entier($actuelle['est_annulee']) === 1 || texte($actuelle['statut']) !== 'inscrit') {
            $erreur = 'Cette inscription n\'est plus dans la liste des inscrits.';
        } elseif (!$activite['a_liste_attente']) {
            $erreur = 'Cette activité n\'a pas de liste d\'attente.';
        }
        if ($erreur !== '') {
            db()->rollBack();
            return $erreur;
        }
        db_executer(
            'UPDATE inscriptions SET statut = \'attente\', attente_le = ?, modification_par = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), entier($_SESSION['utilisateur_id'] ?? 0), $inscription_id]
        );
        journaliser(
            'modification',
            'inscriptions',
            $inscription_id,
            'Passage en liste d\'attente : ' . nom_adherent($inscription),
            ['statut' => 'inscrit'],
            ['statut' => 'attente']
        );
        db()->commit();
        return '';
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $exception;
    }
}
