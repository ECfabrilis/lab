# Déploiement sur Hostinger

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
   - Dépôt : `git@github.com:ECfabrilis/lab.git` ; branche : `main` ;
     dossier : `mon_application` (il doit être absent ou vide).

3. **Vérifier `vendor/`** dans le gestionnaire de fichiers : le dossier
   `mon_application/vendor/phpmailer` doit exister, ainsi que
   `mon_application/vendor/dompdf` (reçus en PDF). S'ils manquent, les installer
   d'une de ces deux façons :
   - en SSH : `cd ~/domains/monsite.com/public_html/mon_application`, puis
     `composer install --no-dev` ;
   - sans SSH : lancer `composer install --no-dev` dans une copie du projet sur
     le poste local, puis envoyer le dossier `vendor/` obtenu par FTP.

4. **Créer `_chemins.php`** : dans `mon_application/public/`, copier
   `_chemins.exemple.php` sous le nom `_chemins.php`, sans le modifier.

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
   une installation terminée.

7. **Contrôler la protection** de la partie privée. Ces deux adresses doivent
   répondre « 403 Forbidden » :

   - `https://monsite.com/mon_application/config.exemple.php`
   - `https://monsite.com/mon_application/.git/config`

   Si l'une d'elles affiche un contenu, ne saisir aucune donnée et corriger
   d'abord.

## Mise à jour (à chaque nouvelle version)

1. Pousser les commits sur GitHub.
2. **Si la version contient un nouveau fichier dans `sql/migrations/`** : faire
   une sauvegarde de la base, puis passer ce fichier dans phpMyAdmin.
3. Panneau Hostinger → Avancé → Git → bouton « Déployer ».
4. Si `composer.json` a changé (rare) : refaire l'étape 3 de la première installation.
   C'est le cas de la version « étape 3 » (cotisations et paiements), qui ajoute
   Dompdf : sans lui, tout fonctionne sauf le reçu en PDF.

La mise à jour ne touche jamais à `config.php`, `public/_chemins.php`, `logs/`,
`sauvegardes/`, ni au logo et à la couleur enregistrés depuis les options : ces
fichiers ne sont pas versionnés.

**Déploiement automatique.** Le panneau propose une adresse (« webhook ») à
ajouter dans GitHub pour déployer à chaque `git push`. À n'activer qu'en
connaissance de cause : le code part alors en ligne avant qu'une éventuelle
migration SQL ait été passée.

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
