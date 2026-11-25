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
const APP_VERSION = '0.18.0';

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
            'version'     => '0.18.0',
            'date'        => '2026-11-26',
            'changements' => [
                'Sécurité : un nouveau mot de passe doit compter au moins 12 caractères et ne contenir ni le prénom ni l\'adresse e-mail. Les mots de passe actuels restent valables.',
                'Sécurité : changer ou réinitialiser un mot de passe ferme toutes les autres sessions ouvertes avec ce compte. À cette mise à jour, chacun doit se reconnecter une fois.',
                'Sécurité : après trop d\'essais de connexion refusés depuis un même poste, les essais sont suspendus 15 minutes ; « Mot de passe oublié » n\'envoie qu\'un courriel toutes les 10 minutes.',
                'Sauvegardes : celles faites d\'office avant une mise à jour sont limitées aux 5 plus récentes ; la page avertit quand trop de fichiers s\'accumulent.',
                'Fiche d\'une activité : le tarif n\'est plus affiché aux lecteurs, comme les autres montants.',
            ],
        ],
        [
            'version'     => '0.17.1',
            'date'        => '2026-11-25',
            'changements' => [
                'Un guide d\'installation et un guide d\'utilisation sont fournis avec l\'application (dossier docs/).',
                'La licence d\'utilisation et la liste des composants de tiers sont fournies avec l\'application.',
            ],
        ],
        [
            'version'     => '0.17.0',
            'date'        => '2026-11-25',
            'changements' => [
                'Informations recueillies : trois nouveaux interrupteurs, pour le quartier, le quartier prioritaire (QP) et le QPV. Non recueillis, ils disparaissent de la fiche, des filtres des listes, des statistiques et de l\'export.',
                'Un quartier, un QP ou un QPV non recueilli n\'est plus calculé à l\'enregistrement d\'une adresse ; les adresses déjà enregistrées gardent les leurs.',
            ],
        ],
        [
            'version'     => '0.16.0',
            'date'        => '2026-11-25',
            'changements' => [
                'Nouvelle page Paramètres > Options > Informations recueillies : le bénévolat et la scolarité peuvent ne plus être demandés. Ils disparaissent alors des formulaires, des listes, des statistiques et des exports ; ce qui a déjà été saisi est conservé.',
                'Statistiques : la date à laquelle l\'âge est calculé se règle dans les options des statistiques (31 décembre de l\'année de début, premier jour ou dernier jour de la saison).',
                'Installation : l\'assistant demande le mois où commence une saison (septembre proposé) pour créer la première saison.',
            ],
        ],
        [
            'version'     => '0.15.0',
            'date'        => '2026-11-25',
            'changements' => [
                'Nouvelle page Paramètres > Configuration > État du système : versions, contrôles du serveur et protection de la partie privée (réglages, journaux, sauvegardes).',
                'Installation : l\'assistant vérifie le serveur avant de commencer (version de PHP, extensions, dossiers accessibles en écriture) et refuse un serveur de base de données qui n\'est pas MariaDB.',
                'Installation : elle ne peut pas se terminer si la partie privée de l\'application est visible depuis le web.',
            ],
        ],
        [
            'version'     => '0.14.1',
            'date'        => '2026-11-25',
            'changements' => [
                'Installation : l\'application peut être installée à partir d\'une archive prête à déposer, sans outil particulier sur le serveur.',
                'Installation : le fichier de chemins (_chemins.php) est créé par l\'assistant, il n\'est plus à copier à la main.',
                'Mise à jour de la base : elle peut aussi être lancée en ligne de commande sur le serveur.',
            ],
        ],
        [
            'version'     => '0.14.0',
            'date'        => '2026-11-25',
            'changements' => [
                'Mise à jour de la base : après l\'installation d\'une nouvelle version, l\'application détecte elle-même les changements à apporter à la base. Un administrateur les applique d\'un clic depuis Paramètres > La base de données > Mise à jour.',
                'Une sauvegarde de la base est toujours faite juste avant la mise à jour.',
                'Tant que la mise à jour n\'est pas faite, l\'application est fermée aux autres utilisateurs, qui voient « Mise à jour en cours ».',
            ],
        ],
        [
            'version'     => '0.13.1',
            'date'        => '2026-11-25',
            'changements' => [
                'L\'icône de l\'onglet du navigateur est désormais le logo choisi dans Paramètres > Options ; sans logo, il n\'y a pas d\'icône.',
                'Nouvelle installation : les quartiers et les quartiers prioritaires ne sont plus remplis d\'office, ils s\'importent dans « Données de référence » ; aucun groupe de lieu de résidence n\'est créé. Les installations existantes ne changent pas.',
                'Données de référence : « Rétablir les données fournies avec l\'application » n\'est proposé que pour les QPV.',
            ],
        ],
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
