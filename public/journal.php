<?php

declare(strict_types=1);

// Journal des actions (administrateurs seulement) : consultation de logs_bdd.
// Les filtres sont dans l'adresse de la page : ils ne contiennent aucune donnée personnelle.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

/** Nombre de lignes du journal par page. */
const JOURNAL_PAR_PAGE = 50;

// --- Filtres ----------------------------------------------------------------
$filtre_du = texte($_GET['du'] ?? '');
$filtre_au = texte($_GET['au'] ?? '');
$filtre_auteur = entier($_GET['auteur'] ?? 0);
$filtre_table = texte($_GET['table'] ?? '');
$numero_page = max(1, entier($_GET['page'] ?? 1));

$tables_journal = array_map(
    static fn (array $ligne): string => texte($ligne['nom_table']),
    db_lignes('SELECT DISTINCT nom_table FROM logs_bdd ORDER BY nom_table')
);
$auteurs = utilisateurs_liste();

$conditions = ['1 = 1'];
$valeurs = [];
if (date_valide($filtre_du)) {
    $conditions[] = 'l.date_action >= ?';
    $valeurs[] = $filtre_du . ' 00:00:00';
} else {
    $filtre_du = '';
}
if (date_valide($filtre_au)) {
    $conditions[] = 'l.date_action <= ?';
    $valeurs[] = $filtre_au . ' 23:59:59';
} else {
    $filtre_au = '';
}
if ($filtre_auteur > 0) {
    $conditions[] = 'l.utilisateur_id = ?';
    $valeurs[] = $filtre_auteur;
}
if (in_array($filtre_table, $tables_journal, true)) {
    $conditions[] = 'l.nom_table = ?';
    $valeurs[] = $filtre_table;
} else {
    $filtre_table = '';
}
$ou = ' FROM logs_bdd l LEFT JOIN utilisateurs u ON u.id = l.utilisateur_id WHERE ' . implode(' AND ', $conditions);

$total = entier(db_valeur('SELECT COUNT(*)' . $ou, $valeurs));
$nb_pages = max(1, (int) ceil($total / JOURNAL_PAR_PAGE));
$lignes = db_lignes(
    'SELECT l.id, l.date_action, l.type_action, l.nom_table, l.id_enregistrement, l.description,
            l.donnee_ancienne, l.donnee_nouvelle, u.prenom AS auteur'
    . $ou . ' ORDER BY l.id DESC LIMIT ? OFFSET ?',
    [...$valeurs, JOURNAL_PAR_PAGE, ($numero_page - 1) * JOURNAL_PAR_PAGE]
);

// Adresse d'une page de résultats, avec les filtres en cours.
$filtres = array_filter(['du' => $filtre_du, 'au' => $filtre_au, 'auteur' => $filtre_auteur > 0 ? (string) $filtre_auteur : '', 'table' => $filtre_table]);
$adresse_page = static fn (int $page): string => chemin_site('journal.php?' . http_build_query($filtres + ['page' => $page]));

/** Présente les données d'une ligne du journal (JSON) de façon lisible. */
$presenter = static function (mixed $json): string {
    $donnees = json_decode(texte($json), true);
    if (!is_array($donnees)) {
        return '';
    }
    $morceaux = [];
    foreach ($donnees as $cle => $valeur) {
        $morceaux[] = $cle . ' : ' . (is_scalar($valeur) || $valeur === null ? texte($valeur) : (string) json_encode($valeur, JSON_UNESCAPED_UNICODE));
    }
    return implode("\n", $morceaux);
};

$titre_page = 'Journal';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Journal des actions</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <form method="get" action="<?= e(chemin_site('journal.php')) ?>">
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label" for="du">Du</label>
                    <input class="form-control" type="date" id="du" name="du" value="<?= e($filtre_du) ?>">
                </div>
                <div class="col-6">
                    <label class="form-label" for="au">Au</label>
                    <input class="form-control" type="date" id="au" name="au" value="<?= e($filtre_au) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="auteur">Utilisateur</label>
                    <select class="form-select" id="auteur" name="auteur">
                        <option value="0">Tous</option>
                        <?php foreach ($auteurs as $auteur) : ?>
                            <option value="<?= entier($auteur['id']) ?>" <?= $filtre_auteur === entier($auteur['id']) ? 'selected' : '' ?>><?= e($auteur['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="table">Données concernées</label>
                    <select class="form-select" id="table" name="table">
                        <option value="">Toutes</option>
                        <?php foreach ($tables_journal as $table_journal) : ?>
                            <option value="<?= e($table_journal) ?>" <?= $filtre_table === $table_journal ? 'selected' : '' ?>><?= e($table_journal) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Filtrer</button>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('journal.php')) ?>">Tout afficher</a>
            </div>
        </form>
        <div class="d-grid mt-4">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
        <p class="text-body-secondary small mt-3 mb-0">Le journal couvre la saison en cours : un mois après le début d'une nouvelle saison, les actions des saisons précédentes sont effacées. Il ne contient ni mot de passe ni jeton.</p>
    </div>
    <div class="col-md-8 order-md-1">
        <p class="text-body-secondary"><?= $total ?> action<?= $total > 1 ? 's' : '' ?></p>
        <?php if ($lignes === []) : ?>
            <div class="alert alert-info" role="alert">Aucune action ne correspond.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-sm align-top">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Utilisateur</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $ligne) : ?>
                            <?php $avant = $presenter($ligne['donnee_ancienne']); ?>
                            <?php $apres = $presenter($ligne['donnee_nouvelle']); ?>
                            <tr>
                                <td class="text-nowrap small"><?= e(date_heure_fr($ligne['date_action'])) ?></td>
                                <td><?= e(texte($ligne['auteur']) !== '' ? $ligne['auteur'] : '—') ?></td>
                                <td>
                                    <span class="badge text-bg-light border"><?= e($ligne['type_action']) ?></span>
                                    <span class="text-body-secondary small"><?= e($ligne['nom_table']) ?><?= entier($ligne['id_enregistrement']) > 0 ? ' n° ' . entier($ligne['id_enregistrement']) : '' ?></span><br>
                                    <?= e($ligne['description']) ?>
                                    <?php if ($avant !== '' || $apres !== '') : ?>
                                        <details class="small mt-1">
                                            <summary>Détail</summary>
                                            <?php if ($avant !== '') : ?>
                                                <div class="fw-semibold mt-1">Avant</div>
                                                <pre class="mb-1 text-wrap"><?= e($avant) ?></pre>
                                            <?php endif; ?>
                                            <?php if ($apres !== '') : ?>
                                                <div class="fw-semibold mt-1">Après</div>
                                                <pre class="mb-0 text-wrap"><?= e($apres) ?></pre>
                                            <?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($nb_pages > 1) : ?>
                <nav aria-label="Pages du journal">
                    <ul class="pagination flex-wrap">
                        <?php if ($numero_page > 1) : ?>
                            <li class="page-item"><a class="page-link" href="<?= e($adresse_page($numero_page - 1)) ?>">Précédente</a></li>
                        <?php endif; ?>
                        <li class="page-item disabled"><span class="page-link">Page <?= $numero_page ?> sur <?= $nb_pages ?></span></li>
                        <?php if ($numero_page < $nb_pages) : ?>
                            <li class="page-item"><a class="page-link" href="<?= e($adresse_page($numero_page + 1)) ?>">Suivante</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
