<?php

declare(strict_types=1);

/**
 * Version de l'application et notes de version.
 *
 * À chaque livraison : changer APP_VERSION et ajouter une entrée en tête de
 * notes_version(). Le pied de page affiche la version, avec un lien vers la
 * page notes_version.php.
 */

/** Version de l'application : majeure.mineure.correctif. */
const APP_VERSION = '0.13.0';

/** Version courte, sans le numéro de correctif (0.9 pour 0.9.3) : pages sans connexion. */
function version_courte(): string
{
    return implode('.', array_slice(explode('.', APP_VERSION), 0, 2));
}

/**
 * Notes de version, de la plus récente à la plus ancienne.
 *
 * @return list<array{version: string, date: string, changements: list<string>}> date au format AAAA-MM-JJ
 */
function notes_version(): array
{
    return [
        [
            'version'     => '0.13.0',
            'date'        => '2026-11-17',
            'changements' => [
                'Activités : nouveau champ facultatif « Partenaire » (nom de la structure partenaire), affiché sur la fiche, dans la liste et dans les documents PDF de l\'activité.',
                'Liste des activités : un interrupteur « Avec partenaire » n\'affiche que les activités qui ont un partenaire.',
                'Statistiques : un interrupteur « Avec partenaire » limite les chiffres aux activités qui ont un partenaire, et à leur public.',
            ],
        ],
        [
            'version'     => '0.12.0',
            'date'        => '2026-11-17',
            'changements' => [
                'Liste des familles : seules les familles inscrites pour la saison sont affichées par défaut ; un interrupteur affiche aussi les familles non inscrites.',
                'Liste des familles : nombre d\'enfants de chaque famille, dont ceux de 6 ans ou moins et ceux de 7 à 12 ans.',
                'Liste des familles : filtres par quartier, QP et QPV (au moins un membre de la famille y habite), et familles ayant un enfant de 6 ans ou moins ou de 12 ans ou moins.',
            ],
        ],
        [
            'version'     => '0.11.4',
            'date'        => '2026-11-17',
            'changements' => [
                'Fenêtre « Modifier la fiche » : le choix du responsable parmi les adultes de la famille apparaît dès que la date de naissance saisie est celle d\'une personne mineure, sans enregistrer la fiche d\'abord.',
                'Fenêtre « Modifier la fiche » : la partie « Responsable adulte » n\'est plus affichée pour une personne majeure, et le responsable d\'une personne majeure est effacé à l\'enregistrement de sa fiche.',
            ],
        ],
        [
            'version'     => '0.11.3',
            'date'        => '2026-11-17',
            'changements' => [
                'Fiche d\'une personne mineure : la fenêtre « Modifier la fiche » propose, comme à l\'inscription, de choisir le responsable parmi les adultes de la famille (ou « une autre personne »).',
            ],
        ],
        [
            'version'     => '0.11.2',
            'date'        => '2026-11-17',
            'changements' => [
                'Tableau de bord : « Export Google Contacts » et « Émargement de l\'AG » sont regroupés dans une nouvelle partie « Documents », en dernier.',
            ],
        ],
        [
            'version'     => '0.11.1',
            'date'        => '2026-11-17',
            'changements' => [
                'Export de la liste des paiements pour un tableur, avec les filtres appliqués (période, mode, objet) ; les paiements annulés y figurent, avec la date et l\'auteur de l\'annulation, quand la case « Montrer aussi les paiements annulés » est cochée.',
            ],
        ],
        [
            'version'     => '0.11.0',
            'date'        => '2026-11-17',
            'changements' => [
                'Export de la liste des adhérents pour un tableur (Excel, LibreOffice), avec la recherche et les filtres appliqués (bénévoles, quartier, QP, QPV…), sur toutes les pages de la liste.',
                'Export vers Google Contacts des adhérents de la saison (tableau de bord) : personnes majeures et structures qui ont accepté les communications, avec les libellés « Adhérents [saison] » et « Bénévoles ».',
                'Export vers Google Contacts des participants d\'une activité, depuis sa fiche (liste d\'attente et inscriptions inactives sur demande).',
                'Chaque export est noté dans le journal.',
            ],
        ],
        [
            'version'     => '0.10.0',
            'date'        => '2026-11-16',
            'changements' => [
                'Documents à imprimer (PDF) sur la fiche d\'une activité : liste des participants, liste de sortie (téléphones, responsables des mineurs, case « présent ») et feuille d\'émargement ; la liste d\'attente et les inscriptions inactives s\'ajoutent sur demande.',
                'Attestation de participation (PDF) sur la fiche d\'une personne : pour une activité ou pour toutes les activités de la saison.',
                'Feuille d\'émargement de l\'assemblée générale (PDF), depuis le tableau de bord : adhérents majeurs et structures de la saison.',
                'Les documents PDF portent en bas de page leur titre et le numéro de page.',
            ],
        ],
        [
            'version'     => '0.9.4',
            'date'        => '2026-11-16',
            'changements' => [
                'RGPD : l\'anonymisation d\'une personne ne provoque plus d\'erreur sur certains serveurs (classement des textes de la base).',
                'Quartiers : chaque commune ou arrondissement se déplie pour montrer ses quartiers ; une case permet de tout cocher ou tout décocher.',
                'Quartiers : une zone importée n\'est plus prise en compte d\'office ; la page Paramètres avertit quand aucun quartier ne l\'est.',
            ],
        ],
        [
            'version'     => '0.9.3',
            'date'        => '2026-11-15',
            'changements' => [
                'Quartiers : une zone (commune ou arrondissement) peut être supprimée de la table des quartiers, après confirmation.',
            ],
        ],
        [
            'version'     => '0.9.2',
            'date'        => '2026-11-15',
            'changements' => [
                'Titres des pages : la saison n\'est plus répétée à côté du titre (elle est indiquée par le sélecteur).',
                'Sans logo choisi, le nom de l\'association s\'affiche à la place.',
            ],
        ],
        [
            'version'     => '0.9.1',
            'date'        => '2026-11-15',
            'changements' => [
                'Une icône sur chaque choix du tableau de bord, des paramètres et du menu.',
                'Mise à jour de Bootstrap (5.3.9).',
            ],
        ],
        [
            'version'     => '0.9.0',
            'date'        => '2026-11-15',
            'changements' => [
                'Première version suivie par des notes de version : adhérents, familles, activités, inscriptions, tarifs, paiements, statistiques et RGPD.',
                'Fiche d\'une personne : la nouvelle adresse peut être appliquée aux autres membres de la famille.',
                'Tarifs : les types « Adhésion » et « Forfait » sont proposés en premiers.',
                'Pied de page avec la version de l\'application ; icônes Font Awesome disponibles.',
            ],
        ],
    ];
}
