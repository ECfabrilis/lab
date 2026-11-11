<?php

declare(strict_types=1);

/**
 * Adhérents : fiche, vérifications, doublons, recherche.
 */

/** Âge à partir duquel une personne n'a plus besoin d'un responsable. */
const AGE_MAJORITE = 18;

/** Nombre d'adhérents affichés par page dans la liste. */
const ADHERENTS_PAR_PAGE = 50;

/**
 * Libellés des sexes.
 *
 * @return array<string, string>
 */
function libelles_sexes(): array
{
    return ['F' => 'Femme', 'H' => 'Homme', 'A' => 'Autre'];
}

/** Âge en années révolues à une date donnée (aujourd'hui par défaut). L'âge n'est jamais stocké. */
function age_ans(string $date_naissance, ?string $a_la_date = null): int
{
    if (!date_valide($date_naissance)) {
        return 0;
    }
    $reference = new DateTimeImmutable($a_la_date ?? 'today');
    return (new DateTimeImmutable($date_naissance))->diff($reference)->y;
}

/** Vrai si la personne est mineure aujourd'hui. */
function est_mineur(string $date_naissance): bool
{
    return date_valide($date_naissance) && age_ans($date_naissance) < AGE_MAJORITE;
}

/**
 * Pays proposés pour la nationalité : la France d'abord, puis l'ordre alphabétique.
 *
 * @return array<string, string> code du pays => nom
 */
function pays_liste(): array
{
    $pays = [];
    foreach (db_lignes("SELECT iso3, name_fr FROM pays ORDER BY (iso3 = 'FRA') DESC, name_fr") as $ligne) {
        $pays[texte($ligne['iso3'])] = texte($ligne['name_fr']);
    }
    return $pays;
}

/**
 * Un adhérent par son identifiant, avec le nom de sa famille et de son pays, ou null.
 *
 * @return array<string, mixed>|null
 */
function adherent_par_id(int $id): ?array
{
    return db_ligne(
        'SELECT a.*, f.nom AS famille_nom, p.name_fr AS nationalite_nom
           FROM adherents a
           JOIN familles f ON f.id = a.famille_id
           LEFT JOIN pays p ON p.iso3 = a.nationalite_iso3
          WHERE a.id = ?',
        [$id]
    );
}

/**
 * Champs de la fiche adhérent lus dans le formulaire envoyé.
 *
 * @return array<string, string>
 */
function adherent_saisie(): array
{
    return [
        'nom'              => post('nom'),
        'prenom'           => post('prenom'),
        'sexe'             => post('sexe'),
        'date_naissance'   => post('date_naissance'),
        'nationalite_iso3' => post('nationalite_iso3'),
        'mail'             => post('mail'),
        'tel'              => post('tel'),
        'responsable_nom'  => post('responsable_nom'),
        'responsable_mail' => post('responsable_mail'),
        'responsable_tel'  => post('responsable_tel'),
        'est_benevole'     => post('est_benevole') === '1' ? '1' : '0',
        'a_consenti'       => post('a_consenti') === '1' ? '1' : '0',
    ];
}

/** Vrai si le texte ressemble à un numéro de téléphone (chiffres, espaces, + . - et parenthèses). */
function telephone_valide(string $tel): bool
{
    return preg_match('/^[0-9+ .()\-]{6,20}$/', $tel) === 1;
}

/**
 * Vérifie la fiche d'un adhérent.
 *
 * @param array<string, string> $saisie
 * @return list<string> messages d'erreur (vide si tout va bien)
 */
function erreurs_adherent(array $saisie): array
{
    $erreurs = [];
    if ($saisie['nom'] === '' || mb_strlen($saisie['nom']) > 100) {
        $erreurs[] = 'Le nom est obligatoire (100 caractères au maximum).';
    }
    if ($saisie['prenom'] === '' || mb_strlen($saisie['prenom']) > 100) {
        $erreurs[] = 'Le prénom est obligatoire (100 caractères au maximum).';
    }
    if (!array_key_exists($saisie['sexe'], libelles_sexes())) {
        $erreurs[] = 'Le sexe est obligatoire.';
    }

    $naissance_correcte = date_valide($saisie['date_naissance'])
        && $saisie['date_naissance'] <= date('Y-m-d')
        && $saisie['date_naissance'] >= '1900-01-01';
    if (!$naissance_correcte) {
        $erreurs[] = 'La date de naissance est obligatoire et ne peut pas être dans le futur.';
    }

    if ($saisie['nationalite_iso3'] !== '' && !array_key_exists($saisie['nationalite_iso3'], pays_liste())) {
        $erreurs[] = 'La nationalité choisie n\'existe pas dans la liste des pays.';
    }
    if ($saisie['mail'] !== '' && (filter_var($saisie['mail'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($saisie['mail']) > 150)) {
        $erreurs[] = 'L\'adresse e-mail n\'est pas valide.';
    }
    if ($saisie['tel'] !== '' && !telephone_valide($saisie['tel'])) {
        $erreurs[] = 'Le téléphone n\'est pas valide (chiffres, espaces et + seulement).';
    }

    // Responsable : obligatoire pour un mineur.
    if (mb_strlen($saisie['responsable_nom']) > 150) {
        $erreurs[] = 'Le nom du responsable est trop long (150 caractères au maximum).';
    }
    if ($saisie['responsable_mail'] !== '' && (filter_var($saisie['responsable_mail'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($saisie['responsable_mail']) > 150)) {
        $erreurs[] = 'L\'adresse e-mail du responsable n\'est pas valide.';
    }
    if ($saisie['responsable_tel'] !== '' && !telephone_valide($saisie['responsable_tel'])) {
        $erreurs[] = 'Le téléphone du responsable n\'est pas valide.';
    }
    if ($naissance_correcte && est_mineur($saisie['date_naissance'])) {
        if ($saisie['responsable_nom'] === '') {
            $erreurs[] = 'Cette personne est mineure : le nom de son responsable adulte est obligatoire.';
        }
        if ($saisie['responsable_mail'] === '' && $saisie['responsable_tel'] === '') {
            $erreurs[] = 'Cette personne est mineure : indiquez le téléphone ou l\'adresse e-mail de son responsable.';
        }
    }
    return $erreurs;
}

/**
 * Adhérents qui portent déjà ce nom, ce prénom et cette date de naissance.
 *
 * @return list<array<string, mixed>>
 */
function adherent_doublons(string $nom, string $prenom, string $date_naissance, int $sauf_id = 0): array
{
    return db_lignes(
        'SELECT a.id, a.nom, a.prenom, f.nom AS famille_nom
           FROM adherents a
           JOIN familles f ON f.id = a.famille_id
          WHERE a.nom = ? AND a.prenom = ? AND a.date_naissance = ? AND a.id <> ? AND a.anonymise_le IS NULL',
        [$nom, $prenom, $date_naissance, $sauf_id]
    );
}

/** Renvoie null pour un texte vide (colonnes facultatives de la base). */
function nul_si_vide(string $valeur): ?string
{
    return $valeur === '' ? null : $valeur;
}

/**
 * Crée un adhérent dans une famille.
 *
 * @param array<string, string> $saisie fiche déjà vérifiée
 * @return int identifiant du nouvel adhérent
 */
function creer_adherent(int $famille_id, array $saisie): int
{
    $consent = $saisie['a_consenti'] === '1';
    db_executer(
        'INSERT INTO adherents (famille_id, nom, prenom, sexe, date_naissance, nationalite_iso3, mail, tel,
                                responsable_nom, responsable_mail, responsable_tel,
                                est_benevole, a_consenti, consentement_le, creation_par)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $famille_id,
            $saisie['nom'],
            $saisie['prenom'],
            $saisie['sexe'],
            $saisie['date_naissance'],
            nul_si_vide($saisie['nationalite_iso3']),
            nul_si_vide($saisie['mail']),
            nul_si_vide($saisie['tel']),
            nul_si_vide($saisie['responsable_nom']),
            nul_si_vide($saisie['responsable_mail']),
            nul_si_vide($saisie['responsable_tel']),
            (int) $saisie['est_benevole'],
            $consent ? 1 : 0,
            $consent ? date('Y-m-d H:i:s') : null,
            entier($_SESSION['utilisateur_id'] ?? 0),
        ]
    );
    $adherent_id = entier(db()->lastInsertId());
    journaliser('creation', 'adherents', $adherent_id, 'Création de l\'adhérent ' . $saisie['prenom'] . ' ' . $saisie['nom'], null, $saisie);
    return $adherent_id;
}

/**
 * Modifie la fiche d'un adhérent.
 *
 * @param array<string, mixed>  $actuel fiche en base avant la modification
 * @param array<string, string> $saisie fiche déjà vérifiée
 * @return bool false s'il n'y avait rien à modifier
 */
function modifier_adherent(array $actuel, array $saisie): bool
{
    $ancien = [];
    foreach (array_keys($saisie) as $champ) {
        $ancien[$champ] = texte($actuel[$champ] ?? '');
    }
    if ($ancien === $saisie) {
        return false;
    }

    // La date du consentement est posée quand il est donné, effacée quand il est retiré.
    $consent = $saisie['a_consenti'] === '1';
    $consentement_le = null;
    if ($consent) {
        $consentement_le = $ancien['a_consenti'] === '1' && texte($actuel['consentement_le'] ?? '') !== ''
            ? texte($actuel['consentement_le'])
            : date('Y-m-d H:i:s');
    }

    $adherent_id = entier($actuel['id'] ?? 0);
    db_executer(
        'UPDATE adherents
            SET nom = ?, prenom = ?, sexe = ?, date_naissance = ?, nationalite_iso3 = ?, mail = ?, tel = ?,
                responsable_nom = ?, responsable_mail = ?, responsable_tel = ?,
                est_benevole = ?, a_consenti = ?, consentement_le = ?, modification_par = ?
          WHERE id = ?',
        [
            $saisie['nom'],
            $saisie['prenom'],
            $saisie['sexe'],
            $saisie['date_naissance'],
            nul_si_vide($saisie['nationalite_iso3']),
            nul_si_vide($saisie['mail']),
            nul_si_vide($saisie['tel']),
            nul_si_vide($saisie['responsable_nom']),
            nul_si_vide($saisie['responsable_mail']),
            nul_si_vide($saisie['responsable_tel']),
            (int) $saisie['est_benevole'],
            $consent ? 1 : 0,
            $consentement_le,
            entier($_SESSION['utilisateur_id'] ?? 0),
            $adherent_id,
        ]
    );
    journaliser('modification', 'adherents', $adherent_id, 'Modification de l\'adhérent ' . $saisie['prenom'] . ' ' . $saisie['nom'], $ancien, $saisie);
    return true;
}

/**
 * Recherche d'adhérents par nom, prénom, famille, courriel ou téléphone.
 * Chaque mot saisi doit être trouvé dans l'un de ces champs.
 *
 * @param bool $saison_seule true : seulement les adhérents inscrits dans un dossier de la saison
 * @return array{total: int, lignes: list<array<string, mixed>>}
 */
function adherents_rechercher(string $recherche, int $saison_id, bool $saison_seule, int $page = 1): array
{
    $conditions = ['a.anonymise_le IS NULL'];
    $parametres = [];
    if ($saison_seule) {
        $conditions[] = 'da.adherent_id IS NOT NULL';
    }
    $mots = preg_split('/\s+/', $recherche, -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($mots === false ? [] : $mots, 0, 5) as $mot) {
        // % et _ sont des caractères spéciaux de LIKE : on les neutralise.
        $motif = '%' . addcslashes($mot, '%_\\') . '%';
        $conditions[] = '(a.nom LIKE ? OR a.prenom LIKE ? OR f.nom LIKE ? OR a.mail LIKE ? OR a.tel LIKE ?)';
        array_push($parametres, $motif, $motif, $motif, $motif, $motif);
    }

    $base = ' FROM adherents a
              JOIN familles f ON f.id = a.famille_id
              LEFT JOIN dossiers d ON d.famille_id = a.famille_id AND d.saison_id = ?
              LEFT JOIN dossiers_adherents da ON da.dossier_id = d.id AND da.adherent_id = a.id
             WHERE ' . implode(' AND ', $conditions);
    array_unshift($parametres, $saison_id);

    $total = entier(db_valeur('SELECT COUNT(*)' . $base, $parametres));

    array_push($parametres, ADHERENTS_PAR_PAGE, (max(1, $page) - 1) * ADHERENTS_PAR_PAGE);
    $lignes = db_lignes(
        'SELECT a.id, a.nom, a.prenom, a.date_naissance, a.mail, a.tel, a.famille_id, f.nom AS famille_nom,
                d.id AS dossier_id, da.code_postal, da.ville, (da.adherent_id IS NOT NULL) AS dans_saison'
        . $base . ' ORDER BY a.nom, a.prenom, a.id LIMIT ? OFFSET ?',
        $parametres
    );
    return ['total' => $total, 'lignes' => $lignes];
}

/**
 * Affiche les champs de la fiche adhérent (création et modification).
 * Le formulaire doit contenir <input type="hidden" name="formulaire" value="adherent">
 * pour que les cases à cocher soient réaffichées correctement après une erreur.
 *
 * @param array<string, mixed> $defauts valeurs affichées quand rien n'a été saisi
 */
function afficher_champs_adherent(array $defauts): void
{
    $reaffichage = ancien('formulaire') === 'adherent';
    $coche = static function (string $champ) use ($reaffichage, $defauts): string {
        $oui = $reaffichage ? ancien($champ) === '1' : entier($defauts[$champ] ?? 0) === 1;
        return $oui ? 'checked' : '';
    };
    $sexe = ancien('sexe', $defauts['sexe'] ?? '');
    $nationalite = ancien('nationalite_iso3', $defauts['nationalite_iso3'] ?? '');
    ?>
    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label class="form-label" for="nom">Nom</label>
            <input class="form-control" type="text" id="nom" name="nom" maxlength="100" value="<?= e(ancien('nom', $defauts['nom'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="prenom">Prénom</label>
            <input class="form-control" type="text" id="prenom" name="prenom" maxlength="100" value="<?= e(ancien('prenom', $defauts['prenom'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="sexe">Sexe</label>
            <select class="form-select" id="sexe" name="sexe" required>
                <option value="">Choisir…</option>
                <?php foreach (libelles_sexes() as $code_sexe => $libelle_sexe) : ?>
                    <option value="<?= e($code_sexe) ?>" <?= $sexe === $code_sexe ? 'selected' : '' ?>><?= e($libelle_sexe) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="date_naissance">Date de naissance</label>
            <input class="form-control" type="date" id="date_naissance" name="date_naissance" max="<?= e(date('Y-m-d')) ?>" value="<?= e(ancien('date_naissance', $defauts['date_naissance'] ?? '')) ?>" required>
        </div>
        <div class="col-sm-4">
            <label class="form-label" for="nationalite_iso3">Nationalité <span class="text-body-secondary">(facultatif)</span></label>
            <select class="form-select" id="nationalite_iso3" name="nationalite_iso3">
                <option value="">Non renseignée</option>
                <?php foreach (pays_liste() as $code_pays => $nom_pays) : ?>
                    <option value="<?= e($code_pays) ?>" <?= $nationalite === $code_pays ? 'selected' : '' ?>><?= e($nom_pays) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="mail">Adresse e-mail <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="email" id="mail" name="mail" maxlength="150" value="<?= e(ancien('mail', $defauts['mail'] ?? '')) ?>">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="tel">Téléphone <span class="text-body-secondary">(facultatif)</span></label>
            <input class="form-control" type="tel" id="tel" name="tel" maxlength="20" value="<?= e(ancien('tel', $defauts['tel'] ?? '')) ?>">
        </div>
    </div>
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="est_benevole" name="est_benevole" value="1" <?= $coche('est_benevole') ?>>
        <label class="form-check-label" for="est_benevole">Bénévole de l'association</label>
    </div>
    <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" id="a_consenti" name="a_consenti" value="1" <?= $coche('a_consenti') ?>>
        <label class="form-check-label" for="a_consenti">Accepte de recevoir les communications de l'association (programme, informations)</label>
    </div>

    <h2 class="h5">Responsable adulte</h2>
    <p class="text-body-secondary small">Obligatoire pour une personne de moins de <?= AGE_MAJORITE ?> ans : le nom, et le téléphone ou l'adresse e-mail.</p>
    <div class="row g-3 mb-4">
        <div class="col-sm-12">
            <label class="form-label" for="responsable_nom">Nom et prénom du responsable</label>
            <input class="form-control" type="text" id="responsable_nom" name="responsable_nom" maxlength="150" value="<?= e(ancien('responsable_nom', $defauts['responsable_nom'] ?? '')) ?>">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="responsable_mail">Adresse e-mail du responsable</label>
            <input class="form-control" type="email" id="responsable_mail" name="responsable_mail" maxlength="150" value="<?= e(ancien('responsable_mail', $defauts['responsable_mail'] ?? '')) ?>">
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="responsable_tel">Téléphone du responsable</label>
            <input class="form-control" type="tel" id="responsable_tel" name="responsable_tel" maxlength="20" value="<?= e(ancien('responsable_tel', $defauts['responsable_tel'] ?? '')) ?>">
        </div>
    </div>
    <?php
}
