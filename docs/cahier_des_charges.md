# Cahier des charges — Application de gestion CEFIA

Version 1 — rédigée à partir des réponses de l'association et du rapport d'activité 2025.
Les points marqués **[À TRANCHER]** sont des questions encore ouvertes (voir section 14).

---

## 1. Contexte et objectifs

CEFIA est un centre social associatif parisien (17e arrondissement), présent sur plusieurs sites. L'application remplace l'outil actuel et sert à :

1. **Inscrire** les personnes (adhésion + activités) et **encaisser** les paiements, principalement à l'accueil.
2. **Suivre** les activités : listes de participants, émargement, listes d'attente.
3. **Produire** les documents (reçus, attestations, listes) et les **exports** (comptabilité, Google Contacts).
4. **Mesurer** l'activité : statistiques par période, secteur et type d'activité, pour les bilans (rapport d'activité, CAF, mairie, CNAV, autres financeurs).

**Volumétrie** : 1 000 adhérents maximum par saison. Usage principal sur ordinateur ; tablette et téléphone à prévoir pour la consultation.

**Mise en service** : dès que le socle (sections 15, étapes 0 à 3) est prêt. Reprise des anciennes données : optionnelle, plus tard.

---

## 2. Utilisateurs et droits

| Profil | Usage | Rôle |
|---|---|---|
| Accueil | Inscriptions, paiements, réductions : l'essentiel de la saisie | `standard` |
| Salariés | Ajout de leurs activités, consultation des fiches adhérents et statistiques ; parfois saisie d'inscriptions et paiements comme l'accueil | `lecteur` ou `standard` |
| Administrateurs (salariés ou bénévoles) | Paramétrage, utilisateurs, saisons, tarifs, actions sensibles | `admin` |
| Bénévoles | Consultation ponctuelle (liste d'inscrits, un numéro de téléphone) ; en général **sans compte** | — |

**Décision** : on garde les 3 rôles `lecteur` < `standard` < `admin`. Le détail des droits action par action sera revu plus tard, une fois les fonctions connues.

Règles déjà actées (reprises de l'ancienne application) : un administrateur ne peut pas se désactiver, changer son propre rôle ni retirer le dernier administrateur actif ; un nouvel utilisateur choisit son mot de passe via un lien par courriel valable 7 jours ; mot de passe oublié par courriel.

---

## 3. Personnes

### 3.1 Familles, dossiers, adhérents

Modèle conservé : **famille → dossier (par saison) → adhérents → inscriptions**.

- Une personne ne peut appartenir qu'à **une seule famille**.
- Une famille peut être une personne seule.
- Le **dossier** regroupe, pour une saison, les adhésions, inscriptions, paiements et réductions de la famille.
- Les **paiements sont toujours rattachés à un dossier**.

### 3.2 Fiche adhérent

| Champ | Obligatoire | Remarques |
|---|---|---|
| Nom, prénom | oui | |
| Sexe | oui | |
| Date de naissance | oui | l'âge est calculé, jamais saisi |
| Nationalité | non | liste de pays ; le continent est déduit (statistiques) |
| Adresse | oui | vérifiée par l'API Adresse (BAN) |
| Code postal | oui | proposé à partir d'une table |
| Quartier, quartier prioritaire (QP), QPV | calculés | déterminés automatiquement depuis l'adresse, conservés en **instantané** (API BAN) |
| Courriel, téléphone | non | |
| Bénévole | oui/non | un bénévole est aussi un adhérent |
| Consentement aux communications | oui/non | nécessaire pour l'export Google Contacts |
| Établissement scolaire / niveau | non | facultatif, utile à l'accompagnement à la scolarité ; pourra être retiré s'il n'est pas utilisé |
| Notes | non | notes du dossier |

**Mineur** : un seul **responsable adulte** avec nom, courriel et téléphone.

### 3.3 Structures

Une structure (association, école, etc.) peut être adhérente : nom, adresse, courriel, téléphone, notes. Elle a son propre dossier (pas de famille) et **peut s'inscrire à des activités, plusieurs fois et pour plusieurs places** (exemple : un club réserve 5 places sur 12 pour une activité de yoga). Une structure n'a ni sexe ni âge : elle est exclue des statistiques par personne (voir 10).

---

## 4. Saisons

- Pas de chevauchement ; **une seule saison à venir** à la fois ; saisons passées possibles avec confirmation ; durée maximale 2 ans ; nom unique.
- L'installation crée la première saison.
- Les tarifs, activités et cotisations sont rattachés à une saison.

---

## 5. Cotisation (adhésion)

- La **cotisation est distincte du prix des activités**.
- Elle n'est **pas fixe sur la saison** : un tarif réduit s'applique à partir de la mi-saison. Le tarif applicable est celui **de la date d'inscription**.
- Deux formes : **individuelle** ou **familiale**. Une famille de 3 personnes peut ne prendre qu'une cotisation individuelle si un seul membre participe.
- C'est **l'accueil qui choisit** la cotisation d'après ce que dit la personne ; l'application ne l'impose pas.
- Certaines activités sont **sans adhésion** (simple indicateur sur l'activité).
- L'application calcule le **montant dû** et signale un **trop-perçu**.

---

## 6. Activités et inscriptions

### 6.1 Activité

| Champ | Remarques |
|---|---|
| Nom | |
| Type | atelier, sortie loisirs, sortie culturelle, évènement, séjour |
| Secteur | **un seul** secteur par activité (liste modifiable par un admin) |
| Tarif | **un seul** tarif par activité et par saison |
| Date de début | obligatoire |
| Date de fin | facultative |
| Places limitées | facultatif ; nombre de places |
| Liste d'attente | facultative |
| Nombre d'accompagnateurs | facultatif |
| Description | texte libre facultatif, pour préciser des détails |
| Sans adhésion | oui/non |

Pas de gestion d'horaires, de séances ni de lieu. Les séances et heures d'animation sont suivies dans un calendrier, **hors de la base** : rien n'est stocké dans l'application.

### 6.2 Inscription

- Un adhérent s'inscrit à une activité ; une inscription porte un **nombre de places** (1 pour une personne, plusieurs pour une structure) qui s'impute sur les places de l'activité. Pour une structure, le **montant est négocié** : il est saisi à l'inscription au lieu d'être déduit du tarif ; si les places sont épuisées et que la liste d'attente est activée, il passe en liste d'attente.
- Annulation par indicateur (pas de suppression), pour garder l'historique.

---

## 7. Paiements, réductions, reçus

- Modes : **espèces, chèque, carte bancaire, autres**.
- **Paiement en plusieurs fois** possible.
- Affichage du **reste à payer** et du **trop-perçu** par dossier.
- **Reçu** imprimable ou envoyé par courriel.
- **Réduction** : un **montant** appliqué soit à un adhérent, soit au dossier ; accordée par l'accueil et les admins. Aucun motif et aucune trace spécifique n'est enregistré.
- Les paiements ne se suppriment jamais : on les **annule** par indicateur.

---

## 8. Documents (PDF)

Tous portent le **logo**, le **nom** et l'**adresse** de l'association ; certains ont un texte spécifique, aucun texte juridique.

- Reçu de paiement.
- Attestation de participation.
- Liste des participants / inscrits d'une activité.
- Feuille d'émargement (ex. assemblée générale).
- Liste d'inscrits à vérifier avant une sortie.

---

## 9. Exports

- **Comptable** : paiements sur une période (colonnes à définir avec la comptabilité — demander un fichier exemple). **[À TRANCHER]**
- **Google Contacts** : adhérents de la saison **ayant consenti**, au format CSV importable, pour l'envoi du programme.
- Listes de participants au format tableur (CSV).

---

## 10. Statistiques et bilans

Filtres : **période** (dates libres), **saison**, **secteur**, **type d'activité**. Graphiques (histogrammes, anneaux) quand c'est utile. Comparaison avec la saison précédente. **Tous les indicateurs sont conservés, sauf « montants encaissés ».**

**Calcul de l'âge** : à la **fin de l'exercice, le 31 décembre**, date du bilan. Il est calculé à la demande, jamais stocké. Pour une saison à cheval sur deux années (ex. 2025-2026), la date retenue est le 31 décembre de l'année où elle commence ; pour une période libre, le 31 décembre de l'année de sa date de début. Les chiffres d'une saison passée ne changent donc pas d'une année sur l'autre.

Indicateurs issus du rapport d'activité 2025 :

**Adhérents**
- Nombre d'adhérents (et évolution N / N-1) ; nouveaux adhérents / réadhésions.
- Répartition par **sexe** ; **âge moyen** ; tranches **0-6, 7-11, 12-17, 18-25, 26-59, 60 et +** ; part des moins de 24 ans.
- **Nationalité** : France, autres pays d'Europe, Afrique, Asie, Amériques.
- **Lieu de résidence** : Paris 17e ; arrondissements proches (8, 9, 16, 18) ; autres arrondissements ; communes limitrophes (Clichy, Saint-Ouen, Levallois) ; autres.
- **Quartier** (zone de compétence : Épinettes-Bessières et La Fourche-Guy Môquet), part en **QPV** / **QP**.

**Activités**
- Nombre d'activités par secteur et par type.
- **Participants différents** par secteur / type / activité ; participations totales.
- Familles concernées ; adultes / enfants pour les activités parents-enfants.
- Sorties et séjours : nombre de départs, de bénéficiaires, de familles.
- Listes d'attente (volume).

**Établissement scolaire** (accompagnement à la scolarité) : nombre d'enfants par établissement, si le champ est utilisé.

**Écartés** : heures de bénévolat, lieu de l'activité, séances et heures d'animation, fréquentation de l'accueil et des permanences, enfants gardés.

---

## 11. Sécurité, RGPD, sauvegardes

- Données personnelles de mineurs et de personnes en situation de fragilité : accès par compte, journal des actions, pas de données dans les URL.
- **Consentement** explicite pour les communications / Google Contacts, enregistré avec la date.
- **Anonymisation** des familles inactives après 3 ans ; **purge des journaux** après 1 an.
- **Sauvegardes** : export de la base **toutes les semaines** (recommandation : au minimum), en plus des sauvegardes de l'hébergeur ; une copie hors hébergeur.
- Droit d'accès / effacement : fonction « anonymiser cette famille » réservée aux admins.

---

## 12. Contraintes techniques

Voir `CLAUDE.md` : PHP 8.5 / MariaDB 11.8 / Bootstrap 5.3 / SCSS, `declare(strict_types=1)`, `db()`, Post/Redirect/Get, requêtes préparées, etc.

À choisir au moment de la réalisation :
- **Génération de PDF** (bibliothèque Composer légère, ex. Dompdf ou TCPDF) — compatible hébergement mutualisé.
- **Graphiques** : Chart.js (côté navigateur).
- **API Adresse (BAN)** : vérifier l'adresse officielle actuelle du service au moment de coder (le service a évolué).
- **Quartier / QPV** : déterminés à partir de l'adresse via l'API BAN (déjà éprouvé).

### 12.1 Installation (`install.php`)

Un assistant d'installation, à lancer une seule fois sur chaque environnement (local, Hostinger), en 5 étapes :

1. **Connexion à la base** : serveur, nom de la base, utilisateur, mot de passe. La connexion est testée avant de continuer.
2. **Courriel (PHPMailer)** : serveur SMTP, port, chiffrement, identifiant, mot de passe, adresse et nom d'expéditeur ; adresse publique du site (`APP_URL`). Un envoi d'essai est proposé.
3. **Création de la structure** : toutes les tables, à partir de `sql/structure.sql`.
4. **Remplissage des tables de référence** : pays, quartiers, quartiers prioritaires (QP), QPV, codes postaux, à partir de fichiers fournis dans `sql/` (les sources officielles sont à rassembler avant l'étape de développement correspondante).
5. **Premier administrateur** (prénom, courriel, mot de passe) et **première saison**.

Règles :
- L'assistant **écrit `config.php`** dans la partie privée (jamais dans `public/`), avec des droits restreints ; les mots de passe saisis ne sont ni affichés de nouveau ni journalisés.
- **Verrouillage** : `config.php` est écrit dès la fin de l'étape 2, avec le réglage `INSTALLATION_TERMINEE` à `false`, ce qui permet de reprendre une installation interrompue. Ce réglage passe à `true` à la fin de l'étape 5 : `install.php` refuse alors de s'exécuter. La documentation recommande de supprimer `install.php` du serveur une fois l'installation terminée.
- En cas d'erreur à une étape, on peut reprendre sans repartir de zéro ; les tables existantes ne sont jamais écrasées sans confirmation explicite.
- Les mises à jour ultérieures de structure passent par des fichiers de migration datés (voir `CLAUDE.md`), pas par `install.php`.

Accessibilité : écrans utilisables sur tablette ; pages de consultation (liste d'inscrits, coordonnées) lisibles sur téléphone.

---

## 13. Hors périmètre (version 1)

Gestion d'horaires, de séances et de lieux ; suivi des heures de bénévolat ; comptage des passages à l'accueil et aux permanences ; enfants gardés ; planning des permanences et rendez-vous ; paie ; comptabilité ; envoi de courriels en masse (on exporte vers Google Contacts) ; paiement en ligne ; reprise de l'historique (optionnelle, plus tard).

---

## 14. Questions encore ouvertes

1. **Bénévoles accompagnateurs** (musée, sortie…) : ils doivent voir inscrits, présences et contacts. Décision : en V1, leur remettre une **liste imprimée / PDF** ; plus tard, éventuellement un profil restreint aux activités qui leur sont affectées (4e rôle).
2. **Colonnes de l'export comptable** : à demander à la comptabilité (fichier exemple).

Tranchées : passages à l'accueil et permanences non comptés ; âge au 31 décembre ; prix d'une inscription de structure = montant négocié ; structures inscriptibles ; établissement scolaire facultatif ; mode de paiement « autres » suffisant ; enfants gardés ignorés.

---

## 15. Ordre de construction proposé (tranches verticales)

Chaque étape est livrable et testable : écrans, base de données, droits et documents ensemble.

| # | Étape | Contenu |
|---|---|---|
| 0 | Socle | Dépôt, outils, **`install.php` (section 12.1)**, connexion, utilisateurs, mot de passe oublié, saisons, paramètres, mise en page, sauvegarde |
| 1 | Familles et adhérents | Famille, dossier, adhérents, responsable de mineur, adresse BAN, recherche |
| 2 | Activités et inscriptions | Secteurs, types, tarifs, activités, inscriptions, places, liste d'attente |
| 3 | Cotisations et paiements | Cotisation, réductions, paiements, reste à payer, reçu PDF — **première mise en service possible** |
| 4 | Documents | Listes de participants, émargement, attestations |
| 5 | Exports | Comptable, Google Contacts, CSV |
| 6 | Statistiques | Indicateurs de la section 10, graphiques, comparaisons |
| 7 | Finitions | RGPD (anonymisation, purge), import des anciennes données, profil bénévole accompagnateur |

Règle de travail : un plan court est validé avant chaque étape ; les tests (`php -l`, PHPStan, compilation CSS) sont lancés avant de la déclarer terminée.
