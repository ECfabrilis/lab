<?php

declare(strict_types=1);

// Liste des utilisateurs de l'application (administrateurs seulement).

require __DIR__ . '/_chemins.php';
require DOSSIER_PRIVE . '/includes/init.php';

exiger_role('admin');

$comptes = utilisateurs_liste();

$titre_page = 'Utilisateurs';
require DOSSIER_PRIVE . '/includes/header.php';
?>
<h1 class="h3 mb-4">Utilisateurs</h1>
<div class="row g-4">
    <div class="col-md-4 order-md-2" id="menuLateral">
        <div class="d-grid gap-2">
            <a class="btn btn-primary" href="<?= e(chemin_site('ajout_utilisateur.php')) ?>">Ajouter un utilisateur</a>
        </div>
        <p class="d-none d-lg-block text-body-secondary small mt-3 mb-0">Un nouvel utilisateur reçoit un courriel avec un lien, valable 7 jours, pour choisir son mot de passe.</p>
    </div>
    <div class="col-md-8 order-md-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th scope="col">Prénom</th>
                        <th scope="col">Adresse e-mail</th>
                        <th scope="col" class="d-none d-lg-table-cell">Rôle</th>
                        <th scope="col" class="d-none d-lg-table-cell">État</th>
                        <th scope="col">Dernière connexion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comptes as $compte) : ?>
                        <tr>
                            <td><a href="<?= e(chemin_site('utilisateur.php?id=' . entier($compte['id']))) ?>"><?= e($compte['prenom']) ?></a></td>
                            <td><?= e($compte['mail']) ?></td>
                            <td class="d-none d-lg-table-cell"><?= e(libelles_roles()[texte($compte['role'])] ?? '') ?></td>
                            <td class="d-none d-lg-table-cell">
                                <?php if (entier($compte['est_actif']) !== 1) : ?>
                                    <span class="badge text-bg-secondary">Désactivé</span>
                                <?php elseif (entier($compte['invitation_en_attente']) === 1) : ?>
                                    <span class="badge text-bg-warning">Invitation envoyée</span>
                                <?php else : ?>
                                    <span class="badge text-bg-success">Actif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(date_heure_fr($compte['derniere_connexion']) ?: 'Jamais') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require DOSSIER_PRIVE . '/includes/footer.php'; ?>
