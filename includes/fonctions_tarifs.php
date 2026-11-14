<?php

declare(strict_types=1);

/**
 * Tarifs.
 *
 * Un tarif est une ligne nommée d'une saison (exemple : « Atelier annuel —
 * 120,00 € ») ; plusieurs activités peuvent le partager. Il a un type, un
 * montant individuel et, s'il y a lieu, un montant familial, valable pour
 * toute la famille (exemple : centre de loisirs, 5 € par enfant ou 10 € pour
 * tous les enfants).
 *
 * Les types de tarif sont une liste de choix (table types_tarif, fonctions
 * choix_* de fonctions_activites.php), gérée par un administrateur dans
 * « Options des paiements ». Deux types sont figés, car l'application leur
 * donne un comportement particulier (colonne comportement) :
 *  - « adhesion » : réservé aux cotisations ; une activité ne peut pas le choisir ;
 *  - « forfait » : il ne se paie qu'une fois, comme une cotisation : il compte
 *    une fois par personne (une fois par famille au montant familial), quel
 *    que soit le nombre d'activités qui l'utilisent.
 * Les autres types sont libres : leur tarif est dû à chaque inscription. Le
 * calcul est dans fonctions_paiements.php (comptes_lignes()).
 *
 * Dans les lignes lues ici, « comportement » vaut 'adhesion', 'forfait' ou ''
 * (type libre), et « type_nom » est le nom du type.
 *
 * Le montant est copié sur chaque inscription au moment où elle est faite :
 * modifier un tarif ne change pas les inscriptions déjà enregistrées.
 *
 * Droits : consulter et ajouter, rôle standard ; modifier et supprimer, admin.
 */

/** Morceau de requête commun aux lectures de tarifs (alias t, type tyt). */
const TARIFS_COLONNES = 't.id, t.saison_id, t.nom, t.type_id, tyt.nom AS type_nom, COALESCE(tyt.comportement, \'\') AS comportement,
                t.montant_individuel, t.montant_familial, t.description';

/**
 * Comportement d'un type de tarif : 'adhesion', 'forfait', '' pour un type
 * libre, ou null si le type n'existe pas.
 */
function type_tarif_comportement(int $type_id): ?string
{
    $type = db_ligne('SELECT comportement FROM types_tarif WHERE id = ?', [$type_id]);
    return $type === null ? null : texte($type['comportement'] ?? '');
}

/**
 * Tarifs d'une saison, avec le nombre d'activités qui les utilisent.
 * Ordre : les adhésions, les forfaits, puis les autres types par nom.
 *
 * @param bool $pour_activite true : seulement ceux qu'une activité peut choisir (pas les tarifs « adhésion »)
 * @return list<array<string, mixed>>
 */
function tarifs_liste(int $saison_id, bool $pour_activite = false): array
{
    return db_lignes(
        'SELECT ' . TARIFS_COLONNES . ',
                (SELECT COUNT(*) FROM activites ac WHERE ac.tarif_id = t.id) AS nb_activites
           FROM tarifs t
           JOIN types_tarif tyt ON tyt.id = t.type_id
          WHERE t.saison_id = ?' . ($pour_activite ? ' AND COALESCE(tyt.comportement, \'\') <> \'adhesion\'' : '') . '
          ORDER BY tyt.comportement IS NULL, tyt.comportement, tyt.nom, t.nom',
        [$saison_id]
    );
}

/**
 * Un tarif par son identifiant, avec le nom de sa saison et le nombre d'activités, d'inscriptions (même annulées) et de cotisations qui l'utilisent, ou null.
 *
 * @return array<string, mixed>|null
 */
function tarif_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT ' . TARIFS_COLONNES . ', s.nom AS saison_nom,
                (SELECT COUNT(*) FROM activites ac WHERE ac.tarif_id = t.id) AS nb_activites,
                (SELECT COUNT(*) FROM inscriptions i WHERE i.tarif_id = t.id) AS nb_inscriptions,
                (SELECT COUNT(*) FROM cotisations c WHERE c.tarif_id = t.id) AS nb_cotisations
           FROM tarifs t
           JOIN types_tarif tyt ON tyt.id = t.type_id
           JOIN saisons s ON s.id = t.saison_id
          WHERE t.id = ?',
        [$id]
    );
}

/**
 * Montants d'un tarif, pour l'affichage : « 5,00 € » ou « 5,00 € (famille : 10,00 €) ».
 *
 * @param mixed $individuel montant individuel
 * @param mixed $familial   montant familial, ou null
 */
function tarif_montants(mixed $individuel, mixed $familial): string
{
    return montant_fr($individuel) . ($familial === null ? '' : ' (famille : ' . montant_fr($familial) . ')');
}

/**
 * Champs d'un tarif lus dans le formulaire envoyé (montants tels qu'ils ont été saisis).
 *
 * @return array{nom: string, type_id: string, montant_individuel: string, montant_familial: string, description: string}
 */
function tarif_saisie(): array
{
    return [
        'nom'                => post('nom'),
        'type_id'            => post('type_id'),
        'montant_individuel' => post('montant_individuel'),
        'montant_familial'   => post('montant_familial'),
        'description'        => post('description'),
    ];
}

/**
 * Vérifie un tarif avant création ou modification.
 *
 * @param array{nom: string, type_id: string, montant_individuel: string, montant_familial: string, description: string} $saisie
 * @param int $sauf_id identifiant du tarif modifié (0 pour une création)
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_tarif(array $saisie, int $saison_id, int $sauf_id = 0): array
{
    $erreurs = [];
    if ($saisie['nom'] === '' || mb_strlen($saisie['nom']) > 100) {
        $erreurs[] = 'Le nom du tarif est obligatoire (100 caractères au maximum).';
    } elseif (entier(db_valeur('SELECT COUNT(*) FROM tarifs WHERE saison_id = ? AND nom = ? AND id <> ?', [$saison_id, $saisie['nom'], $sauf_id])) > 0) {
        $erreurs[] = 'Un tarif porte déjà ce nom pour cette saison.';
    }
    // Types proposés : les types actifs, plus celui que le tarif a déjà (même rendu inactif depuis).
    $type_id = entier($saisie['type_id']);
    $type_actuel = $sauf_id > 0 ? entier(db_valeur('SELECT type_id FROM tarifs WHERE id = ?', [$sauf_id])) : 0;
    $comportement = array_key_exists($type_id, choix_proposes('types_tarif', $type_actuel)) ? type_tarif_comportement($type_id) : null;
    if ($comportement === null) {
        $erreurs[] = 'Choisissez le type du tarif dans la liste.';
    } elseif ($comportement === 'adhesion' && $sauf_id > 0 && entier(db_valeur('SELECT COUNT(*) FROM activites WHERE tarif_id = ?', [$sauf_id])) > 0) {
        $erreurs[] = 'Des activités utilisent ce tarif : il ne peut pas devenir un tarif « adhésion », réservé aux cotisations.';
    } elseif ($comportement === 'adhesion' && $sauf_id > 0 && entier(db_valeur('SELECT COUNT(*) FROM inscriptions WHERE tarif_id = ?', [$sauf_id])) > 0) {
        $erreurs[] = 'Des inscriptions à des activités ont été faites à ce tarif : il ne peut pas devenir un tarif « adhésion », réservé aux cotisations.';
    } elseif ($comportement !== 'adhesion' && $sauf_id > 0 && entier(db_valeur('SELECT COUNT(*) FROM cotisations WHERE tarif_id = ?', [$sauf_id])) > 0) {
        $erreurs[] = 'Des cotisations utilisent ce tarif : il doit rester un tarif « adhésion ».';
    }
    if (montant_lire($saisie['montant_individuel']) === null) {
        $erreurs[] = 'Le montant individuel est obligatoire : un nombre positif ou nul, par exemple 120 ou 12,50.';
    }
    if ($saisie['montant_familial'] !== '' && montant_lire($saisie['montant_familial']) === null) {
        $erreurs[] = 'Le montant familial n\'est pas valide : un nombre positif ou nul, ou laissez vide.';
    }
    if (mb_strlen($saisie['description']) > 2000) {
        $erreurs[] = 'La description est trop longue (2 000 caractères au maximum).';
    }
    return $erreurs;
}

/**
 * Valeurs d'un tarif prêtes pour la base, dans l'ordre des colonnes.
 *
 * @param array{nom: string, type_id: string, montant_individuel: string, montant_familial: string, description: string} $saisie tarif déjà vérifié
 * @return array{nom: string, type_id: int, montant_individuel: string, montant_familial: string|null, description: string|null}
 */
function tarif_valeurs(array $saisie): array
{
    return [
        'nom'                => $saisie['nom'],
        'type_id'            => entier($saisie['type_id']),
        'montant_individuel' => montant_lire($saisie['montant_individuel']) ?? '0.00',
        'montant_familial'   => $saisie['montant_familial'] === '' ? null : montant_lire($saisie['montant_familial']),
        'description'        => nul_si_vide($saisie['description']),
    ];
}

/**
 * Crée un tarif (déjà vérifié) et renvoie son identifiant.
 *
 * @param array{nom: string, type_id: string, montant_individuel: string, montant_familial: string, description: string} $saisie
 */
function creer_tarif(int $saison_id, array $saisie): int
{
    $valeurs = tarif_valeurs($saisie);
    db_executer(
        'INSERT INTO tarifs (nom, type_id, montant_individuel, montant_familial, description, saison_id, creation_par) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [...array_values($valeurs), $saison_id, entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    $tarif_id = entier(db()->lastInsertId());
    journaliser('creation', 'tarifs', $tarif_id, 'Création du tarif « ' . $saisie['nom'] . ' »', null, $valeurs + ['saison_id' => $saison_id]);
    return $tarif_id;
}

/**
 * Modifie un tarif (déjà vérifié).
 *
 * @param array<string, mixed> $actuel tarif en base avant la modification
 * @param array{nom: string, type_id: string, montant_individuel: string, montant_familial: string, description: string} $saisie
 * @return bool false s'il n'y avait rien à modifier
 */
function modifier_tarif(array $actuel, array $saisie): bool
{
    $valeurs = tarif_valeurs($saisie);
    $ancien = [
        'nom'                => texte($actuel['nom'] ?? ''),
        'type_id'            => entier($actuel['type_id'] ?? 0),
        'montant_individuel' => texte($actuel['montant_individuel'] ?? ''),
        'montant_familial'   => ($actuel['montant_familial'] ?? null) === null ? null : texte($actuel['montant_familial']),
        'description'        => ($actuel['description'] ?? null) === null ? null : texte($actuel['description']),
    ];
    if ($ancien === $valeurs) {
        return false;
    }
    $tarif_id = entier($actuel['id'] ?? 0);
    db_executer(
        'UPDATE tarifs SET nom = ?, type_id = ?, montant_individuel = ?, montant_familial = ?, description = ?, modification_par = ? WHERE id = ?',
        [...array_values($valeurs), entier($_SESSION['utilisateur_id'] ?? 0), $tarif_id]
    );
    journaliser('modification', 'tarifs', $tarif_id, 'Modification du tarif « ' . $saisie['nom'] . ' »', $ancien, $valeurs);
    return true;
}

/**
 * Supprime un tarif qu'aucune activité, aucune inscription ni aucune cotisation (même annulées) n'utilise.
 *
 * @param array<string, mixed> $tarif
 * @return bool false s'il est encore utilisé
 */
function supprimer_tarif(array $tarif): bool
{
    $tarif_id = entier($tarif['id'] ?? 0);
    if (entier(db_valeur('SELECT COUNT(*) FROM activites WHERE tarif_id = ?', [$tarif_id])) > 0
        || entier(db_valeur('SELECT COUNT(*) FROM inscriptions WHERE tarif_id = ?', [$tarif_id])) > 0
        || entier(db_valeur('SELECT COUNT(*) FROM cotisations WHERE tarif_id = ?', [$tarif_id])) > 0) {
        return false;
    }
    $ancien = [];
    foreach (['saison_id', 'nom', 'type_id', 'montant_individuel', 'montant_familial', 'description'] as $champ) {
        $ancien[$champ] = $tarif[$champ] ?? null;
    }
    db_executer('DELETE FROM tarifs WHERE id = ?', [$tarif_id]);
    journaliser('suppression', 'tarifs', $tarif_id, 'Suppression du tarif « ' . texte($tarif['nom'] ?? '') . ' »', $ancien);
    return true;
}

/**
 * Affiche les champs du formulaire d'un tarif (création et modification).
 *
 * @param array<string, mixed> $defauts valeurs affichées quand rien n'a été saisi
 */
function afficher_champs_tarif(array $defauts): void
{
    $type_actuel = entier($defauts['type_id'] ?? 0);
    $type_choisi = entier(ancien('type_id', (string) $type_actuel));
    // Les montants de la base (12.50) sont proposés à la française (12,50).
    $montant = static fn (string $champ): string => ancien(
        $champ,
        ($defauts[$champ] ?? null) === null ? '' : number_format((float) texte($defauts[$champ]), 2, ',', '')
    );
    ?>
    <div class="row g-3 mb-3">
        <div class="col-sm-8">
            <label class="form-label" for="nom">Nom</label>
            <input class="form-control" type="text" id="nom" name="nom" maxlength="100" placeholder="Atelier annuel" value="<?= e(ancien('nom', $defauts['nom'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="type_id">Type</label>
            <select class="form-select" id="type_id" name="type_id" required>
                <option value="">Choisir…</option>
                <?php foreach (choix_proposes('types_tarif', $type_actuel) as $id_type => $nom_type) : ?>
                    <option value="<?= $id_type ?>" <?= $type_choisi === $id_type ? 'selected' : '' ?>><?= e($nom_type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="montant_individuel">Montant individuel (€)</label>
            <input class="form-control" type="text" inputmode="decimal" id="montant_individuel" name="montant_individuel" maxlength="10" placeholder="120,00" value="<?= e($montant('montant_individuel')) ?>" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="montant_familial">Montant familial (€) <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="text" inputmode="decimal" id="montant_familial" name="montant_familial" maxlength="10" value="<?= e($montant('montant_familial')) ?>" aria-describedby="aide_familial">
            <div class="form-text" id="aide_familial">Pour toute la famille, quel que soit le nombre d'inscrits. Vide : pas de tarif familial.</div>
        </div>
        <div class="col-sm-12">
            <label class="form-label" for="description">Description <span class="text-body-secondary">(facultatif)</span></label>
            <textarea class="form-control" id="description" name="description" rows="2" maxlength="2000"><?= e(ancien('description', $defauts['description'] ?? '')) ?></textarea>
        </div>
    </div>
    <?php
}
