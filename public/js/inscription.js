// Formulaire d'inscription, étape 1 : pendant la saisie du nom, du prénom et de
// la date de naissance, la colonne de droite affiche les adhérents proches déjà
// enregistrés (5 au maximum). Cliquer sur l'un d'eux ouvre sa fiche, d'où il
// peut être inscrit pour la saison au lieu de créer une deuxième fiche.

document.addEventListener('DOMContentLoaded', function () {
    var formulaire = document.querySelector('form[data-doublons-url]');
    var liste = document.getElementById('doublonsProches');
    if (!formulaire || !liste) {
        return;
    }
    var attente = null;
    var demandeEnCours = null;

    function valeur(nom) {
        return formulaire.elements[nom] ? formulaire.elements[nom].value.trim() : '';
    }

    function afficher(resultats) {
        liste.replaceChildren();
        resultats.forEach(function (proche) {
            var lien = document.createElement('a');
            lien.className = 'list-group-item list-group-item-action';
            lien.href = formulaire.dataset.doublonsFiche + encodeURIComponent(proche.id);

            var nom = document.createElement('strong');
            nom.textContent = proche.nom;
            lien.appendChild(nom);

            var details = [];
            if (proche.naissance) {
                details.push('né(e) le ' + proche.naissance);
            }
            if (proche.famille) {
                details.push('famille ' + proche.famille);
            }
            details.push(proche.saison ? 'dernière saison : ' + proche.saison : 'aucune saison');
            var ligne = document.createElement('span');
            ligne.className = 'd-block small text-body-secondary';
            ligne.textContent = details.join(' — ');
            lien.appendChild(ligne);

            liste.appendChild(lien);
        });
    }

    function chercher() {
        if (demandeEnCours) {
            demandeEnCours.abort();
        }
        demandeEnCours = new AbortController();
        var donnees = new FormData();
        donnees.append('csrf', formulaire.elements.csrf ? formulaire.elements.csrf.value : '');
        donnees.append('nom', valeur('nom'));
        donnees.append('prenom', valeur('prenom'));
        donnees.append('date_naissance', valeur('date_naissance'));
        donnees.append('structure', formulaire.dataset.doublonsStructure || '0');
        fetch(formulaire.dataset.doublonsUrl, { method: 'POST', body: donnees, credentials: 'same-origin', signal: demandeEnCours.signal })
            .then(function (reponse) {
                return reponse.ok ? reponse.json() : {};
            })
            .then(function (json) {
                afficher(Array.isArray(json.resultats) ? json.resultats : []);
            })
            .catch(function () {
                // Demande annulée : une autre est en cours.
            });
    }

    ['nom', 'prenom', 'date_naissance'].forEach(function (nom) {
        var champ = formulaire.elements[nom];
        if (champ) {
            champ.addEventListener('input', function () {
                clearTimeout(attente);
                attente = setTimeout(chercher, 300);
            });
        }
    });
    // Formulaire prérempli (retour à l'étape 1, autre membre d'une famille).
    chercher();
});
