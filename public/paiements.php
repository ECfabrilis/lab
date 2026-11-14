<?php

declare(strict_types=1);

// Liste des paiements enregistrés sur une période (toutes saisons), avec les
// totaux par mode de paiement et par objet. Les filtres (dates, mode, objet)
// sont dans l'adresse de la page : aucune donnée personnelle n'y figure.
// Rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

// Période : par défaut, du premier jour du mois à aujourd'hui.
$date_du = texte($_GET['du'] ?? '');
$date_au = texte($_GET['au'] ?? '');
$date_du = date_valide($date_du) ? $date_du : date('Y-m-01');
$date_au = date_valide($date_au) ? $date_au : date('Y-m-d');
if ($date_au < $date_du) {
    [$date_du, $date_au] = [$date_au, $date_du];
}
$filtre_mode = entier($_GET['mode'] ?? 0);
$modes_paiement = libelles_modes_paiement();
$filtre_mode = array_key_exists($filtre_mode, $modes_paiement) ? $filtre_mode : 0;
$filtre_objet = texte($_GET['objet'] ?? '');
$filtre_objet = array_key_exists($filtre_objet, libelles_objets_paiement()) ? $filtre_objet : '';
$avec_annules = texte($_GET['annules'] ?? '') === '1';

$liste_paiements = paiements_periode($date_du, $date_au, $filtre_mode, $filtre_objet, $avec_annules);

// Totaux (en centimes), sans les paiements annulés.
$total = 0;
$totaux_modes = array_fill_keys(array_keys($modes_paiement), 0);
$totaux_objets = array_fill_keys(array_keys(libelles_objets_paiement()), 0);
$nb_valides = 0;
foreach ($liste_paiements as $ligne_paiement) {
    if (entier($ligne_paiement['est_annule']) === 1) {
        continue;
    }
    $montant_ligne = centimes($ligne_paiement['montant']);
    $total += $montant_ligne;
    $nb_valides++;
    $mode_ligne = entier($ligne_paiement['mode_id']);
    $totaux_modes[$mode_ligne] = ($totaux_modes[$mode_ligne] ?? 0) + $montant_ligne;
    $totaux_objets[texte($ligne_paiement['objet'])] = ($totaux_objets[texte($ligne_paiement['objet'])] ?? 0) + $montant_ligne;
}

$titre_page = 'Paiements';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Paiements du <?= e(date_fr($date_du)) ?> au <?= e(date_fr($date_au)) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2 mb-3">
            <a class="btn btn-primary" href="<?= e(chemin_site('ajout_paiement.php')) ?>">Nouveau paiement</a>
        </div>
        <form method="get" action="<?= e(chemin_site('paiements.php')) ?>">
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="du">Du</label>
                    <input class="form-control" type="date" id="du" name="du" value="<?= e($date_du) ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label" for="au">Au</label>
                    <input class="form-control" type="date" id="au" name="au" value="<?= e($date_au) ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="mode">Mode de paiement</label>
                <select class="form-select" id="mode" name="mode">
                    <option value="">Tous les modes</option>
                    <?php foreach ($modes_paiement as $code_mode => $libelle_mode) : ?>
                        <option value="<?= $code_mode ?>" <?= $filtre_mode === $code_mode ? 'selected' : '' ?>><?= e($libelle_mode) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="objet">Objet</label>
                <select class="form-select" id="objet" name="objet">
                    <option value="">Tous les objets</option>
                    <?php foreach (libelles_objets_paiement() as $code_objet => $libelle_objet) : ?>
                        <option value="<?= e($code_objet) ?>" <?= $filtre_objet === $code_objet ? 'selected' : '' ?>><?= e($libelle_objet) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="annules" name="annules" value="1" <?= $avec_annules ? 'checked' : '' ?>>
                <label class="form-check-label" for="annules">Montrer aussi les paiements annulés</label>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">Afficher</button>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('paiements.php')) ?>">Mois en cours</a>
            </div>
        </form>

        <h2 class="h6 mt-4">Totaux de la période</h2>
        <table class="table table-sm mb-0">
            <tbody>
                <?php foreach ($modes_paiement as $code_mode => $libelle_mode) : ?>
                    <?php if ($totaux_modes[$code_mode] > 0) : ?>
                        <tr><th scope="row" class="fw-normal"><?= e($libelle_mode) ?></th><td class="text-end"><?= e(centimes_fr($totaux_modes[$code_mode])) ?></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach (libelles_objets_paiement() as $code_objet => $libelle_objet) : ?>
                    <tr><th scope="row" class="fw-normal text-body-secondary">dont <?= e(mb_strtolower($libelle_objet)) ?></th><td class="text-end text-body-secondary"><?= e(centimes_fr($totaux_objets[$code_objet])) ?></td></tr>
                <?php endforeach; ?>
                <tr><th scope="row">Total</th><td class="text-end fw-bold"><?= e(centimes_fr($total)) ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="col-md-8 order-md-1">
        <p class="text-body-secondary">
            <?= $nb_valides ?> paiement<?= $nb_valides > 1 ? 's' : '' ?>
            <?= $filtre_mode > 0 ? '— ' . e(mb_strtolower($modes_paiement[$filtre_mode])) : '' ?>
            <?= $filtre_objet !== '' ? '— ' . e(mb_strtolower(libelles_objets_paiement()[$filtre_objet])) : '' ?>
        </p>
        <?php if ($liste_paiements === []) : ?>
            <div class="alert alert-info" role="alert">Aucun paiement sur cette période.</div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Famille ou structure</th>
                            <th scope="col">Mode</th>
                            <th scope="col">Objet</th>
                            <th scope="col" class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liste_paiements as $ligne_paiement) : ?>
                            <?php $ligne_annulee = entier($ligne_paiement['est_annule']) === 1; ?>
                            <tr class="<?= $ligne_annulee ? 'text-body-secondary' : '' ?>">
                                <td><a href="<?= e(chemin_site('paiement.php?id=' . entier($ligne_paiement['id']))) ?>"><?= e(date_fr($ligne_paiement['date_paiement'])) ?></a></td>
                                <td>
                                    <a href="<?= e(chemin_site('compte.php?' . paiement_lien_compte($ligne_paiement['famille_id'], $ligne_paiement['structure_id']))) ?>"><?= e($ligne_paiement['titulaire_nom']) ?></a>
                                    <?php if (entier($ligne_paiement['structure_id']) > 0) : ?><span class="badge text-bg-secondary">Structure</span><?php endif; ?>
                                    <?php if ($ligne_annulee) : ?><span class="badge text-bg-secondary">Annulé</span><?php endif; ?>
                                    <?php if (texte($ligne_paiement['note']) !== '') : ?>
                                        <span class="d-block small text-body-secondary"><?= e($ligne_paiement['note']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($ligne_paiement['mode_nom']) ?></td>
                                <td><?= e(libelles_objets_paiement()[texte($ligne_paiement['objet'])] ?? '') ?></td>
                                <td class="text-end <?= $ligne_annulee ? 'text-decoration-line-through' : '' ?>"><?= e(montant_fr($ligne_paiement['montant'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
