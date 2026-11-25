# CLAUDE.md — Application web de gestion d'association (bddv3)

Application PHP / Bootstrap / SCSS pour une association : adhérents, familles, dossiers, activités, saisons, tarifs, paiements.
Réponds toujours **en français** (code commenté en français, messages affichés en français).

**Avant toute tâche fonctionnelle, lis `docs/cahier_des_charges.md`** : il décrit ce que l'application doit faire, les décisions prises, les questions encore ouvertes et l'ordre de construction. En cas de doute ou de contradiction, demande plutôt que de deviner ; ne construis pas une fonction listée « hors périmètre ».

## Environnement

- **Production** : Hostinger, PHP 8.5.12, MariaDB 11.8.9. Pas de Node ni de Composer dev en production.
- **Local** : WAMP (mêmes versions de PHP et MariaDB), Mailpit (faux serveur de courriel), base de test locale, `config.php` local.
- **Outils** : Git + GitHub (dépôt privé), Composer (PHPMailer, Dompdf, PHPStan), Node + Sass (Bootstrap 5.3.9, numéro exact dans `package.json` : après une montée de version, `npm run css` puis `npm run js`, qui recopie `bootstrap.bundle.min.js` dans `public/js/`).
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
- **Installation** : `public/install.php` (cahier des charges, section 12.1), en 5 étapes, à lancer une fois par environnement. Tant que le réglage `INSTALLATION_TERMINEE` n'est pas à `true`, `init.php` renvoie toutes les pages vers l'assistant ; ensuite `install.php` refuse de s'exécuter (403) ; à la fin d'une installation réussie il se supprime lui-même si possible (en local, ne pas committer cette suppression : `git restore public/install.php`). Les tables déjà présentes ne sont jamais modifiées sans confirmation.
- Une seule porte d'entrée de démarrage : `includes/init.php` (session sécurisée, en-têtes de sécurité, chargement des fonctions).

**Emplacement selon l'environnement** (le chemin diffère, le code ne change pas) :

| | Pages publiques | Partie privée |
|---|---|---|
| Local (WAMP) | `<projet>/public/` | `<projet>/` (includes, config.php, sql, logs) |
| Hostinger | `public_html/<dossier>/public/` (cible du sous-domaine) | `public_html/<dossier>/` (clone du dépôt) |

- Sur Hostinger comme en local, tout le projet est sous la racine web : **le `.htaccess` de la racine du projet** interdit tout ce qui n'est pas `public/`. C'est la seule protection de la partie privée (journaux, sauvegardes, `.git`) : ne jamais le supprimer ni l'affaiblir.
- **Contrôles du serveur** (`systeme_controles()`, `systeme_base()` dans `includes/fonctions_installation.php`) : faits par `install.php` avant l'étape 1 et affichés par `etat_systeme.php` ; toute nouvelle exigence (extension PHP, dossier où l'application écrit) y est ajoutée. La protection de la partie privée se contrôle depuis le navigateur (`public/js/protection.js`), qui tente de lire `includes/temoin_protection.txt` : ne pas supprimer ce fichier ; l'installation ne se termine pas s'il est lisible.
- **`public/_chemins.php`** définit `DOSSIER_PRIVE` (chemin de la partie privée). Toutes les pages le chargent en premier, puis font `require DOSSIER_PRIVE . '/includes/init.php';`. Dans les deux environnements : `define('DOSSIER_PRIVE', dirname(__DIR__));`
- `_chemins.php` n'est pas versionné (il peut différer selon l'environnement) ; le modèle `_chemins.exemple.php` l'est. Ne jamais l'écraser lors d'un déploiement. `install.php` le crée à partir du modèle s'il manque.
- **`outils/`** (partie privée, ligne de commande seulement) : `creer_archive.php` fabrique sur le poste de développement `livraisons/bddv3-X.Y.Z.zip` à partir du dernier commit étiqueté (`vendor/` sans les outils de développement) ; ce qui entre dans l'archive est la liste `ARCHIVE_CONTENU` : tout nouveau dossier nécessaire au fonctionnement y est ajouté. `mise_a_jour.php` passe les migrations sur le serveur, comme la page du même nom.
- En local, le site est dans un **sous-dossier** : les liens internes ne doivent jamais commencer par `/` ; ils passent par `chemin_site()`, qui s'appuie sur `APP_URL`.
- **Documentation remise aux associations** (dans l'archive) : `docs/installation.md` (installer et mettre à jour à partir de l'archive, sans Git) et `docs/guide_utilisation.md` (accueil et administrateurs) ; une fonction ajoutée ou modifiée y est décrite. `LICENCE.txt` (licence propriétaire : usage seul) et `LICENCES_TIERCES.txt` (bibliothèques et données fournies, à mettre à jour avec `composer.lock` et `package.json`). Hors archive : `docs/deploiement.md` (déploiement par Git, pour le développeur), `docs/modeles/` (contrat de sous-traitance, mention d'information), le cahier des charges.
- **Déploiement** (`docs/deploiement.md`) : la fonction « Git » du panneau Hostinger copie la branche `main` du dépôt dans `public_html/<dossier>/` (bouton « Déployer »). `vendor/` n'est pas versionné : il s'installe à part (`composer install --no-dev`). Les migrations SQL se passent après le déploiement, par un administrateur, depuis la page « Mise à jour ». Pas de Node en production : le CSS compilé est versionné.

## Conventions de code

- **`declare(strict_types=1);`** en première instruction de **chaque** fichier PHP, et types déclarés sur tous les paramètres et retours de fonction.
- **Pas de variables globales partagées** : la connexion à la base s'obtient avec `db()` (jamais `$pdo` global) ; l'utilisateur courant avec `utilisateur_courant()`.
- Fonctions réutilisables dans `includes/mes_fonctions.php` ; fonctions propres à un domaine dans `includes/fonctions_<domaine>.php` (jamais deux fichiers de même nom dans deux dossiers).
- Pages : fichiers à la racine de `public/`, noms en minuscules avec underscore : liste `xxxs.php`, fiche `xxx.php`, création `ajout_xxx.php`.
- **Formulaires : Post/Redirect/Get.** Le traitement du POST est en haut de la page, avant tout HTML, et se termine toujours par `rediriger()` (303), y compris en cas d'erreur. Jamais de page affichée directement en réponse à un POST.
- **Exception : les aides à la saisie** (`public/api_*.php`, appelées par le JavaScript des formulaires) répondent en JSON à un POST, sans redirection. Elles commencent par `api_exiger('standard')` (connexion, rôle, jeton CSRF).
- **Menu, tableau de bord et paramètres** (cahier des charges, section 12.3) : les choix sont des cards décrites dans `includes/fonctions_navigation.php`. Pour ajouter un choix, on y ajoute une ligne ; une card dont la page n'existe pas encore s'affiche « À venir » et s'active quand la page est créée.
- **Version** (`includes/fonctions_version.php`) : `APP_VERSION` et `notes_version()` ; à chaque livraison, changer le numéro et ajouter une entrée en tête des notes. Le pied de page (`footer.php`) affiche la version avec un lien vers `notes_version.php` ; les pages sans connexion n'affichent que `version_courte()`, sans lien.
- **Icônes** : chaque card et chaque entrée du menu porte une clé `icone` (nom Font Awesome sans « fa- »), affichée par `icone()`. Font Awesome Free, copié dans `public/css/fontawesome.min.css` et `public/webfonts/` par `npm run icones` et versionné (pas de kit ni de script extérieur : la CSP les bloque). Usage : `<i class="fa-solid fa-user" aria-hidden="true"></i>`.
- **Le mot « dossier » n'apparaît jamais à l'écran** (cahier des charges, section 3.1) : ni libellé, ni lien, ni page. Le dossier reste dans la base et dans le code ; l'interface dit « inscrit(e) pour la saison », « notes de la saison », « paiements de la famille ».
- **Informations recueillies** (cahier des charges, section 3.2 ; page `options_fiche.php`) : le bénévolat, la scolarité, le quartier, le QP et le QPV sont facultatifs. Tout code qui demande, affiche, filtre, compte ou exporte l'une de ces informations teste `champ_actif()` avec sa clé (`benevole`, `scolarite`, `quartier`, `qp`, `qpv`). Quartier, QP et QPV non recueillis ne sont pas calculés à l'enregistrement d'une adresse (`quartiers_du_point()`) ; une phrase qui les cite passe par `quartiers_phrase()`, qui ne nomme que ceux qui sont recueillis. Une information non recueillie n'est jamais effacée ni remise à zéro par l'enregistrement d'un formulaire où elle n'apparaît pas. Pour rendre facultative une autre information : une ligne dans `champs_recueillis()` (`includes/mes_fonctions.php`), puis les mêmes tests.
- **Formulaire d'inscription** (section 12.2) : `ajout_adherent.php`, 4 étapes gardées en session (`includes/fonctions_inscription.php`) ; rien n'est écrit en base avant la validation finale.
- Chaque formulaire contient `csrf_champ()` ; les formulaires de création ajoutent `jeton_unique_champ('nom')` (anti-double envoi). Les valeurs saisies sont réaffichées avec `ancien()` (jamais les mots de passe).
- Messages à l'utilisateur : `flash($type, $message)` avant une redirection ; types `succes`, `avertissement`, `erreur`, `info`.
- **Tout texte venant d'un utilisateur ou de la base passe par `e()`** avant d'être écrit dans la page.
- Requêtes SQL : toujours préparées (PDO, `ATTR_EMULATE_PREPARES => false`), jamais de concaténation de valeurs.
- Mots de passe : `password_hash(PASSWORD_DEFAULT)` ; longueur minimale 12 pour un nouveau mot de passe (`erreur_mdp()`, qui refuse aussi le prénom et l'adresse e-mail). Jamais de mot de passe en clair dans la base, les journaux ou les messages.
- Noms de variables de pages : ne jamais réutiliser ceux posés par `header.php` (`$u`, `$menu`, `$logo`, `$nom_asso`, `$saison`, `$saisons`, `$libelles_roles`, `$messages`, `$retour_saison`, `$css_couleur`). Une page ne lit pas non plus ces variables (PHPStan ne suit pas l'inclusion) : elle appelle les fonctions (`utilisateur_courant()`, `saison_courante()`, `saisons_liste()`, `libelles_roles()`).
- **Rien de propre à une association dans le code** (l'application doit pouvoir servir à d'autres) : ni nom (CEFIA), ni lieu (Paris, 17e, codes postaux), ni logo, ni texte qui les cite, y compris comme valeur par défaut. Le nom, l'adresse et le logo se lisent par `parametre('nom_association')`, `parametre('adresse_association')` et `logo_association()` (qui renvoie '' sans logo : prévoir l'affichage sans image). Ce qui est propre à l'association va dans les réglages (`parametres`) ou les données saisies.
- Pas de script ni de style écrits dans les pages : la politique de sécurité du contenu (CSP) les bloque. Le JavaScript va dans `public/js/`, les styles dans le SCSS.
- HTML/Bootstrap : un `.row` va dans un `.container` ou dans un `.col`, sans décoration (fond, bordure) sur le `.row` lui-même ; le décor va sur une `div` parente.
- Mise en page des listes : colonne principale à gauche (`col-md-8`), colonne des actions à droite (`col-md-4`, `#menuLateral`) ; en dessous de `md`, la colonne d'actions passe en premier.

## Sécurité

- Ne jamais committer `config.php`, `.env`, `vendor/`, `node_modules/` ni de sauvegarde de base.
- Ne jamais demander ni utiliser les identifiants de la base de production.
- Les actions sensibles (rôle, désactivation, adresse e-mail) sont réservées aux administrateurs et vérifiées **côté serveur**, pas seulement par le masquage d'un bouton.
- Chaque création, modification, suppression ou action sensible est notée dans `logs_bdd` (sans mot de passe ni jeton). Exception voulue : les réductions ne sont pas journalisées en détail (décision de l'association).
- Un administrateur ne peut pas se désactiver ni changer son propre rôle, ni retirer le dernier administrateur actif.
- **Connexion** (`includes/fonctions_auth.php`) : un seul message d'erreur, quel que soit le motif du refus ; blocage par compte (5 échecs) et par adresse IP (table `connexions_echecs`, 10 échecs en 15 minutes, lignes effacées après 24 heures). La session garde l'empreinte du mot de passe (`session_noter_mdp()`) : tout code qui change le mot de passe de l'utilisateur connecté l'appelle ensuite, sinon il est déconnecté.
- **HTTPS** : `site_en_https()` (d'après `APP_URL`) décide du cookie sécurisé et de l'en-tête HSTS ; `est_https()` (d'après la requête) ne sert qu'à l'information, jamais à une décision de sécurité.
- **Partie privée** : chaque dossier privé porte un `.htaccess` « tout interdit » (`Require all denied`) ; tout nouveau dossier privé en reçoit un, et entre dans `ARCHIVE_CONTENU`. Les sauvegardes ont un nom aléatoire (`SAUVEGARDE_MOTIF_NOM`).

## Base de données

- Schéma de référence : `sql/structure.sql` et `sql/donnees_reference.sql`. Toute modification de structure passe par un fichier de migration daté, jamais par une modification directe de la base.
- **Migrations** (`includes/fonctions_mise_a_jour.php`, page `mise_a_jour.php`) : un fichier `sql/migrations/AAAA-MM-JJ_nom.sql` (minuscules, chiffres, underscore) par changement, reporté aussi dans `sql/structure.sql`. Les fichiers passés sont notés dans la table `migrations` ; ils s'appliquent dans l'ordre des noms, uniquement par `mise_a_jour_appliquer()`, après une sauvegarde. Une migration doit être **relançable** (`IF NOT EXISTS`, `IF EXISTS`, `INSERT IGNORE`) : un changement de structure ne s'annule pas, on relance après une erreur. Tant qu'un fichier n'est pas passé, `init.php` ferme l'application à tous sauf aux administrateurs (`mise_a_jour_controler()`). Ne jamais modifier une migration déjà livrée : en écrire une nouvelle.
- Nommage : `snake_case`, noms de tables au pluriel, sans accents ; clés étrangères systématiques ; `ON DELETE RESTRICT` pour les paiements (annulation par un indicateur, jamais par suppression).
- Données rattachées à une saison par `saison_id` ; clé composite (tarif, saison) pour garantir qu'une activité utilise un tarif de sa saison. Les secteurs, les types d'activité et les récurrences sont communs à toutes les saisons : ils se rendent inactifs (`est_actif = 0`) et ne se suppriment que s'ils ne sont utilisés par aucune activité.
- Les types de tarif (`types_tarif`) et les modes de paiement (`modes_paiement`) sont deux listes du même genre (fonctions `choix_*`, page `options_paiements.php`). Deux types de tarif sont figés par leur `comportement` : `adhesion` (réservé aux cotisations) et `forfait` (ne se paie qu'une fois) ; le code teste ce comportement, jamais le nom du type.
- Inscriptions aux activités (`inscriptions`) : annulation par indicateur, jamais par suppression ; le montant est copié sur l'inscription au moment où elle est faite ; les places se vérifient dans une transaction qui verrouille l'activité (`activite_verrouiller()`).
- Tarif groupe (`tarifs.montant_groupe`, `inscriptions.est_tarif_groupe`) : montant par personne, jamais sur un tarif `adhesion` ou `forfait`, jamais avec un montant familial. Il s'applique uniquement par `tarif_groupe_appliquer()`, dès que deux membres de la famille sont inscrits à la même activité : c'est la seule exception au montant copié (l'inscription du premier membre passe aussi au montant groupe) ; rien n'est défait si un membre annule.
- Trois valeurs de `inscriptions.statut` : `inscrit` (prend ses places), `attente` (liste d'attente), `inactif` (places libérées, mais toujours comptée dans les statistiques, le montant dû et le signalement « sans cotisation » ; `inscription_rendre_inactive()` / `inscription_reactiver()`, date et auteur dans `inactive_le` / `inactivation_par`). Places prises : `statut = 'inscrit'` seulement ; montant dû et statistiques : `statut IN ('inscrit', 'inactif')` ; une inscription annulée (`est_annulee = 1`) ne compte nulle part, quel que soit son statut.
- RGPD (`includes/fonctions_rgpd.php`, page `rgpd.php`) : anonymisation **manuelle et définitive** des familles inactives après 3 ans, uniquement par `famille_anonymiser()`, ou `adherent_anonymiser()` pour une personne seule (le dernier membre entraîne la famille entière) (identité, contacts, adresse, notes et journal effacés ; sexe, année de naissance, code postal, quartiers, scolarité et montants gardés pour les statistiques) ; refusée si la famille est inscrite pour la saison en cours ou à venir ; structures non concernées. Le journal (`logs_bdd`) ne couvre que la saison en cours : un mois après le début d'une saison, les lignes antérieures sont effacées à la première connexion (`journal_purger_saisons_passees()`).
- Noms enregistrés en majuscules (`nom_normaliser()`), prénoms avec une majuscule initiale (`prenom_normaliser()`) ; le nom d'une structure reste tel que saisi.
- Une **structure** (association, école…) est une ligne de `adherents` avec `est_structure = 1`, sans famille ; son dossier est rattaché par `dossiers.structure_id`.
- Changement de famille (page `changer_famille.php`, cahier des charges section 3.1) : uniquement par `adherent_quitter_famille()` (la personne part dans une nouvelle famille à son nom, ses lignes la suivent pour toutes les saisons, les paiements restent) et `adherent_rejoindre_famille()` (personne seule dans sa famille : tout passe dans la famille d'accueil, l'ancienne famille est supprimée). Les lignes d'une personne sont toujours dans les dossiers de sa famille actuelle.
- Cotisations, réductions et paiements (`includes/fonctions_paiements.php`) sont rattachés au dossier. Cotisations et paiements s'annulent par indicateur ; une réduction se supprime. Le montant dû se calcule toujours par `comptes_lignes()` / `compte_du_dossier()` (en centimes), jamais ailleurs. Les montants ne s'affichent qu'au rôle `standard` au minimum.
- Statistiques (`includes/fonctions_statistiques.php`, page `statistiques.php`) : calculées à la demande, jamais stockées ; âge à la date de référence réglée (`stats_date_age()`, réglage `stats_age_reference` : par défaut le 31 décembre de l'année de début de la saison) ; structures exclues des chiffres par personne. Tranches d'âge (réglage `stats_tranches_age`) et groupes de lieu de résidence (`groupes_residence`, `groupes_residence_codes`) se règlent dans `options_statistiques.php`, pour toutes les saisons, ainsi que la palette des graphiques (réglages `stats_palette` et `stats_palette_perso`). Graphiques : Chart.js, copié dans `public/js/` par `npm run js` et versionné ; un script propre à une page se déclare par `$scripts_page` avant `footer.php`.
- Documents PDF : `document_pdf()` (`includes/fonctions_documents.php`, Dompdf), avec l'en-tête de l'association.
- Adresse appliquée à toute la famille (case à cocher de `adherent.php`) : uniquement par `adresse_appliquer_aux_membres()`, pour les autres membres du même dossier ; l'établissement scolaire et le niveau ne sont jamais recopiés.
- Quartier, quartier prioritaire (QP) et quartier de la politique de la ville (QPV) de l'adresse sont conservés en **instantané** (texte) au moment de la saisie.
- Données fournies à l'installation : pays, QPV (France entière), codes postaux. Les quartiers et les QP dépendent de la commune : ils ne sont jamais installés d'office, ils s'importent en GeoJSON dans `donnees_reference.php` (exemples dans `sql/quartiers/`). Aucun groupe de lieu de résidence n'est créé par défaut.

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
