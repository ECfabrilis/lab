<?php

declare(strict_types=1);

// Mise à jour de la base (administrateurs seulement) : après l'installation
// d'une nouvelle version, applique les migrations de sql/migrations/ qui n'ont
// pas encore été passées. Une sauvegarde est toujours faite juste avant.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

if (est_post()) {
    csrf_verifier();
    if (post('action') === 'mettre_a_jour' && migrations_en_attente() !== []) {
        set_time_limit(300);
        $utilisateur_id = entier($_SESSION['utilisateur_id'] ?? 0);

        // Sauvegarde obligatoire : sans elle, la mise à jour ne démarre pas.
        try {
            $nom_sauvegarde = sauvegarde_creer();
            parametre_enregistrer('derniere_sauvegarde', date('Y-m-d H:i:s'), $utilisateur_id);
            journaliser('sauvegarde', 'sauvegardes', null, 'Création de la sauvegarde ' . $nom_sauvegarde . ' (avant mise à jour)');
        } catch (Throwable $exception) {
            journal_fichier('mise_a_jour.log', 'Sauvegarde avant mise à jour : ' . $exception->getMessage());
            flash('erreur', 'La sauvegarde a échoué : la mise à jour n\'a pas été lancée. ' . $exception->getMessage());
            rediriger('mise_a_jour.php');
        }

        $bilan = mise_a_jour_appliquer($utilisateur_id);
        $nombre = count($bilan['appliquees']);
        if ($bilan['erreur'] === '') {
            flash('succes', 'La base est à jour : ' . $nombre . ' migration' . ($nombre > 1 ? 's appliquées' : ' appliquée') . '. Sauvegarde faite avant : ' . $nom_sauvegarde . '.');
        } else {
            flash('erreur', 'La migration ' . $bilan['fichier_en_erreur'] . ' a échoué : ' . $bilan['erreur']);
            flash('avertissement', 'La mise à jour est interrompue (' . $nombre . ' migration' . ($nombre > 1 ? 's passées' : ' passée') . ' avant l\'erreur). '
                . 'Corrigez puis relancez, ou restaurez la sauvegarde ' . $nom_sauvegarde . '.');
        }
    }
    rediriger('mise_a_jour.php');
}

$migrations_attente = migrations_en_attente();
$migrations_passees = migrations_passees();
if ($migrations_attente === [] && $migrations_passees !== [] && base_en_retard()) {
    // Tout est passé mais le réglage ne le dit pas (base restaurée, par exemple) : on le remet d'accord.
    migrations_noter_a_jour(db());
    parametres_tous(true);
}

$titre_page = 'Mise à jour';
// Base en retard : page réduite, sans le menu ni la saison (l'en-tête complet
// lit des tables que la mise à jour peut modifier).
$page_publique = $migrations_attente !== [];
require DOSSIER_PRIVE . '/includes/header.php';
?>
<?php if ($migrations_attente !== []) : ?>
<div class="mx-auto px-3 messages-publics">
    <h1 class="h4 mb-3">Mise à jour de la base</h1>
    <p>Une nouvelle version de l'application est installée. La base doit être mise à jour avant de continuer : l'application est fermée aux autres utilisateurs en attendant.</p>
    <ul class="list-group text-start mb-3">
        <?php foreach ($migrations_attente as $fichier_migration) : ?>
            <li class="list-group-item"><code><?= e($fichier_migration) ?></code></li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(chemin_site('mise_a_jour.php')) ?>">
        <?= csrf_champ() ?>
        <button class="btn btn-primary w-100 py-2" type="submit" name="action" value="mettre_a_jour">Sauvegarder puis mettre à jour</button>
    </form>
    <p class="small text-body-secondary mt-3">Une sauvegarde de la base est faite juste avant. L'opération peut prendre une minute : ne fermez pas la page.</p>
    <p class="small mb-0"><a href="<?= e(chemin_site('sauvegarde.php')) ?>">Voir les sauvegardes</a></p>
</div>
<?php else : ?>
<h1 class="h3 mb-4">Mise à jour de la base</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Après l'installation d'une nouvelle version, l'application demande d'elle-même la mise à jour de la base au premier administrateur qui se connecte.</li>
            <li>Une sauvegarde est toujours faite juste avant.</li>
            <li>Pendant ce temps, l'application est fermée aux autres utilisateurs.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <div class="alert alert-success" role="alert">La base est à jour (version <?= e(APP_VERSION) ?>).</div>
        <?php if ($migrations_passees !== []) : ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Migration</th>
                            <th scope="col">Date</th>
                            <th scope="col">Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($migrations_passees as $migration_passee) : ?>
                            <tr>
                                <td><code><?= e($migration_passee['fichier']) ?></code></td>
                                <td><?= e($migration_passee['est_reprise'] ? 'déjà en place' : date_heure_fr($migration_passee['appliquee_le'])) ?></td>
                                <td><?= e($migration_passee['est_reprise'] ? '' : $migration_passee['prenom']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
