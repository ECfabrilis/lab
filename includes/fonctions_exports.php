<?php

declare(strict_types=1);

/**
 * Exports (cahier des charges, section 9) : fichiers CSV téléchargés.
 *
 * - liste des adhérents, avec les filtres de la page « Adhérents », pour un
 *   tableur (Excel, LibreOffice) : point-virgule et UTF-8 avec BOM ;
 * - Google Contacts : adhérents de la saison, ou participants d'une activité,
 *   ayant consenti aux communications, majeurs, avec un courriel ou un
 *   téléphone ; virgule et UTF-8 sans BOM (format d'import de Google).
 *
 * L'export comptable attend le fichier exemple de la comptabilité.
 * Chaque export est noté dans le journal (type d'export et nombre de lignes,
 * sans les données).
 */

/**
 * Neutralise une valeur qu'un tableur prendrait pour une formule (=, +, -, @
 * en tête). Un numéro de téléphone (« +33 6… », « -12 ») est laissé tel quel.
 */
function csv_valeur_sure(string $valeur): string
{
    if ($valeur === '') {
        return '';
    }
    $premier = $valeur[0];
    $formule = in_array($premier, ['=', '@', "\t", "\r"], true)
        || (in_array($premier, ['+', '-'], true) && preg_match('/^[+\-][\d\s.()]*$/', $valeur) !== 1);
    return $formule ? '\'' . $valeur : $valeur;
}

/**
 * Envoie un fichier CSV en téléchargement et termine la page.
 *
 * @param list<string>       $entetes
 * @param list<list<string>> $lignes
 * @param bool $pour_tableur true : point-virgule et BOM (Excel en français) ; false : virgule, sans BOM (Google Contacts)
 */
function csv_envoyer(string $nom_fichier, array $entetes, array $lignes, bool $pour_tableur): never
{
    $sortie = fopen('php://temp', 'w+');
    if ($sortie === false) {
        throw new RuntimeException('Impossible de préparer le fichier CSV.');
    }
    $separateur = $pour_tableur ? ';' : ',';
    fputcsv($sortie, $entetes, $separateur, '"', '', "\r\n");
    foreach ($lignes as $ligne) {
        fputcsv($sortie, $pour_tableur ? array_map('csv_valeur_sure', $ligne) : $ligne, $separateur, '"', '', "\r\n");
    }
    rewind($sortie);
    $contenu = ($pour_tableur ? "\u{FEFF}" : '') . stream_get_contents($sortie);
    fclose($sortie);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nom_fichier . '"');
    header('Content-Length: ' . strlen($contenu));
    header('Cache-Control: no-store');
    echo $contenu;
    exit;
}

/** Nom d'un fichier CSV : minuscules, sans accents ni espaces, avec la date du jour. */
function csv_nom_fichier(string ...$parties): string
{
    $nom = str_replace(' ', '_', texte_simplifie(implode(' ', $parties)));
    return mb_substr($nom !== '' ? $nom : 'export', 0, 80) . '_' . date('Y-m-d') . '.csv';
}

// ---------------------------------------------------------------------------
// Liste des adhérents (tableur)
// ---------------------------------------------------------------------------

/**
 * En-têtes de l'export de la liste des adhérents.
 *
 * @return list<string>
 */
function export_adherents_entetes(): array
{
    return [
        'Nom', 'Prénom', 'Structure', 'Sexe', 'Date de naissance', 'Âge', 'Bénévole', 'Famille', 'Inscrit pour la saison',
        'Adresse', 'Complément', 'Code postal', 'Ville', 'Quartier', 'QP', 'QPV',
        'Courriel', 'Téléphone', 'Responsable', 'Courriel du responsable', 'Téléphone du responsable',
        'Communications acceptées le',
    ];
}

/**
 * Lignes de l'export de la liste des adhérents : toute la liste qui répond aux
 * critères de la page « Adhérents » (recherche, périmètre, lettre, bénévoles,
 * quartier, QP, QPV), sans découpage en pages. L'adresse est celle de la saison.
 *
 * @return list<list<string>>
 */
function export_adherents_lignes(int $saison_id): array
{
    $criteres = adherents_criteres($saison_id);
    [$base, $parametres] = adherents_requete($criteres['texte'], $saison_id, $criteres['saison_seule'], $criteres['filtres']);
    $adherents = db_lignes(
        'SELECT a.est_structure, a.nom, a.prenom, a.sexe, a.date_naissance, a.est_benevole, f.nom AS famille_nom,
                (da.adherent_id IS NOT NULL) AS dans_saison, da.adresse, da.complement, da.code_postal, da.ville,
                da.quartier, da.est_qp, da.qp_nom, da.est_qpv, da.qpv_nom,
                a.mail, a.tel, a.responsable_nom, a.responsable_mail, a.responsable_tel, a.a_consenti, a.consentement_le'
        . $base . ' ORDER BY a.nom, a.prenom, a.id',
        $parametres
    );
    $sexes = libelles_sexes();
    $oui = static fn (mixed $valeur): string => entier($valeur) === 1 ? 'oui' : 'non';
    $lignes = [];
    foreach ($adherents as $a) {
        $est_structure = entier($a['est_structure']) === 1;
        $naissance = texte($a['date_naissance']);
        $lignes[] = [
            texte($a['nom']),
            texte($a['prenom']),
            $oui($a['est_structure']),
            $sexes[texte($a['sexe'])] ?? '',
            date_fr($naissance),
            $est_structure || $naissance === '' ? '' : (string) age_ans($naissance),
            $est_structure ? '' : $oui($a['est_benevole']),
            texte($a['famille_nom']),
            $oui($a['dans_saison']),
            texte($a['adresse']),
            texte($a['complement']),
            texte($a['code_postal']),
            texte($a['ville']),
            texte($a['quartier']),
            entier($a['est_qp']) === 1 ? texte($a['qp_nom']) ?: 'oui' : '',
            entier($a['est_qpv']) === 1 ? texte($a['qpv_nom']) ?: 'oui' : '',
            texte($a['mail']),
            texte($a['tel']),
            texte($a['responsable_nom']),
            texte($a['responsable_mail']),
            texte($a['responsable_tel']),
            entier($a['a_consenti']) === 1 ? date_fr($a['consentement_le']) : '',
        ];
    }
    return $lignes;
}

// ---------------------------------------------------------------------------
// Google Contacts
// ---------------------------------------------------------------------------

/**
 * En-têtes du fichier d'import de Google Contacts (noms de colonnes reconnus
 * par Google ; « Labels » contient les libellés séparés par « ::: »).
 *
 * @return list<string>
 */
function contacts_entetes(): array
{
    return ['First Name', 'Last Name', 'Organization Name', 'Labels', 'E-mail 1 - Label', 'E-mail 1 - Value', 'Phone 1 - Label', 'Phone 1 - Value'];
}

/**
 * Adhérents qui peuvent être exportés vers Google Contacts : consentement
 * donné, fiche non anonymisée, majeur (à la date du jour) ou structure, avec
 * un courriel ou un téléphone.
 *
 * @param list<array<string, mixed>> $adherents lignes avec est_structure, date_naissance, a_consenti, anonymise_le, mail, tel
 * @return list<array<string, mixed>>
 */
function contacts_filtrer(array $adherents): array
{
    return array_values(array_filter(
        $adherents,
        static fn (array $a): bool => entier($a['a_consenti']) === 1
            && texte($a['anonymise_le']) === ''
            && (entier($a['est_structure']) === 1 || !est_mineur(texte($a['date_naissance'])))
            && (texte($a['mail']) !== '' || texte($a['tel']) !== '')
    ));
}

/**
 * Lignes du fichier Google Contacts.
 *
 * @param list<array<string, mixed>> $adherents déjà filtrés par contacts_filtrer()
 * @param list<string> $libelles libellés posés sur chaque contact
 * @param bool $libelle_benevoles ajouter le libellé « Bénévoles » aux bénévoles
 * @return list<list<string>>
 */
function contacts_lignes(array $adherents, array $libelles, bool $libelle_benevoles): array
{
    $lignes = [];
    foreach ($adherents as $a) {
        $est_structure = entier($a['est_structure']) === 1;
        $libelles_contact = $libelles;
        if ($libelle_benevoles && !$est_structure && entier($a['est_benevole']) === 1) {
            $libelles_contact[] = 'Bénévoles';
        }
        $mail = texte($a['mail']);
        $tel = texte($a['tel']);
        $lignes[] = [
            $est_structure ? '' : texte($a['prenom']),
            $est_structure ? '' : texte($a['nom']),
            $est_structure ? texte($a['nom']) : '',
            implode(' ::: ', $libelles_contact),
            $mail !== '' ? 'Other' : '',
            $mail,
            $tel !== '' ? 'Mobile' : '',
            $tel,
        ];
    }
    return $lignes;
}

/** Colonnes d'un adhérent lues pour Google Contacts. */
const CONTACTS_COLONNES = 'a.id, a.est_structure, a.nom, a.prenom, a.date_naissance, a.est_benevole, a.a_consenti, a.anonymise_le, a.mail, a.tel';

/**
 * Adhérents de la saison exportables vers Google Contacts, par nom.
 *
 * @return list<array<string, mixed>>
 */
function contacts_saison(int $saison_id): array
{
    return contacts_filtrer(db_lignes(
        'SELECT DISTINCT ' . CONTACTS_COLONNES . '
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN adherents a ON a.id = da.adherent_id
          WHERE d.saison_id = ? AND a.a_consenti = 1
          ORDER BY a.nom, a.prenom, a.id',
        [$saison_id]
    ));
}

/**
 * Participants d'une activité exportables vers Google Contacts, par nom : les
 * inscrits, et sur demande la liste d'attente et les inscriptions inactives
 * (jamais les annulées). Une structure inscrite plusieurs fois n'est exportée qu'une fois.
 *
 * @return list<array<string, mixed>>
 */
function contacts_activite(int $activite_id, bool $avec_attente, bool $avec_inactifs): array
{
    $statuts = ['inscrit'];
    if ($avec_attente) {
        $statuts[] = 'attente';
    }
    if ($avec_inactifs) {
        $statuts[] = 'inactif';
    }
    return contacts_filtrer(db_lignes(
        'SELECT DISTINCT ' . CONTACTS_COLONNES . '
           FROM inscriptions i
           JOIN adherents a ON a.id = i.adherent_id
          WHERE i.activite_id = ? AND i.est_annulee = 0 AND a.a_consenti = 1
            AND i.statut IN (' . implode(', ', array_fill(0, count($statuts), '?')) . ')
          ORDER BY a.nom, a.prenom, a.id',
        [$activite_id, ...$statuts]
    ));
}
