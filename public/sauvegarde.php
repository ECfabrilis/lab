<?php

declare(strict_types=1);

// Sauvegarde de la base (administrateurs seulement) : création, téléchargement, suppression.
// Recommandation : une sauvegarde par semaine, et une copie conservée hors de l'hébergeur.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

// --- Téléchargement d'une sauvegarde (lien de la liste) ----------------------
if (!est_post() && isset($_GET['telecharger'])) {
    $chemin_fichier = sauvegarde_chemin(texte($_GET['telecharger']));
    if ($chemin_fichier === null) {
        flash('erreur', 'Cette sauvegarde n\'existe pas.');
        rediriger('sauvegarde.php');
    }
    journaliser('telechargement', 'sauvegardes', null, 'Téléchargement de la sauvegarde ' . basename($chemin_fichier));
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($chemin_fichier) . '"');
    header('Content-Length: ' . (string) filesize($chemin_fichier));
    readfile($chemin_fichier);
    exit;
}

if (est_post()) {
    csrf_verifier();
    $action = post('action');

    if ($action === 'creer') {
        set_time_limit(300);
        try {
            $nom_fichier = sauvegarde_creer();
            parametre_enregistrer('derniere_sauvegarde', date('Y-m-d H:i:s'), entier($_SESSION['utilisateur_id'] ?? 0));
            journaliser('sauvegarde', 'sauvegardes', null, 'Création de la sauvegarde ' . $nom_fichier);
            flash('succes', 'La sauvegarde ' . $nom_fichier . ' est créée. Téléchargez-la pour en garder une copie hors de l\'hébergeur.');
        } catch (Throwable $exception) {
            journal_fichier('php_erreurs.log', 'Sauvegarde : ' . $exception->getMessage());
            flash('erreur', 'La sauvegarde a échoué : ' . $exception->getMessage());
        }
    }

    if ($action === 'supprimer') {
        $chemin_fichier = sauvegarde_chemin(post('nom'));
        if ($chemin_fichier !== null && unlink($chemin_fichier)) {
            journaliser('suppression', 'sauvegardes', null, 'Suppression de la sauvegarde ' . basename($chemin_fichier));
            flash('succes', 'La sauvegarde ' . basename($chemin_fichier) . ' est supprimée.');
        } else {
            flash('erreur', 'Cette sauvegarde n\'a pas pu être supprimée.');
        }
    }

    rediriger('sauvegarde.php');
}

$fichiers_sauvegarde = sauvegardes_liste();
$derniere_sauvegarde = parametre('derniere_sauvegarde');

$titre_page = 'Sauvegarde';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Sauvegarde de la base</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <form class="d-grid" method="post" action="<?= e(chemin_site('sauvegarde.php')) ?>">
            <?= csrf_champ() ?>
            <button class="btn btn-primary" type="submit" name="action" value="creer">Créer une sauvegarde maintenant</button>
        </form>
        <p class="mt-3 mb-1">
            Dernière sauvegarde :
            <strong><?= e($derniere_sauvegarde !== '' ? date_heure_fr($derniere_sauvegarde) : 'jamais') ?></strong>
        </p>
        <ul class="text-body-secondary small mb-0 ps-3">
            <li>Faites une sauvegarde au moins une fois par semaine.</li>
            <li>Téléchargez-la et gardez une copie hors de l'hébergeur.</li>
            <li>Le fichier contient des données personnelles : rangez-le en lieu sûr.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <?php if ($fichiers_sauvegarde === []) : ?>
            <p class="text-body-secondary">Aucune sauvegarde n'est présente sur le serveur.</p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Fichier</th>
                            <th scope="col">Date</th>
                            <th scope="col">Taille</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fichiers_sauvegarde as $fichier_sauvegarde) : ?>
                            <tr>
                                <td><?= e($fichier_sauvegarde['nom']) ?></td>
                                <td><?= e(date('d/m/Y à H:i', $fichier_sauvegarde['date'])) ?></td>
                                <td><?= e(number_format($fichier_sauvegarde['taille'] / 1048576, 1, ',', ' ')) ?> Mo</td>
                                <td class="text-end">
                                    <form class="d-inline-flex gap-2" method="post" action="<?= e(chemin_site('sauvegarde.php')) ?>">
                                        <?= csrf_champ() ?>
                                        <input type="hidden" name="nom" value="<?= e($fichier_sauvegarde['nom']) ?>">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(chemin_site('sauvegarde.php?telecharger=' . $fichier_sauvegarde['nom'])) ?>">Télécharger</a>
                                        <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="supprimer">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
