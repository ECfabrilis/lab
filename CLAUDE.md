# CLAUDE.md — Application web de gestion d'association (bddv3)

Application PHP / Bootstrap / SCSS pour une association : adhérents, familles, dossiers, activités, saisons, tarifs, paiements.
Réponds toujours **en français** (code commenté en français, messages affichés en français).

**Avant toute tâche fonctionnelle, lis `docs/cahier_des_charges.md`** : il décrit ce que l'application doit faire, les décisions prises, les questions encore ouvertes et l'ordre de construction. En cas de doute ou de contradiction, demande plutôt que de deviner ; ne construis pas une fonction listée « hors périmètre ».

## Environnement

- **Production** : Hostinger, PHP 8.5.12, MariaDB 11.8.9. Pas de Node ni de Composer dev en production.
- **Local** : WAMP (mêmes versions de PHP et MariaDB), Mailpit (faux serveur de courriel), base de test locale, `config.php` local.
- **Outils** : Git + GitHub (dépôt privé), Composer (PHPMailer, PHPStan), Node + Sass (Bootstrap 5.3.8).
- Version minimale du code : **PHP 8.3**. Utilise les fonctionnalités modernes (types, `match`, `str_contains`, `?->`).

## Commandes à lancer avant de dire qu'une tâche est terminée

- `php -l fichier.php` sur chaque fichier PHP modifié.
- `vendor\bin\phpstan analyse` (niveau défini dans `phpstan.neon`) : aucune nouvelle erreur.
- `npm run css` après toute modification du SCSS : le CSS doit se compiler sans erreur.
- Les avertissements « deprecation » venant de `node_modules/bootstrap` sont connus et ignorés.

## Architecture

Le projet est séparé en une partie publique et une partie privée.

- **`public/`** : seules les pages et les ressources web (`css/`, `img/`, `js/`, `.htaccess`).
- **Partie privée** (hors de `public/`) : `includes/` (fonctions), `config.php` (secrets), `sql/`, `logs/`, `sauvegardes/`, `docs/`.
- `config.php` n'est jamais versionné ni lu par Claude : il contient les mots de passe.
- `config.php` est **écrit par l'assistant d'installation**, jamais à la main. Il renvoie un tableau (`return [...]`) ; un réglage se lit avec `config('CLE')`, jamais par une constante ni un `require` direct. La liste des réglages et leur rôle sont dans le modèle versionné `config.exemple.php` : tout nouveau réglage y est ajouté.
- **Installation** : `public/install.php` (cahier des charges, section 12.1), en 5 étapes, à lancer une fois par environnement. Tant que le réglage `INSTALLATION_TERMINEE` n'est pas à `true`, `init.php` renvoie toutes les pages vers l'assistant ; ensuite `install.php` refuse de s'exécuter (403). Les tables déjà présentes ne sont jamais modifiées sans confirmation.
- Une seule porte d'entrée de démarrage : `includes/init.php` (session sécurisée, en-têtes de sécurité, chargement des fonctions).

**Emplacement selon l'environnement** (le chemin diffère, le code ne change pas) :

| | Pages publiques | Partie privée |
|---|---|---|
| Local (WAMP) | `<projet>/public/` | `<projet>/` (includes, config.php, sql, logs) |
| Hostinger | `public_html/<sous-dossier>/` | `<dossier du domaine>/app_bddv3/` (à côté de `public_html`) |

- **`public/_chemins.php`** définit `DOSSIER_PRIVE` (chemin de la partie privée). Toutes les pages le chargent en premier, puis font `require DOSSIER_PRIVE . '/includes/init.php';`.
  - Local : `define('DOSSIER_PRIVE', dirname(__DIR__));`
  - Hostinger, pages dans `public_html/<sous-dossier>/` : `define('DOSSIER_PRIVE', dirname(__DIR__, 2) . '/app_bddv3');` (`__DIR__` = le sous-dossier ; 1 niveau = `public_html`, 2 niveaux = le dossier du domaine). Ajouter 1 par niveau de sous-dossier supplémentaire.
- `_chemins.php` n'est pas versionné (il diffère selon l'environnement) ; le modèle `_chemins.exemple.php` l'est. Ne jamais l'écraser lors d'un déploiement.
- Le site est dans un **sous-dossier** : les liens internes ne doivent jamais commencer par `/` ; ils passent par `chemin_site()`, qui s'appuie sur `APP_URL`.
- **Déploiement** : deux dossiers à mettre à jour (`public/` → `public_html/<sous-dossier>/`, le reste → `app_bddv3/`). Pas de Node en production : le CSS compilé est versionné.

## Conventions de code

- **`declare(strict_types=1);`** en première instruction de **chaque** fichier PHP, et types déclarés sur tous les paramètres et retours de fonction.
- **Pas de variables globales partagées** : la connexion à la base s'obtient avec `db()` (jamais `$pdo` global) ; l'utilisateur courant avec `utilisateur_courant()`.
- Fonctions réutilisables dans `includes/mes_fonctions.php` ; fonctions propres à un domaine dans `includes/fonctions_<domaine>.php` (jamais deux fichiers de même nom dans deux dossiers).
- Pages : fichiers à la racine de `public/`, noms en minuscules avec underscore : liste `xxxs.php`, fiche `xxx.php`, création `ajout_xxx.php`.
- **Formulaires : Post/Redirect/Get.** Le traitement du POST est en haut de la page, avant tout HTML, et se termine toujours par `rediriger()` (303), y compris en cas d'erreur. Jamais de page affichée directement en réponse à un POST.
- Chaque formulaire contient `csrf_champ()` ; les formulaires de création ajoutent `jeton_unique_champ('nom')` (anti-double envoi). Les valeurs saisies sont réaffichées avec `ancien()` (jamais les mots de passe).
- Messages à l'utilisateur : `flash($type, $message)` avant une redirection ; types `succes`, `avertissement`, `erreur`, `info`.
- **Tout texte venant d'un utilisateur ou de la base passe par `e()`** avant d'être écrit dans la page.
- Requêtes SQL : toujours préparées (PDO, `ATTR_EMULATE_PREPARES => false`), jamais de concaténation de valeurs.
- Mots de passe : `password_hash(PASSWORD_DEFAULT)` ; longueur minimale 8. Jamais de mot de passe en clair dans la base, les journaux ou les messages.
- Noms de variables de pages : ne jamais réutiliser ceux posés par `header.php` (`$u`, `$menu`, `$logo`, `$nom_asso`, `$saison`, `$saisons`, `$libelles_roles`, `$messages`). Une page ne lit pas non plus ces variables (PHPStan ne suit pas l'inclusion) : elle appelle les fonctions (`utilisateur_courant()`, `saison_courante()`, `saisons_liste()`, `libelles_roles()`).
- Pas de script ni de style écrits dans les pages : la politique de sécurité du contenu (CSP) les bloque. Le JavaScript va dans `public/js/`, les styles dans le SCSS.
- HTML/Bootstrap : un `.row` va dans un `.container` ou dans un `.col`, sans décoration (fond, bordure) sur le `.row` lui-même ; le décor va sur une `div` parente.
- Mise en page des listes : colonne principale à gauche (`col-md-8`), colonne des actions à droite (`col-md-4`, `#menuLateral`) ; en dessous de `md`, la colonne d'actions passe en premier.

## Sécurité

- Ne jamais committer `config.php`, `.env`, `vendor/`, `node_modules/` ni de sauvegarde de base.
- Ne jamais demander ni utiliser les identifiants de la base de production.
- Les actions sensibles (rôle, désactivation, adresse e-mail) sont réservées aux administrateurs et vérifiées **côté serveur**, pas seulement par le masquage d'un bouton.
- Chaque création, modification, suppression ou action sensible est notée dans `logs_bdd` (sans mot de passe ni jeton). Exception voulue : les réductions ne sont pas journalisées en détail (décision de l'association).
- Un administrateur ne peut pas se désactiver ni changer son propre rôle, ni retirer le dernier administrateur actif.

## Base de données

- Schéma de référence : `sql/structure.sql` et `sql/donnees_reference.sql`. Toute modification de structure passe par un fichier de migration daté, jamais par une modification directe de la base.
- Nommage : `snake_case`, noms de tables au pluriel, sans accents ; clés étrangères systématiques ; `ON DELETE RESTRICT` pour les paiements (annulation par un indicateur, jamais par suppression).
- Données rattachées à une saison par `saison_id` ; clés composites (secteur, saison) et (tarif, saison) pour garantir la cohérence.
- RGPD : anonymisation des familles inactives après 3 ans ; purge des journaux après 1 an.
- Quartier, quartier prioritaire (QP) et quartier de la politique de la ville (QPV) de l'adresse sont conservés en **instantané** (texte) au moment de la saisie.

## Règles métier

- Rôles : `lecteur` < `standard` < `admin`.
- Un utilisateur de l'application est identifié par son **prénom** et son adresse e-mail (pas de nom de famille).
- Saisons : pas de chevauchement ; **une seule saison à venir** à la fois ; les saisons passées sont possibles avec confirmation ; durée maximale 2 ans.
- Un nouvel utilisateur ne reçoit pas de mot de passe : il reçoit un lien par courriel (valable 7 jours) pour choisir le sien.

## Git

- Commits petits et en français, un sujet par commit ; commit avant toute grosse modification.
- Le CSS compilé (`public/css/bddv3.css`) **est versionné** : Hostinger n'exécute pas Node.

## Comment travailler

- Pour une tâche large ou ambiguë, propose un plan court et demande confirmation avant de modifier beaucoup de fichiers.
- Ne modifie pas ce qui n'est pas demandé ; signale ce que tu as remarqué à côté.
- Quand une vérification n'a pas pu être faite (page non testée dans le navigateur, par exemple), dis-le clairement.
