<?php

declare(strict_types=1);

/**
 * Couleur principale de l'application (Paramètres > Options > Couleur).
 *
 * La politique de sécurité du site interdit les styles écrits dans les pages :
 * la couleur choisie est donc traduite en une petite feuille de style,
 * css/couleur.css, que l'application génère à chaque changement.
 */

/** Couleur principale quand aucune n'a été choisie (le bleu de Bootstrap). */
const COULEUR_DEFAUT = '#0d6efd';

/** Vrai si le texte est une couleur au format #rrggbb. */
function couleur_valide(string $couleur): bool
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) === 1;
}

/**
 * Composantes rouge, vert, bleu (0 à 255) d'une couleur #rrggbb.
 *
 * @return array{int, int, int}
 */
function couleur_rvb(string $couleur): array
{
    return [
        (int) hexdec(substr($couleur, 1, 2)),
        (int) hexdec(substr($couleur, 3, 2)),
        (int) hexdec(substr($couleur, 5, 2)),
    ];
}

/** Assombrit une couleur : $part de noir, de 0 (inchangée) à 1 (noir). */
function couleur_assombrir(string $couleur, float $part): string
{
    [$r, $v, $b] = couleur_rvb($couleur);
    return sprintf('#%02x%02x%02x', (int) round($r * (1 - $part)), (int) round($v * (1 - $part)), (int) round($b * (1 - $part)));
}

/** Luminance relative d'une couleur (0 = noir, 1 = blanc), selon les règles d'accessibilité WCAG. */
function couleur_luminance(string $couleur): float
{
    $composantes = [];
    foreach (couleur_rvb($couleur) as $valeur) {
        $c = $valeur / 255;
        $composantes[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $composantes[0] + 0.7152 * $composantes[1] + 0.0722 * $composantes[2];
}

/** Contraste d'une couleur avec le blanc (de 1 à 21 ; 4,5 est le minimum pour un texte lisible). */
function couleur_contraste_blanc(string $couleur): float
{
    return 1.05 / (couleur_luminance($couleur) + 0.05);
}

/** Feuille de style qui applique la couleur principale aux boutons, liens, menus et cases. */
function couleur_css(string $couleur): string
{
    $couleur = strtolower($couleur);
    $rvb = implode(', ', couleur_rvb($couleur));
    $survol = couleur_assombrir($couleur, 0.15);
    $actif = couleur_assombrir($couleur, 0.25);
    // Texte posé sur la couleur : blanc si elle est assez foncée, noir sinon.
    $texte = couleur_contraste_blanc($couleur) >= 3 ? '#ffffff' : '#000000';

    // Les liens sont écrits sur fond blanc : on fonce la couleur jusqu'à ce qu'elle soit lisible.
    $lien = $couleur;
    for ($i = 0; $i < 20 && couleur_contraste_blanc($lien) < 4.5; $i++) {
        $lien = couleur_assombrir($lien, 0.1);
    }
    $lien_rvb = implode(', ', couleur_rvb($lien));
    $lien_survol = couleur_assombrir($lien, 0.2);
    $lien_survol_rvb = implode(', ', couleur_rvb($lien_survol));

    return <<<CSS
    /* Fichier généré par l'application (Paramètres > Options > Couleur). Ne pas le modifier à la main. */
    :root {
      --bs-primary: $couleur;
      --bs-primary-rgb: $rvb;
      --bs-link-color: $lien;
      --bs-link-color-rgb: $lien_rvb;
      --bs-link-hover-color: $lien_survol;
      --bs-link-hover-color-rgb: $lien_survol_rvb;
      --couleur-texte-sur-principale: $texte;
    }
    .btn-primary {
      --bs-btn-color: $texte;
      --bs-btn-bg: $couleur;
      --bs-btn-border-color: $couleur;
      --bs-btn-hover-color: $texte;
      --bs-btn-hover-bg: $survol;
      --bs-btn-hover-border-color: $survol;
      --bs-btn-focus-shadow-rgb: $rvb;
      --bs-btn-active-color: $texte;
      --bs-btn-active-bg: $actif;
      --bs-btn-active-border-color: $actif;
      --bs-btn-disabled-color: $texte;
      --bs-btn-disabled-bg: $couleur;
      --bs-btn-disabled-border-color: $couleur;
    }
    .btn-outline-primary {
      --bs-btn-color: $lien;
      --bs-btn-border-color: $lien;
      --bs-btn-hover-color: $texte;
      --bs-btn-hover-bg: $couleur;
      --bs-btn-hover-border-color: $couleur;
      --bs-btn-focus-shadow-rgb: $rvb;
      --bs-btn-active-color: $texte;
      --bs-btn-active-bg: $couleur;
      --bs-btn-active-border-color: $couleur;
      --bs-btn-disabled-color: $lien;
      --bs-btn-disabled-border-color: $lien;
    }
    .text-bg-primary {
      color: $texte !important;
    }
    .form-check-input:checked {
      background-color: $couleur;
      border-color: $couleur;
    }
    .form-control:focus, .form-select:focus, .form-check-input:focus {
      border-color: $couleur;
      box-shadow: 0 0 0 0.25rem rgba($rvb, 0.25);
    }
    .pagination {
      --bs-pagination-color: $lien;
      --bs-pagination-hover-color: $lien_survol;
      --bs-pagination-active-color: $texte;
      --bs-pagination-active-bg: $couleur;
      --bs-pagination-active-border-color: $couleur;
    }
    .list-group {
      --bs-list-group-active-color: $texte;
      --bs-list-group-active-bg: $couleur;
      --bs-list-group-active-border-color: $couleur;
    }
    .dropdown-menu {
      --bs-dropdown-link-active-color: $texte;
      --bs-dropdown-link-active-bg: $couleur;
    }

    CSS;
}

/** Chemin du fichier css/couleur.css dans le dossier public. */
function couleur_fichier(): string
{
    return dossier_public() . '/css/couleur.css';
}

/**
 * Applique une couleur principale : écrit css/couleur.css.
 * Avec la couleur par défaut, le fichier est supprimé.
 *
 * @return bool false si le dossier css/ n'est pas accessible en écriture
 */
function couleur_appliquer(string $couleur): bool
{
    $fichier = couleur_fichier();
    if (strtolower($couleur) === COULEUR_DEFAUT) {
        return !is_file($fichier) || @unlink($fichier);
    }
    return @file_put_contents($fichier, couleur_css($couleur), LOCK_EX) !== false;
}

/** Adresse de css/couleur.css, ou '' si aucune couleur n'a été choisie. */
function couleur_adresse_css(): string
{
    $fichier = couleur_fichier();
    if (!is_file($fichier)) {
        return '';
    }
    // La date du fichier dans l'adresse force le navigateur à recharger après un changement.
    return chemin_site('css/couleur.css?v=' . filemtime($fichier));
}
