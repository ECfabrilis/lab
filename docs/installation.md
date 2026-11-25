# Installer et mettre à jour l'application

Ce guide s'adresse à la personne qui installe l'application pour une
association. Il ne demande ni Git, ni Composer, ni ligne de commande : un
hébergement web, l'archive de l'application et un navigateur suffisent.

Dans ce guide, `monasso.org` et `gestion` sont des exemples à remplacer par
votre domaine et votre dossier.

## 1. Ce qu'il faut

| | Minimum |
|---|---|
| Serveur web | Apache (c'est le cas de la plupart des hébergements mutualisés) |
| PHP | 8.3 ou plus, avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `dom` ; `curl` et `gd` sont conseillées |
| Base de données | **MariaDB**, 10.11 ou plus conseillée. L'application ne fonctionne pas avec MySQL. |
| Adresse du site | en `https` |
| Courriel | un compte d'envoi SMTP (serveur, port, identifiant, mot de passe) |

L'assistant d'installation vérifie lui-même ces points et dit quoi corriger :
inutile de les contrôler un par un à l'avance.

## 2. Première installation

1. **Créer la base de données** dans le panneau de l'hébergeur : une base
   vide, un utilisateur et son mot de passe. Notez-les.

2. **Déposer les fichiers.** Décompressez l'archive `bddv3-X.Y.Z.zip` sur votre
   ordinateur, puis envoyez **le contenu** du dossier `bddv3/` dans le dossier
   de l'application chez l'hébergeur (par FTP ou par le gestionnaire de
   fichiers). Vérifiez que le fichier `.htaccess` de la racine est bien parti :
   certains logiciels masquent les fichiers dont le nom commence par un point.

   ```
   gestion/
   ├── .htaccess          interdit tout sauf public/
   ├── includes/  sql/  vendor/  outils/  docs/
   ├── logs/  sauvegardes/
   └── public/            les pages du site
   ```

3. **Faire pointer l'adresse du site sur le dossier `public/`.** Créez par
   exemple le sous-domaine `gestion.monasso.org` et donnez-lui pour dossier
   `gestion/public`. C'est le réglage le plus important : il met tout le reste
   (réglages, journaux, sauvegardes) hors de portée du web.

4. **Ouvrir l'assistant** : `https://gestion.monasso.org/install.php`.
   - Il vérifie d'abord le serveur. Une ligne « à corriger » empêche de
     continuer : suivez l'indication donnée, puis rechargez la page.
   - Étape 1 : la base de données créée au point 1.
   - Étape 2 : l'envoi des courriels et l'adresse du site. Un envoi d'essai est
     proposé : faites-le.
   - Étapes 3 et 4 : création des tables, puis remplissage des pays, des
     codes postaux et des quartiers de la politique de la ville (une minute).
   - Étape 5 : le nom de l'association, le premier administrateur, et le mois
     où commence une saison (septembre, ou janvier pour une année civile).

   Avant de terminer, l'assistant vérifie que la partie privée n'est pas
   visible depuis le web. Si elle l'est, il refuse de terminer et indique les
   deux remèdes : revoir le point 3, ou remettre le fichier `.htaccess`.

5. **Se connecter** avec le compte administrateur créé. L'assistant se
   supprime de lui-même ; s'il n'y arrive pas, il le dit : supprimez alors
   `public/install.php` à la main.

## 3. Après l'installation

À faire une fois, dans **Paramètres** :

- **Options d'affichage** : l'adresse de l'association, son logo et sa couleur.
  Ils figurent sur les reçus et les autres documents.
- **Informations recueillies** : coupez ce que vous ne demandez pas
  (bénévolat, scolarité, quartier, QP, QPV).
- **Options des activités** et **Options des paiements** : adaptez les listes
  proposées (types d'activité, récurrences, types de tarif, modes de paiement).
- **Option des statistiques** : tranches d'âge, date de référence de l'âge,
  groupes de lieu de résidence (par codes postaux).
- **Quartiers**, si vous les recueillez : importez d'abord les contours des
  quartiers de votre commune dans **Données de référence** (fichier GeoJSON,
  en général publié dans les données ouvertes de la commune), puis cochez les
  communes à prendre en compte dans **Options > Quartiers**.
- **Utilisateurs** : créez les comptes. Chaque personne reçoit un lien par
  courriel pour choisir son mot de passe.
- **État du système** : vérifiez que tout est « bon », et que la partie privée
  est bien protégée.

Si le dossier de l'application est aussi joignable par une autre adresse (par
exemple `https://monasso.org/gestion/`), ouvrez
`https://monasso.org/gestion/config.exemple.php` : la réponse doit être
« 403 Forbidden ». Si un contenu ou une page blanche s'affiche, ne saisissez
aucune donnée et corrigez d'abord.

## 4. Sauvegardes

- **Paramètres > Sauvegarde** crée un fichier qui contient toute la base.
  Faites-le au moins une fois par semaine, téléchargez le fichier et gardez-en
  une copie hors de l'hébergeur.
- Ce fichier contient des données personnelles : rangez-le en lieu sûr et
  supprimez les anciennes copies.
- Le logo et la couleur ne sont pas dans la base : ils se remettent en deux
  minutes depuis les options.

**Restaurer une sauvegarde** : dans phpMyAdmin (ou l'outil de l'hébergeur),
ouvrez la base de l'application et importez le fichier de sauvegarde. Il
remplace les tables par celles du jour de la sauvegarde : tout ce qui a été
saisi depuis est perdu. Si la sauvegarde date d'une version plus ancienne de
l'application, la page « Mise à jour » s'affichera à la connexion suivante.

## 5. Mise à jour

1. Faites une **sauvegarde** et téléchargez-la.
2. Décompressez la nouvelle archive sur votre ordinateur.
3. Chez l'hébergeur, **remplacez** les dossiers `includes/`, `sql/`, `vendor/`,
   `outils/` et `docs/` par ceux de l'archive (supprimez les anciens, déposez
   les nouveaux).
4. Dans `public/`, remplacez tout **sauf** ces trois éléments, qui sont
   propres à votre site :
   - `_chemins.php`
   - `css/couleur.css`
   - `img/logo_association.png` (ou `.jpg`)
5. **Ne touchez pas** à `config.php`, `logs/` ni `sauvegardes/`.
6. Connectez-vous avec un compte administrateur. Si la version modifie la
   base, la page « Mise à jour » s'affiche d'elle-même : cliquez sur
   « Sauvegarder puis mettre à jour ». Pendant ce temps, les autres
   utilisateurs voient « Mise à jour en cours ».

Les nouveautés de chaque version sont dans l'application : cliquez sur le
numéro de version, en bas de page.

Avec un accès SSH, la base se met aussi à jour par la commande
`php outils/mise_a_jour.php`, lancée dans le dossier de l'application.

## 6. En cas de problème

| Ce que vous voyez | Cause probable et remède |
|---|---|
| L'assistant marque une ligne « à corriger » | Suivez le conseil affiché sous la ligne : version de PHP ou extension à activer chez l'hébergeur, droits d'écriture d'un dossier. |
| « Ce serveur n'est pas MariaDB » | L'hébergement propose MySQL. Choisissez une base MariaDB ; si l'hébergeur n'en a pas, l'application ne peut pas y être installée. |
| L'assistant refuse de terminer : « la partie privée est visible » | L'adresse du site pointe sur le dossier de l'application au lieu de `public/`, ou le `.htaccess` de la racine manque. |
| Tout le site répond « 403 Forbidden » | L'adresse du site ne pointe pas sur `public/` : le `.htaccess` de la racine bloque alors tout. Revoir le point 3 de l'installation. |
| Une page blanche, ou « Une erreur est survenue » | Le détail est dans `logs/php_erreurs.log`. Le dossier `vendor/` est peut-être incomplet : redéposez-le. |
| Les courriels ne partent pas | Paramètres > Paramètres des mails : vérifiez le serveur, le port et le chiffrement, puis faites un envoi d'essai. |
| Un reçu PDF sans logo | Le logo est une image PNG et l'extension PHP `gd` n'est pas activée. |
| « Mise à jour en cours » qui ne disparaît pas | Un administrateur doit se connecter et terminer la mise à jour. En cas d'échec, l'erreur est affichée et écrite dans `logs/mise_a_jour.log`. |
| Mot de passe administrateur perdu | « Mot de passe oublié » sur la page de connexion envoie un lien par courriel. |

Pour demander de l'aide, joignez ce qu'affiche **Paramètres > État du
système** : cette page ne contient aucun mot de passe.
