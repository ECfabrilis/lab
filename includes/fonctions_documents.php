<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Documents PDF (cahier des charges, section 8), produits avec Dompdf.
 * Tous portent le logo, le nom et l'adresse de l'association.
 */

/**
 * Logo de l'association sous forme d'image intégrée au document (« data: »),
 * ou '' s'il est absent ou illisible. Dompdf a besoin de l'extension GD pour
 * les images PNG.
 */
function document_logo(): string
{
    $fichier = dossier_public() . '/' . parametre('logo', 'img/logo-cefia75.png');
    if (!is_file($fichier)) {
        return '';
    }
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
 * Fabrique un document PDF (A4, portrait) avec l'en-tête de l'association.
 *
 * @param string $titre titre du document (propriétés du fichier PDF)
 * @param string $corps contenu HTML, déjà échappé avec e()
 * @return string|null contenu du fichier PDF, ou null si Dompdf est absent (dossier vendor/)
 */
function document_pdf(string $titre, string $corps): ?string
{
    if (!class_exists(Dompdf::class)) {
        journal_fichier('php_erreurs.log', 'Dompdf est absent (dossier vendor/ manquant : lancer « composer install --no-dev »).');
        return null;
    }
    $logo = document_logo();
    $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . e($titre) . '</title><style>
        @page { margin: 18mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #212529; }
        .entete { border-bottom: 1px solid #adb5bd; padding-bottom: 4mm; margin-bottom: 10mm; }
        .entete td { vertical-align: middle; }
        .entete img { max-height: 22mm; max-width: 45mm; }
        .entete .association { text-align: right; font-size: 10pt; }
        .entete .association strong { font-size: 13pt; }
        h1 { font-size: 16pt; margin: 0 0 8mm 0; }
        table { border-collapse: collapse; width: 100%; }
        .payeur { margin: 0 0 8mm 55%; }
        .detail th, .detail td { border: 1px solid #adb5bd; padding: 2.5mm 3mm; text-align: left; }
        .detail th { width: 38%; background: #f1f3f5; font-weight: normal; }
        .fait { margin-top: 10mm; }
    </style></head><body>'
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
    $dompdf->setPaper('A4');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    return $dompdf->output();
}
