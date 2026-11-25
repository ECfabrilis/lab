# Fichiers de quartiers (exemples)

Les quartiers et les quartiers prioritaires (QP) dépendent de la commune de
l'association : l'installation ne les remplit pas. Ils s'importent dans
**Paramètres > Données de référence**, à partir d'un fichier GeoJSON de contours
(en général publié par la commune dans ses données ouvertes).

Ce dossier contient deux fichiers prêts à importer, donnés comme exemples :

| Fichier | Table | Contenu |
|---|---|---|
| `paris_17_quartiers.geojson` | Quartiers | les 9 quartiers du 17e arrondissement de Paris |
| `paris_quartiers_prioritaires.geojson` | Quartiers prioritaires (QP) | les 72 quartiers prioritaires de Paris |

Pour ces deux fichiers, les propriétés à indiquer dans le formulaire portent le
nom de la colonne :

| Champ du formulaire | Propriété à saisir |
|---|---|
| Code du quartier (QP seulement) | `code_quartier` |
| Nom du quartier | `nom` |
| Commune | `commune` |
| Arrondissement | `arrondissement` |

Après l'import des quartiers, cocher les communes et arrondissements à prendre
en compte dans **Paramètres > Options > Quartiers** : sans cela, les adresses
sont enregistrées sans quartier.

Les quartiers de la politique de la ville (QPV) couvrent toute la France : ils
sont remplis à l'installation.

**Source et licence.**

- `paris_17_quartiers.geojson` : base dérivée du jeu « quartier_paris »
  (quartiers administratifs) des données ouvertes de la Ville de Paris
  (<https://opendata.paris.fr/explore/dataset/quartier_paris/information/>),
  dont les contours ont été modifiés pour suivre les quartiers du 17e.
  Open Database License (ODbL) : le fichier reste sous cette licence, quelle
  que soit celle de l'application.
- `paris_quartiers_prioritaires.geojson` : jeu « quartiers-prioritaires »
  (« Quartiers populaires ») des données ouvertes de la Ville de Paris
  (<https://opendata.paris.fr/explore/dataset/quartiers-prioritaires/information/>).
  La fiche de ce jeu n'indique pas de licence : à confirmer avant de
  transmettre ce fichier.
