<?php

declare(strict_types=1);

/**
 * Saisons : liste, saison affichée, règles de création et de modification.
 *
 * Règles : nom unique ; pas de chevauchement ; une seule saison à venir ;
 * une saison passée demande une confirmation ; durée maximale 2 ans.
 */

/**
 * Toutes les saisons, de la plus récente à la plus ancienne.
 *
 * @return list<array<string, mixed>>
 */
function saisons_liste(): array
{
    return db_lignes('SELECT id, nom, date_debut, date_fin FROM saisons ORDER BY date_debut DESC');
}

/**
 * Une saison par son identifiant, ou null.
 *
 * @return array<string, mixed>|null
 */
function saison_par_id(int $id): ?array
{
    return db_ligne('SELECT id, nom, date_debut, date_fin FROM saisons WHERE id = ?', [$id]);
}

/**
 * État d'une saison par rapport à aujourd'hui.
 *
 * @return 'passee'|'en_cours'|'a_venir'
 */
function etat_saison(string $date_debut, string $date_fin): string
{
    $aujourdhui = date('Y-m-d');
    if ($date_fin < $aujourdhui) {
        return 'passee';
    }
    return $date_debut > $aujourdhui ? 'a_venir' : 'en_cours';
}

/** Libellé d'un état de saison. */
function libelle_etat_saison(string $etat): string
{
    return match ($etat) {
        'passee'  => 'Passée',
        'a_venir' => 'À venir',
        default   => 'En cours',
    };
}

/**
 * Saison affichée : celle choisie dans le menu, sinon la saison en cours,
 * sinon la plus récente. Null s'il n'existe aucune saison.
 *
 * @return array<string, mixed>|null
 */
function saison_courante(): ?array
{
    $choisie = entier($_SESSION['saison_id'] ?? 0);
    if ($choisie > 0) {
        $saison = saison_par_id($choisie);
        if ($saison !== null) {
            return $saison;
        }
        unset($_SESSION['saison_id']);
    }
    $aujourdhui = date('Y-m-d');
    return db_ligne(
        'SELECT id, nom, date_debut, date_fin FROM saisons WHERE date_debut <= ? AND date_fin >= ?',
        [$aujourdhui, $aujourdhui]
    ) ?? db_ligne(
        'SELECT id, nom, date_debut, date_fin FROM saisons WHERE date_debut <= ? ORDER BY date_debut DESC LIMIT 1',
        [$aujourdhui]
    ) ?? db_ligne('SELECT id, nom, date_debut, date_fin FROM saisons ORDER BY date_debut LIMIT 1');
}

/**
 * Vérifie une saison avant création ou modification.
 *
 * @param int $sauf_id identifiant de la saison modifiée (0 pour une création)
 * @return array{erreurs: list<string>, est_passee: bool}
 */
function verifier_saison(string $nom, string $date_debut, string $date_fin, int $sauf_id = 0): array
{
    $erreurs = [];

    if ($nom === '' || mb_strlen($nom) > 50) {
        $erreurs[] = 'Le nom de la saison est obligatoire (50 caractères au maximum).';
    } elseif (db_valeur('SELECT COUNT(*) FROM saisons WHERE nom = ? AND id <> ?', [$nom, $sauf_id]) > 0) {
        $erreurs[] = 'Une saison porte déjà ce nom.';
    }

    if (!date_valide($date_debut) || !date_valide($date_fin)) {
        $erreurs[] = 'Les dates de début et de fin sont obligatoires.';
        return ['erreurs' => $erreurs, 'est_passee' => false];
    }

    if ($date_debut >= $date_fin) {
        $erreurs[] = 'La date de fin doit être après la date de début.';
        return ['erreurs' => $erreurs, 'est_passee' => false];
    }

    // Durée maximale : 2 ans.
    $limite = (new DateTimeImmutable($date_debut))->modify('+2 years')->format('Y-m-d');
    if ($date_fin > $limite) {
        $erreurs[] = 'Une saison ne peut pas durer plus de 2 ans.';
    }

    // Pas de chevauchement avec une autre saison.
    $chevauchement = db_ligne(
        'SELECT nom FROM saisons WHERE id <> ? AND date_debut <= ? AND date_fin >= ? LIMIT 1',
        [$sauf_id, $date_fin, $date_debut]
    );
    if ($chevauchement !== null) {
        $erreurs[] = 'Ces dates chevauchent la saison « ' . texte($chevauchement['nom']) . ' ».';
    }

    // Une seule saison à venir à la fois.
    $etat = etat_saison($date_debut, $date_fin);
    if ($etat === 'a_venir') {
        $autre = db_ligne(
            'SELECT nom FROM saisons WHERE id <> ? AND date_debut > ? LIMIT 1',
            [$sauf_id, date('Y-m-d')]
        );
        if ($autre !== null) {
            $erreurs[] = 'Il existe déjà une saison à venir (« ' . texte($autre['nom']) . ' ») : une seule est possible à la fois.';
        }
    }

    return ['erreurs' => $erreurs, 'est_passee' => $etat === 'passee'];
}
