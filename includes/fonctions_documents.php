<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Documents PDF (cahier des charges, section 8), produits avec Dompdf.
 * Tous portent le logo, le nom et l'adresse de l'association.
 *
 * - reçu de paiement : recu_pdf() (fonctions_paiements.php) ;
 * - documents d'une activité : liste des participants, liste de sortie,
 *   feuille d'émargement (activite_document_pdf()) ;
 * - attestation de participation (attestation_pdf()) ;
 * - feuille d'émargement de l'assemblée générale (emargement_ag_pdf()).
 *
 * Aucun de ces documents ne porte de montant, sauf le reçu.
 */

/**
 * Logo de l'association sous forme d'image intégrée au document (« data: »),
 * ou '' s'il est absent ou illisible. Dompdf a besoin de l'extension GD pour
 * les images PNG.
 */
function document_logo(): string
{
    $logo = logo_association();
    if ($logo === '') {
        return '';
    }
    $fichier = dossier_public() . '/' . $logo;
    $infos = getimagesize($fichier);
    $type = $infos === false ? 0 : $infos[2];
    if ($type !== IMAGETYPE_JPEG && !($type === IMAGETYPE_PNG && extension_loaded('gd'))) {
        return '';
    }
    $contenu = file_get_contents($fichier);
    return $contenu === false
        ? ''
        : 'data:' . ($type === IMAGETYPE_JPEG ? 'image/jpeg' : 'image/png') . ';base64,' . base64_encode($contenu);
}

/**
 * Fabrique un document PDF (A4) avec l'en-tête de l'association et, en bas
 * de chaque page, le titre du document et le numéro de page.
 *
 * @param string $titre titre du document (propriétés du fichier PDF, pied de page)
 * @param string $corps contenu HTML, déjà échappé avec e()
 * @param string $orientation 'portrait' ou 'landscape' (paysage)
 * @return string|null contenu du fichier PDF, ou null si Dompdf est absent (dossier vendor/)
 */
function document_pdf(string $titre, string $corps, string $orientation = 'portrait'): ?string
{
    if (!class_exists(Dompdf::class)) {
        journal_fichier('php_erreurs.log', 'Dompdf est absent (dossier vendor/ manquant : lancer « composer install --no-dev »).');
        return null;
    }
    $logo = document_logo();
    $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . e($titre) . '</title><style>
        @page { margin: 18mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #212529; }
        .pied { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #6c757d; }
        .entete { border-bottom: 1px solid #adb5bd; padding-bottom: 4mm; margin-bottom: 10mm; }
        .entete td { vertical-align: middle; }
        .entete img { max-height: 22mm; max-width: 45mm; }
        .entete .association { text-align: right; font-size: 10pt; }
        .entete .association strong { font-size: 13pt; }
        h1 { font-size: 16pt; margin: 0 0 4mm 0; }
        h2 { font-size: 12pt; margin: 8mm 0 3mm 0; }
        table { border-collapse: collapse; width: 100%; }
        .payeur { margin: 0 0 8mm 55%; }
        .detail th, .detail td { border: 1px solid #adb5bd; padding: 2.5mm 3mm; text-align: left; }
        .detail th { width: 38%; background: #f1f3f5; font-weight: normal; }
        .fait { margin-top: 10mm; }
        .infos { margin: 0 0 6mm 0; font-size: 10pt; color: #495057; }
        .liste { font-size: 10pt; }
        .liste thead { display: table-header-group; }
        .liste tr { page-break-inside: avoid; }
        .liste th, .liste td { border: 1px solid #adb5bd; padding: 1.8mm 2.5mm; text-align: left; vertical-align: top; }
        .liste th { background: #f1f3f5; font-weight: bold; }
        .liste .numero { width: 8mm; text-align: right; color: #6c757d; }
        .liste .centre { text-align: center; }
        .liste .petit { font-size: 8.5pt; color: #6c757d; }
        .liste.emargement td { height: 11mm; }
        .liste .signature { width: 45%; }
        .case { display: inline-block; width: 4mm; height: 4mm; border: 1px solid #495057; }
        .bloc { page-break-inside: avoid; }
        .total { margin: 0 0 2mm 0; font-size: 10pt; color: #495057; }
        .vide { color: #6c757d; font-style: italic; }
        .texte { line-height: 1.5; }
        .signataire { margin: 12mm 0 0 55%; }
        .signataire .ligne { margin-top: 22mm; border-top: 1px solid #adb5bd; padding-top: 1mm; font-size: 9pt; color: #6c757d; }
    </style></head><body>'
        . '<div class="pied">' . e($titre) . '</div>'
        . '<table class="entete"><tr>'
        . '<td>' . ($logo !== '' ? '<img src="' . $logo . '" alt="">' : '') . '</td>'
        . '<td class="association"><strong>' . e(parametre('nom_association', 'Association')) . '</strong><br>' . e(parametre('adresse_association')) . '</td>'
        . '</tr></table>'
        . $corps
        . '</body></html>';

    $options = new Options();
    $options->setIsRemoteEnabled(false);
    $options->setDefaultFont('DejaVu Sans');
    // Fichiers de travail de Dompdf : hors du projet (vendor/ n'est pas toujours accessible en écriture).
    $options->setTempDir(sys_get_temp_dir());
    $options->setFontCache(sys_get_temp_dir());
    $dompdf = new Dompdf($options);
    $dompdf->setPaper('A4', $orientation === 'landscape' ? 'landscape' : 'portrait');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    // Numéro de page en bas à droite (le nombre total de pages n'est connu qu'après la mise en page).
    $canvas = $dompdf->getCanvas();
    $police = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
    if ($police !== null) {
        $largeur = $dompdf->getFontMetrics()->getTextWidth('Page 0 / 0', $police, 8);
        $canvas->page_text($canvas->get_width() - 18 * 72 / 25.4 - $largeur, $canvas->get_height() - 33, 'Page {PAGE_NUM} / {PAGE_COUNT}', $police, 8, [0.42, 0.46, 0.49]);
    }
    return $dompdf->output();
}

/**
 * Envoie un document PDF au navigateur (affiché dans un onglet, pour
 * l'imprimer ou l'enregistrer) et termine la page.
 */
function document_envoyer(string $pdf, string $nom_fichier): never
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $nom_fichier . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

/**
 * Nom de fichier d'un document : minuscules, sans accents ni espaces
 * (« Liste des participants », « Yoga d'été » → liste_des_participants_yoga_d_ete.pdf).
 */
function document_nom_fichier(string ...$parties): string
{
    $nom = str_replace(' ', '_', texte_simplifie(implode(' ', $parties)));
    return mb_substr($nom !== '' ? $nom : 'document', 0, 80) . '.pdf';
}

/**
 * Nom affiché dans une liste imprimée : « NOM Prénom », ou le nom seul pour une structure.
 *
 * @param array<string, mixed> $adherent
 */
function document_nom_liste(array $adherent): string
{
    return trim(texte($adherent['nom'] ?? '') . ' ' . texte($adherent['prenom'] ?? ''));
}

// ---------------------------------------------------------------------------
// Documents d'une activité
// ---------------------------------------------------------------------------

/**
 * Documents qu'on peut produire pour une activité : modèle => titre.
 *
 * @return array<string, string>
 */
function activite_documents_modeles(): array
{
    return [
        'liste'      => 'Liste des participants',
        'sortie'     => 'Liste de sortie',
        'emargement' => 'Feuille d\'émargement',
    ];
}

/**
 * Inscriptions d'une activité à faire figurer sur un document, en trois
 * parties : les inscrits (toujours), la liste d'attente et les inscriptions
 * inactives (si demandées). Les annulées n'y figurent jamais. Inscrits et
 * inactifs sont classés par nom ; la liste d'attente garde l'ordre d'entrée.
 *
 * @return array{inscrit: list<array<string, mixed>>, attente: list<array<string, mixed>>, inactif: list<array<string, mixed>>}
 */
function activite_documents_lignes(int $activite_id, bool $avec_attente, bool $avec_inactifs): array
{
    $parties = ['inscrit' => [], 'attente' => [], 'inactif' => []];
    foreach (inscriptions_activite($activite_id) as $ligne) {
        $statut = texte($ligne['statut']);
        if (entier($ligne['est_annulee']) === 1
            || ($statut === 'attente' && !$avec_attente)
            || ($statut === 'inactif' && !$avec_inactifs)
            || !isset($parties[$statut])) {
            continue;
        }
        $parties[$statut][] = $ligne;
    }
    $par_nom = static fn (array $a, array $b): int => strcmp(texte_simplifie(document_nom_liste($a)), texte_simplifie(document_nom_liste($b)));
    usort($parties['inscrit'], $par_nom);
    usort($parties['inactif'], $par_nom);
    return $parties;
}

/**
 * Tableau HTML d'une partie (inscrits, liste d'attente ou inactives) pour
 * l'un des modèles de document d'une activité.
 *
 * @param list<array<string, mixed>> $lignes
 */
function activite_document_tableau(string $modele, array $lignes, string $partie): string
{
    if ($lignes === []) {
        return '<p class="vide">' . ($partie === 'inscrit' ? 'Aucun inscrit.' : 'Personne.') . '</p>';
    }
    $avec_places = array_filter($lignes, static fn (array $l): bool => entier($l['nb_places']) > 1) !== [];

    $entetes = ['<th class="numero">N°</th>', '<th>Nom</th>'];
    if ($modele !== 'emargement') {
        $entetes[] = '<th>Âge</th>';
    }
    if ($avec_places) {
        $entetes[] = '<th class="centre">Places</th>';
    }
    if ($modele === 'sortie') {
        $entetes[] = '<th>Téléphone</th>';
        $entetes[] = '<th>Responsable (mineur)</th>';
        $entetes[] = '<th class="centre">Présent</th>';
    }
    if ($modele === 'emargement') {
        $entetes[] = '<th class="signature">Signature</th>';
    }

    $corps = '';
    foreach ($lignes as $numero => $ligne) {
        $est_structure = entier($ligne['est_structure']) === 1;
        $naissance = texte($ligne['date_naissance']);
        $mineur = !$est_structure && est_mineur($naissance);
        $precision = match ($partie) {
            'attente' => 'en attente depuis le ' . date_fr($ligne['attente_le'] ?? $ligne['date_crea']),
            'inactif' => 'inactive depuis le ' . date_fr($ligne['inactive_le']),
            default   => '',
        };
        $cellules = [
            '<td class="numero">' . ($numero + 1) . '</td>',
            '<td>' . e(document_nom_liste($ligne))
                . ($est_structure ? ' <span class="petit">(structure)</span>' : '')
                . ($precision !== '' ? '<br><span class="petit">' . e($precision) . '</span>' : '') . '</td>',
        ];
        if ($modele !== 'emargement') {
            $cellules[] = '<td>' . ($est_structure ? '—' : age_ans($naissance) . ' ans' . ($mineur ? ' <span class="petit">(mineur)</span>' : '')) . '</td>';
        }
        if ($avec_places) {
            $cellules[] = '<td class="centre">' . entier($ligne['nb_places']) . '</td>';
        }
        if ($modele === 'sortie') {
            $cellules[] = '<td>' . e($ligne['tel']) . '</td>';
            $cellules[] = '<td>' . ($mineur ? e(trim(texte($ligne['responsable_nom']) . ' ' . texte($ligne['responsable_tel']))) : '') . '</td>';
            $cellules[] = '<td class="centre"><span class="case"></span></td>';
        }
        if ($modele === 'emargement') {
            $cellules[] = '<td></td>';
        }
        $corps .= '<tr>' . implode('', $cellules) . '</tr>';
    }

    $places = array_sum(array_map(static fn (array $l): int => entier($l['nb_places']), $lignes));
    $total = count($lignes) . ' ' . (count($lignes) > 1 ? 'personnes ou structures' : 'personne ou structure')
        . ($avec_places ? ', ' . $places . ' places' : '');

    return '<p class="total">' . e($total) . '</p>'
        . '<table class="liste' . ($modele === 'emargement' ? ' emargement' : '') . '"><thead><tr>' . implode('', $entetes) . '</tr></thead>'
        . '<tbody>' . $corps . '</tbody></table>';
}

/**
 * Contenu (HTML) d'un document d'une activité, pour document_pdf().
 *
 * @param array<string, mixed> $activite ligne lue par activite_par_id()
 * @param array{inscrit: list<array<string, mixed>>, attente: list<array<string, mixed>>, inactif: list<array<string, mixed>>} $parties
 * @param bool $avec_attente la liste d'attente a été demandée
 * @param bool $avec_inactifs les inscriptions inactives ont été demandées
 */
function activite_document_html(array $activite, string $modele, array $parties, bool $avec_attente, bool $avec_inactifs): string
{
    $titre = activite_documents_modeles()[$modele] ?? '';
    $infos = [
        'Saison ' . texte($activite['saison_nom']) . ' — ' . activite_dates($activite),
        'Référent : ' . texte($activite['referent']),
    ];
    if (texte($activite['partenaire'] ?? '') !== '') {
        $infos[] = 'Partenaire : ' . texte($activite['partenaire']);
    }
    if ($modele === 'sortie' && entier($activite['nb_accompagnateurs'] ?? 0) > 0) {
        $infos[] = 'Accompagnateurs prévus : ' . entier($activite['nb_accompagnateurs']);
    }

    $html = '<h1>' . e($titre) . ' — ' . e($activite['nom']) . '</h1>'
        . '<p class="infos">' . implode('<br>', array_map('e', $infos))
        . ($modele !== 'liste' ? '<br>Date : ______________________' : '') . '</p>';

    // Chaque partie a son titre quand il y en a plusieurs. Une partie courte reste
    // d'un seul tenant : son titre ne reste pas seul en bas d'une page.
    $sections = ['inscrit' => 'Inscrits'];
    if ($avec_attente) {
        $sections['attente'] = 'Liste d\'attente';
    }
    if ($avec_inactifs) {
        $sections['inactif'] = 'Inscriptions inactives';
    }
    foreach ($sections as $partie => $titre_partie) {
        $contenu = (count($sections) > 1 ? '<h2>' . e($titre_partie) . '</h2>' : '')
            . activite_document_tableau($modele, $parties[$partie], $partie);
        $html .= count($parties[$partie]) <= 12 ? '<div class="bloc">' . $contenu . '</div>' : $contenu;
    }
    return $html;
}

/**
 * Document PDF d'une activité (liste des participants, liste de sortie ou
 * feuille d'émargement). La liste de sortie est en paysage (plus de colonnes).
 *
 * @param array<string, mixed> $activite ligne lue par activite_par_id()
 * @return string|null contenu du fichier PDF, ou null si la bibliothèque PDF est absente
 */
function activite_document_pdf(array $activite, string $modele, bool $avec_attente, bool $avec_inactifs): ?string
{
    $parties = activite_documents_lignes(entier($activite['id']), $avec_attente, $avec_inactifs);
    return document_pdf(
        (activite_documents_modeles()[$modele] ?? '') . ' — ' . texte($activite['nom']),
        activite_document_html($activite, $modele, $parties, $avec_attente, $avec_inactifs),
        $modele === 'sortie' ? 'landscape' : 'portrait'
    );
}

// ---------------------------------------------------------------------------
// Attestation de participation
// ---------------------------------------------------------------------------

/**
 * Inscriptions d'un adhérent qui peuvent figurer sur une attestation pour une
 * saison : en cours ou inactives (la personne a participé), hors liste
 * d'attente et hors annulations, par date de début.
 *
 * @return list<array<string, mixed>>
 */
function attestation_inscriptions(int $adherent_id, int $saison_id): array
{
    return array_values(array_filter(
        inscriptions_adherent($adherent_id, $saison_id),
        static fn (array $i): bool => entier($i['est_annulee']) === 0 && in_array(texte($i['statut']), ['inscrit', 'inactif'], true)
    ));
}

/**
 * Contenu (HTML) d'une attestation de participation, pour document_pdf() :
 * pour une activité, ou pour toutes les activités de la saison.
 *
 * @param array<string, mixed> $adherent
 * @param array<string, mixed> $saison
 * @param list<array<string, mixed>> $inscriptions celles qui figurent sur l'attestation (une seule pour une activité)
 * @param bool $toute_la_saison attestation pour toutes les activités de la saison
 */
function attestation_html(array $adherent, array $saison, array $inscriptions, bool $toute_la_saison): string
{
    $est_structure = entier($adherent['est_structure'] ?? 0) === 1;
    $personne = '<strong>' . e(nom_adherent($adherent)) . '</strong>';
    if (!$est_structure) {
        $ne = match (texte($adherent['sexe'] ?? '')) {
            'F'     => 'née',
            'H'     => 'né',
            default => 'né(e)',
        };
        $personne .= ', ' . $ne . ' le ' . e(date_fr($adherent['date_naissance'] ?? '')) . ',';
    }
    $dates = static function (array $inscription): string {
        $texte = activite_dates($inscription);
        if (texte($inscription['statut']) === 'inactif') {
            $texte .= ' (jusqu\'au ' . date_fr($inscription['inactive_le']) . ')';
        }
        return $texte;
    };

    $html = '<h1>Attestation de participation</h1>'
        . '<p class="texte">' . e(parametre('nom_association', 'L\'association')) . ' atteste la participation de ' . $personne;
    if (!$toute_la_saison && count($inscriptions) === 1) {
        $inscription = $inscriptions[0];
        $html .= ' à l\'activité « ' . e($inscription['activite_nom']) . ' », ' . e($dates($inscription))
            . ', au cours de la saison ' . e($saison['nom']) . '.</p>';
    } else {
        $html .= ' aux activités suivantes, au cours de la saison ' . e($saison['nom']) . ' :</p>'
            . '<table class="liste"><thead><tr><th>Activité</th><th>Dates</th></tr></thead><tbody>';
        foreach ($inscriptions as $inscription) {
            $html .= '<tr><td>' . e($inscription['activite_nom']) . '</td><td>' . e($dates($inscription)) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    return $html
        . '<p class="fait">Fait le ' . e(date('d/m/Y')) . '.</p>'
        . '<div class="signataire"><div class="ligne">Signature</div></div>';
}

/**
 * Attestation de participation au format PDF.
 *
 * @param array<string, mixed> $adherent
 * @param array<string, mixed> $saison
 * @param list<array<string, mixed>> $inscriptions
 * @return string|null contenu du fichier PDF, ou null si la bibliothèque PDF est absente
 */
function attestation_pdf(array $adherent, array $saison, array $inscriptions, bool $toute_la_saison): ?string
{
    return document_pdf(
        'Attestation de participation — ' . nom_adherent($adherent),
        attestation_html($adherent, $saison, $inscriptions, $toute_la_saison)
    );
}

// ---------------------------------------------------------------------------
// Feuille d'émargement de l'assemblée générale
// ---------------------------------------------------------------------------

/**
 * Adhérents qui signent la feuille d'émargement de l'assemblée générale :
 * les personnes majeures (à la date du jour) et les structures inscrites pour
 * la saison, hors fiches anonymisées, classées par nom.
 *
 * @return list<array<string, mixed>>
 */
function emargement_ag_lignes(int $saison_id): array
{
    $lignes = db_lignes(
        'SELECT DISTINCT a.id, a.nom, a.prenom, a.est_structure, a.date_naissance
           FROM dossiers_adherents da
           JOIN dossiers d ON d.id = da.dossier_id
           JOIN adherents a ON a.id = da.adherent_id
          WHERE d.saison_id = ? AND a.anonymise_le IS NULL',
        [$saison_id]
    );
    $lignes = array_values(array_filter(
        $lignes,
        static fn (array $l): bool => entier($l['est_structure']) === 1 || !est_mineur(texte($l['date_naissance']))
    ));
    usort($lignes, static fn (array $a, array $b): int => strcmp(texte_simplifie(document_nom_liste($a)), texte_simplifie(document_nom_liste($b))));
    return $lignes;
}

/**
 * Contenu (HTML) de la feuille d'émargement de l'assemblée générale.
 *
 * @param array<string, mixed> $saison
 * @param list<array<string, mixed>> $lignes lues par emargement_ag_lignes()
 */
function emargement_ag_html(array $saison, array $lignes): string
{
    $html = '<h1>Feuille d\'émargement — assemblée générale</h1>'
        . '<p class="infos">Adhérents majeurs et structures inscrits pour la saison ' . e($saison['nom'])
        . '<br>Date : ______________________</p>';
    if ($lignes === []) {
        return $html . '<p class="vide">Aucun adhérent majeur inscrit pour cette saison.</p>';
    }
    $html .= '<p class="total">' . count($lignes) . ' adhérent' . (count($lignes) > 1 ? 's' : '') . '</p>'
        . '<table class="liste emargement"><thead><tr><th class="numero">N°</th><th>Nom</th><th class="signature">Signature</th></tr></thead><tbody>';
    foreach ($lignes as $numero => $ligne) {
        $html .= '<tr><td class="numero">' . ($numero + 1) . '</td><td>' . e(document_nom_liste($ligne))
            . (entier($ligne['est_structure']) === 1 ? '<br><span class="petit">structure, représentée par :</span>' : '')
            . '</td><td></td></tr>';
    }
    return $html . '</tbody></table>';
}

/**
 * Feuille d'émargement de l'assemblée générale au format PDF.
 *
 * @param array<string, mixed> $saison
 * @return string|null contenu du fichier PDF, ou null si la bibliothèque PDF est absente
 */
function emargement_ag_pdf(array $saison): ?string
{
    return document_pdf(
        'Émargement de l\'assemblée générale — saison ' . texte($saison['nom']),
        emargement_ag_html($saison, emargement_ag_lignes(entier($saison['id'])))
    );
}
