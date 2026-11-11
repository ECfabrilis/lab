<?php

declare(strict_types=1);

// Fiche d'une famille : ses membres et ses dossiers, saison par saison.
// Consultation : tout utilisateur connecté. Modification : rôle standard au minimum.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_connexion();

$famille_id = entier(est_post() ? post('id') : ($_GET['id'] ?? 0));
$famille = famille_par_id($famille_id);
if ($famille === null) {
    flash('erreur', 'Cette famille n\'existe pas.');
    rediriger('adherents.php');
}
$page_fiche = 'famille.php?id=' . $famille_id;
$peut_modifier = role_au_moins('standard') && texte($famille['anonymise_le']) === '';

$saison_affichee = saison_courante();
$saison_affichee_id = entier($saison_affichee['id'] ?? 0);

if (est_post()) {
    if (!csrf_valide()) {
        flash('erreur', 'Le formulaire a expiré. Merci de recommencer.');
        rediriger($page_fiche);
    }
    if (!$peut_modifier) {
        flash('erreur', 'Vous n\'avez pas les droits nécessaires pour modifier cette famille.');
        rediriger($page_fiche);
    }
    $action = post('action');

    // --- Nom de la famille ---------------------------------------------------
    if ($action === 'renommer') {
        $nom_saisi = post('nom_famille');
        if ($nom_saisi === '' || mb_strlen($nom_saisi) > 100) {
            flash('erreur', 'Le nom de la famille est obligatoire (100 caractères au maximum).');
            ancien_memoriser();
        } elseif ($nom_saisi === texte($famille['nom'])) {
            flash('info', 'Aucune modification à enregistrer.');
        } else {
            db_executer('UPDATE familles SET nom = ? WHERE id = ?', [$nom_saisi, $famille_id]);
            journaliser('modification', 'familles', $famille_id, 'Changement du nom de la famille', ['nom' => $famille['nom']], ['nom' => $nom_saisi]);
            flash('succes', 'Le nom de la famille est modifié.');
        }
        rediriger($page_fiche);
    }

    // --- Ouverture du dossier de la saison affichée -------------------------
    if ($action === 'ouvrir_dossier' && $saison_affichee_id > 0) {
        if (dossier_id_de_famille($famille_id, $saison_affichee_id) > 0) {
            flash('info', 'Le dossier de cette saison existe déjà.');
            rediriger($page_fiche);
        }
        db()->beginTransaction();
        try {
            $dossier_id = dossier_obtenir($famille_id, $saison_affichee_id);
            db()->commit();
        } catch (Throwable $erreur) {
            db()->rollBack();
            throw $erreur;
        }
        flash('succes', 'Le dossier de la saison ' . texte($saison_affichee['nom'] ?? '') . ' est ouvert. Ajoutez-y les membres concernés.');
        rediriger('dossier.php?id=' . $dossier_id);
    }

    rediriger($page_fiche);
}

$membres = famille_membres($famille_id, $saison_affichee_id);
$dossiers = dossiers_de_famille($famille_id);
$dossier_saison_id = dossier_id_de_famille($famille_id, $saison_affichee_id);

$titre_page = 'Famille';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Famille <?= e($famille['nom']) ?></h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <?php if ($peut_modifier) : ?>
                <a class="btn btn-primary" href="<?= e(chemin_site('ajout_adherent.php?famille=' . $famille_id)) ?>">Ajouter un membre</a>
            <?php endif; ?>
            <?php if ($dossier_saison_id > 0) : ?>
                <a class="btn btn-outline-primary" href="<?= e(chemin_site('dossier.php?id=' . $dossier_saison_id)) ?>">Dossier <?= e($saison_affichee['nom'] ?? '') ?></a>
            <?php elseif ($peut_modifier && $saison_affichee !== null && $membres !== []) : ?>
                <form class="d-grid" method="post" action="<?= e(chemin_site('famille.php')) ?>">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="id" value="<?= $famille_id ?>">
                    <button class="btn btn-outline-primary" type="submit" name="action" value="ouvrir_dossier">Ouvrir le dossier <?= e($saison_affichee['nom']) ?></button>
                </form>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(chemin_site('adherents.php')) ?>">Retour à la liste</a>
        </div>

        <?php if ($peut_modifier) : ?>
            <form class="mt-4" method="post" action="<?= e(chemin_site('famille.php')) ?>">
                <?= csrf_champ() ?>
                <input type="hidden" name="id" value="<?= $famille_id ?>">
                <input type="hidden" name="action" value="renommer">
                <label class="form-label" for="nom_famille">Nom de la famille</label>
                <div class="input-group">
                    <input class="form-control" type="text" id="nom_famille" name="nom_famille" maxlength="100" value="<?= e(ancien('nom_famille', $famille['nom'])) ?>" required>
                    <button class="btn btn-outline-secondary" type="submit">Renommer</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    <div class="col-md-8 order-md-1">
        <h2 class="h5">Membres</h2>
        <div class="table-responsive mb-4">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Nom</th>
                        <th scope="col">Âge</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Saison <?= e($saison_affichee['nom'] ?? '') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($membres as $membre) : ?>
                        <tr>
                            <td><a href="<?= e(chemin_site('adherent.php?id=' . entier($membre['id']))) ?>"><?= e($membre['prenom']) ?> <?= e($membre['nom']) ?></a></td>
                            <td><?= age_ans(texte($membre['date_naissance'])) ?> ans</td>
                            <td class="small">
                                <?= e($membre['tel']) ?>
                                <?php if (texte($membre['tel']) !== '' && texte($membre['mail']) !== '') : ?><br><?php endif; ?>
                                <?= e($membre['mail']) ?>
                            </td>
                            <td>
                                <?php if (entier($membre['dans_dossier']) === 1) : ?>
                                    <span class="badge text-bg-success">Dans le dossier</span>
                                <?php else : ?>
                                    <span class="badge text-bg-secondary">Absent</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="h5">Dossiers</h2>
        <?php if ($dossiers === []) : ?>
            <p class="text-body-secondary">Aucun dossier.</p>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Saison</th>
                            <th scope="col">Ouvert le</th>
                            <th scope="col">Adhérents</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dossiers as $dossier) : ?>
                            <tr>
                                <td><a href="<?= e(chemin_site('dossier.php?id=' . entier($dossier['id']))) ?>"><?= e($dossier['saison_nom']) ?></a></td>
                                <td><?= e(date_fr($dossier['date_dossier'])) ?></td>
                                <td><?= entier($dossier['nb_adherents']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
