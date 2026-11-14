<?php

declare(strict_types=1);

/**
 * RGPD : anonymisation des familles (cahier des charges, section 11).
 *
 * - Une famille est « inactive » quand la dernière saison où l'un de ses
 *   membres est inscrit est terminée depuis plus de 3 ans (ou, si elle n'a
 *   jamais été inscrite, quand elle a été créée il y a plus de 3 ans).
 * - Une personne peut aussi être anonymisée seule (droit à l'effacement) :
 *   sa famille et les autres membres ne sont pas touchés.
 * - L'anonymisation est manuelle (page rgpd.php, administrateurs) et
 *   définitive. Elle efface ce qui identifie les personnes et garde ce qui
 *   sert aux statistiques et à la comptabilité des saisons passées.
 * - Les structures (associations, écoles) ne sont pas concernées.
 */

/** Durée d'inactivité après laquelle une famille est proposée à l'anonymisation. */
const RGPD_DELAI_INACTIVITE = '-3 years';

/** Mot à retaper pour confirmer l'anonymisation de plusieurs familles. */
const RGPD_MOT_CONFIRMATION = 'ANONYMISER';

/** Nom donné à une famille et à ses membres anonymisés. */
const RGPD_NOM_ANONYME = 'ANONYME';
const RGPD_PRENOM_ANONYME = 'Anonyme';

/** Date (AAAA-MM-JJ) avant laquelle une dernière saison terminée rend la famille inactive. */
function rgpd_date_limite(): string
{
    return (new DateTimeImmutable('today'))->modify(RGPD_DELAI_INACTIVITE)->format('Y-m-d');
}

/**
 * Familles inactives, non encore anonymisées, par ordre alphabétique.
 *
 * @return list<array{id: int, nom: string, membres: string, derniere_saison: string, derniere_fin: string, date_crea: string}>
 *         derniere_saison et derniere_fin sont vides pour une famille jamais inscrite
 */
function rgpd_familles_inactives(): array
{
    // Saisons où au moins un membre de la famille est inscrit.
    $saisons_inscrites = ' FROM dossiers d JOIN saisons s ON s.id = d.saison_id
                          WHERE d.famille_id = f.id
                            AND EXISTS (SELECT 1 FROM dossiers_adherents da WHERE da.dossier_id = d.id)';
    $lignes = db_lignes(
        'SELECT f.id, f.nom, f.date_crea,
                (SELECT GROUP_CONCAT(a.prenom ORDER BY a.date_naissance SEPARATOR \', \') FROM adherents a WHERE a.famille_id = f.id) AS membres,
                (SELECT MAX(s.date_fin)' . $saisons_inscrites . ') AS derniere_fin,
                (SELECT s.nom' . $saisons_inscrites . ' ORDER BY s.date_fin DESC LIMIT 1) AS derniere_saison
           FROM familles f
          WHERE f.anonymise_le IS NULL
         HAVING COALESCE(derniere_fin, DATE(f.date_crea)) < ?
          ORDER BY f.nom, f.id',
        [rgpd_date_limite()]
    );
    $familles = [];
    foreach ($lignes as $ligne) {
        $familles[] = [
            'id'              => entier($ligne['id']),
            'nom'             => texte($ligne['nom']),
            'membres'         => texte($ligne['membres']),
            'derniere_saison' => texte($ligne['derniere_saison']),
            'derniere_fin'    => texte($ligne['derniere_fin']),
            'date_crea'       => texte($ligne['date_crea']),
        ];
    }
    return $familles;
}

/** Nombre de familles déjà anonymisées. */
function rgpd_familles_anonymisees_nb(): int
{
    return entier(db_valeur('SELECT COUNT(*) FROM familles WHERE anonymise_le IS NOT NULL'));
}

/**
 * Raison pour laquelle une famille ne peut pas être anonymisée, ou '' si
 * c'est possible. Une famille inscrite pour la saison en cours ou à venir
 * doit d'abord être retirée de la saison.
 *
 * @param array<string, mixed> $famille ligne de famille_par_id()
 */
function rgpd_refus_anonymisation(array $famille): string
{
    if (texte($famille['anonymise_le'] ?? '') !== '') {
        return 'Cette famille est déjà anonymisée.';
    }
    $saisons_ouvertes = db_lignes(
        'SELECT DISTINCT s.nom, s.date_debut
           FROM dossiers d
           JOIN saisons s ON s.id = d.saison_id
           JOIN dossiers_adherents da ON da.dossier_id = d.id
          WHERE d.famille_id = ? AND s.date_fin >= ?
          ORDER BY s.date_debut',
        [entier($famille['id'] ?? 0), date('Y-m-d')]
    );
    if ($saisons_ouvertes === []) {
        return '';
    }
    $noms = array_map(static fn (array $ligne): string => texte($ligne['nom']), $saisons_ouvertes);
    return 'Cette famille est inscrite pour la saison ' . implode(' et la saison ', $noms)
        . ' : retirez d\'abord chacun de ses membres de la saison, sur la fiche de la famille.';
}

/**
 * Suite de « ? » pour une clause IN (...).
 *
 * @param list<int> $identifiants liste non vide
 */
function rgpd_marques(array $identifiants): string
{
    return implode(', ', array_fill(0, count($identifiants), '?'));
}

/**
 * Efface ce qui identifie des personnes : identité, contacts, responsable,
 * consentement, adresses, et les lignes du journal de leur fiche. À appeler
 * dans une transaction. Une personne déjà anonymisée garde sa date
 * d'anonymisation.
 *
 * @param list<int> $adherents identifiants des personnes
 */
function rgpd_effacer_personnes(array $adherents): void
{
    if ($adherents === []) {
        return;
    }
    $marques = rgpd_marques($adherents);
    // La date de naissance est ramenée au 1er janvier : l'année suffit aux tranches d'âge.
    db_executer(
        'UPDATE adherents
            SET nom = ?, prenom = ?, date_naissance = MAKEDATE(YEAR(date_naissance), 1),
                mail = NULL, tel = NULL, responsable_nom = NULL, responsable_mail = NULL, responsable_tel = NULL,
                a_consenti = 0, consentement_le = NULL, anonymise_le = COALESCE(anonymise_le, NOW()), modification_par = ?
          WHERE id IN (' . $marques . ')',
        [RGPD_NOM_ANONYME, RGPD_PRENOM_ANONYME, entier($_SESSION['utilisateur_id'] ?? 0), ...$adherents]
    );
    db_executer(
        'UPDATE dossiers_adherents
            SET adresse = \'\', complement = NULL, est_validee = 0, latitude = NULL, longitude = NULL
          WHERE adherent_id IN (' . $marques . ')',
        $adherents
    );
    db_executer(
        'DELETE FROM logs_bdd WHERE nom_table IN (\'adherents\', \'dossiers_adherents\') AND id_enregistrement IN (' . $marques . ')',
        $adherents
    );
}

/**
 * Raison pour laquelle une personne ne peut pas être anonymisée seule, ou ''
 * si c'est possible. Une personne inscrite pour la saison en cours ou à venir
 * doit d'abord être retirée de la saison.
 *
 * @param array<string, mixed> $adherent ligne de adherent_par_id()
 */
function rgpd_refus_anonymisation_adherent(array $adherent): string
{
    if (entier($adherent['est_structure'] ?? 0) === 1) {
        return 'Les structures ne sont pas concernées par l\'anonymisation.';
    }
    if (texte($adherent['anonymise_le'] ?? '') !== '') {
        return 'Cette personne est déjà anonymisée.';
    }
    $saisons_ouvertes = db_lignes(
        'SELECT DISTINCT s.nom, s.date_debut
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN saisons s ON s.id = d.saison_id
          WHERE da.adherent_id = ? AND s.date_fin >= ?
          ORDER BY s.date_debut',
        [entier($adherent['id'] ?? 0), date('Y-m-d')]
    );
    if ($saisons_ouvertes === []) {
        return '';
    }
    $noms = array_map(static fn (array $ligne): string => texte($ligne['nom']), $saisons_ouvertes);
    return 'Cette personne est inscrite pour la saison ' . implode(' et la saison ', $noms)
        . ' : retirez-la d\'abord de la saison, sur sa fiche.';
}

/**
 * Vrai si la personne est le dernier membre non anonymisé de sa famille :
 * l'anonymiser revient alors à anonymiser la famille entière.
 *
 * @param array<string, mixed> $adherent ligne de adherent_par_id()
 */
function rgpd_est_dernier_membre(array $adherent): bool
{
    return entier(db_valeur(
        'SELECT COUNT(*) FROM adherents WHERE famille_id = ? AND id <> ? AND anonymise_le IS NULL',
        [entier($adherent['famille_id'] ?? 0), entier($adherent['id'] ?? 0)]
    )) === 0;
}

/**
 * Mineurs de la même famille dont le responsable enregistré a le courriel ou
 * le téléphone de cette personne : leur responsable n'est pas effacé par
 * l'anonymisation de la personne, il faut le corriger sur leur fiche.
 *
 * @param array<string, mixed> $adherent ligne de adherent_par_id()
 * @return list<array{id: int, nom: string}> nom : « Prénom NOM »
 */
function rgpd_mineurs_a_charge(array $adherent): array
{
    $mail = texte($adherent['mail'] ?? '');
    $tel = texte($adherent['tel'] ?? '');
    if ($mail === '' && $tel === '') {
        return [];
    }
    $mineurs = [];
    $lignes = db_lignes(
        'SELECT id, nom, prenom
           FROM adherents
          WHERE famille_id = ? AND id <> ? AND anonymise_le IS NULL
            AND ((? <> \'\' AND responsable_mail = ?) OR (? <> \'\' AND responsable_tel = ?))
          ORDER BY date_naissance, prenom',
        [entier($adherent['famille_id'] ?? 0), entier($adherent['id'] ?? 0), $mail, $mail, $tel, $tel]
    );
    foreach ($lignes as $ligne) {
        $mineurs[] = ['id' => entier($ligne['id']), 'nom' => trim(texte($ligne['prenom']) . ' ' . texte($ligne['nom']))];
    }
    return $mineurs;
}

/**
 * Anonymise une seule personne, en une transaction. Définitif.
 *
 * Effacé : son identité, ses contacts, son adresse, les lignes du journal de
 * sa fiche, de ses inscriptions, cotisations et réductions. Non touché : le
 * nom de la famille, les notes des saisons et les précisions des paiements,
 * qui appartiennent à toute la famille. Si la personne est le dernier membre
 * non anonymisé, c'est la famille entière qui est anonymisée.
 *
 * @return 'personne'|'famille'|'' ce qui a été anonymisé ; '' si c'est impossible
 */
function adherent_anonymiser(int $adherent_id): string
{
    $adherent = adherent_par_id($adherent_id);
    if ($adherent === null || rgpd_refus_anonymisation_adherent($adherent) !== '') {
        return '';
    }
    if (rgpd_est_dernier_membre($adherent)) {
        return famille_anonymiser(entier($adherent['famille_id'])) ? 'famille' : '';
    }
    db()->beginTransaction();
    try {
        // Relu sous verrou : la fiche peut avoir changé entre-temps.
        $verrou = db_ligne('SELECT id, est_structure, anonymise_le FROM adherents WHERE id = ? FOR UPDATE', [$adherent_id]);
        if ($verrou === null || rgpd_refus_anonymisation_adherent($verrou) !== '') {
            db()->rollBack();
            return '';
        }
        rgpd_effacer_personnes([$adherent_id]);
        foreach (['inscriptions', 'cotisations', 'reductions'] as $table) {
            db_executer(
                'DELETE FROM logs_bdd
                  WHERE nom_table = \'' . $table . '\'
                    AND id_enregistrement IN (SELECT id FROM ' . $table . ' WHERE adherent_id = ?)',
                [$adherent_id]
            );
        }
        // Écrit après le nettoyage du journal, sans aucun nom.
        journaliser('anonymisation', 'adherents', $adherent_id, 'Anonymisation de la personne n° ' . $adherent_id);
        db()->commit();
    } catch (Throwable $erreur) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $erreur;
    }
    return 'personne';
}

/**
 * Anonymise une famille et tous ses membres, en une seule transaction.
 * Définitif : les données effacées ne peuvent pas être retrouvées.
 *
 * Effacé : noms, prénoms, jour et mois de naissance, courriels, téléphones,
 * responsable, consentement, adresses (voie, complément, position), notes des
 * saisons, notes des paiements, lignes du journal qui concernent ces fiches.
 * Gardé (statistiques et comptabilité des saisons passées) : sexe, année de
 * naissance, nationalité, bénévolat, code postal, ville, quartier, QP, QPV,
 * scolarité, inscriptions, cotisations, réductions et paiements.
 *
 * @return bool false si la famille n'existe pas ou ne peut pas être anonymisée
 */
function famille_anonymiser(int $famille_id): bool
{
    db()->beginTransaction();
    try {
        $famille = db_ligne('SELECT id, nom, anonymise_le FROM familles WHERE id = ? FOR UPDATE', [$famille_id]);
        if ($famille === null || rgpd_refus_anonymisation($famille) !== '') {
            db()->rollBack();
            return false;
        }
        $adherents = array_map(
            static fn (array $ligne): int => entier($ligne['id']),
            db_lignes('SELECT id FROM adherents WHERE famille_id = ?', [$famille_id])
        );
        $dossiers = array_map(
            static fn (array $ligne): int => entier($ligne['id']),
            db_lignes('SELECT id FROM dossiers WHERE famille_id = ?', [$famille_id])
        );

        db_executer('UPDATE familles SET nom = ?, anonymise_le = NOW() WHERE id = ?', [RGPD_NOM_ANONYME, $famille_id]);
        db_executer('DELETE FROM logs_bdd WHERE nom_table = \'familles\' AND id_enregistrement = ?', [$famille_id]);

        rgpd_effacer_personnes($adherents);

        if ($dossiers !== []) {
            $marques = rgpd_marques($dossiers);
            db_executer('UPDATE dossiers SET notes = NULL, modification_par = ? WHERE id IN (' . $marques . ')', [entier($_SESSION['utilisateur_id'] ?? 0), ...$dossiers]);
            db_executer('UPDATE paiements SET note = NULL WHERE note IS NOT NULL AND dossier_id IN (' . $marques . ')', $dossiers);
            db_executer('DELETE FROM logs_bdd WHERE nom_table = \'dossiers\' AND id_enregistrement IN (' . $marques . ')', $dossiers);
            // Journal des inscriptions, cotisations, réductions et paiements de la famille.
            foreach (['inscriptions', 'cotisations', 'reductions', 'paiements'] as $table) {
                db_executer(
                    'DELETE FROM logs_bdd
                      WHERE nom_table = \'' . $table . '\'
                        AND id_enregistrement IN (SELECT id FROM ' . $table . ' WHERE dossier_id IN (' . $marques . '))',
                    $dossiers
                );
            }
        }

        // Écrit après le nettoyage du journal, sans aucun nom.
        journaliser(
            'anonymisation',
            'familles',
            $famille_id,
            'Anonymisation de la famille n° ' . $famille_id . ' (' . count($adherents) . ' personne(s))'
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
