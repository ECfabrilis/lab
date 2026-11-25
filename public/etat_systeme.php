<?php

declare(strict_types=1);

// État du système (administrateurs seulement) : versions, contrôles du serveur
// et protection de la partie privée. Les mêmes contrôles que ceux de
// l'assistant d'installation, consultables à tout moment (après un changement
// d'hébergement, ou pour décrire l'installation à la personne qui dépanne).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$controles_systeme = systeme_controles();
$serveur_base = systeme_base(db());
$adresse_site = texte(config('APP_URL'));
$derniere_sauvegarde = parametre('derniere_sauvegarde');

// Lignes du tableau : libellé, valeur, remarque éventuelle (affichée en avertissement).
$lignes_etat = [
    ['Application', 'Version ' . APP_VERSION, ''],
    ['PHP', PHP_VERSION, ''],
    ['Base de données', $serveur_base['texte'], match (true) {
        !$serveur_base['est_mariadb']  => 'Ce serveur n\'est pas MariaDB : l\'application n\'est pas prévue pour lui.',
        $serveur_base['est_ancienne'] => 'Version antérieure à MariaDB ' . SYSTEME_MARIADB_CONSEILLEE . ' : l\'application n\'y a pas été essayée.',
        default                       => '',
    }],
    ['Structure de la base', base_en_retard() ? 'mise à jour en attente' : 'à jour', ''],
    ['Adresse du site', $adresse_site, systeme_adresse_sans_https($adresse_site)
        ? 'L\'adresse n\'est pas en https : les mots de passe et les données circulent en clair.' : ''],
    ['Dernière sauvegarde', $derniere_sauvegarde !== '' ? date_heure_fr($derniere_sauvegarde) : 'jamais', sauvegarde_en_retard()
        ? 'Faites une sauvegarde au moins une fois par semaine.' : ''],
];

$titre_page = 'État du système';
$scripts_page = ['protection.js'];
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">État du système</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('mise_a_jour.php')) ?>">Mise à jour de la base</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('sauvegarde.php')) ?>">Sauvegarde</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Consultez cette page après un changement d'hébergement ou de réglage du domaine.</li>
            <li>En cas de problème, ces informations aident la personne qui vous dépanne. La page ne contient aucun mot de passe.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <h2 class="h5">Protection de la partie privée</h2>
        <div class="mb-2" data-protection-url="<?= e(chemin_site('../includes/temoin_protection.txt')) ?>" data-protection-marqueur="<?= e(PROTECTION_MARQUEUR) ?>">
            <div class="alert alert-secondary mb-0" role="status" data-protection-etat="attente">Contrôle en cours…</div>
            <div class="alert alert-success mb-0 d-none" role="status" data-protection-etat="ok">La partie privée (réglages, journaux, sauvegardes) n'est pas visible depuis l'adresse du site.</div>
            <div class="alert alert-warning mb-0 d-none" role="status" data-protection-etat="inconnu">Le contrôle n'a pas pu se faire (réseau). Rechargez la page.</div>
            <div class="alert alert-danger mb-0 d-none" role="alert" data-protection-etat="expose">
                <p><strong>La partie privée de l'application est visible depuis le web.</strong> Les réglages, les journaux et les sauvegardes de la base peuvent être lus par n'importe qui.</p>
                <p class="mb-0">À corriger sans attendre : faire pointer le domaine sur le dossier <code>public/</code> de l'application, ou remettre à la racine de l'application le fichier <code>.htaccess</code> fourni (il interdit tout sauf <code>public/</code>).</p>
            </div>
        </div>
        <p class="small text-body-secondary mb-4">Ce contrôle porte sur l'adresse du site. Si le dossier de l'application est aussi joignable par une autre adresse (le domaine principal de l'hébergement, par exemple), ouvrez-y <code>config.exemple.php</code> : la réponse doit être « 403 Forbidden ».</p>

        <h2 class="h5">Versions et réglages</h2>
        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <tbody>
                    <?php foreach ($lignes_etat as [$libelle_etat, $valeur_etat, $remarque_etat]) : ?>
                        <tr>
                            <th scope="row" class="fw-normal text-body-secondary"><?= e($libelle_etat) ?></th>
                            <td>
                                <?= e($valeur_etat) ?>
                                <?php if ($remarque_etat !== '') : ?>
                                    <div class="small text-danger"><?= e($remarque_etat) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="h5">Serveur</h2>
        <ul class="list-group">
            <?php foreach ($controles_systeme as $controle_systeme) : ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between gap-3">
                        <span><?= e($controle_systeme['libelle']) ?> <span class="text-body-secondary small">— <?= e($controle_systeme['detail']) ?></span></span>
                        <?php if ($controle_systeme['ok']) : ?>
                            <span class="badge text-bg-success align-self-start">bon</span>
                        <?php elseif ($controle_systeme['obligatoire']) : ?>
                            <span class="badge text-bg-danger align-self-start">à corriger</span>
                        <?php else : ?>
                            <span class="badge text-bg-warning align-self-start">conseillé</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$controle_systeme['ok']) : ?>
                        <div class="small mt-1"><?= e($controle_systeme['conseil']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
