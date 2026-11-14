<?php

declare(strict_types=1);

// RGPD (administrateurs seulement) : anonymisation des familles.
// - Sans paramètre : liste des familles inactives depuis plus de 3 ans, à
//   cocher puis à anonymiser en retapant le mot de confirmation.
// - Avec ?famille=N : anonymisation d'une famille précise (droit à
//   l'effacement), en retapant son nom. Refusée si la famille est inscrite
//   pour la saison en cours ou à venir.
// - Avec ?adherent=N : anonymisation d'une seule personne, en retapant son
//   prénom et son nom ; la famille n'est pas touchée, sauf si la personne
//   en est le dernier membre. Même refus si elle est inscrite.
// L'anonymisation est définitive ; les structures ne sont pas concernées.

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

if (est_post()) {
    csrf_verifier();
    $action = post('action');

    // --- Une famille précise : le nom de la famille est retapé ---------------
    if ($action === 'anonymiser_famille') {
        $cible_id = entier(post('id'));
        $cible = famille_par_id($cible_id);
        if ($cible === null) {
            flash('erreur', 'Cette famille n\'existe pas.');
            rediriger('rgpd.php');
        }
        $page_confirmation = 'rgpd.php?famille=' . $cible_id;
        $refus = rgpd_refus_anonymisation($cible);
        if ($refus !== '') {
            flash('erreur', $refus);
            rediriger($page_confirmation);
        }
        if (nom_normaliser(post('nom_confirmation')) !== nom_normaliser(texte($cible['nom']))) {
            flash('erreur', 'Le nom retapé ne correspond pas au nom de la famille : rien n\'a été modifié.');
            rediriger($page_confirmation);
        }
        if (famille_anonymiser($cible_id)) {
            flash('succes', 'La famille est anonymisée. Cette opération est définitive.');
        } else {
            flash('erreur', 'La famille n\'a pas pu être anonymisée.');
        }
        rediriger('famille.php?id=' . $cible_id);
    }

    // --- Une personne seule : son prénom et son nom sont retapés --------------
    if ($action === 'anonymiser_personne') {
        $personne_id = entier(post('id'));
        $personne = adherent_par_id($personne_id);
        if ($personne === null) {
            flash('erreur', 'Cette personne n\'existe pas.');
            rediriger('rgpd.php');
        }
        $page_confirmation = 'rgpd.php?adherent=' . $personne_id;
        $refus = rgpd_refus_anonymisation_adherent($personne);
        if ($refus !== '') {
            flash('erreur', $refus);
            rediriger($page_confirmation);
        }
        $attendu = texte_simplifie(texte($personne['prenom']) . ' ' . texte($personne['nom']));
        if (texte_simplifie(post('nom_confirmation')) !== $attendu) {
            flash('erreur', 'Le prénom et le nom retapés ne correspondent pas à cette personne : rien n\'a été modifié.');
            rediriger($page_confirmation);
        }
        $resultat = adherent_anonymiser($personne_id);
        if ($resultat === 'personne') {
            flash('succes', 'La personne est anonymisée. Cette opération est définitive.');
        } elseif ($resultat === 'famille') {
            flash('succes', 'La personne était le dernier membre de sa famille : la famille entière est anonymisée. Cette opération est définitive.');
        } else {
            flash('erreur', 'La personne n\'a pas pu être anonymisée.');
        }
        rediriger('adherent.php?id=' . $personne_id);
    }

    // --- Familles inactives cochées : le mot de confirmation est retapé ------
    if ($action === 'anonymiser_inactives') {
        $cochees = [];
        $envoyees = $_POST['familles'] ?? [];
        foreach (is_array($envoyees) ? $envoyees : [] as $valeur) {
            if (is_string($valeur) && entier($valeur) > 0) {
                $cochees[] = entier($valeur);
            }
        }
        // Seules les familles réellement inactives sont acceptées, quoi que dise le formulaire.
        $inactives_ids = array_map(static fn (array $ligne): int => $ligne['id'], rgpd_familles_inactives());
        $a_anonymiser = array_values(array_intersect($inactives_ids, $cochees));
        if ($a_anonymiser === []) {
            flash('erreur', 'Cochez au moins une famille.');
            rediriger('rgpd.php');
        }
        if (trim(post('confirmation')) !== RGPD_MOT_CONFIRMATION) {
            flash('erreur', 'Pour confirmer, tapez le mot ' . RGPD_MOT_CONFIRMATION . ' en majuscules : rien n\'a été modifié.');
            rediriger('rgpd.php');
        }
        $nb_faites = 0;
        foreach ($a_anonymiser as $inactive_id) {
            if (famille_anonymiser($inactive_id)) {
                $nb_faites++;
            }
        }
        if ($nb_faites > 0) {
            flash('succes', $nb_faites . ($nb_faites > 1 ? ' familles sont anonymisées.' : ' famille est anonymisée.') . ' Cette opération est définitive.');
        }
        if ($nb_faites < count($a_anonymiser)) {
            flash('avertissement', (count($a_anonymiser) - $nb_faites) . ' famille(s) n\'ont pas pu être anonymisées.');
        }
        rediriger('rgpd.php');
    }

    rediriger('rgpd.php');
}

// --- Affichage ---------------------------------------------------------------
$cible_id = entier($_GET['famille'] ?? 0);
$cible = null;
$cible_refus = '';
$cible_membres = [];
$cible_saisons = [];
$inactives = [];
$personne_id = entier($_GET['adherent'] ?? 0);
$personne = null;
$personne_refus = '';
$personne_derniere = false;
$personne_mineurs = [];
if ($personne_id > 0) {
    $personne = adherent_par_id($personne_id);
    if ($personne === null) {
        flash('erreur', 'Cette personne n\'existe pas.');
        rediriger('rgpd.php');
    }
    $personne_refus = rgpd_refus_anonymisation_adherent($personne);
    if ($personne_refus === '') {
        $personne_derniere = rgpd_est_dernier_membre($personne);
        $personne_mineurs = rgpd_mineurs_a_charge($personne);
    }
} elseif ($cible_id > 0) {
    $cible = famille_par_id($cible_id);
    if ($cible === null) {
        flash('erreur', 'Cette famille n\'existe pas.');
        rediriger('rgpd.php');
    }
    $cible_refus = rgpd_refus_anonymisation($cible);
    $cible_membres = famille_membres($cible_id);
    $cible_saisons = array_values(array_filter(
        dossiers_de_famille($cible_id),
        static fn (array $ligne): bool => entier($ligne['nb_adherents']) > 0
    ));
} else {
    $inactives = rgpd_familles_inactives();
}
$nb_anonymisees = rgpd_familles_anonymisees_nb();

$titre_page = 'RGPD';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">RGPD — anonymisation</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2 mb-3">
            <?php if ($personne !== null) : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('adherent.php?id=' . $personne_id)) ?>">Retour à la fiche</a>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('rgpd.php')) ?>">Familles inactives</a>
            <?php elseif ($cible !== null) : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('famille.php?id=' . $cible_id)) ?>">Retour à la famille</a>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('rgpd.php')) ?>">Familles inactives</a>
            <?php else : ?>
                <a class="btn btn-outline-secondary" href="<?= e(chemin_site('parametres.php')) ?>">Retour aux paramètres</a>
            <?php endif; ?>
        </div>
        <h2 class="h6">Ce que fait l'anonymisation</h2>
        <ul class="small ps-3">
            <li><strong>Effacé</strong> : noms, prénoms, jour et mois de naissance, courriels, téléphones, responsable, adresse, notes, précisions des paiements, lignes du journal.</li>
            <li><strong>Gardé</strong> pour les statistiques et la comptabilité : sexe, année de naissance, nationalité, code postal, ville, quartier, scolarité, inscriptions, cotisations et paiements.</li>
            <li>L'opération est <strong>définitive</strong>.</li>
            <li>Les structures ne sont pas concernées.</li>
        </ul>
        <p class="small text-body-secondary">
            Les sauvegardes faites avant une anonymisation contiennent encore les données effacées :
            pensez à supprimer les anciennes, sur le serveur et dans vos copies.
        </p>
        <p class="small mb-0">Familles déjà anonymisées : <strong><?= $nb_anonymisees ?></strong></p>
    </div>
    <div class="col-md-8 order-md-1">
        <?php if ($personne !== null) : ?>
            <?php $personne_nom = trim(texte($personne['prenom']) . ' ' . texte($personne['nom'])); ?>
            <h2 class="h5"><?= e($personne_nom) ?></h2>
            <?php if (texte($personne['famille_nom']) !== '') : ?>
                <p>Famille <a href="<?= e(chemin_site('famille.php?id=' . entier($personne['famille_id']))) ?>"><?= e($personne['famille_nom']) ?></a></p>
            <?php endif; ?>
            <?php if ($personne_refus !== '') : ?>
                <div class="alert alert-warning" role="alert"><?= e($personne_refus) ?></div>
            <?php else : ?>
                <?php if ($personne_derniere) : ?>
                    <div class="alert alert-danger" role="alert">
                        Cette personne est le dernier membre de sa famille : la <strong>famille entière</strong> sera anonymisée
                        (nom de la famille, notes des saisons et précisions des paiements compris). Cette opération ne peut pas être annulée.
                    </div>
                <?php else : ?>
                    <div class="alert alert-danger" role="alert">
                        Seule cette personne sera anonymisée. Cette opération ne peut pas être annulée.
                    </div>
                    <p class="small text-body-secondary">
                        Le nom de la famille, les notes des saisons et les précisions des paiements appartiennent à toute la famille :
                        ils ne sont pas effacés. Vérifiez qu'ils ne nomment pas cette personne, ou anonymisez la famille entière.
                    </p>
                <?php endif; ?>
                <?php if ($personne_mineurs !== [] && !$personne_derniere) : ?>
                    <div class="alert alert-warning" role="alert">
                        Cette personne semble être le responsable enregistré de :
                        <?php foreach ($personne_mineurs as $rang => $personne_mineur) : ?>
                            <?= $rang > 0 ? ', ' : '' ?><a class="alert-link" href="<?= e(chemin_site('adherent.php?id=' . $personne_mineur['id'])) ?>"><?= e($personne_mineur['nom']) ?></a><?php endforeach; ?>.
                        Ses coordonnées resteront sur ces fiches : corrigez-les si besoin.
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(chemin_site('rgpd.php')) ?>" autocomplete="off">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="action" value="anonymiser_personne">
                    <input type="hidden" name="id" value="<?= $personne_id ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nom_confirmation">Pour confirmer, retapez le prénom et le nom : <strong><?= e($personne_nom) ?></strong></label>
                        <input class="form-control" type="text" id="nom_confirmation" name="nom_confirmation" maxlength="201" required>
                    </div>
                    <button class="btn btn-danger" type="submit"><?= $personne_derniere ? 'Anonymiser la famille' : 'Anonymiser cette personne' ?></button>
                    <a class="btn btn-link" href="<?= e(chemin_site('adherent.php?id=' . $personne_id)) ?>">Annuler</a>
                </form>
            <?php endif; ?>
        <?php elseif ($cible !== null) : ?>
            <h2 class="h5">Famille <?= e($cible['nom']) ?></h2>
            <?php if ($cible_membres !== []) : ?>
                <ul>
                    <?php foreach ($cible_membres as $cible_membre) : ?>
                        <li><?= e($cible_membre['prenom']) ?> <?= e($cible_membre['nom']) ?>, <?= age_ans(texte($cible_membre['date_naissance'])) ?> ans</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p>
                <?php if ($cible_saisons === []) : ?>
                    Cette famille n'est inscrite pour aucune saison.
                <?php else : ?>
                    Saisons : <?= e(implode(', ', array_map(static fn (array $ligne): string => texte($ligne['saison_nom']), $cible_saisons))) ?>.
                <?php endif; ?>
            </p>
            <?php if ($cible_refus !== '') : ?>
                <div class="alert alert-warning" role="alert"><?= e($cible_refus) ?></div>
            <?php else : ?>
                <div class="alert alert-danger" role="alert">
                    Toutes les personnes de cette famille seront anonymisées. Cette opération ne peut pas être annulée.
                </div>
                <form method="post" action="<?= e(chemin_site('rgpd.php')) ?>" autocomplete="off">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="action" value="anonymiser_famille">
                    <input type="hidden" name="id" value="<?= $cible_id ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nom_confirmation">Pour confirmer, retapez le nom de la famille : <strong><?= e($cible['nom']) ?></strong></label>
                        <input class="form-control" type="text" id="nom_confirmation" name="nom_confirmation" maxlength="100" required>
                    </div>
                    <button class="btn btn-danger" type="submit">Anonymiser cette famille</button>
                    <a class="btn btn-link" href="<?= e(chemin_site('famille.php?id=' . $cible_id)) ?>">Annuler</a>
                </form>
            <?php endif; ?>
        <?php else : ?>
            <h2 class="h5">Familles inactives depuis plus de 3 ans</h2>
            <p class="text-body-secondary">
                Familles dont la dernière saison d'inscription s'est terminée avant le <?= e(date_fr(rgpd_date_limite())) ?>
                (ou créées avant cette date et jamais inscrites).
            </p>
            <?php if ($inactives === []) : ?>
                <p>Aucune famille n'est à anonymiser.</p>
            <?php else : ?>
                <form method="post" action="<?= e(chemin_site('rgpd.php')) ?>" autocomplete="off">
                    <?= csrf_champ() ?>
                    <input type="hidden" name="action" value="anonymiser_inactives">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col"><span class="visually-hidden">Choisir</span></th>
                                    <th scope="col">Famille</th>
                                    <th scope="col">Membres</th>
                                    <th scope="col">Dernière saison</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inactives as $inactive) : ?>
                                    <tr>
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="familles[]" value="<?= $inactive['id'] ?>" id="famille_<?= $inactive['id'] ?>" aria-label="Choisir la famille <?= e($inactive['nom']) ?>">
                                        </td>
                                        <td><a href="<?= e(chemin_site('famille.php?id=' . $inactive['id'])) ?>"><?= e($inactive['nom']) ?></a></td>
                                        <td class="small"><?= e($inactive['membres']) ?></td>
                                        <td class="small">
                                            <?php if ($inactive['derniere_saison'] !== '') : ?>
                                                <?= e($inactive['derniere_saison']) ?>, terminée le <?= e(date_fr($inactive['derniere_fin'])) ?>
                                            <?php else : ?>
                                                Jamais inscrite (créée le <?= e(date_fr($inactive['date_crea'])) ?>)
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-danger" role="alert">
                        Les familles cochées seront anonymisées. Cette opération ne peut pas être annulée.
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirmation">Pour confirmer, tapez le mot <strong><?= e(RGPD_MOT_CONFIRMATION) ?></strong></label>
                        <input class="form-control" type="text" id="confirmation" name="confirmation" maxlength="20" required>
                    </div>
                    <button class="btn btn-danger" type="submit">Anonymiser les familles cochées</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
