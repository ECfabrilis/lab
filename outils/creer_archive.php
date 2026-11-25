<?php

declare(strict_types=1);

/**
 * Fabrique l'archive d'une version, prête à déposer sur un hébergement
 * (ni Git, ni Composer, ni Node sur le serveur). À lancer sur le poste de
 * développement, depuis la racine du projet :
 *
 *     php outils/creer_archive.php
 *
 * L'archive part du dernier commit, jamais du dossier de travail : config.php,
 * les journaux, les sauvegardes et les fichiers d'essai ne peuvent pas s'y
 * glisser. Le script refuse de continuer si des modifications ne sont pas
 * commitées ou si le commit ne porte pas l'étiquette de la version (vX.Y.Z).
 *
 * Résultat : livraisons/bddv3-X.Y.Z.zip et son empreinte SHA-256.
 * Nécessite git, composer et l'extension PHP zip.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Ce qui entre dans l'archive : seulement ce qui sert à faire tourner
 * l'application. Tout fichier absent de cette liste reste hors de l'archive
 * (CLAUDE.md, cahier des charges, guide de déploiement par Git, modèles de
 * contrat, scss/, fichiers Node, ce script…).
 * vendor/ est ajouté à part, sans les outils de développement.
 */
const ARCHIVE_CONTENU = [
    '.htaccess',
    'config.exemple.php',
    'includes',
    'public',
    'sql',
    'logs',
    'sauvegardes',
    'outils/.htaccess',
    'outils/mise_a_jour.php',
    'docs/.htaccess',
    'docs/installation.md',
    'docs/guide_utilisation.md',
    'LICENCE.txt',
    'LICENCES_TIERCES.txt',
];

/** Dossier racine à l'intérieur de l'archive. */
const ARCHIVE_DOSSIER = 'bddv3';

/** Arrête le script avec un message d'erreur. */
function arreter(string $message): never
{
    fwrite(STDERR, 'ERREUR : ' . $message . PHP_EOL);
    exit(1);
}

/**
 * Lance une commande et renvoie sa sortie ; arrête le script si elle échoue.
 *
 * @return list<string> lignes de la sortie
 */
function commande(string $commande, string $dossier): array
{
    $sortie = [];
    $code = 0;
    $retour = getcwd();
    chdir($dossier);
    exec($commande . ' 2>&1', $sortie, $code);
    if ($retour !== false) {
        chdir($retour);
    }
    if ($code !== 0) {
        arreter('la commande « ' . $commande . ' » a échoué :' . PHP_EOL . implode(PHP_EOL, $sortie));
    }
    return $sortie;
}

/** Supprime un dossier et tout son contenu. */
function supprimer_dossier(string $dossier): void
{
    if (!is_dir($dossier)) {
        return;
    }
    $elements = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($elements as $element) {
        if (!$element instanceof SplFileInfo) {
            continue;
        }
        if ($element->isDir() && !$element->isLink()) {
            @rmdir($element->getPathname());
        } else {
            // Sous Windows, un fichier en lecture seule (objets Git) ne se supprime pas tel quel.
            @chmod($element->getPathname(), 0666);
            @unlink($element->getPathname());
        }
    }
    @rmdir($dossier);
}

/**
 * Ajoute un fichier ou un dossier (avec tout son contenu) à l'archive.
 *
 * @return int nombre de fichiers ajoutés
 */
function archive_ajouter(ZipArchive $zip, string $source, string $nom): int
{
    if (is_file($source)) {
        if (!$zip->addFile($source, $nom)) {
            arreter('impossible d\'ajouter ' . $nom . ' à l\'archive.');
        }
        return 1;
    }
    $nombre = 0;
    $zip->addEmptyDir($nom);
    $elements = scandir($source);
    foreach ($elements === false ? [] : $elements as $element) {
        if ($element !== '.' && $element !== '..') {
            $nombre += archive_ajouter($zip, $source . '/' . $element, $nom . '/' . $element);
        }
    }
    return $nombre;
}

$racine = dirname(__DIR__);

// --- Contrôles ---------------------------------------------------------------
if (!class_exists(ZipArchive::class)) {
    arreter('l\'extension PHP « zip » est absente.');
}
$source_version = file_get_contents($racine . '/includes/fonctions_version.php');
if ($source_version === false || preg_match('/const APP_VERSION = \'(\d+\.\d+\.\d+)\';/', $source_version, $trouve) !== 1) {
    arreter('version introuvable dans includes/fonctions_version.php.');
}
$version = $trouve[1];

if (commande('git status --porcelain', $racine) !== []) {
    arreter('des modifications ne sont pas commitées : l\'archive part du dernier commit. Commitez d\'abord.');
}
if (!in_array('v' . $version, commande('git tag --points-at HEAD', $racine), true)) {
    arreter('le dernier commit ne porte pas l\'étiquette v' . $version . ' (git tag v' . $version . ').');
}

// --- Copie du dernier commit dans un dossier temporaire -----------------------
$temporaire = sys_get_temp_dir() . '/bddv3_archive_' . bin2hex(random_bytes(4));
$copie = $temporaire . '/copie';
if (!mkdir($copie, 0700, true)) {
    arreter('impossible de créer le dossier temporaire ' . $temporaire . '.');
}
echo 'Version ' . $version . ' : copie du dernier commit…' . PHP_EOL;
commande('git archive --format=zip -o ' . escapeshellarg($temporaire . '/commit.zip') . ' HEAD', $racine);
$commit = new ZipArchive();
if ($commit->open($temporaire . '/commit.zip') !== true || !$commit->extractTo($copie)) {
    arreter('impossible de lire la copie du dernier commit.');
}
$commit->close();

// --- Bibliothèques PHP, sans les outils de développement ----------------------
echo 'Installation de vendor/ (sans les outils de développement)…' . PHP_EOL;
commande('composer install --no-dev --optimize-autoloader --no-interaction --no-progress', $copie);
if (!is_file($copie . '/vendor/autoload.php')) {
    arreter('vendor/autoload.php est absent après « composer install ».');
}
// vendor/ n'est pas versionné : son .htaccess « tout interdit » est copié de celui de includes/.
if (!copy($copie . '/includes/.htaccess', $copie . '/vendor/.htaccess')) {
    arreter('impossible de protéger le dossier vendor/ de l\'archive.');
}

// --- Écriture de l'archive ----------------------------------------------------
$livraisons = $racine . '/livraisons';
if (!is_dir($livraisons) && !mkdir($livraisons, 0700)) {
    arreter('impossible de créer le dossier livraisons/.');
}
$chemin_archive = $livraisons . '/bddv3-' . $version . '.zip';
if (is_file($chemin_archive)) {
    unlink($chemin_archive);
}
$zip = new ZipArchive();
if ($zip->open($chemin_archive, ZipArchive::CREATE) !== true) {
    arreter('impossible de créer ' . $chemin_archive . '.');
}
$nombre = 0;
$absents = [];
foreach ([...ARCHIVE_CONTENU, 'vendor'] as $element) {
    if (!file_exists($copie . '/' . $element)) {
        $absents[] = $element;
        continue;
    }
    $nombre += archive_ajouter($zip, $copie . '/' . $element, ARCHIVE_DOSSIER . '/' . $element);
}
if (!$zip->close()) {
    arreter('l\'écriture de l\'archive a échoué.');
}
supprimer_dossier($temporaire);

$empreinte = hash_file('sha256', $chemin_archive);
file_put_contents($chemin_archive . '.sha256', $empreinte . '  ' . basename($chemin_archive) . "\n");

echo PHP_EOL . 'Archive : livraisons/' . basename($chemin_archive) . PHP_EOL
    . 'Fichiers : ' . $nombre . ' ; taille : ' . number_format((int) filesize($chemin_archive) / 1048576, 1, ',', ' ') . ' Mo' . PHP_EOL
    . 'SHA-256 : ' . $empreinte . PHP_EOL;
foreach ($absents as $absent) {
    echo 'ATTENTION : ' . $absent . ' est absent du dépôt, il n\'est pas dans l\'archive.' . PHP_EOL;
}
