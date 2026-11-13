<?php

declare(strict_types=1);

/**
 * Cotisations, réductions, paiements et calcul du montant dû.
 *
 * Tout est rattaché au dossier : la famille (ou la structure) pour une saison.
 * Le mot « dossier » reste dans le code ; à l'écran on parle des « paiements
 * de la famille » pour la saison.
 *
 * Montant dû = cotisations + inscriptions en cours aux activités (hors liste
 * d'attente ; le tarif familial ne compte qu'une fois par activité) − réductions.
 * Reste à payer = montant dû − paiements non annulés ; s'il est négatif, c'est
 * un trop-perçu.
 *
 * Les calculs se font en centimes (entiers), pour éviter les erreurs d'arrondi.
 *
 * Droits : toute cette partie est réservée au rôle standard au minimum.
 */

// ---------------------------------------------------------------------------
// Libellés et montants
// ---------------------------------------------------------------------------

/**
 * Libellés des modes de paiement.
 *
 * @return array<string, string>
 */
function libelles_modes_paiement(): array
{
    return ['especes' => 'Espèces', 'cheque' => 'Chèque', 'cb' => 'Carte bancaire', 'autre' => 'Autre'];
}

/**
 * Libellés de ce que règle un paiement (distinction demandée par la comptabilité).
 *
 * @return array<string, string>
 */
function libelles_objets_paiement(): array
{
    return ['adhesion' => 'Adhésion', 'activites' => 'Activités'];
}

/** Montant de la base (« 12.50 ») en centimes (1250). */
function centimes(mixed $montant): int
{
    return (int) round((float) texte($montant) * 100);
}

/** Montant en centimes au format français : « 12,50 € ». */
function centimes_fr(int $centimes): string
{
    return montant_fr($centimes / 100);
}

// ---------------------------------------------------------------------------
// Titulaire du compte : une famille ou une structure
// ---------------------------------------------------------------------------

/**
 * Famille ou structure dont on regarde les paiements.
 *
 * @return array{type: 'famille'|'structure', id: int, nom: string, titre: string, lien: string, fiche: string, anonymise: bool}|null
 *         lien : paramètre d'adresse (« famille=12 ») ; fiche : page de la famille ou de la structure
 */
function compte_titulaire(int $famille_id, int $structure_id): ?array
{
    if ($famille_id > 0) {
        $famille = famille_par_id($famille_id);
        if ($famille === null) {
            return null;
        }
        return [
            'type'      => 'famille',
            'id'        => $famille_id,
            'nom'       => texte($famille['nom']),
            'titre'     => 'Famille ' . texte($famille['nom']),
            'lien'      => 'famille=' . $famille_id,
            'fiche'     => 'famille.php?id=' . $famille_id,
            'anonymise' => texte($famille['anonymise_le']) !== '',
        ];
    }
    $structure = $structure_id > 0 ? adherent_par_id($structure_id) : null;
    if ($structure === null || entier($structure['est_structure']) !== 1) {
        return null;
    }
    return [
        'type'      => 'structure',
        'id'        => $structure_id,
        'nom'       => texte($structure['nom']),
        'titre'     => texte($structure['nom']),
        'lien'      => 'structure=' . $structure_id,
        'fiche'     => 'adherent.php?id=' . $structure_id,
        'anonymise' => texte($structure['anonymise_le']) !== '',
    ];
}

/**
 * Titulaire du compte auquel appartient un adhérent : sa famille, ou lui-même si c'est une structure.
 *
 * @param array<string, mixed> $adherent
 * @return array{type: 'famille'|'structure', id: int, nom: string, titre: string, lien: string, fiche: string, anonymise: bool}|null
 */
function compte_titulaire_de_adherent(array $adherent): ?array
{
    return entier($adherent['est_structure'] ?? 0) === 1
        ? compte_titulaire(0, entier($adherent['id'] ?? 0))
        : compte_titulaire(entier($adherent['famille_id'] ?? 0), 0);
}

/**
 * Identifiant du dossier d'un titulaire pour une saison (0 s'il n'existe pas).
 *
 * @param array{type: 'famille'|'structure', id: int, nom: string, titre: string, lien: string, fiche: string, anonymise: bool} $titulaire
 */
function compte_dossier_id(array $titulaire, int $saison_id): int
{
    if ($titulaire['type'] === 'famille') {
        return dossier_id_de_famille($titulaire['id'], $saison_id);
    }
    return entier(db_valeur('SELECT id FROM dossiers WHERE structure_id = ? AND saison_id = ?', [$titulaire['id'], $saison_id]));
}

// ---------------------------------------------------------------------------
// Calcul du montant dû, du reste à payer et du trop-perçu
// ---------------------------------------------------------------------------

/**
 * Comptes des familles et structures : une ligne par dossier, montants en centimes.
 *
 * solde = montant dû − payé : positif, c'est le reste à payer ; négatif, un trop-perçu.
 *
 * @param int $saison_id  tous les dossiers de cette saison (0 : sans filtre de saison)
 * @param int $dossier_id un seul dossier (0 : tous)
 * @return list<array{dossier_id: int, famille_id: int, structure_id: int, nom: string, cotisations: int, activites: int, reductions: int, du: int, paye_adhesion: int, paye_activites: int, paye: int, solde: int}>
 */
function comptes_lignes(int $saison_id, int $dossier_id = 0): array
{
    $conditions = [];
    $parametres = [];
    if ($saison_id > 0) {
        $conditions[] = 'd.saison_id = ?';
        $parametres[] = $saison_id;
    }
    if ($dossier_id > 0) {
        $conditions[] = 'd.id = ?';
        $parametres[] = $dossier_id;
    }
    if ($conditions === []) {
        return [];
    }
    $lignes = db_lignes(
        'SELECT d.id AS dossier_id, d.famille_id, d.structure_id, COALESCE(f.nom, st.nom) AS nom,
                COALESCE(c.total, 0) AS cotisations, COALESCE(i.total, 0) AS activites, COALESCE(r.total, 0) AS reductions,
                COALESCE(p.adhesion, 0) AS paye_adhesion, COALESCE(p.activites, 0) AS paye_activites
           FROM dossiers d
           LEFT JOIN familles f ON f.id = d.famille_id
           LEFT JOIN adherents st ON st.id = d.structure_id
           LEFT JOIN (SELECT dossier_id, SUM(montant) AS total
                        FROM cotisations WHERE est_annulee = 0 GROUP BY dossier_id) c ON c.dossier_id = d.id
           LEFT JOIN (SELECT x.dossier_id, SUM(x.montant) AS total
                        FROM (SELECT dossier_id, montant
                                FROM inscriptions
                               WHERE est_annulee = 0 AND statut = \'inscrit\' AND est_tarif_familial = 0
                              UNION ALL
                              SELECT dossier_id, MAX(montant) AS montant
                                FROM inscriptions
                               WHERE est_annulee = 0 AND statut = \'inscrit\' AND est_tarif_familial = 1
                               GROUP BY dossier_id, activite_id) x
                       GROUP BY x.dossier_id) i ON i.dossier_id = d.id
           LEFT JOIN (SELECT dossier_id, SUM(montant) AS total
                        FROM reductions GROUP BY dossier_id) r ON r.dossier_id = d.id
           LEFT JOIN (SELECT dossier_id,
                             SUM(CASE WHEN objet = \'adhesion\' THEN montant ELSE 0 END) AS adhesion,
                             SUM(CASE WHEN objet = \'activites\' THEN montant ELSE 0 END) AS activites
                        FROM paiements WHERE est_annule = 0 GROUP BY dossier_id) p ON p.dossier_id = d.id
          WHERE ' . implode(' AND ', $conditions) . '
          ORDER BY nom, d.id',
        $parametres
    );
    $comptes = [];
    foreach ($lignes as $ligne) {
        $cotisations = centimes($ligne['cotisations']);
        $activites = centimes($ligne['activites']);
        $reductions = centimes($ligne['reductions']);
        $paye_adhesion = centimes($ligne['paye_adhesion']);
        $paye_activites = centimes($ligne['paye_activites']);
        // Des réductions supérieures au total ne créent pas de trop-perçu : le dû est alors nul.
        $du = max(0, $cotisations + $activites - $reductions);
        $comptes[] = [
            'dossier_id'     => entier($ligne['dossier_id']),
            'famille_id'     => entier($ligne['famille_id']),
            'structure_id'   => entier($ligne['structure_id']),
            'nom'            => texte($ligne['nom']),
            'cotisations'    => $cotisations,
            'activites'      => $activites,
            'reductions'     => $reductions,
            'du'             => $du,
            'paye_adhesion'  => $paye_adhesion,
            'paye_activites' => $paye_activites,
            'paye'           => $paye_adhesion + $paye_activites,
            'solde'          => $du - $paye_adhesion - $paye_activites,
        ];
    }
    return $comptes;
}

/**
 * Compte d'un dossier (montants en centimes), ou null si le dossier n'existe pas.
 *
 * @return array{dossier_id: int, famille_id: int, structure_id: int, nom: string, cotisations: int, activites: int, reductions: int, du: int, paye_adhesion: int, paye_activites: int, paye: int, solde: int}|null
 */
function compte_du_dossier(int $dossier_id): ?array
{
    return $dossier_id > 0 ? (comptes_lignes(0, $dossier_id)[0] ?? null) : null;
}

/**
 * Inscriptions qui comptent dans le montant dû d'un dossier : en cours, hors
 * liste d'attente. « compte » vaut 0 pour les inscriptions au tarif familial
 * déjà comptées par une autre inscription de la famille à la même activité.
 *
 * @return list<array<string, mixed>>
 */
function compte_inscriptions(int $dossier_id): array
{
    $lignes = db_lignes(
        'SELECT i.id, i.activite_id, i.adherent_id, i.nb_places, i.montant, i.est_tarif_familial,
                ac.nom AS activite_nom, a.nom, a.prenom
           FROM inscriptions i
           JOIN activites ac ON ac.id = i.activite_id
           JOIN adherents a ON a.id = i.adherent_id
          WHERE i.dossier_id = ? AND i.est_annulee = 0 AND i.statut = \'inscrit\'
          ORDER BY ac.date_debut, ac.nom, i.montant DESC, i.id',
        [$dossier_id]
    );
    $familial_compte = [];
    foreach ($lignes as $cle => $ligne) {
        $compte = 1;
        if (entier($ligne['est_tarif_familial']) === 1) {
            // Les lignes sont triées par montant décroissant : la première porte le montant retenu.
            $activite_id = entier($ligne['activite_id']);
            $compte = isset($familial_compte[$activite_id]) ? 0 : 1;
            $familial_compte[$activite_id] = true;
        }
        $lignes[$cle]['compte'] = $compte;
    }
    return $lignes;
}

/**
 * Affiche le résumé d'un compte : dû, payé, reste à payer ou trop-perçu.
 *
 * @param array{dossier_id: int, famille_id: int, structure_id: int, nom: string, cotisations: int, activites: int, reductions: int, du: int, paye_adhesion: int, paye_activites: int, paye: int, solde: int} $compte
 * @param bool $detail true : avec le détail du dû et la répartition des paiements
 */
function afficher_resume_compte(array $compte, bool $detail = false): void
{
    ?>
    <table class="table table-sm align-middle mb-2">
        <tbody>
            <?php if ($detail) : ?>
                <tr><th scope="row" class="fw-normal">Cotisations</th><td class="text-end"><?= e(centimes_fr($compte['cotisations'])) ?></td></tr>
                <tr><th scope="row" class="fw-normal">Activités</th><td class="text-end"><?= e(centimes_fr($compte['activites'])) ?></td></tr>
                <?php if ($compte['reductions'] > 0) : ?>
                    <tr><th scope="row" class="fw-normal">Réductions</th><td class="text-end">− <?= e(centimes_fr($compte['reductions'])) ?></td></tr>
                <?php endif; ?>
            <?php endif; ?>
            <tr><th scope="row">Montant dû</th><td class="text-end fw-bold"><?= e(centimes_fr($compte['du'])) ?></td></tr>
            <tr>
                <th scope="row" class="fw-normal">Payé
                    <?php if ($detail && $compte['paye'] > 0) : ?>
                        <span class="small text-body-secondary">(adhésion : <?= e(centimes_fr($compte['paye_adhesion'])) ?> ; activités : <?= e(centimes_fr($compte['paye_activites'])) ?>)</span>
                    <?php endif; ?>
                </th>
                <td class="text-end"><?= e(centimes_fr($compte['paye'])) ?></td>
            </tr>
            <tr>
                <?php if ($compte['solde'] > 0) : ?>
                    <th scope="row">Reste à payer</th>
                    <td class="text-end"><span class="badge text-bg-warning fs-6"><?= e(centimes_fr($compte['solde'])) ?></span></td>
                <?php elseif ($compte['solde'] < 0) : ?>
                    <th scope="row">Trop-perçu</th>
                    <td class="text-end"><span class="badge text-bg-danger fs-6"><?= e(centimes_fr(-$compte['solde'])) ?></span></td>
                <?php else : ?>
                    <th scope="row">Reste à payer</th>
                    <td class="text-end"><span class="badge text-bg-success fs-6"><?= e(centimes_fr(0)) ?></span></td>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>
    <?php
}

// ---------------------------------------------------------------------------
// Cotisations (adhésions)
// ---------------------------------------------------------------------------

/**
 * Tarifs « adhésion » d'une saison, proposés pour les cotisations.
 *
 * @return list<array<string, mixed>>
 */
function tarifs_adhesion(int $saison_id): array
{
    return db_lignes(
        'SELECT id, nom, montant_individuel, montant_familial
           FROM tarifs
          WHERE saison_id = ? AND type = \'adhesion\'
          ORDER BY nom',
        [$saison_id]
    );
}

/**
 * Cotisations d'un dossier (les annulées en dernier), avec le tarif et la personne.
 *
 * @return list<array<string, mixed>>
 */
function cotisations_dossier(int $dossier_id): array
{
    return db_lignes(
        'SELECT c.id, c.adherent_id, c.forme, c.montant, c.date_cotisation, c.est_annulee, c.annulee_le,
                t.nom AS tarif_nom, a.nom, a.prenom, a.est_structure
           FROM cotisations c
           LEFT JOIN tarifs t ON t.id = c.tarif_id
           LEFT JOIN adherents a ON a.id = c.adherent_id
          WHERE c.dossier_id = ?
          ORDER BY c.est_annulee, c.date_cotisation, c.id',
        [$dossier_id]
    );
}

/** Nombre de cotisations individuelles en cours d'un adhérent dans un dossier (elles empêchent de le retirer de la saison). */
function cotisations_actives_nb(int $dossier_id, int $adherent_id): int
{
    return entier(db_valeur(
        'SELECT COUNT(*) FROM cotisations WHERE dossier_id = ? AND adherent_id = ? AND est_annulee = 0',
        [$dossier_id, $adherent_id]
    ));
}

/**
 * Personnes inscrites à une activité qui demande l'adhésion, sans cotisation
 * qui les couvre (ni la leur, ni une cotisation familiale). Simple
 * avertissement : l'accueil reste libre de son choix.
 *
 * @return list<string> noms affichés
 */
function cotisations_manquantes(int $dossier_id): array
{
    $lignes = db_lignes(
        'SELECT DISTINCT a.id, a.nom, a.prenom
           FROM inscriptions i
           JOIN activites ac ON ac.id = i.activite_id
           JOIN adherents a ON a.id = i.adherent_id
          WHERE i.dossier_id = ? AND i.est_annulee = 0 AND i.statut = \'inscrit\' AND ac.est_sans_adhesion = 0
            AND NOT EXISTS (SELECT 1 FROM cotisations c
                             WHERE c.dossier_id = i.dossier_id AND c.est_annulee = 0
                               AND (c.forme = \'familiale\' OR c.adherent_id = i.adherent_id))
          ORDER BY a.nom, a.prenom',
        [$dossier_id]
    );
    return array_map(static fn (array $ligne): string => nom_adherent($ligne), $lignes);
}

/**
 * Enregistre une cotisation.
 *
 * Règles : une seule cotisation familiale en cours par famille ; une seule
 * cotisation individuelle en cours par personne ; pas de cotisation
 * individuelle quand la famille a déjà une cotisation familiale, et
 * inversement (il faut d'abord annuler). Le montant du tarif est copié ; pour
 * une structure, c'est le montant saisi (négocié).
 *
 * @param array<string, mixed> $dossier        ligne lue par dossier_par_id()
 * @param int                  $adherent_id    personne ou structure concernée ; 0 : cotisation familiale
 * @param int                  $tarif_id       tarif « adhésion » choisi (ignoré pour une structure)
 * @param string               $montant_saisi  montant négocié d'une structure, tel que saisi
 * @param string               $date           date de la cotisation (AAAA-MM-JJ)
 * @return string message d'erreur, ou '' si la cotisation est enregistrée
 */
function cotisation_creer(array $dossier, int $adherent_id, int $tarif_id, string $montant_saisi, string $date): string
{
    $dossier_id = entier($dossier['id'] ?? 0);
    $est_structure = entier($dossier['structure_id'] ?? 0) > 0;
    $forme = $adherent_id > 0 ? 'individuelle' : 'familiale';

    if (!date_valide($date) || $date > date('Y-m-d')) {
        return 'La date de la cotisation n\'est pas valide (elle ne peut pas être dans le futur).';
    }
    if ($est_structure) {
        $adherent_id = entier($dossier['structure_id']);
        $forme = 'individuelle';
        $tarif_id = 0;
        $montant = montant_lire($montant_saisi);
        if ($montant === null) {
            return 'Le montant de la cotisation est obligatoire : un nombre positif ou nul, par exemple 50 ou 0.';
        }
    } else {
        $tarif = db_ligne(
            'SELECT id, nom, montant_individuel, montant_familial FROM tarifs WHERE id = ? AND saison_id = ? AND type = \'adhesion\'',
            [$tarif_id, entier($dossier['saison_id'] ?? 0)]
        );
        if ($tarif === null) {
            return 'Choisissez un tarif « adhésion » de la saison.';
        }
        if ($forme === 'familiale' && $tarif['montant_familial'] === null) {
            return 'Le tarif « ' . texte($tarif['nom']) . ' » n\'a pas de montant familial.';
        }
        $montant = texte($forme === 'familiale' ? $tarif['montant_familial'] : $tarif['montant_individuel']);
    }

    db()->beginTransaction();
    try {
        // Verrou sur le dossier : deux envois simultanés ne créent pas deux cotisations.
        db_valeur('SELECT id FROM dossiers WHERE id = ? FOR UPDATE', [$dossier_id]);
        $erreur = '';
        $familiale_en_cours = entier(db_valeur(
            'SELECT COUNT(*) FROM cotisations WHERE dossier_id = ? AND forme = \'familiale\' AND est_annulee = 0',
            [$dossier_id]
        )) > 0;
        if ($forme === 'familiale') {
            if ($familiale_en_cours) {
                $erreur = 'La famille a déjà une cotisation familiale pour cette saison.';
            } elseif (entier(db_valeur('SELECT COUNT(*) FROM cotisations WHERE dossier_id = ? AND est_annulee = 0', [$dossier_id])) > 0) {
                $erreur = 'Des membres de la famille ont déjà une cotisation individuelle : annulez-les d\'abord pour passer à la cotisation familiale.';
            }
        } elseif (entier(db_valeur('SELECT COUNT(*) FROM dossiers_adherents WHERE dossier_id = ? AND adherent_id = ?', [$dossier_id, $adherent_id])) === 0) {
            $erreur = 'Cette personne n\'est pas inscrite pour la saison.';
        } elseif ($familiale_en_cours) {
            $erreur = 'La famille a déjà une cotisation familiale, qui couvre tous ses membres.';
        } elseif (cotisations_actives_nb($dossier_id, $adherent_id) > 0) {
            $erreur = $est_structure
                ? 'Cette structure a déjà une cotisation pour cette saison.'
                : 'Cette personne a déjà une cotisation pour cette saison.';
        }
        if ($erreur !== '') {
            db()->rollBack();
            return $erreur;
        }
        db_executer(
            'INSERT INTO cotisations (dossier_id, adherent_id, tarif_id, forme, montant, date_cotisation, creation_par) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$dossier_id, $adherent_id > 0 ? $adherent_id : null, $tarif_id > 0 ? $tarif_id : null, $forme, $montant, $date, entier($_SESSION['utilisateur_id'] ?? 0)]
        );
        $cotisation_id = entier(db()->lastInsertId());
        journaliser('creation', 'cotisations', $cotisation_id, 'Cotisation ' . $forme . ' enregistrée', null, [
            'dossier_id'      => $dossier_id,
            'adherent_id'     => $adherent_id > 0 ? $adherent_id : null,
            'tarif_id'        => $tarif_id > 0 ? $tarif_id : null,
            'forme'           => $forme,
            'montant'         => $montant,
            'date_cotisation' => $date,
        ]);
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
 * Annule une cotisation d'un dossier (elle reste en base, pour l'historique).
 *
 * @return bool false si elle n'existe pas dans ce dossier ou était déjà annulée
 */
function cotisation_annuler(int $cotisation_id, int $dossier_id): bool
{
    $utilisateur_id = entier($_SESSION['utilisateur_id'] ?? 0);
    $requete = db_executer(
        'UPDATE cotisations SET est_annulee = 1, annulee_le = ?, annulation_par = ?, modification_par = ? WHERE id = ? AND dossier_id = ? AND est_annulee = 0',
        [date('Y-m-d H:i:s'), $utilisateur_id, $utilisateur_id, $cotisation_id, $dossier_id]
    );
    if ($requete->rowCount() === 0) {
        return false;
    }
    journaliser('modification', 'cotisations', $cotisation_id, 'Annulation d\'une cotisation', ['est_annulee' => 0], ['est_annulee' => 1]);
    return true;
}

// ---------------------------------------------------------------------------
// Réductions
// ---------------------------------------------------------------------------

/**
 * Réductions d'un dossier, avec la personne concernée (vide : toute la famille).
 *
 * @return list<array<string, mixed>>
 */
function reductions_dossier(int $dossier_id): array
{
    return db_lignes(
        'SELECT r.id, r.adherent_id, r.montant, r.date_crea, a.nom, a.prenom
           FROM reductions r
           LEFT JOIN adherents a ON a.id = r.adherent_id
          WHERE r.dossier_id = ?
          ORDER BY r.date_crea, r.id',
        [$dossier_id]
    );
}

/**
 * Accorde une réduction. Décision de l'association : ni motif, ni détail dans
 * le journal (seule l'action est notée, sans montant).
 *
 * @param int    $adherent_id   personne concernée ; 0 : toute la famille (ou la structure)
 * @param string $montant_saisi montant tel que saisi
 * @return string message d'erreur, ou '' si la réduction est enregistrée
 */
function reduction_creer(int $dossier_id, int $adherent_id, string $montant_saisi): string
{
    $montant = montant_lire($montant_saisi);
    if ($montant === null || centimes($montant) <= 0) {
        return 'Le montant de la réduction est obligatoire : un nombre supérieur à zéro, par exemple 10 ou 12,50.';
    }
    if ($adherent_id > 0 && entier(db_valeur('SELECT COUNT(*) FROM dossiers_adherents WHERE dossier_id = ? AND adherent_id = ?', [$dossier_id, $adherent_id])) === 0) {
        return 'Cette personne n\'est pas inscrite pour la saison.';
    }
    db_executer(
        'INSERT INTO reductions (dossier_id, adherent_id, montant, creation_par) VALUES (?, ?, ?, ?)',
        [$dossier_id, $adherent_id > 0 ? $adherent_id : null, $montant, entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    journaliser('creation', 'reductions', entier(db()->lastInsertId()), 'Réduction ajoutée');
    return '';
}

/**
 * Supprime une réduction d'un dossier.
 *
 * @return bool false si elle n'existe pas dans ce dossier
 */
function reduction_supprimer(int $reduction_id, int $dossier_id): bool
{
    if (db_executer('DELETE FROM reductions WHERE id = ? AND dossier_id = ?', [$reduction_id, $dossier_id])->rowCount() === 0) {
        return false;
    }
    journaliser('suppression', 'reductions', $reduction_id, 'Réduction supprimée');
    return true;
}

// ---------------------------------------------------------------------------
// Paiements
// ---------------------------------------------------------------------------

/** Morceau de requête commun aux lectures de paiements. */
const PAIEMENTS_SELECT = 'SELECT p.id, p.dossier_id, p.montant, p.date_paiement, p.mode, p.objet, p.note,
                p.est_annule, p.annule_le, p.date_crea,
                d.famille_id, d.structure_id, d.saison_id, s.nom AS saison_nom,
                COALESCE(f.nom, st.nom) AS titulaire_nom, u.prenom AS saisi_par, ua.prenom AS annule_par
           FROM paiements p
           JOIN dossiers d ON d.id = p.dossier_id
           JOIN saisons s ON s.id = d.saison_id
           LEFT JOIN familles f ON f.id = d.famille_id
           LEFT JOIN adherents st ON st.id = d.structure_id
           LEFT JOIN utilisateurs u ON u.id = p.creation_par
           LEFT JOIN utilisateurs ua ON ua.id = p.annulation_par';

/**
 * Un paiement par son identifiant, avec la famille ou la structure et la saison, ou null.
 *
 * @return array<string, mixed>|null
 */
function paiement_par_id(int $id): ?array
{
    return db_ligne(PAIEMENTS_SELECT . ' WHERE p.id = ?', [$id]);
}

/**
 * Paiements d'un dossier, du plus récent au plus ancien (annulés compris).
 *
 * @return list<array<string, mixed>>
 */
function paiements_dossier(int $dossier_id): array
{
    return db_lignes(PAIEMENTS_SELECT . ' WHERE p.dossier_id = ? ORDER BY p.date_paiement DESC, p.id DESC', [$dossier_id]);
}

/**
 * Paiements d'une période (toutes saisons), du plus récent au plus ancien.
 *
 * @param string $mode          '' : tous les modes
 * @param string $objet         '' : tous les objets
 * @param bool   $avec_annules  true : les paiements annulés sont aussi listés
 * @return list<array<string, mixed>>
 */
function paiements_periode(string $date_du, string $date_au, string $mode = '', string $objet = '', bool $avec_annules = false): array
{
    $conditions = ['p.date_paiement BETWEEN ? AND ?'];
    $parametres = [$date_du, $date_au];
    if ($mode !== '') {
        $conditions[] = 'p.mode = ?';
        $parametres[] = $mode;
    }
    if ($objet !== '') {
        $conditions[] = 'p.objet = ?';
        $parametres[] = $objet;
    }
    if (!$avec_annules) {
        $conditions[] = 'p.est_annule = 0';
    }
    return db_lignes(
        PAIEMENTS_SELECT . ' WHERE ' . implode(' AND ', $conditions) . ' ORDER BY p.date_paiement DESC, p.id DESC',
        $parametres
    );
}

/** Lien (paramètre d'adresse) vers le compte auquel appartient un paiement : « famille=12 » ou « structure=34 ». */
function paiement_lien_compte(mixed $famille_id, mixed $structure_id): string
{
    return entier($famille_id) > 0 ? 'famille=' . entier($famille_id) : 'structure=' . entier($structure_id);
}

/**
 * Champs d'un paiement lus dans le formulaire envoyé.
 *
 * @return array{montant: string, date_paiement: string, mode: string, objet: string, note: string}
 */
function paiement_saisie(): array
{
    return [
        'montant'       => post('montant'),
        'date_paiement' => post('date_paiement'),
        'mode'          => post('mode'),
        'objet'         => post('objet'),
        'note'          => post('note'),
    ];
}

/**
 * Vérifie un paiement avant de l'enregistrer.
 *
 * @param array{montant: string, date_paiement: string, mode: string, objet: string, note: string} $saisie
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_paiement(array $saisie): array
{
    $erreurs = [];
    $montant = montant_lire($saisie['montant']);
    if ($montant === null || centimes($montant) <= 0) {
        $erreurs[] = 'Le montant est obligatoire : un nombre supérieur à zéro, par exemple 120 ou 12,50.';
    }
    if (!date_valide($saisie['date_paiement'])) {
        $erreurs[] = 'La date du paiement est obligatoire.';
    } elseif ($saisie['date_paiement'] > date('Y-m-d')) {
        $erreurs[] = 'La date du paiement ne peut pas être dans le futur.';
    }
    if (!array_key_exists($saisie['mode'], libelles_modes_paiement())) {
        $erreurs[] = 'Choisissez le mode de paiement.';
    }
    if (!array_key_exists($saisie['objet'], libelles_objets_paiement())) {
        $erreurs[] = 'Indiquez ce que règle le paiement : l\'adhésion ou les activités.';
    }
    if (mb_strlen($saisie['note']) > 255) {
        $erreurs[] = 'La note est trop longue (255 caractères au maximum).';
    }
    return $erreurs;
}

/**
 * Enregistre un paiement (déjà vérifié) et renvoie son identifiant.
 *
 * @param array{montant: string, date_paiement: string, mode: string, objet: string, note: string} $saisie
 */
function paiement_creer(int $dossier_id, array $saisie): int
{
    $valeurs = [
        'dossier_id'    => $dossier_id,
        'montant'       => montant_lire($saisie['montant']) ?? '0.00',
        'date_paiement' => $saisie['date_paiement'],
        'mode'          => $saisie['mode'],
        'objet'         => $saisie['objet'],
        'note'          => nul_si_vide($saisie['note']),
    ];
    db_executer(
        'INSERT INTO paiements (dossier_id, montant, date_paiement, mode, objet, note, creation_par) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [...array_values($valeurs), entier($_SESSION['utilisateur_id'] ?? 0)]
    );
    $paiement_id = entier(db()->lastInsertId());
    journaliser('creation', 'paiements', $paiement_id, 'Paiement de ' . montant_fr($valeurs['montant']) . ' enregistré', null, $valeurs);
    return $paiement_id;
}

/**
 * Annule un paiement (il reste en base : un paiement ne se supprime jamais).
 *
 * @param array<string, mixed> $paiement ligne lue par paiement_par_id()
 * @return bool false s'il était déjà annulé
 */
function paiement_annuler(array $paiement): bool
{
    $paiement_id = entier($paiement['id'] ?? 0);
    $utilisateur_id = entier($_SESSION['utilisateur_id'] ?? 0);
    $requete = db_executer(
        'UPDATE paiements SET est_annule = 1, annule_le = ?, annulation_par = ?, modification_par = ? WHERE id = ? AND est_annule = 0',
        [date('Y-m-d H:i:s'), $utilisateur_id, $utilisateur_id, $paiement_id]
    );
    if ($requete->rowCount() === 0) {
        return false;
    }
    journaliser(
        'modification',
        'paiements',
        $paiement_id,
        'Annulation du paiement de ' . montant_fr($paiement['montant'] ?? 0),
        ['est_annule' => 0],
        ['est_annule' => 1]
    );
    return true;
}

// ---------------------------------------------------------------------------
// Reçu de paiement (PDF)
// ---------------------------------------------------------------------------

/** Numéro du reçu d'un paiement : son identifiant sur 6 chiffres. */
function recu_numero(int $paiement_id): string
{
    return str_pad((string) $paiement_id, 6, '0', STR_PAD_LEFT);
}

/**
 * Adresses e-mail auxquelles le reçu peut être envoyé : celles des adultes de
 * la famille, des responsables des mineurs inscrits, ou celle de la structure.
 *
 * @param array<string, mixed> $paiement ligne lue par paiement_par_id()
 * @return list<string> adresses sans doublon
 */
function recu_destinataires(array $paiement): array
{
    $candidates = [];
    $famille_id = entier($paiement['famille_id'] ?? 0);
    if ($famille_id > 0) {
        foreach (famille_adultes_joignables($famille_id) as $adulte) {
            $candidates[] = $adulte['mail'];
        }
        foreach (db_lignes('SELECT responsable_mail FROM adherents WHERE famille_id = ? AND anonymise_le IS NULL', [$famille_id]) as $ligne) {
            $candidates[] = texte($ligne['responsable_mail']);
        }
    } else {
        $candidates[] = texte(db_valeur('SELECT mail FROM adherents WHERE id = ?', [entier($paiement['structure_id'] ?? 0)]));
    }
    $adresses = [];
    foreach ($candidates as $adresse) {
        if ($adresse !== '' && !in_array(mb_strtolower($adresse), array_map('mb_strtolower', $adresses), true)) {
            $adresses[] = $adresse;
        }
    }
    return $adresses;
}

/**
 * Contenu (HTML) du reçu d'un paiement, pour document_pdf().
 *
 * @param array<string, mixed> $paiement ligne lue par paiement_par_id()
 */
function recu_html(array $paiement): string
{
    $est_famille = entier($paiement['famille_id'] ?? 0) > 0;
    // Adresse du payeur : celle d'un membre inscrit pour la saison.
    $adresse = db_ligne(
        'SELECT da.adresse, da.complement, da.code_postal, da.ville
           FROM dossiers_adherents da
           JOIN adherents a ON a.id = da.adherent_id
          WHERE da.dossier_id = ?
          ORDER BY a.date_naissance, a.id
          LIMIT 1',
        [entier($paiement['dossier_id'] ?? 0)]
    );
    $lignes_adresse = '';
    if ($adresse !== null) {
        $lignes_adresse = '<br>' . e($adresse['adresse'])
            . (texte($adresse['complement']) !== '' ? '<br>' . e($adresse['complement']) : '')
            . '<br>' . e(texte($adresse['code_postal']) . ' ' . texte($adresse['ville']));
    }
    $ligne = static fn (string $libelle, string $valeur): string => '<tr><th>' . e($libelle) . '</th><td>' . e($valeur) . '</td></tr>';

    return '<h1>Reçu de paiement n° ' . e(recu_numero(entier($paiement['id'] ?? 0))) . '</h1>'
        . '<p class="payeur"><strong>' . e(($est_famille ? 'Famille ' : '') . texte($paiement['titulaire_nom'] ?? '')) . '</strong>' . $lignes_adresse . '</p>'
        . '<table class="detail">'
        . $ligne('Date du paiement', date_fr($paiement['date_paiement'] ?? ''))
        . $ligne('Montant reçu', montant_fr($paiement['montant'] ?? 0))
        . $ligne('Mode de paiement', libelles_modes_paiement()[texte($paiement['mode'] ?? '')] ?? '')
        . $ligne('Objet', (libelles_objets_paiement()[texte($paiement['objet'] ?? '')] ?? '') . ' — saison ' . texte($paiement['saison_nom'] ?? ''))
        . (texte($paiement['note'] ?? '') !== '' ? $ligne('Précision', texte($paiement['note'])) : '')
        . '</table>'
        . '<p class="fait">Reçu établi le ' . e(date('d/m/Y')) . '.</p>';
}

/**
 * Reçu d'un paiement au format PDF.
 *
 * @param array<string, mixed> $paiement ligne lue par paiement_par_id()
 * @return string|null contenu du fichier PDF, ou null si la bibliothèque PDF est absente
 */
function recu_pdf(array $paiement): ?string
{
    return document_pdf('Reçu de paiement n° ' . recu_numero(entier($paiement['id'] ?? 0)), recu_html($paiement));
}

/** Nom du fichier PDF du reçu d'un paiement. */
function recu_nom_fichier(int $paiement_id): string
{
    return 'recu_' . recu_numero($paiement_id) . '.pdf';
}

/**
 * Envoie le reçu d'un paiement par courriel, en pièce jointe.
 *
 * @param array<string, mixed> $paiement ligne lue par paiement_par_id()
 */
function envoyer_recu(string $destinataire, array $paiement): bool
{
    $pdf = recu_pdf($paiement);
    if ($pdf === null) {
        return false;
    }
    $paiement_id = entier($paiement['id'] ?? 0);
    $nom_association = parametre('nom_association', 'l\'association');
    $texte = "Bonjour,\n\n"
        . 'Veuillez trouver ci-joint le reçu de votre paiement de ' . montant_fr($paiement['montant'] ?? 0)
        . ' du ' . date_fr($paiement['date_paiement'] ?? '') . ".\n\n"
        . "À bientôt,\n$nom_association";
    return envoyer_courriel(
        $destinataire,
        'Reçu de paiement n° ' . recu_numero($paiement_id) . ' — ' . $nom_association,
        $texte,
        [['nom' => recu_nom_fichier($paiement_id), 'contenu' => $pdf, 'type' => 'application/pdf']]
    );
}
