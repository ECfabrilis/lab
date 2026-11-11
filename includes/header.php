<?php

declare(strict_types=1);

/**
 * En-tête commun des pages. À inclure après le traitement du formulaire.
 *
 * La page définit avant l'inclusion :
 *   $titre_page    (string) titre affiché dans l'onglet ;
 *   $page_publique (bool, facultatif) true pour les pages sans connexion
 *                  (connexion, mot de passe oublié, choix du mot de passe).
 *
 * Variables posées ici, à ne pas réutiliser dans les pages :
 *   $u, $menu, $logo, $nom_asso, $saison, $saisons, $libelles_roles, $messages,
 *   $retour_saison.
 *
 * @var string $titre_page
 */

$page_publique = isset($page_publique) && $page_publique === true;

$u = $page_publique ? null : utilisateur_courant();
$nom_asso = parametre('nom_association', 'Association');
$logo = chemin_site(parametre('logo', 'img/logo-cefia75.png'));
$libelles_roles = libelles_roles();
$saisons = $u === null ? [] : saisons_liste();
$saison = $u === null ? null : saison_courante();
$messages = flash_lire();

// Page où revenir après un changement de saison : la page en cours, avec son identifiant.
$retour_saison = page_courante();
foreach (['id', 'famille'] as $parametre_retour) {
    if (entier($_GET[$parametre_retour] ?? 0) > 0) {
        $retour_saison .= '?' . $parametre_retour . '=' . entier($_GET[$parametre_retour] ?? 0);
        break;
    }
}

// Menu principal : chaque entrée est visible à partir d'un rôle minimal.
$menu = [];
foreach ([
    ['page' => 'index.php', 'libelle' => 'Accueil', 'role' => 'lecteur'],
    ['page' => 'adherents.php', 'libelle' => 'Adhérents', 'role' => 'lecteur'],
    ['page' => 'utilisateurs.php', 'libelle' => 'Utilisateurs', 'role' => 'admin'],
    ['page' => 'saisons.php', 'libelle' => 'Saisons', 'role' => 'admin'],
    ['page' => 'parametres.php', 'libelle' => 'Paramètres', 'role' => 'admin'],
    ['page' => 'sauvegarde.php', 'libelle' => 'Sauvegarde', 'role' => 'admin'],
] as $entree_menu) {
    if ($u !== null && role_au_moins($entree_menu['role'])) {
        $menu[] = $entree_menu;
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titre_page) ?> — <?= e($nom_asso) ?></title>
    <link rel="icon" href="<?= e(chemin_site('img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(chemin_site('css/bddv3.css')) ?>">
<?php if ($page_publique) : ?>
    <link rel="stylesheet" href="<?= e(chemin_site('css/connexion.css')) ?>">
<?php endif; ?>
</head>
<?php if ($page_publique) : ?>
<body class="d-flex align-items-center py-4 bg-body-tertiary">
<main class="w-100 m-auto text-center">
    <img class="mb-3" src="<?= e($logo) ?>" alt="<?= e($nom_asso) ?>" height="75">
    <div class="mx-auto px-3 messages-publics">
        <?php foreach ($messages as $message_flash) : ?>
            <div class="alert alert-<?= e($message_flash['classe']) ?>" role="alert"><?= e($message_flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php else : ?>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom mb-4">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(chemin_site('index.php')) ?>">
            <img src="<?= e($logo) ?>" alt="" height="40">
            <span><?= e($nom_asso) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Afficher le menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu as $entree_menu) : ?>
                    <li class="nav-item">
                        <a class="nav-link <?= page_courante() === $entree_menu['page'] ? 'active' : '' ?>" href="<?= e(chemin_site($entree_menu['page'])) ?>"><?= e($entree_menu['libelle']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($saisons !== []) : ?>
                <form class="d-flex align-items-center gap-2 me-lg-3 my-2 my-lg-0" method="post" action="<?= e(chemin_site('changer_saison.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="retour" value="<?= e($retour_saison) ?>">
                    <label class="form-label mb-0 text-nowrap" for="choixSaison">Saison</label>
                    <select class="form-select form-select-sm" id="choixSaison" name="saison_id" data-envoi-auto>
                        <?php foreach ($saisons as $saison_menu) : ?>
                            <option value="<?= e($saison_menu['id']) ?>" <?= $saison !== null && entier($saison['id']) === entier($saison_menu['id']) ? 'selected' : '' ?>><?= e($saison_menu['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary" type="submit" data-masquer-si-js>OK</button>
                </form>
            <?php endif; ?>

            <?php if ($u !== null) : ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= e($u['prenom']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text text-body-secondary small"><?= e($libelles_roles[texte($u['role'])] ?? '') ?></span></li>
                        <li><a class="dropdown-item" href="<?= e(chemin_site('mon_compte.php')) ?>">Mon compte</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(chemin_site('deconnexion.php')) ?>">
                                <?= csrf_champ() ?>
                                <button class="dropdown-item" type="submit">Se déconnecter</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container pb-5">
    <?php foreach ($messages as $message_flash) : ?>
        <div class="alert alert-<?= e($message_flash['classe']) ?>" role="alert"><?= e($message_flash['message']) ?></div>
    <?php endforeach; ?>
<?php endif; ?>
