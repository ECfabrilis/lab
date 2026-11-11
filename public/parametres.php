<?php

declare(strict_types=1);

// Paramètres de l'association : nom, adresse, logo (administrateurs seulement).
// Ces informations figurent sur les documents (reçus, attestations, listes).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

/** Taille maximale du logo : 500 Ko. */
const LOGO_TAILLE_MAX = 512000;

if (est_post()) {
    csrf_verifier();
    $moi_id = entier($_SESSION['utilisateur_id'] ?? 0);
    $nom_saisi = post('nom_association');
    $adresse_saisie = post('adresse_association');

    $erreurs = [];
    if ($nom_saisi === '' || mb_strlen($nom_saisi) > 255) {
        $erreurs[] = 'Le nom de l\'association est obligatoire (255 caractères au maximum).';
    }
    if (mb_strlen($adresse_saisie) > 255) {
        $erreurs[] = 'L\'adresse est trop longue (255 caractères au maximum).';
    }

    // Logo facultatif : image PNG ou JPEG, vérifiée d'après son contenu et non d'après son nom.
    $logo_envoye = $_FILES['logo'] ?? null;
    $chemin_temporaire = '';
    $extension_logo = '';
    if (is_array($logo_envoye) && entier($logo_envoye['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $chemin_temporaire = texte($logo_envoye['tmp_name'] ?? '');
        if (entier($logo_envoye['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file($chemin_temporaire)) {
            $erreurs[] = 'Le logo n\'a pas pu être reçu (fichier trop volumineux ?).';
        } elseif (entier($logo_envoye['size'] ?? 0) > LOGO_TAILLE_MAX) {
            $erreurs[] = 'Le logo dépasse 500 Ko.';
        } else {
            $infos_image = getimagesize($chemin_temporaire);
            $extension_logo = match ($infos_image === false ? 0 : $infos_image[2]) {
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_JPEG => 'jpg',
                default        => '',
            };
            if ($extension_logo === '') {
                $erreurs[] = 'Le logo doit être une image PNG ou JPEG.';
            }
        }
    }

    if ($erreurs !== []) {
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        ancien_memoriser();
        rediriger('parametres.php');
    }

    $ancien = ['nom_association' => parametre('nom_association'), 'adresse_association' => parametre('adresse_association'), 'logo' => parametre('logo')];
    parametre_enregistrer('nom_association', $nom_saisi, $moi_id);
    parametre_enregistrer('adresse_association', $adresse_saisie, $moi_id);
    if ($extension_logo !== '') {
        $fichier_logo = 'img/logo_association.' . $extension_logo;
        if (move_uploaded_file($chemin_temporaire, __DIR__ . '/' . $fichier_logo)) {
            parametre_enregistrer('logo', $fichier_logo, $moi_id);
        } else {
            flash('avertissement', 'Le logo n\'a pas pu être enregistré : le dossier img/ n\'est pas accessible en écriture.');
        }
    }
    $nouveau = ['nom_association' => parametre('nom_association'), 'adresse_association' => parametre('adresse_association'), 'logo' => parametre('logo')];
    journaliser('modification', 'parametres', null, 'Modification des paramètres de l\'association', $ancien, $nouveau);
    flash('succes', 'Les paramètres sont enregistrés.');
    rediriger('parametres.php');
}

$logo_actuel = chemin_site(parametre('logo', 'img/logo-cefia75.png'));

$titre_page = 'Paramètres';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paramètres de l'association</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <p class="mb-2">Logo actuel</p>
        <div class="border rounded p-3 text-center bg-body-tertiary">
            <img class="img-fluid" src="<?= e($logo_actuel) ?>" alt="Logo de l'association">
        </div>
        <p class="text-body-secondary small mt-3 mb-0">Le nom, l'adresse et le logo figurent sur les documents (reçus, attestations, listes). Les réglages techniques (base, envoi des courriels) sont dans <code>config.php</code>.</p>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('parametres.php')) ?>" enctype="multipart/form-data">
            <?= csrf_champ() ?>
            <div class="mb-3">
                <label class="form-label" for="nom_association">Nom de l'association</label>
                <input class="form-control" type="text" id="nom_association" name="nom_association" maxlength="255" value="<?= e(ancien('nom_association', parametre('nom_association'))) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="adresse_association">Adresse</label>
                <input class="form-control" type="text" id="adresse_association" name="adresse_association" maxlength="255" value="<?= e(ancien('adresse_association', parametre('adresse_association'))) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="logo">Nouveau logo (facultatif)</label>
                <input class="form-control" type="file" id="logo" name="logo" accept="image/png, image/jpeg" aria-describedby="aide_logo">
                <div class="form-text" id="aide_logo">Image PNG ou JPEG, 500 Ko au maximum.</div>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
