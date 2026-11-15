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
const APP_VERSION = '0.9.0';

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
