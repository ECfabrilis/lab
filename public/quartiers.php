<?php

declare(strict_types=1);

// Quartiers pris en compte (administrateurs seulement).
// À l'inscription d'un adhérent, le quartier de son adresse est cherché parmi
// les quartiers des zones cochées ici. Une zone est une commune et un
// arrondissement (« Paris 17ème »), ou une commune entière quand
// l'arrondissement n'est pas renseigné : on coche une zone, jamais un quartier seul.
// La liste vient de la table des quartiers (Données de référence).
// Les adresses déjà saisies gardent le quartier calculé le jour de leur saisie.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

if (est_post()) {
    csrf_verifier();

    // Suppression d'une zone entière (tous ses quartiers), après confirmation.
    if (post('action') === 'supprimer') {
        if (post('confirmer') !== '1') {
            flash('erreur', 'Cochez la case de confirmation pour supprimer les quartiers de la zone choisie.');
            rediriger('quartiers.php');
        }
        $suppression = quartiers_supprimer_zone(post('zone'));
        if ($suppression === null) {
            flash('erreur', 'Cette zone n\'existe plus dans la table des quartiers.');
        } else {
            $zone_supprimee = 'la zone « ' . $suppression['libelle'] . ' »';
            flash('succes', ($suppression['nb_quartiers'] > 1
                    ? 'Les ' . $suppression['nb_quartiers'] . ' quartiers de ' . $zone_supprimee . ' sont supprimés.'
                    : 'Le quartier de ' . $zone_supprimee . ' est supprimé.')
                . ' Les adresses déjà saisies gardent leur quartier.');
        }
        rediriger('quartiers.php');
    }

    $cles_cochees = [];
    $envoyees = $_POST['zones'] ?? [];
    foreach (is_array($envoyees) ? $envoyees : [] as $valeur) {
        if (is_string($valeur)) {
            $cles_cochees[] = $valeur;
        }
    }
    $choix = quartiers_choisir($cles_cochees);
    if (!$choix['modifie']) {
        flash('info', 'Aucune modification à enregistrer.');
    } elseif ($choix['libelles'] === []) {
        flash('avertissement', 'Plus aucun quartier n\'est pris en compte : les prochaines adresses seront enregistrées sans quartier.');
    } else {
        flash('succes', 'Quartiers pris en compte : ' . implode(', ', $choix['libelles']) . '. Les adresses déjà saisies gardent leur quartier.');
    }
    rediriger('quartiers.php');
}

$zones = quartiers_zones();

$titre_page = 'Quartiers';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Quartiers pris en compte</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('donnees_reference.php')) ?>">Données de référence</a>
        </div>
        <ul class="d-none d-lg-block text-body-secondary small mt-3 mb-0 ps-3">
            <li>À l'inscription d'un adhérent, le quartier de son adresse est cherché parmi les quartiers des communes et arrondissements cochés.</li>
            <li>Une adresse située ailleurs est enregistrée sans quartier.</li>
            <li>Les adresses déjà saisies gardent le quartier calculé le jour de leur saisie.</li>
            <li>Pour ajouter les quartiers d'une autre commune ou d'un autre arrondissement : « Données de référence », bouton « Ajouter à la table ».</li>
        </ul>
        <?php if ($zones !== []) : ?>
            <form class="border rounded p-3 mt-4" method="post" action="<?= e(chemin_site('quartiers.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="action" value="supprimer">
                <h2 class="h6">Supprimer une zone</h2>
                <label class="form-label small" for="zone_supprimer">Zone dont tous les quartiers seront retirés de la table</label>
                <select class="form-select mb-2" id="zone_supprimer" name="zone" required>
                    <option value="">Choisir…</option>
                    <?php foreach ($zones as $zone) : ?>
                        <option value="<?= e($zone['cle']) ?>"><?= e($zone['libelle']) ?> (<?= $zone['nb_quartiers'] ?> quartier<?= $zone['nb_quartiers'] > 1 ? 's' : '' ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="confirmer_suppression" name="confirmer" value="1" required>
                    <label class="form-check-label small" for="confirmer_suppression">Je confirme la suppression. Pour retrouver cette zone, il faudra la réimporter dans « Données de référence ».</label>
                </div>
                <div class="d-grid">
                    <button class="btn btn-outline-danger" type="submit">Supprimer la zone</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-md-8 order-md-1">
        <?php if ($zones === []) : ?>
            <div class="alert alert-info" role="alert">La table des quartiers est vide : remplissez-la dans « Données de référence ».</div>
        <?php else : ?>
            <form method="post" action="<?= e(chemin_site('quartiers.php')) ?>">
                <?= csrf_champ() ?>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Prendre en compte les quartiers de</legend>
                    <?php foreach ($zones as $rang => $zone) : ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="zone_<?= $rang ?>" name="zones[]" value="<?= e($zone['cle']) ?>" <?= $zone['est_actif'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="zone_<?= $rang ?>">
                                <?= e($zone['libelle']) ?>
                                <span class="text-body-secondary small">— <?= $zone['nb_quartiers'] ?> quartier<?= $zone['nb_quartiers'] > 1 ? 's' : '' ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
