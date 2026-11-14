<?php

declare(strict_types=1);

// Données de référence (administrateurs seulement) : pays, quartiers, QP, QPV,
// codes postaux. Les codes postaux et les quartiers se mettent à jour à tout
// moment, par l'envoi d'un fichier ou à partir des fichiers fournis avec l'application.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';
require_once DOSSIER_PRIVE . '/includes/fonctions_installation.php';

exiger_role('admin');

$tables_quartiers = reference_tables_quartiers();

if (est_post()) {
    csrf_verifier();
    $action = post('action');
    $table = post('table');
    // Un gros fichier demande plus de temps que la durée habituelle d'une page.
    set_time_limit(300);

    try {
        // --- Codes postaux : fichier CSV de La Poste -----------------------
        if ($action === 'codes_postaux_fichier' || $action === 'codes_postaux_origine') {
            $avant = entier(db_valeur('SELECT COUNT(*) FROM codes_postaux'));
            $chemin = $action === 'codes_postaux_fichier' ? fichier_envoye('fichier') : '';
            if ($chemin !== '') {
                // Vérification du format avant de vider la table : la 2e ligne doit contenir un code postal en 3e colonne.
                $apercu = fopen($chemin, 'r');
                $ligne_donnees = '';
                if ($apercu !== false) {
                    fgets($apercu);
                    $ligne_donnees = (string) fgets($apercu);
                    fclose($apercu);
                }
                $colonnes_apercu = explode(';', trim($ligne_donnees));
                if (count($colonnes_apercu) < 4 || preg_match('/^\d{5}$/', $colonnes_apercu[2]) !== 1) {
                    throw new RuntimeException('Ce fichier n\'a pas le format attendu : fichier CSV de La Poste, séparé par des points-virgules (code INSEE ; commune ; code postal ; libellé d\'acheminement ; ligne 5).');
                }
            }
            installation_importer_codes_postaux(db(), $chemin, true);
            $apres = entier(db_valeur('SELECT COUNT(*) FROM codes_postaux'));
            journaliser('reference', 'codes_postaux', null, 'Mise à jour des codes postaux', ['lignes' => $avant], ['lignes' => $apres]);
            flash('succes', 'Les codes postaux sont mis à jour : ' . $apres . ' lignes (' . $avant . ' auparavant).');
            rediriger('donnees_reference.php');
        }

        // --- Quartiers, QP, QPV : fichier GeoJSON ---------------------------
        // « Ajouter à la table » n'existe que pour les quartiers : ceux du fichier
        // s'ajoutent à ceux déjà présents (d'autres communes, par exemple).
        $ajout = $action === 'quartiers_ajouter' && $table === 'quartiers';
        if (($action === 'quartiers_fichier' || $ajout) && isset($tables_quartiers[$table])) {
            $avant = entier(db_valeur('SELECT COUNT(*) FROM `' . $table . '`'));
            $proprietes = [];
            foreach (array_keys($tables_quartiers[$table]['colonnes']) as $colonne) {
                $proprietes[$colonne] = post('propriete_' . $colonne);
            }
            $bilan = reference_importer_geojson($table, fichier_envoye('fichier'), $proprietes, $ajout);
            $apres = entier(db_valeur('SELECT COUNT(*) FROM `' . $table . '`'));
            journaliser('reference', $table, null, $ajout ? 'Ajout par un fichier GeoJSON' : 'Mise à jour par un fichier GeoJSON', ['lignes' => $avant], ['lignes' => $apres]);
            if ($ajout) {
                flash('succes', 'Quartiers : ' . $bilan['ajoutes'] . ' ajouté(s), ' . $bilan['mis_a_jour'] . ' déjà présent(s) dont le contour est mis à jour. La table compte ' . $apres . ' lignes.');
                if ($bilan['ajoutes'] > 0) {
                    flash('info', 'Une nouvelle commune ou un nouvel arrondissement est pris en compte d\'office ; un quartier ajouté à une commune ou à un arrondissement déjà présent suit son choix. Ce choix se modifie dans Paramètres > Options > Quartiers.');
                }
            } else {
                flash('succes', $tables_quartiers[$table]['libelle'] . ' : ' . $apres . ' lignes enregistrées (' . $avant . ' auparavant). Les adresses déjà saisies gardent leur quartier.');
            }
            rediriger('donnees_reference.php');
        }

        // --- Quartiers, QP, QPV : retour aux données fournies avec l'application
        if ($action === 'quartiers_origine' && isset($tables_quartiers[$table])) {
            $avant = entier(db_valeur('SELECT COUNT(*) FROM `' . $table . '`'));
            $apres = reference_retablir($table);
            journaliser('reference', $table, null, 'Retour aux données d\'origine', ['lignes' => $avant], ['lignes' => $apres]);
            flash('succes', $tables_quartiers[$table]['libelle'] . ' : données d\'origine rétablies (' . $apres . ' lignes).');
            rediriger('donnees_reference.php');
        }
    } catch (RuntimeException $erreur) {
        flash('erreur', $erreur->getMessage());
        ancien_memoriser();
    }
    rediriger('donnees_reference.php');
}

$nombres = reference_compter();

$titre_page = 'Données de référence';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Données de référence</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Une mise à jour <strong>remplace</strong> tout le contenu de la table (sauf « Ajouter à la table », pour les quartiers). En cas d'erreur dans le fichier, rien n'est modifié.</li>
            <li>Les adresses déjà saisies gardent le quartier calculé le jour de leur saisie.</li>
            <li>Faites une sauvegarde avant une mise à jour.</li>
            <li>Les pays ne se modifient pas ici : ils sont liés à la nationalité des adhérents.</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <div class="table-responsive mb-4">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Table</th>
                        <th scope="col" class="text-end">Lignes</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Pays</td><td class="text-end"><?= $nombres['pays'] ?></td></tr>
                    <?php foreach ($tables_quartiers as $nom_table => $definition) : ?>
                        <tr><td><?= e($definition['libelle']) ?></td><td class="text-end"><?= $nombres[$nom_table] ?? 0 ?></td></tr>
                    <?php endforeach; ?>
                    <tr><td>Codes postaux</td><td class="text-end"><?= $nombres['codes_postaux'] ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="border rounded p-3 mb-4">
            <h2 class="h5">Codes postaux</h2>
            <p class="text-body-secondary small">Fichier CSV « Base officielle des codes postaux » de La Poste (séparateur point-virgule).</p>
            <form method="post" action="<?= e(chemin_site('donnees_reference.php')) ?>" enctype="multipart/form-data">
                <?= csrf_champ() ?>
                <div class="mb-3">
                    <label class="form-label" for="fichier_cp">Nouveau fichier</label>
                    <input class="form-control" type="file" id="fichier_cp" name="fichier" accept=".csv,text/csv" required>
                </div>
                <button class="btn btn-primary" type="submit" name="action" value="codes_postaux_fichier">Remplacer les codes postaux</button>
            </form>
            <form class="mt-2" method="post" action="<?= e(chemin_site('donnees_reference.php')) ?>">
                <?= csrf_champ() ?>
                <button class="btn btn-link px-0" type="submit" name="action" value="codes_postaux_origine">Rétablir le fichier fourni avec l'application</button>
            </form>
        </div>

        <?php foreach ($tables_quartiers as $nom_table => $definition) : ?>
            <div class="border rounded p-3 mb-4">
                <h2 class="h5"><?= e($definition['libelle']) ?></h2>
                <p class="text-body-secondary small">Fichier GeoJSON de contours (polygones, coordonnées en longitude / latitude). Indiquez le nom des propriétés du fichier ; laissez vide une information absente.</p>
                <form method="post" action="<?= e(chemin_site('donnees_reference.php')) ?>" enctype="multipart/form-data">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="table" value="<?= e($nom_table) ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-12">
                            <label class="form-label" for="fichier_<?= e($nom_table) ?>">Nouveau fichier</label>
                            <input class="form-control" type="file" id="fichier_<?= e($nom_table) ?>" name="fichier" accept=".geojson,.json,application/geo+json,application/json" required>
                        </div>
                        <?php foreach ($definition['colonnes'] as $colonne => $propriete_defaut) : ?>
                            <div class="col-sm-6">
                                <label class="form-label" for="propriete_<?= e($nom_table . '_' . $colonne) ?>"><?= e(reference_libelle_colonne($colonne)) ?> : propriété</label>
                                <input class="form-control" type="text" id="propriete_<?= e($nom_table . '_' . $colonne) ?>" name="propriete_<?= e($colonne) ?>" maxlength="60"
                                       value="<?= e(ancien('table') === $nom_table ? ancien('propriete_' . $colonne, $propriete_defaut) : $propriete_defaut) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($nom_table === 'quartiers') : ?>
                        <button class="btn btn-primary" type="submit" name="action" value="quartiers_fichier">Remplacer la table</button>
                        <button class="btn btn-outline-primary" type="submit" name="action" value="quartiers_ajouter">Ajouter à la table</button>
                        <div class="form-text">« Ajouter » garde les quartiers déjà présents : pour un quartier de même commune, arrondissement et nom, seul le contour est mis à jour.
                            Le choix des communes et arrondissements pris en compte se fait dans <a href="<?= e(chemin_site('quartiers.php')) ?>">Options &gt; Quartiers</a> ; « Remplacer » les prend tous en compte.</div>
                    <?php else : ?>
                        <button class="btn btn-primary" type="submit" name="action" value="quartiers_fichier">Remplacer cette table</button>
                    <?php endif; ?>
                </form>
                <form class="mt-2" method="post" action="<?= e(chemin_site('donnees_reference.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="table" value="<?= e($nom_table) ?>">
                    <button class="btn btn-link px-0" type="submit" name="action" value="quartiers_origine">Rétablir les données fournies avec l'application</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
