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
- Le dossier est une notion **interne** : il sert au fonctionnement de l'application mais **n'apparaît jamais à l'écran** (ni le mot, ni un lien, ni une page). L'interface parle de la famille (ou de la structure) « pour la saison » : inscrit(e) pour la saison, notes de la saison, paiements de la famille. Le dossier est créé automatiquement à la première inscription de la saison. Sur la fiche famille, chaque membre peut être **inscrit pour la saison** ou **retiré de la saison**, et les notes de la saison s'y modifient (pour une structure : sur sa fiche).
- La **liste des adhérents** affiche par défaut les adhérents inscrits pour la saison affichée (à la connexion : la saison en cours) ; la recherche peut être élargie à toutes les saisons.
- **Recherche** : dès 3 lettres, des propositions s'affichent pendant la frappe ; le texte est cherché **n'importe où** dans le nom ou le prénom (« pond » propose DUPOND, « arti » propose MARTIN).
- **Filtres de la liste** : première lettre du nom, bénévoles uniquement, quartier, quartier prioritaire (QP), QPV ; nombre par page au choix (50 par défaut, 100, 200 ou 500). Quartier, QP et QPV sont ceux de l'adresse de la saison affichée.

### 3.2 Fiche adhérent

| Champ | Obligatoire | Remarques |
|---|---|---|
| Nom, prénom | oui | enregistrés avec le nom en majuscules (DUPOND) et le prénom avec une majuscule initiale (Jean-Pierre) ; le nom d'une famille est aussi en majuscules, celui d'une structure reste tel que saisi |
| Sexe | oui | |
| Date de naissance | oui | l'âge est calculé, jamais saisi |
| Nationalité | non | liste de pays, **France par défaut** dans le formulaire d'inscription ; le continent est déduit (statistiques) |
| Adresse | oui | vérifiée par l'API Adresse (BAN, Géoplateforme) ; si le service ne répond pas, une indication le dit (« Géoplateforme est indisponible, l'adresse ne sera pas vérifiée ») et la saisie reste possible |
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
- L'installation crée la première saison **sans rien demander** : la saison en cours, du 1er septembre au 31 août (à partir du 1er septembre de l'année N : saison « N-(N+1) » ; avant : saison « (N-1)-N »).
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
| Type | **un seul** type par activité. Liste créée à l'installation (atelier, sortie loisirs, sortie culturelle, évènement, séjour), modifiable par un admin dans les paramètres |
| Secteur | **un seul** secteur par activité (liste modifiable par un admin) ; dans le formulaire, il se choisit juste après le nom |
| Référent | obligatoire ; texte libre (un référent n'a pas forcément de compte dans l'application) |
| Récurrence | obligatoire : ponctuel, régulier ou vacances |
| Tarif | **un seul** tarif par activité et par saison, de type « forfait » ou « autre » ; sans tarif, l'activité est gratuite (c'est le cas d'une activité qui ne demande que l'adhésion) |
| Date de début | obligatoire, dans la saison de l'activité |
| Date de fin | facultative |
| Places limitées | facultatif ; nombre de places |
| Liste d'attente | facultative |
| Nombre d'accompagnateurs | facultatif ; simple information, ils ne prennent pas de places |
| Description | texte libre facultatif, pour préciser des détails |
| Sans adhésion | oui/non |

Pas de gestion d'horaires, de séances ni de lieu. Les séances et heures d'animation sont suivies dans un calendrier, **hors de la base** : rien n'est stocké dans l'application.

**Secteurs et types** : deux listes **communes à toutes les saisons**. Un secteur ou un type peut être rendu **inactif** (il n'est plus proposé pour les nouvelles activités ; celles qui l'utilisent le gardent) ; il ne se **supprime** que si aucune activité ne l'utilise. Un secteur a une **description** facultative.

**Tarifs** : un tarif est une ligne nommée d'une saison (exemple : « Atelier annuel — 120 € ») ; plusieurs activités peuvent le partager. Un tarif ne se supprime que si aucune activité ne l'utilise.

| Champ | Remarques |
|---|---|
| Nom | unique dans la saison |
| Type | obligatoire : **forfait**, **adhésion** ou **autre**. Les tarifs « adhésion » sont réservés aux cotisations (section 5) : une activité ne peut pas en choisir un |
| Montant individuel | obligatoire |
| Montant familial | facultatif ; il vaut pour **toute la famille** (exemple : centre de loisirs, 5 € par enfant ou 10 € pour l'ensemble des enfants) |
| Description | texte libre facultatif |

Une activité ne se **supprime** que si elle est vide (aucune inscription, même annulée). Son nombre de places ne peut pas descendre sous les places déjà prises.

**Activité déjà créée ?** À la création, l'application cherche dans la saison une activité au **nom presque identique** (sans tenir compte des accents ni des majuscules, à une ou deux lettres près) et aux **dates similaires** (périodes qui se chevauchent, ou débuts à 7 jours d'écart au plus). Si elle en trouve une, elle la cite et demande de cocher « Créer quand même ».

**Recherche** : la liste des activités se filtre par nom (n'importe où dans le nom), secteur et type ; la même recherche par nom existe au moment de choisir l'activité d'une inscription.

### 6.2 Inscription

- Un adhérent s'inscrit à une activité ; une inscription porte un **nombre de places** (1 pour une personne, plusieurs pour une structure) qui s'impute sur les places de l'activité. Pour une structure, le **montant est négocié** : il est saisi à l'inscription au lieu d'être déduit du tarif ; si les places sont épuisées et que la liste d'attente est activée, il passe en liste d'attente.
- L'adhérent doit d'abord être **inscrit pour la saison** de l'activité, y compris pour une activité « sans adhésion » (cet indicateur ne sert qu'au calcul de la cotisation). Tant qu'il a une inscription en cours à une activité, il ne peut pas être retiré de la saison.
- Une personne n'a qu'**une inscription en cours** par activité ; une structure peut en avoir plusieurs.
- Le **montant est copié** sur l'inscription au moment où elle est faite : modifier un tarif ne change pas les inscriptions déjà enregistrées.
- **Tarif familial** : quand le tarif de l'activité a un montant familial, l'accueil choisit à l'inscription d'une personne entre le tarif individuel et le tarif familial. Chaque inscription au tarif familial porte le montant familial, qui ne **compte qu'une fois par famille et par activité**, quel que soit le nombre de membres inscrits à ce tarif (calcul du montant dû, étape 3).
- **Places** : si les places demandées ne tiennent pas en entier, l'inscription va **entièrement en liste d'attente** quand l'activité en a une (exemple : une structure demande 5 places, il en reste 3) ; sinon elle est refusée.
- **Liste d'attente** : on peut y inscrire quelqu'un **directement**, même s'il reste des places. Quand une place se libère, rien n'est automatique : la fiche de l'activité le signale et l'accueil **choisit** qui passe de la liste d'attente aux inscrits. À l'inverse, un inscrit peut être **passé en liste d'attente** (si l'activité en a une) : ses places sont libérées. La liste d'attente s'affiche dans l'**ordre d'entrée** dans cette liste.
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
- **Anonymisation** des familles inactives après 3 ans.
- **Journal des actions (logs)** : conservé pour la saison en cours et **remis à zéro à chaque nouvelle saison**. Un mois après le début de la saison, à la première connexion, les actions antérieures à ce début sont effacées (sans export : les sauvegardes de la base en gardent la trace).
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
5. **Premier administrateur** (prénom, courriel, mot de passe) et **première saison**, créée automatiquement (voir section 4).

Règles :
- L'assistant **écrit `config.php`** dans la partie privée (jamais dans `public/`), avec des droits restreints ; les mots de passe saisis ne sont ni affichés de nouveau ni journalisés.
- **Verrouillage** : `config.php` est écrit dès la fin de l'étape 2, avec le réglage `INSTALLATION_TERMINEE` à `false`, ce qui permet de reprendre une installation interrompue. Ce réglage passe à `true` à la fin de l'étape 5 : `install.php` refuse alors de s'exécuter. Une fois l'installation réussie, `install.php` **se supprime lui-même** du serveur si c'est possible ; sinon un message demande de le supprimer à la main.
- En cas d'erreur à une étape, on peut reprendre sans repartir de zéro ; les tables existantes ne sont jamais écrasées sans confirmation explicite.
- Les mises à jour ultérieures de structure passent par des fichiers de migration datés (voir `CLAUDE.md`), pas par `install.php`.

### 12.2 Formulaire d'inscription (`ajout_adherent.php`)

Le formulaire se déroule en 4 étapes. Objectifs : saisie rapide, peu d'erreurs, pas de doublons. Les champs marqués * sont obligatoires.

**Principes**
- Les informations saisies sont gardées côté serveur (session) entre les étapes ; **rien n'est écrit en base avant la validation finale**, qui enregistre tout en une seule opération. Chaque étape suit Post/Redirect/Get : le bouton « page précédente » du navigateur ne redemande jamais de renvoyer le formulaire.
- Les aides à la saisie (doublons, codes postaux, adresse) sont du JavaScript (`public/js/`) qui interroge de petites pages réservées aux utilisateurs connectés ayant le droit de saisie (`standard` ou `admin`) ; elles renvoient du JSON et sont protégées par le jeton CSRF.
- Un brouillon abandonné est simplement oublié à la fin de la session.

**Choix du type de personne.** Quatre boutons : **Homme**, **Femme**, **Autre**, **Association / Structure**. Pour une structure, le formulaire demande un **nom de structure** au lieu de nom et prénom, ne demande ni date de naissance, ni nationalité, ni mention bénévole, ni famille (une structure a son propre dossier) ; le code postal, l'adresse et les contacts restent demandés.

**Étape 1 — Bénévolat, identité, code postal**
- *Bénévolat* : bouton on/off « est bénévole ».
- *Identité* : nom*, prénom*, sexe* (via les boutons), date de naissance*, nationalité.
- *Doublons* : pendant la saisie du nom, du prénom et de la date de naissance, une liste d'adhérents proches s'affiche dans la colonne de droite. Est « proche » une personne qui a **la même date de naissance**, ou **un nom et un prénom presque identiques** (sans tenir compte des accents ni des majuscules) ; 5 propositions au maximum, avec la famille et la dernière saison. Cliquer sur une proposition ouvre sa fiche et permet de **l'inscrire pour la saison en cours** au lieu de créer une deuxième fiche.
- *Code postal** : une liste de communes est proposée dès 3 chiffres. À 4 chiffres, si un seul code postal correspond, le code et la commune sont remplis sans clic (exemple : « 6542 » → « 65420 » et « IBOS »). Si plusieurs communes partagent le code, on propose le choix.

**Étape 2 — Famille** (sauf structure)
- Trois choix : **créer une nouvelle famille** avec cet adhérent ; **choisir** une famille parmi celles dont le nom est proche de celui de l'adhérent ; **rechercher** une famille.
- Si une famille existante est choisie, son **adresse et son téléphone sont proposés par défaut** à l'étape suivante.

**Étape 3 — Adresse, contact, notes**
- *Adresse** et complément d'adresse : vérification en direct auprès de l'API BAN. Une indication en petit sous le champ dit si l'adresse est **certifiée** ou non. À partir des coordonnées, une deuxième indication donne le **quartier**, le **quartier prioritaire (QP)** et le **quartier de la politique de la ville (QPV)**. Le code postal saisi à l'étape 1 oriente la recherche ; l'adresse certifiée corrige le code postal et la commune si besoin.
- *Contact* : courriel, téléphone, **consentement aux communications** (case à cocher, enregistrée avec la date).
- *Si la personne a moins de 18 ans* (à la date du jour) : un **responsable** est demandé (nom, courriel, téléphone) ; si une famille existante a été choisie et qu'elle compte des adultes ayant un téléphone ou un courriel, un menu permet de **reprendre l'un d'eux** comme responsable (les trois champs se remplissent et restent modifiables) ; l'**établissement scolaire / niveau** (facultatif) est proposé.
- *Notes* : texte libre ; elles concernent la **famille pour la saison** (en base : le dossier), pas la personne.

**Étape 4 — Validation**
- Récapitulatif de toutes les informations, avec un lien « modifier » pour chaque partie.
- Si l'adresse n'est **pas certifiée**, l'enregistrement n'est possible qu'après une **confirmation explicite** (case à cocher). Quartier et QPV sont alors laissés vides.
- **Confirmation par courriel** : une case « Envoyer une confirmation d'inscription par courriel », cochée par défaut, s'affiche si une adresse est connue. Après la validation, le courriel part à l'adhérent **et** à son responsable (personne mineure), pour chaque adresse renseignée. Un échec d'envoi n'annule pas l'inscription : un avertissement s'affiche.
- La validation utilise le jeton anti-double envoi.

**Après la validation**, l'utilisateur choisit entre :
1. voir la fiche de l'adhérent ;
2. ajouter un paiement pour cet adhérent ;
3. inscrire cet adhérent à une activité ;
4. inscrire une autre personne dans la même famille (formulaire prérempli avec la famille, son adresse et son téléphone) ;
5. faire une nouvelle inscription (formulaire vide).

### 12.3 Menu, tableau de bord et paramètres

**Menu principal** (dans l'en-tête, à côté du logo) : trois entrées seulement.

| Entrée | Accès |
|---|---|
| Tableau de bord (`index.php`) | tous |
| Statistiques | tous |
| Paramètres | `admin` |

**Barre sous l'en-tête** : elle contient la **saison** (affichage et changement de saison) et l'**utilisateur** (son nom, accès à son profil, déconnexion). L'en-tête au-dessus ne garde que le logo et le menu. Sur téléphone, la barre reste lisible (éléments empilés ou réduits).

**Tableau de bord** : trois parties, chacune présentée sous forme de **cards Bootstrap** cliquables.

| Partie | Choix | Accès |
|---|---|---|
| Les adhérents | Liste des adhérents ; Nouvelle inscription ; Les familles | liste et familles : tous ; nouvelle inscription : `standard` au moins |
| Les activités | Liste des activités ; Nouvelle activité ; Secteurs d'activité | liste : tous ; nouvelle activité : `standard` au moins ; secteurs : `admin` |
| Les paiements (partie entière réservée à `standard` au moins) | Liste des paiements ; Nouveau paiement ; Les tarifs ; État des paiements | tous : `standard` au moins (tarifs : consulter et ajouter ; les modifier ou les supprimer : `admin`) |

« État des paiements » : vue d'ensemble des familles et structures de la saison avec reste à payer ou trop-perçu (voir section 7).

**Paramètres** (`admin`) : quatre parties, également en cards.

| Partie | Choix |
|---|---|
| Les utilisateurs | Liste des utilisateurs ; Nouvel utilisateur |
| Options | Options d'affichage (une seule card : nom de la structure, logo, couleur) |
| La base de données | Saisons ; Sauvegarde ; Logs ; Données de référence (pays, quartiers, QP, QPV, codes postaux) ; Types d'activité ; RGPD (anonymisation) |
| Configuration | Paramètres des mails (avec l'essai d'envoi) |

**Règles**
- Une card que l'utilisateur n'a pas le droit d'utiliser **n'est pas affichée** ; une partie sans aucune card visible disparaît. Le droit est **toujours revérifié côté serveur** sur chaque page, pas seulement par le masquage.
- Les listes de choix sont **extensibles** : on peut ajouter des cards au fur et à mesure (notamment dans les parties Options, Base de données et Configuration) sans changer la mise en page.
- La couleur choisie dans Options s'applique à l'ensemble de l'application ; le logo et le nom de la structure servent aussi aux documents PDF (section 8).

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
| 0 | Socle | Dépôt, outils, **`install.php` (section 12.1)**, **menu, tableau de bord et paramètres (section 12.3)**, connexion, utilisateurs, mot de passe oublié, saisons, paramètres, mise en page, sauvegarde |
| 1 | Familles et adhérents | Famille, dossier, adhérents, structures, responsable de mineur, adresse BAN, recherche, **formulaire d'inscription (section 12.2)** |
| 2 | Activités et inscriptions | Secteurs, types, tarifs, activités, inscriptions, places, liste d'attente |
| 3 | Cotisations et paiements | Cotisation, réductions, paiements, reste à payer, reçu PDF — **première mise en service possible** |
| 4 | Documents | Listes de participants, émargement, attestations |
| 5 | Exports | Comptable, Google Contacts, CSV |
| 6 | Statistiques | Indicateurs de la section 10, graphiques, comparaisons |
| 7 | Finitions | RGPD (anonymisation, purge), import des anciennes données, profil bénévole accompagnateur |

Règle de travail : un plan court est validé avant chaque étape ; les tests (`php -l`, PHPStan, compilation CSS) sont lancés avant de la déclarer terminée.
