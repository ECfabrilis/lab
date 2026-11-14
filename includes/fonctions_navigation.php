<?php

declare(strict_types=1);

/**
 * Navigation : menu principal, tableau de bord et page des paramètres,
 * présentés sous forme de cards (cahier des charges, section 12.3).
 *
 * Pour ajouter un choix, il suffit d'ajouter une ligne dans l'une des listes
 * ci-dessous. Une card dont la page n'existe pas encore est affichée en
 * grisé avec la mention « À venir » : elle s'active d'elle-même le jour où
 * la page est créée.
 */

/** Dossier des pages publiques (il diffère entre le poste local et l'hébergeur). */
function dossier_public(): string
{
    return dirname(texte($_SERVER['SCRIPT_FILENAME'] ?? ''));
}

/** Vrai si la page existe dans le dossier public. */
function page_existe(string $page): bool
{
    $fichier = explode('#', explode('?', $page)[0])[0];
    return preg_match('/^[a-z_]+\.php$/', $fichier) === 1 && is_file(dossier_public() . '/' . $fichier);
}

/**
 * Entrées du menu principal.
 *
 * @return list<array{page: string, libelle: string, role: string}>
 */
function menu_principal(): array
{
    return [
        ['page' => 'index.php', 'libelle' => 'Tableau de bord', 'role' => 'lecteur'],
        ['page' => 'parametres.php', 'libelle' => 'Paramètres', 'role' => 'admin'],
        ['page' => 'statistiques.php', 'libelle' => 'Statistiques', 'role' => 'lecteur'],
    ];
}

/**
 * Parties et cards du tableau de bord.
 *
 * @return list<array{titre: string, role: string, cards: list<array{titre: string, texte: string, page: string, role: string}>}>
 */
function cards_tableau_de_bord(): array
{
    return [
        [
            'titre' => 'Les adhérents',
            'role'  => 'lecteur',
            'cards' => [
                ['titre' => 'Liste des adhérents', 'texte' => 'Rechercher une personne, ouvrir sa fiche.', 'page' => 'adherents.php', 'role' => 'lecteur'],
                ['titre' => 'Nouvelle inscription', 'texte' => 'Inscrire une personne ou une structure pour la saison.', 'page' => 'ajout_adherent.php', 'role' => 'standard'],
                ['titre' => 'Les familles', 'texte' => 'Membres de chaque famille, saison par saison.', 'page' => 'familles.php', 'role' => 'lecteur'],
            ],
        ],
        [
            'titre' => 'Les activités',
            'role'  => 'lecteur',
            'cards' => [
                ['titre' => 'Liste des activités', 'texte' => 'Ateliers, sorties, évènements, séjours et leurs inscrits.', 'page' => 'activites.php', 'role' => 'lecteur'],
                ['titre' => 'Nouvelle activité', 'texte' => 'Créer une activité pour la saison.', 'page' => 'ajout_activite.php', 'role' => 'standard'],
                ['titre' => 'Secteurs d\'activité', 'texte' => 'Liste des secteurs.', 'page' => 'secteurs.php', 'role' => 'admin'],
            ],
        ],
        [
            'titre' => 'Les paiements',
            'role'  => 'standard',
            'cards' => [
                ['titre' => 'Liste des paiements', 'texte' => 'Paiements enregistrés sur une période.', 'page' => 'paiements.php', 'role' => 'standard'],
                ['titre' => 'Nouveau paiement', 'texte' => 'Encaisser un paiement pour une famille ou une structure.', 'page' => 'ajout_paiement.php', 'role' => 'standard'],
                ['titre' => 'Les tarifs', 'texte' => 'Cotisations et tarifs des activités.', 'page' => 'tarifs.php', 'role' => 'standard'],
                ['titre' => 'État des paiements', 'texte' => 'Reste à payer et trop-perçu des familles pour la saison.', 'page' => 'etat_paiements.php', 'role' => 'standard'],
            ],
        ],
    ];
}

/**
 * Parties et cards de la page des paramètres (administrateurs).
 *
 * @return list<array{titre: string, role: string, cards: list<array{titre: string, texte: string, page: string, role: string}>}>
 */
function cards_parametres(): array
{
    return [
        [
            'titre' => 'Les utilisateurs',
            'role'  => 'admin',
            'cards' => [
                ['titre' => 'Liste des utilisateurs', 'texte' => 'Comptes, rôles, activation.', 'page' => 'utilisateurs.php', 'role' => 'admin'],
                ['titre' => 'Nouvel utilisateur', 'texte' => 'Créer un compte et envoyer l\'invitation.', 'page' => 'ajout_utilisateur.php', 'role' => 'admin'],
            ],
        ],
        [
            'titre' => 'Options',
            'role'  => 'admin',
            'cards' => [
                ['titre' => 'Options d\'affichage', 'texte' => 'Nom de la structure, logo et couleur principale.', 'page' => 'options.php', 'role' => 'admin'],
                ['titre' => 'Options des activités', 'texte' => 'Types d\'activité et récurrences : les listes proposées pour les activités.', 'page' => 'options_activites.php', 'role' => 'admin'],
                ['titre' => 'Quartiers', 'texte' => 'Communes et arrondissements dont les quartiers sont pris en compte pour les adresses.', 'page' => 'quartiers.php', 'role' => 'admin'],
            ],
        ],
        [
            'titre' => 'La base de données',
            'role'  => 'admin',
            'cards' => [
                ['titre' => 'Saisons', 'texte' => 'Créer et modifier les saisons.', 'page' => 'saisons.php', 'role' => 'admin'],
                ['titre' => 'Données de référence', 'texte' => 'Pays, quartiers, QP, QPV, codes postaux : consulter et mettre à jour.', 'page' => 'donnees_reference.php', 'role' => 'admin'],
                ['titre' => 'Logs', 'texte' => 'Journal des actions de la saison en cours.', 'page' => 'journal.php', 'role' => 'admin'],
                ['titre' => 'Sauvegarde', 'texte' => 'Exporter la base et télécharger les sauvegardes.', 'page' => 'sauvegarde.php', 'role' => 'admin'],
                ['titre' => 'RGPD', 'texte' => 'Anonymisation des familles inactives.', 'page' => 'rgpd.php', 'role' => 'admin'],
            ],
        ],
        [
            'titre' => 'Configuration',
            'role'  => 'admin',
            'cards' => [
                ['titre' => 'Paramètres des mails', 'texte' => 'Serveur d\'envoi, expéditeur, essai d\'envoi.', 'page' => 'config_mails.php', 'role' => 'admin'],
            ],
        ],
    ];
}

/**
 * Affiche des parties de cards. Une card que l'utilisateur n'a pas le droit
 * d'utiliser n'est pas affichée ; une partie sans card visible disparaît.
 * (Le droit est de toute façon revérifié sur chaque page.)
 *
 * @param list<array{titre: string, role: string, cards: list<array{titre: string, texte: string, page: string, role: string}>}> $parties
 */
function afficher_cards(array $parties): void
{
    foreach ($parties as $partie) {
        if (!role_au_moins($partie['role'])) {
            continue;
        }
        $visibles = array_values(array_filter(
            $partie['cards'],
            static fn (array $card): bool => role_au_moins($card['role'])
        ));
        if ($visibles === []) {
            continue;
        }
        ?>
        <section class="mb-4">
            <h2 class="h5 mb-3"><?= e($partie['titre']) ?></h2>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
                <?php foreach ($visibles as $card) : ?>
                    <div class="col">
                        <?php if (page_existe($card['page'])) : ?>
                            <div class="card h-100">
                                <div class="card-body">
                                    <h3 class="card-title h6">
                                        <a class="stretched-link text-decoration-none" href="<?= e(chemin_site($card['page'])) ?>"><?= e($card['titre']) ?></a>
                                    </h3>
                                    <p class="card-text small text-body-secondary mb-0"><?= e($card['texte']) ?></p>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="card h-100 card-a-venir">
                                <div class="card-body">
                                    <h3 class="card-title h6"><?= e($card['titre']) ?> <span class="badge text-bg-secondary">À venir</span></h3>
                                    <p class="card-text small text-body-secondary mb-0"><?= e($card['texte']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
