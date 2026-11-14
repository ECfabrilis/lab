<?php

declare(strict_types=1);

// Options (administrateurs seulement) : nom et adresse de la structure, logo,
// couleur principale. Le nom, l'adresse et le logo figurent aussi sur les
// documents (reçus, attestations, listes).

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
    // Couleur principale : celle choisie, ou la couleur par défaut si la case est cochée.
    $couleur_saisie = post('couleur_defaut') === '1' ? COULEUR_DEFAUT : strtolower(post('couleur'));
    if (!couleur_valide($couleur_saisie)) {
        $erreurs[] = 'La couleur choisie n\'est pas valide.';
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
        rediriger('options.php');
    }

    $ancien = ['nom_association' => parametre('nom_association'), 'adresse_association' => parametre('adresse_association'), 'logo' => parametre('logo'), 'couleur' => parametre('couleur', COULEUR_DEFAUT)];
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
    if (couleur_appliquer($couleur_saisie)) {
        parametre_enregistrer('couleur', $couleur_saisie, $moi_id);
        if ($couleur_saisie !== COULEUR_DEFAUT && couleur_contraste_blanc($couleur_saisie) < 3) {
            flash('info', 'La couleur choisie est claire : les textes posés dessus sont écrits en noir et les liens dans une teinte plus foncée, pour rester lisibles.');
        }
    } else {
        flash('avertissement', 'La couleur n\'a pas pu être appliquée : le dossier css/ n\'est pas accessible en écriture.');
    }
    $nouveau = ['nom_association' => parametre('nom_association'), 'adresse_association' => parametre('adresse_association'), 'logo' => parametre('logo'), 'couleur' => parametre('couleur', COULEUR_DEFAUT)];
    journaliser('modification', 'parametres', null, 'Modification des options de la structure', $ancien, $nouveau);
    flash('succes', 'Les options sont enregistrées.');
    rediriger('options.php');
}

$logo_actuel = chemin_site(parametre('logo', 'img/logo-cefia75.png'));

$couleur_actuelle = parametre('couleur', COULEUR_DEFAUT);

$titre_page = 'Options d\'affichage';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Options d'affichage</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <p class="mb-2">Logo actuel</p>
        <div class="border rounded p-3 text-center bg-body-tertiary">
            <img class="img-fluid" src="<?= e($logo_actuel) ?>" alt="Logo de l'association">
        </div>
        <p class="d-none d-lg-block text-body-secondary small mt-3">Le nom, l'adresse et le logo figurent sur les documents (reçus, attestations, listes).</p>
        <div class="d-grid">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('options.php')) ?>" enctype="multipart/form-data">
            <?= csrf_champ() ?>
            <h2 class="h5" id="nom">Nom de la structure</h2>
            <div class="mb-3">
                <label class="form-label" for="nom_association">Nom</label>
                <input class="form-control" type="text" id="nom_association" name="nom_association" maxlength="255" value="<?= e(ancien('nom_association', parametre('nom_association'))) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="adresse_association">Adresse</label>
                <input class="form-control" type="text" id="adresse_association" name="adresse_association" maxlength="255" value="<?= e(ancien('adresse_association', parametre('adresse_association'))) ?>">
            </div>
            <h2 class="h5 mt-4" id="logo_titre">Logo</h2>
            <div class="mb-3">
                <label class="form-label" for="logo">Nouveau logo (facultatif)</label>
                <input class="form-control" type="file" id="logo" name="logo" accept="image/png, image/jpeg" aria-describedby="aide_logo">
                <div class="form-text" id="aide_logo">Image PNG ou JPEG, 500 Ko au maximum.</div>
            </div>
            <h2 class="h5 mt-4" id="couleur_titre">Couleur</h2>
            <div class="mb-3">
                <label class="form-label" for="couleur">Couleur principale (boutons, liens, barre de la saison)</label>
                <input class="form-control form-control-color" type="color" id="couleur" name="couleur" value="<?= e(ancien('couleur', $couleur_actuelle)) ?>">
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="couleur_defaut" name="couleur_defaut" value="1">
                <label class="form-check-label" for="couleur_defaut">Revenir à la couleur par défaut</label>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
