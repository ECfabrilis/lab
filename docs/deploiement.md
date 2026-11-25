# Déploiement sur Hostinger

Ce document est celui du développeur : il décrit le déploiement par Git. Le
guide remis aux associations, à partir de l'archive, est
[installation.md](installation.md).

La mise en ligne se fait avec la fonction « Git » du panneau Hostinger : elle
copie le dépôt GitHub dans un dossier de l'hébergement, puis le met à jour à la
demande.

Dans ce document, `monsite.com` et `mon_application` sont des exemples à remplacer
par le vrai domaine et le vrai dossier.

## Disposition sur l'hébergement

Tout le projet est dans un seul dossier. Le sous-domaine
`mon_application.monsite.com` pointe vers son dossier `public/`.

```
public_html/
└── mon_application/        copie du dépôt Git
    ├── .htaccess           interdit tout sauf public/ (versionné)
    ├── includes/  sql/  logs/  sauvegardes/
    ├── vendor/             bibliothèques PHP (non versionné)
    ├── config.php          écrit par install.php (non versionné)
    └── public/             dossier du sous-domaine
        └── _chemins.php    copie de _chemins.exemple.php (non versionné)
```

**Point de sécurité.** Le dossier `mon_application` est dans `public_html` : il
est donc aussi joignable par `https://monsite.com/mon_application/…`. La partie
privée (journaux, sauvegardes de la base, `.git`) n'est protégée que par le
fichier `.htaccess` de la racine du projet. Ne jamais le supprimer ni le
remplacer, et refaire le contrôle de l'étape 7 après tout changement
d'hébergement ou de réglage du domaine.

## Première installation (une seule fois)

1. **Créer la base de données** dans le panneau Hostinger (Bases de données →
   Gestion) : nom de la base, utilisateur, mot de passe. Les noter pour l'étape 6.

2. **Relier le dépôt** : panneau Hostinger → site `monsite.com` → Avancé → Git.
   - Le dépôt étant privé, générer la clé SSH proposée sur cette page et la
     copier dans GitHub : dépôt → Settings → Deploy keys → Add deploy key,
     **sans** cocher « Allow write access ».
   - Dépôt : `git@github.com:mon_compte/mon_depot.git` (l'adresse SSH du
     dépôt, donnée par GitHub) ; branche : `main` ;
     dossier : `mon_application` (il doit être absent ou vide).

3. **Vérifier `vendor/`** dans le gestionnaire de fichiers : le dossier
   `mon_application/vendor/phpmailer` doit exister, ainsi que
   `mon_application/vendor/dompdf` (reçus en PDF). S'ils manquent, les installer
   d'une de ces deux façons :
   - en SSH : `cd ~/domains/monsite.com/public_html/mon_application`, puis
     `composer install --no-dev` ;
   - sans SSH : lancer `composer install --no-dev` dans une copie du projet sur
     le poste local, puis envoyer le dossier `vendor/` obtenu par FTP.

4. **`_chemins.php`** : rien à faire. L'assistant de l'étape 6 crée lui-même
   `mon_application/public/_chemins.php` à partir de `_chemins.exemple.php`.
   S'il n'y arrive pas (dossier non accessible en écriture), il demande de
   faire cette copie à la main.

5. **Créer le sous-domaine** `mon_application.monsite.com` et le faire pointer
   vers `public_html/mon_application/public`.

6. **Lancer l'assistant** : ouvrir
   `https://mon_application.monsite.com/install.php` et suivre les 5 étapes
   (adresse du site : `https://mon_application.monsite.com`). L'assistant crée
   toutes les tables à partir de `sql/structure.sql` : aucune migration n'est à
   passer sur une base neuve. À la fin, il crée la saison en cours et supprime
   lui-même `install.php` du serveur (un message le signale s'il n'y arrive
   pas : le supprimer alors à la main). Chaque déploiement recopie ce fichier
   depuis le dépôt : il reste verrouillé (403) tant que `config.php` indique
   une installation terminée. Les quartiers de la commune ne sont pas remplis
   par l'assistant : ils s'importent ensuite dans Paramètres > Données de
   référence (voir `sql/quartiers/LISEZMOI.md`).

7. **Contrôler la protection** de la partie privée. L'assistant le fait
   lui-même pour l'adresse du site (il refuse de terminer si la partie privée
   est visible), et la page Paramètres > État du système refait ce contrôle à
   tout moment. Il reste à vérifier à la main l'autre adresse, celle du
   domaine principal. Ces deux adresses doivent répondre « 403 Forbidden » :

   - `https://monsite.com/mon_application/config.exemple.php`
   - `https://monsite.com/mon_application/.git/config`

   Si l'une d'elles affiche un contenu, ne saisir aucune donnée et corriger
   d'abord.

## Mise à jour (à chaque nouvelle version)

1. Pousser les commits sur GitHub.
2. Panneau Hostinger → Avancé → Git → bouton « Déployer ».
3. **Si la version contient un nouveau fichier dans `sql/migrations/`** :
   l'application le détecte seule. Elle se ferme aux utilisateurs (page « Mise
   à jour en cours ») jusqu'à ce qu'un administrateur se connecte : il arrive
   sur la page « Mise à jour » et clique sur « Sauvegarder puis mettre à
   jour ». Une sauvegarde de la base est faite, puis les migrations sont
   passées dans l'ordre. Il n'y a plus rien à passer dans phpMyAdmin.
4. Si `composer.json` a changé (rare) : refaire l'étape 3 de la première installation.
   C'est le cas de la version « étape 3 » (cotisations et paiements), qui ajoute
   Dompdf : sans lui, tout fonctionne sauf le reçu en PDF.

**Passage à la version 0.14** (première version avec le suivi des
migrations) : la base n'a pas encore la table de suivi. La page « Mise à jour »
la crée et considère que toutes les migrations antérieures (jusqu'au
17/11/2026) ont déjà été passées à la main.

**Si une migration échoue**, la page affiche l'erreur et l'application reste
fermée. Les migrations déjà passées le restent. Corriger la cause puis
relancer, ou restaurer la sauvegarde faite juste avant (son nom est affiché) et
revenir à la version précédente du code. Le détail est aussi dans
`logs/mise_a_jour.log`.

La mise à jour ne touche jamais à `config.php`, `public/_chemins.php`, `logs/`,
`sauvegardes/`, ni au logo et à la couleur enregistrés depuis les options : ces
fichiers ne sont pas versionnés.

**Déploiement automatique.** Le panneau propose une adresse (« webhook ») à
ajouter dans GitHub pour déployer à chaque `git push`. À n'activer qu'en
connaissance de cause : dès qu'une version contient une migration,
l'application se ferme aux utilisateurs jusqu'au passage d'un administrateur.

## Sans Git : installer et mettre à jour avec l'archive

Chaque version existe aussi sous la forme d'une archive, `bddv3-X.Y.Z.zip`, qui
contient tout ce qu'il faut (bibliothèques PHP comprises) : ni Git, ni
Composer sur le serveur. Elle convient à tout hébergement PHP 8.3 ou plus avec
MariaDB et Apache.

**Fabriquer l'archive** (sur le poste de développement, une fois la version
commitée et étiquetée `vX.Y.Z`) :

```
php outils/creer_archive.php
```

Le résultat est dans `livraisons/`, avec son empreinte SHA-256. Le script part
du dernier commit : ni `config.php`, ni journaux, ni sauvegardes ne peuvent s'y
trouver.

**Première installation**

1. Créer la base de données chez l'hébergeur (nom, utilisateur, mot de passe).
2. Décompresser l'archive et déposer le contenu du dossier `bddv3/` dans le
   dossier de l'application (par FTP ou par le gestionnaire de fichiers), en
   gardant le fichier `.htaccess` de la racine.
3. Faire pointer le domaine ou le sous-domaine vers le dossier `public/`.
4. Ouvrir `https://…/install.php` : l'assistant vérifie d'abord le serveur
   (PHP 8.3 ou plus, extensions, dossiers accessibles en écriture, base
   MariaDB), puis déroule ses 5 étapes.
5. Faire le contrôle de protection de l'étape 7 ci-dessus.

Le détail, tel qu'il est remis aux associations, est dans
[installation.md](installation.md).

**Mise à jour**

1. Faire une sauvegarde (Paramètres > Sauvegarde) et la télécharger.
2. Remplacer les dossiers `includes/`, `sql/`, `vendor/`, `outils/` et `docs/`
   par ceux de l'archive (supprimer les anciens, déposer les nouveaux).
3. Dans `public/`, remplacer tout **sauf** `_chemins.php`,
   `css/couleur.css` et `img/logo_association.*` : ces trois fichiers sont
   propres au site.
4. Ne pas toucher à `config.php`, `logs/` ni `sauvegardes/`.
5. Se connecter avec un compte administrateur : si la version apporte une
   migration, la page « Mise à jour » s'affiche d'elle-même.

## Mise à jour en ligne de commande

Avec un accès SSH, la base se met à jour sans ouvrir le site :

```
php outils/mise_a_jour.php
```

La commande fait une sauvegarde, passe les migrations en attente et affiche le
résultat (code de retour 0 si la base est à jour, 1 sinon). Pour plusieurs
instances déposées côte à côte :

```
for dossier in ~/domains/monsite.com/public_html/*/; do
    [ -f "$dossier/outils/mise_a_jour.php" ] && php "$dossier/outils/mise_a_jour.php"
done
```

## En cas de problème

- **Le déploiement échoue** : un fichier versionné a probablement été modifié
  directement sur l'hébergement. Le remettre dans son état d'origine (ou le
  supprimer), puis relancer le déploiement.
- **Tout le site répond « 403 » par le sous-domaine** : la règle du `.htaccess`
  de la racine s'applique aussi à `public/`. Ajouter la ligne `RewriteEngine On`
  dans `public/.htaccess` (en local, puis commit et déploiement).
- **Page blanche ou erreur sur PHPMailer** : `vendor/` est absent ou incomplet
  (étape 3 de la première installation).
- **Le reçu PDF ne s'ouvre pas** (« la bibliothèque PDF est absente ») :
  `vendor/dompdf` manque (étape 3 de la première installation). Si le logo
  n'apparaît pas sur le reçu alors que c'est une image PNG : l'extension PHP
  « gd » n'est pas activée (panneau Hostinger → Configuration PHP → Extensions).
- **Revenir à la version précédente** : annuler le commit en local
  (`git revert`), pousser, puis redéployer. Une migration SQL déjà passée n'est
  pas annulée par cette opération.
