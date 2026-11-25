<?php

declare(strict_types=1);

// Informations recueillies (administrateurs seulement) : les informations
// facultatives que l'association choisit de demander ou non (bénévolat,
// scolarité, quartier, QP, QPV). Une information non recueillie n'est plus
// demandée ni affichée ; ce qui a déjà été saisi reste dans la base.
// Quartier, QP et QPV sont calculés à partir de l'adresse : non recueillis,
// ils ne sont plus calculés pour les adresses enregistrées ensuite.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$champs = champs_recueillis();

if (est_post()) {
    csrf_verifier();
    $moi_id = entier(utilisateur_courant()['id'] ?? 0);
    $avant = [];
    $apres = [];
    foreach (array_keys($champs) as $champ) {
        $avant[$champ] = champ_actif($champ) ? 'oui' : 'non';
        $apres[$champ] = post('champ_' . $champ) === '1' ? 'oui' : 'non';
    }
    if ($avant === $apres) {
        flash('info', 'Aucune modification à enregistrer.');
    } else {
        foreach ($apres as $champ => $valeur) {
            if ($avant[$champ] !== $valeur) {
                parametre_enregistrer('champ_' . $champ, $valeur === 'oui' ? '1' : '0', $moi_id);
            }
        }
        journaliser('modification', 'parametres', null, 'Informations recueillies modifiées', $avant, $apres);
        flash('succes', 'Les informations recueillies sont enregistrées.');
    }
    rediriger('options_fiche.php');
}

$titre_page = 'Informations recueillies';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Informations recueillies</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>Une information que vous ne recueillez pas n'est plus demandée ni affichée : formulaires, listes, filtres, statistiques et exports.</li>
            <li>Rien n'est effacé : ce qui a déjà été saisi reste dans la base et réapparaît si vous recueillez de nouveau l'information.</li>
            <li>Le quartier, le QP et le QPV sont calculés à partir de l'adresse, au moment où elle est enregistrée. Non recueillis, ils ne sont plus calculés : une adresse saisie ou modifiée pendant ce temps n'en aura pas.</li>
            <li>Ne recueillez que ce qui vous sert : c'est aussi une règle de la protection des données (RGPD).</li>
        </ul>
    </div>
    <div class="col-md-8 order-md-1">
        <form method="post" action="<?= e(chemin_site('options_fiche.php')) ?>">
            <?= csrf_champ() ?>
            <?php foreach ($champs as $champ => $definition) : ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="champ_<?= e($champ) ?>" name="champ_<?= e($champ) ?>" value="1" <?= champ_actif($champ) ? 'checked' : '' ?> aria-describedby="aide_<?= e($champ) ?>">
                    <label class="form-check-label" for="champ_<?= e($champ) ?>"><?= e($definition['libelle']) ?></label>
                    <div class="form-text" id="aide_<?= e($champ) ?>"><?= e($definition['aide']) ?></div>
                </div>
            <?php endforeach; ?>
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </form>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
