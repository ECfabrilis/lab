<?php

declare(strict_types=1);

// État des paiements de la saison affichée : pour chaque famille et chaque
// structure, le montant dû, ce qui est payé, le reste à payer ou le trop-perçu.
// Rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('standard');

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);

$libelles_etats = ['' => 'Tous', 'reste' => 'Reste à payer', 'trop' => 'Trop-perçu', 'solde' => 'Soldés'];
$filtre_etat = texte($_GET['etat'] ?? '');
$filtre_etat = array_key_exists($filtre_etat, $libelles_etats) ? $filtre_etat : '';

// Seuls les comptes où il y a quelque chose à payer ou de payé sont listés.
$comptes = array_values(array_filter(
    comptes_lignes($saison_affichee_id),
    static fn (array $compte): bool => $compte['du'] > 0 || $compte['paye'] > 0
));
$total_du = 0;
$total_paye = 0;
$total_reste = 0;
$total_trop = 0;
$nombres = ['' => count($comptes), 'reste' => 0, 'trop' => 0, 'solde' => 0];
foreach ($comptes as $compte) {
    $total_du += $compte['du'];
    $total_paye += $compte['paye'];
    $total_reste += max(0, $compte['solde']);
    $total_trop += max(0, -$compte['solde']);
    $nombres[$compte['solde'] > 0 ? 'reste' : ($compte['solde'] < 0 ? 'trop' : 'solde')]++;
}
$comptes_affiches = array_values(array_filter(
    $comptes,
    static fn (array $compte): bool => match ($filtre_etat) {
        'reste' => $compte['solde'] > 0,
        'trop'  => $compte['solde'] < 0,
        'solde' => $compte['solde'] === 0,
        default => true,
    }
));

$titre_page = 'État des paiements';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">État des paiements — saison <?= e($saison_affichee['nom'] ?? '') ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2 mb-3">
            <a class="btn btn-primary" href="<?= e(chemin_site('ajout_paiement.php')) ?>">Nouveau paiement</a>
        </div>
        <div class="list-group mb-4">
            <?php foreach ($libelles_etats as $code_etat => $libelle_etat) : ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $filtre_etat === $code_etat ? 'active' : '' ?>"
                   <?= $filtre_etat === $code_etat ? 'aria-current="true"' : '' ?>
                   href="<?= e(chemin_site('etat_paiements.php' . ($code_etat !== '' ? '?etat=' . $code_etat : ''))) ?>">
                    <?= e($libelle_etat) ?> <span class="badge text-bg-secondary"><?= $nombres[$code_etat] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <h2 class="h6">Totaux de la saison</h2>
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th scope="row" class="fw-normal">Montant dû</th><td class="text-end"><?= e(centimes_fr($total_du)) ?></td></tr>
                <tr><th scope="row" class="fw-normal">Payé</th><td class="text-end"><?= e(centimes_fr($total_paye)) ?></td></tr>
                <tr><th scope="row" class="fw-normal">Reste à payer</th><td class="text-end"><?= e(centimes_fr($total_reste)) ?></td></tr>
                <tr><th scope="row" class="fw-normal">Trop-perçu</th><td class="text-end"><?= e(centimes_fr($total_trop)) ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="col-md-8 order-md-1">
        <?php if ($saison_affichee === null) : ?>
            <div class="alert alert-info" role="alert">Aucune saison n'existe encore.</div>
        <?php elseif ($comptes_affiches === []) : ?>
            <div class="alert alert-info" role="alert">
                <?= $comptes === [] ? 'Aucune cotisation, inscription payante ni paiement pour cette saison.' : 'Aucune famille ni structure dans ce cas.' ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Famille ou structure</th>
                            <th scope="col" class="text-end">Dû</th>
                            <th scope="col" class="text-end">Payé</th>
                            <th scope="col" class="text-end">Reste</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comptes_affiches as $compte) : ?>
                            <tr>
                                <td>
                                    <a href="<?= e(chemin_site('compte.php?' . paiement_lien_compte($compte['famille_id'], $compte['structure_id']))) ?>"><?= e($compte['nom']) ?></a>
                                    <?php if ($compte['structure_id'] > 0) : ?><span class="badge text-bg-secondary">Structure</span><?php endif; ?>
                                </td>
                                <td class="text-end"><?= e(centimes_fr($compte['du'])) ?></td>
                                <td class="text-end"><?= e(centimes_fr($compte['paye'])) ?></td>
                                <td class="text-end">
                                    <?php if ($compte['solde'] > 0) : ?>
                                        <span class="badge text-bg-warning"><?= e(centimes_fr($compte['solde'])) ?></span>
                                    <?php elseif ($compte['solde'] < 0) : ?>
                                        <span class="badge text-bg-danger">Trop-perçu : <?= e(centimes_fr(-$compte['solde'])) ?></span>
                                    <?php else : ?>
                                        <span class="badge text-bg-success">Soldé</span>
                                    <?php endif; ?>
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
