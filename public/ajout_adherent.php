<?php

declare(strict_types=1);

// Formulaire d'inscription en 4 étapes (rôle standard au minimum) :
//   1. type de personne, bénévolat, identité, code postal ;
//   2. famille (sauf pour une structure) ;
//   3. adresse, contact, responsable d'un mineur, notes du dossier ;
//   4. récapitulatif et validation.
// Les informations sont gardées en session entre les étapes ; rien n'est écrit
// en base avant la validation finale. Voir includes/fonctions_inscription.php.
//
// Adresses de la page :
//   ajout_adherent.php            reprend l'inscription en cours ;
//   ajout_adherent.php?etape=N    affiche une étape déjà accessible ;
//   ajout_adherent.php?nouveau=1  commence une inscription vide ;
//   ajout_adherent.php?famille=ID commence une inscription dans cette famille.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_affichee = saison_courante();
if ($saison_affichee === null) {
    flash('erreur', 'Aucune saison n\'existe : créez-en une avant d\'inscrire des adhérents.');
    rediriger('index.php');
}
$saison_affichee_id = entier($saison_affichee['id']);

// --- Début d'une nouvelle inscription --------------------------------------
if (!est_post() && (isset($_GET['nouveau']) || isset($_GET['famille']))) {
    $famille_demandee = entier($_GET['famille'] ?? 0);
    if ($famille_demandee > 0 && famille_par_id($famille_demandee) === null) {
        flash('erreur', 'Cette famille n\'existe pas.');
        rediriger('familles.php');
    }
    inscription_commencer($famille_demandee);
    rediriger('ajout_adherent.php');
}

$brouillon = inscription_brouillon();
$est_structure = inscription_est_structure($brouillon['type']);

// --- Traitement des formulaires ---------------------------------------------
if (est_post()) {
    csrf_verifier();
    $action = post('action');

    // Abandon : le brouillon est oublié.
    if ($action === 'abandonner') {
        inscription_oublier();
        flash('info', 'L\'inscription en cours est abandonnée.');
        rediriger('ajout_adherent.php');
    }

    // Choix du type de personne (les quatre boutons).
    if (str_starts_with($action, 'type_')) {
        $type_choisi = substr($action, 5);
        if (array_key_exists($type_choisi, inscription_types())) {
            // Passer d'une personne à une structure (ou l'inverse) change les étapes à suivre.
            if (inscription_est_structure($type_choisi) !== $est_structure) {
                $brouillon['etape_faite'] = 0;
            }
            $brouillon['type'] = $type_choisi;
            inscription_garder($brouillon);
        }
        rediriger('ajout_adherent.php?etape=1');
    }

    // Étape 1 : bénévolat, identité, code postal.
    if ($action === 'etape1') {
        $brouillon['identite'] = [
            'est_benevole'     => post('est_benevole') === '1' ? '1' : '0',
            'nom'              => post('nom'),
            'prenom'           => post('prenom'),
            'date_naissance'   => post('date_naissance'),
            'nationalite_iso3' => post('nationalite_iso3'),
            'code_postal'      => post('code_postal'),
            'ville'            => post('ville'),
        ];
        $erreurs = inscription_erreurs_etape1($brouillon);
        if ($erreurs === []) {
            // Pour une structure, l'étape 2 (famille) n'existe pas.
            $brouillon['etape_faite'] = max($brouillon['etape_faite'], $est_structure ? 2 : 1);
        }
        inscription_garder($brouillon);
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        rediriger('ajout_adherent.php?etape=' . ($erreurs !== [] ? 1 : ($est_structure ? 3 : 2)));
    }

    // Étape 2 : recherche d'une famille, puis choix.
    if ($action === 'chercher_famille' || $action === 'etape2') {
        $brouillon['famille_recherche'] = mb_substr(post('famille_recherche'), 0, 100);
        $choix = post('famille');
        if ($choix === 'nouvelle') {
            $brouillon['famille_choix'] = 'nouvelle';
            $brouillon['famille_id'] = 0;
        } elseif (entier($choix) > 0) {
            $brouillon['famille_choix'] = 'existante';
            $brouillon['famille_id'] = entier($choix);
        }
        if ($action === 'chercher_famille') {
            inscription_garder($brouillon);
            rediriger('ajout_adherent.php?etape=2');
        }
        $erreurs = inscription_erreurs_etape2($brouillon);
        if ($erreurs === []) {
            $brouillon['etape_faite'] = max($brouillon['etape_faite'], 2);
        }
        inscription_garder($brouillon);
        foreach ($erreurs as $erreur) {
            flash('erreur', $erreur);
        }
        rediriger('ajout_adherent.php?etape=' . ($erreurs !== [] ? 2 : 3));
    }

    // Étape 3 : adresse, contact, responsable, notes.
    if ($action === 'etape3' && $brouillon['etape_faite'] >= 2) {
        $brouillon['contact'] = [
            'adresse'          => post('adresse'),
            'complement'       => post('complement'),
            'code_postal'      => post('code_postal'),
            'ville'            => post('ville'),
            'mail'             => post('mail'),
            'tel'              => post('tel'),
            'a_consenti'       => post('a_consenti') === '1' ? '1' : '0',
            'responsable_nom'  => post('responsable_nom'),
            'responsable_mail' => post('responsable_mail'),
            'responsable_tel'  => post('responsable_tel'),
            'etablissement'    => post('etablissement'),
            'niveau'           => post('niveau'),
            'notes'            => post('notes'),
        ];
        $erreurs = inscription_erreurs_etape3($brouillon);
        if ($erreurs !== []) {
            $brouillon['adresse'] = [];
            $brouillon['etape_faite'] = min($brouillon['etape_faite'], 2);
            inscription_garder($brouillon);
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            rediriger('ajout_adherent.php?etape=3');
        }
        // Vérification de l'adresse auprès de l'API et calcul des quartiers.
        $mineur = est_mineur(inscription_fiche($brouillon)['date_naissance']);
        $brouillon['adresse'] = adresse_preparer(inscription_adresse_saisie($brouillon['contact'], $mineur));
        if ($brouillon['adresse']['est_validee'] === 1) {
            // L'adresse certifiée corrige l'écriture de la voie, le code postal et la commune.
            if ($brouillon['adresse']['code_postal'] !== $brouillon['contact']['code_postal']) {
                flash('info', 'Le code postal a été corrigé d\'après l\'adresse certifiée : ' . texte($brouillon['adresse']['code_postal']) . '.');
            }
            $brouillon['contact']['adresse'] = texte($brouillon['adresse']['adresse']);
            $brouillon['contact']['code_postal'] = texte($brouillon['adresse']['code_postal']);
            $brouillon['contact']['ville'] = texte($brouillon['adresse']['ville']);
        }
        $brouillon['etape_faite'] = 3;
        inscription_garder($brouillon);
        rediriger('ajout_adherent.php?etape=4');
    }

    // Étape 4 : validation finale.
    if ($action === 'valider' && $brouillon['etape_faite'] >= 3) {
        if (!jeton_unique_consommer('inscription')) {
            flash('avertissement', 'Ce formulaire a déjà été envoyé. Vérifiez la liste des adhérents avant de recommencer.');
            rediriger('adherents.php');
        }
        // Tout est revérifié : une étape a pu être modifiée après coup.
        foreach ([1 => inscription_erreurs_etape1($brouillon), 2 => inscription_erreurs_etape2($brouillon), 3 => inscription_erreurs_etape3($brouillon)] as $numero => $erreurs) {
            if ($erreurs !== []) {
                foreach ($erreurs as $erreur) {
                    flash('erreur', $erreur);
                }
                rediriger('ajout_adherent.php?etape=' . $numero);
            }
        }
        $fiche = inscription_fiche($brouillon);
        $erreurs = [];
        if (entier($brouillon['adresse']['est_validee'] ?? 0) !== 1 && post('confirmer_adresse') !== '1') {
            $erreurs[] = 'L\'adresse n\'est pas certifiée : cochez la case de confirmation pour l\'enregistrer quand même.';
        }
        if (
            !$est_structure && post('confirmer_doublon') !== '1'
            && adherent_doublons($fiche['nom'], $fiche['prenom'], $fiche['date_naissance']) !== []
        ) {
            $erreurs[] = 'Une personne porte déjà ce nom, ce prénom et cette date de naissance : cochez la case de confirmation pour créer quand même une deuxième fiche.';
        }
        if ($erreurs !== []) {
            foreach ($erreurs as $erreur) {
                flash('erreur', $erreur);
            }
            rediriger('ajout_adherent.php?etape=4');
        }

        $nouvel_id = inscription_enregistrer($brouillon, $saison_affichee_id);
        inscription_oublier();
        flash('succes', nom_adherent($fiche) . ' est inscrit(e) pour la saison ' . texte($saison_affichee['nom']) . '.');

        // Confirmation par courriel (à l'adhérent et à son responsable) : un
        // échec d'envoi n'annule pas l'inscription, déjà enregistrée.
        if (post('envoyer_confirmation') === '1') {
            $envoyes = [];
            $echecs = [];
            foreach (destinataires_confirmation($fiche) as $destinataire) {
                if (envoyer_confirmation_inscription($destinataire, $fiche, texte($saison_affichee['nom']))) {
                    $envoyes[] = $destinataire;
                } else {
                    $echecs[] = $destinataire;
                }
            }
            if ($envoyes !== []) {
                journaliser('envoi', 'adherents', $nouvel_id, 'Envoi de la confirmation d\'inscription par courriel', null, ['destinataires' => implode(', ', $envoyes)]);
                flash('succes', 'La confirmation d\'inscription est envoyée à ' . implode(' et à ', $envoyes) . '.');
            }
            if ($echecs !== []) {
                flash('avertissement', 'La confirmation d\'inscription n\'a pas pu être envoyée à ' . implode(' ni à ', $echecs) . '. L\'inscription, elle, est bien enregistrée.');
            }
        }
        rediriger('inscription_terminee.php?id=' . $nouvel_id);
    }

    rediriger('ajout_adherent.php');
}

// --- Étape à afficher --------------------------------------------------------
$etapes = inscription_etapes($brouillon['type']);
$etape_max = min(4, $brouillon['etape_faite'] + 1);
$etape = entier($_GET['etape'] ?? 0);
if ($etape < 1 || $etape > $etape_max) {
    $etape = $etape_max;
}
if ($est_structure && $etape === 2) {
    $etape = 3;
}

$fiche = inscription_fiche($brouillon);
$identite = $brouillon['identite'];
$mineur = est_mineur($fiche['date_naissance']);
$famille_choisie = $brouillon['famille_choix'] === 'existante' ? famille_par_id($brouillon['famille_id']) : null;

// Étape 2 : familles proposées (la famille déjà choisie, les noms proches, le résultat de la recherche).
$familles_proposees = [];
if ($etape === 2) {
    $listes = [familles_proches($identite['nom']), familles_par_texte($brouillon['famille_recherche'])];
    if ($famille_choisie !== null) {
        array_unshift($listes, familles_avec_membres(' AND f.id = ?', [(string) $brouillon['famille_id']]));
    }
    foreach ($listes as $liste) {
        foreach ($liste as $famille_proposee) {
            $familles_proposees[$famille_proposee['id']] ??= $famille_proposee;
        }
    }
}

// Étape 3 : valeurs proposées tant que l'étape n'a pas été remplie.
$contact = $brouillon['contact'];
if ($etape === 3 && $contact === []) {
    $contact = inscription_contact_propose($brouillon, $saison_affichee_id);
}

// Étape 4 : doublon exact éventuel.
$doublons_exacts = $etape === 4 && !$est_structure
    ? adherent_doublons($fiche['nom'], $fiche['prenom'], $fiche['date_naissance'])
    : [];
$adresse_certifiee = entier($brouillon['adresse']['est_validee'] ?? 0) === 1;
$destinataires_confirmation = $etape === 4 ? destinataires_confirmation($fiche) : [];

$titre_page = 'Nouvelle inscription';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-3">Nouvelle inscription <span class="fs-6 text-body-secondary">saison <?= e($saison_affichee['nom']) ?></span></h1>

<ol class="list-group list-group-horizontal-md mb-4">
    <?php $rang = 0; ?>
    <?php foreach ($etapes as $numero => $titre_etape) : ?>
        <?php $rang++; ?>
        <li class="list-group-item flex-fill <?= $numero === $etape ? 'active' : ($numero <= $brouillon['etape_faite'] ? 'list-group-item-success' : 'text-body-secondary') ?>">
            <?php if ($numero !== $etape && $numero <= $etape_max) : ?>
                <a class="text-reset text-decoration-none" href="<?= e(chemin_site('ajout_adherent.php?etape=' . $numero)) ?>"><?= $rang ?>. <?= e($titre_etape) ?></a>
            <?php else : ?>
                <?= $rang ?>. <?= e($titre_etape) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>

<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <?php if ($etape === 1 && $brouillon['type'] !== '') : ?>
            <h2 class="h6">Personnes déjà enregistrées</h2>
            <div id="doublonsProches" class="list-group mb-2"></div>
            <p class="text-body-secondary small" id="doublonsAide">Les personnes proches (même date de naissance, ou nom et prénom presque identiques) s'affichent ici pendant la saisie. Cliquez sur une personne pour ouvrir sa fiche et l'inscrire pour la saison, au lieu de créer une deuxième fiche.</p>
        <?php elseif ($etape > 1) : ?>
            <h2 class="h6">Inscription en cours</h2>
            <dl class="small mb-3">
                <dt><?= e(inscription_types()[$brouillon['type']] ?? '') ?></dt>
                <dd><?= e(nom_adherent($fiche)) ?><?= $est_structure ? '' : ', né(e) le ' . e(date_fr($fiche['date_naissance'])) ?></dd>
                <?php if (!$est_structure && $brouillon['etape_faite'] >= 2) : ?>
                    <dt>Famille</dt>
                    <dd><?= $famille_choisie !== null ? e($famille_choisie['nom']) : 'Nouvelle famille ' . e($fiche['nom']) ?></dd>
                <?php endif; ?>
            </dl>
        <?php endif; ?>
        <?php if ($brouillon['type'] !== '') : ?>
            <form class="d-grid" method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>">
                <?= csrf_champ() ?>
                <button class="btn btn-outline-secondary" type="submit" name="action" value="abandonner">Abandonner cette inscription</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-md-8 order-md-1">
<?php if ($etape === 1) : ?>
        <?php // ----- Étape 1 : type de personne, bénévolat, identité, code postal ----- ?>
        <form class="mb-4" method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>">
            <?= csrf_champ() ?>
            <p class="form-label">Type de personne <span class="text-danger">*</span></p>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (inscription_types() as $code_type => $libelle_type) : ?>
                    <button class="btn <?= $brouillon['type'] === $code_type ? 'btn-primary' : 'btn-outline-primary' ?>" type="submit" name="action" value="type_<?= e($code_type) ?>" <?= $brouillon['type'] === $code_type ? 'aria-pressed="true"' : '' ?>><?= e($libelle_type) ?></button>
                <?php endforeach; ?>
            </div>
        </form>

        <?php if ($brouillon['type'] !== '') : ?>
            <form method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>"
                  data-doublons-url="<?= e(chemin_site('api_doublons.php')) ?>" data-doublons-fiche="<?= e(chemin_site('adherent.php?id=')) ?>"
                  data-doublons-structure="<?= $est_structure ? '1' : '0' ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="etape1">
                <?php if ($est_structure) : ?>
                    <div class="mb-3">
                        <label class="form-label" for="nom">Nom de la structure <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e($identite['nom']) ?>" required autofocus>
                    </div>
                <?php else : ?>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="est_benevole" name="est_benevole" value="1" <?= $identite['est_benevole'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="est_benevole">Est bénévole</label>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="nom">Nom <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e($identite['nom']) ?>" required autofocus>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="prenom">Prénom <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" id="prenom" name="prenom" maxlength="100" value="<?= e($identite['prenom']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="date_naissance">Date de naissance <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" id="date_naissance" name="date_naissance" max="<?= e(date('Y-m-d')) ?>" value="<?= e($identite['date_naissance']) ?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="nationalite_iso3">Nationalité</label>
                            <select class="form-select" id="nationalite_iso3" name="nationalite_iso3">
                                <option value="">Non renseignée</option>
                                <?php foreach (pays_liste() as $code_pays => $nom_pays) : ?>
                                    <option value="<?= e($code_pays) ?>" <?= $identite['nationalite_iso3'] === $code_pays ? 'selected' : '' ?>><?= e($nom_pays) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="row g-3 mb-4">
                    <div class="col-sm-4 position-relative">
                        <label class="form-label" for="code_postal">Code postal <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="code_postal" name="code_postal" maxlength="5" inputmode="numeric" pattern="[0-9]{5}" autocomplete="off"
                               data-suggestions="code_postal" data-url="<?= e(chemin_site('api_codes_postaux.php')) ?>"
                               value="<?= e($identite['code_postal']) ?>" required>
                    </div>
                    <div class="col-sm-8 position-relative">
                        <label class="form-label" for="ville">Commune <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="ville" name="ville" maxlength="100" autocomplete="off"
                               data-suggestions="ville" data-url="<?= e(chemin_site('api_codes_postaux.php')) ?>"
                               value="<?= e($identite['ville']) ?>" required>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Continuer</button>
            </form>
        <?php else : ?>
            <p class="text-body-secondary">Choisissez le type de personne pour afficher la suite du formulaire.</p>
        <?php endif; ?>

<?php elseif ($etape === 2) : ?>
        <?php // ----- Étape 2 : famille ----- ?>
        <form method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>">
            <?= csrf_champ() ?>
            <div class="list-group mb-3">
                <label class="list-group-item d-flex gap-2">
                    <input class="form-check-input flex-shrink-0" type="radio" name="famille" value="nouvelle" <?= $brouillon['famille_choix'] === 'nouvelle' ? 'checked' : '' ?>>
                    <span><strong>Créer une nouvelle famille</strong> au nom de <?= e($fiche['nom']) ?></span>
                </label>
                <?php foreach ($familles_proposees as $famille_proposee) : ?>
                    <label class="list-group-item d-flex gap-2">
                        <input class="form-check-input flex-shrink-0" type="radio" name="famille" value="<?= $famille_proposee['id'] ?>" <?= $brouillon['famille_choix'] === 'existante' && $brouillon['famille_id'] === $famille_proposee['id'] ? 'checked' : '' ?>>
                        <span>
                            Famille <strong><?= e($famille_proposee['nom']) ?></strong>
                            <span class="d-block small text-body-secondary"><?= e($famille_proposee['membres']) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if ($familles_proposees === []) : ?>
                <p class="text-body-secondary small">Aucune famille n'a un nom proche de « <?= e($fiche['nom']) ?> ».</p>
            <?php endif; ?>

            <label class="form-label" for="famille_recherche">Rechercher une autre famille</label>
            <div class="input-group mb-4">
                <input class="form-control" type="search" id="famille_recherche" name="famille_recherche" maxlength="100" value="<?= e($brouillon['famille_recherche']) ?>" placeholder="Nom de la famille ou d'un membre">
                <button class="btn btn-outline-secondary" type="submit" name="action" value="chercher_famille">Rechercher</button>
            </div>

            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('ajout_adherent.php?etape=1')) ?>">Retour</a>
            <button class="btn btn-primary" type="submit" name="action" value="etape2">Continuer</button>
        </form>

<?php elseif ($etape === 3) : ?>
        <?php // ----- Étape 3 : adresse, contact, responsable, notes ----- ?>
        <form method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>">
            <?= csrf_champ() ?>
            <input type="hidden" name="action" value="etape3">

            <h2 class="h5">Adresse</h2>
            <?php afficher_champs_adresse($contact, false); ?>

            <h2 class="h5 mt-4">Contact</h2>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="mail">Adresse e-mail</label>
                    <input class="form-control" type="email" id="mail" name="mail" maxlength="150" value="<?= e($contact['mail'] ?? '') ?>">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="tel">Téléphone</label>
                    <input class="form-control" type="tel" id="tel" name="tel" maxlength="20" value="<?= e($contact['tel'] ?? '') ?>">
                </div>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="a_consenti" name="a_consenti" value="1" <?= ($contact['a_consenti'] ?? '') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="a_consenti">Accepte de recevoir les communications de l'association (programme, informations)</label>
            </div>

            <?php if ($mineur) : ?>
                <?php afficher_champs_responsable($contact); ?>
                <div class="row g-3 mb-4">
                    <div class="col-sm-8">
                        <label class="form-label" for="etablissement">Établissement scolaire</label>
                        <input class="form-control" type="text" id="etablissement" name="etablissement" maxlength="150" value="<?= e($contact['etablissement'] ?? '') ?>">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" for="niveau">Niveau</label>
                        <input class="form-control" type="text" id="niveau" name="niveau" maxlength="50" value="<?= e($contact['niveau'] ?? '') ?>">
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="h5">Notes</h2>
            <div class="mb-4">
                <label class="form-label visually-hidden" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="5000" aria-describedby="aide_notes"><?= e($contact['notes'] ?? '') ?></textarea>
                <div class="form-text" id="aide_notes">Les notes concernent <?= $est_structure ? 'la structure' : 'toute la famille' ?> pour la saison, pas seulement cette personne.</div>
            </div>

            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('ajout_adherent.php?etape=' . ($est_structure ? 1 : 2))) ?>">Retour</a>
            <button class="btn btn-primary" type="submit">Continuer</button>
        </form>

<?php else : ?>
        <?php // ----- Étape 4 : récapitulatif et validation ----- ?>
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="h5">Identité</h2>
                <a href="<?= e(chemin_site('ajout_adherent.php?etape=1')) ?>">Modifier</a>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4">Type</dt>
                <dd class="col-sm-8"><?= e(inscription_types()[$brouillon['type']] ?? '') ?></dd>
                <dt class="col-sm-4"><?= $est_structure ? 'Nom de la structure' : 'Nom et prénom' ?></dt>
                <dd class="col-sm-8"><?= e($est_structure ? $fiche['nom'] : $fiche['nom'] . ' ' . $fiche['prenom']) ?></dd>
                <?php if (!$est_structure) : ?>
                    <dt class="col-sm-4">Date de naissance</dt>
                    <dd class="col-sm-8"><?= e(date_fr($fiche['date_naissance'])) ?> (<?= age_ans($fiche['date_naissance']) ?> ans)</dd>
                    <dt class="col-sm-4">Nationalité</dt>
                    <dd class="col-sm-8"><?= e(pays_liste()[$fiche['nationalite_iso3']] ?? 'Non renseignée') ?></dd>
                    <dt class="col-sm-4">Bénévole</dt>
                    <dd class="col-sm-8"><?= $fiche['est_benevole'] === '1' ? 'Oui' : 'Non' ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if (!$est_structure) : ?>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="h5">Famille</h2>
                    <a href="<?= e(chemin_site('ajout_adherent.php?etape=2')) ?>">Modifier</a>
                </div>
                <p class="mb-0"><?= $famille_choisie !== null ? 'Famille existante : ' . e($famille_choisie['nom']) : 'Nouvelle famille : ' . e($fiche['nom']) ?></p>
            </div>
        <?php endif; ?>

        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <h2 class="h5">Adresse et contact</h2>
                <a href="<?= e(chemin_site('ajout_adherent.php?etape=3')) ?>">Modifier</a>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4">Adresse</dt>
                <dd class="col-sm-8">
                    <?= e($brouillon['adresse']['adresse'] ?? '') ?><?= texte($brouillon['adresse']['complement'] ?? '') !== '' ? ', ' . e($brouillon['adresse']['complement']) : '' ?><br>
                    <?= e($brouillon['adresse']['code_postal'] ?? '') ?> <?= e($brouillon['adresse']['ville'] ?? '') ?><br>
                    <?php if ($adresse_certifiee) : ?>
                        <span class="badge text-bg-success">Adresse certifiée</span>
                        <span class="small text-body-secondary d-block"><?= e(quartiers_resume($brouillon['adresse'])) ?></span>
                    <?php else : ?>
                        <span class="badge text-bg-warning">Adresse non certifiée</span>
                    <?php endif; ?>
                </dd>
                <dt class="col-sm-4">Adresse e-mail</dt>
                <dd class="col-sm-8"><?= e($fiche['mail'] !== '' ? $fiche['mail'] : '—') ?></dd>
                <dt class="col-sm-4">Téléphone</dt>
                <dd class="col-sm-8"><?= e($fiche['tel'] !== '' ? $fiche['tel'] : '—') ?></dd>
                <dt class="col-sm-4">Communications</dt>
                <dd class="col-sm-8"><?= $fiche['a_consenti'] === '1' ? 'Acceptées' : 'Non acceptées' ?></dd>
                <?php if ($mineur) : ?>
                    <dt class="col-sm-4">Responsable</dt>
                    <dd class="col-sm-8"><?= e(implode(' — ', array_filter([$fiche['responsable_nom'], $fiche['responsable_tel'], $fiche['responsable_mail']]))) ?></dd>
                    <dt class="col-sm-4">Scolarité</dt>
                    <dd class="col-sm-8"><?= e(trim(texte($brouillon['adresse']['etablissement'] ?? '') . ' ' . texte($brouillon['adresse']['niveau'] ?? '')) ?: '—') ?></dd>
                <?php endif; ?>
                <dt class="col-sm-4">Notes</dt>
                <dd class="col-sm-8"><?= ($contact['notes'] ?? '') !== '' ? nl2br(e($contact['notes'])) : '—' ?></dd>
            </dl>
        </div>

        <form method="post" action="<?= e(chemin_site('ajout_adherent.php')) ?>">
            <?= csrf_champ() ?>
            <?= jeton_unique_champ('inscription') ?>
            <?php if (!$adresse_certifiee) : ?>
                <div class="alert alert-warning">
                    <p class="mb-2">L'adresse n'a pas été reconnue au numéro près : elle n'est pas certifiée. Le quartier, le QP et le QPV resteront vides.</p>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="confirmer_adresse" name="confirmer_adresse" value="1" required>
                        <label class="form-check-label" for="confirmer_adresse">Enregistrer quand même avec cette adresse.</label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($doublons_exacts !== []) : ?>
                <div class="alert alert-warning">
                    <p class="mb-2">Une personne porte déjà ce nom, ce prénom et cette date de naissance :</p>
                    <ul class="mb-2">
                        <?php foreach ($doublons_exacts as $doublon) : ?>
                            <li><a href="<?= e(chemin_site('adherent.php?id=' . entier($doublon['id']))) ?>"><?= e(nom_adherent($doublon)) ?></a>, famille <?= e($doublon['famille_nom']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="confirmer_doublon" name="confirmer_doublon" value="1" required>
                        <label class="form-check-label" for="confirmer_doublon">Ce n'est pas la même personne : créer une deuxième fiche.</label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($destinataires_confirmation !== []) : ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="envoyer_confirmation" name="envoyer_confirmation" value="1" checked aria-describedby="aide_confirmation">
                    <label class="form-check-label" for="envoyer_confirmation">Envoyer une confirmation d'inscription par courriel</label>
                    <div class="form-text" id="aide_confirmation">Envoyée à <?= e(implode(' et à ', $destinataires_confirmation)) ?>.</div>
                </div>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('ajout_adherent.php?etape=3')) ?>">Retour</a>
            <button class="btn btn-primary" type="submit" name="action" value="valider">Valider l'inscription</button>
        </form>
<?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
