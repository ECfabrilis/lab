// Formulaire d'inscription, étape 1 : pendant la saisie du nom, du prénom et de
// la date de naissance, la colonne de droite affiche les adhérents proches déjà
// enregistrés (5 au maximum). Cliquer sur l'un d'eux ouvre sa fiche, d'où il
// peut être inscrit pour la saison au lieu de créer une deuxième fiche.

//
// Étape 3 et fenêtre « Modifier la fiche » (adherent.php), personne mineure :
// choisir un adulte de la famille dans la liste
// remplit le nom, le téléphone et l'adresse e-mail du responsable. Choisir
// « une autre personne » vide ces champs s'ils venaient d'un adulte de la liste.

document.addEventListener('DOMContentLoaded', function () {
    var liste = document.querySelector('[data-responsable-adulte]');
    var formulaire = liste ? liste.closest('form') : null;
    if (!liste || !formulaire) {
        return;
    }
    var champs = formulaire.elements;
    if (!champs.responsable_nom || !champs.responsable_tel || !champs.responsable_mail) {
        return;
    }

    // Vrai si les champs contiennent exactement les coordonnées d'un adulte de la liste.
    function viennentDeLaListe() {
        return Array.prototype.some.call(liste.querySelectorAll('input[type="radio"][data-nom]'), function (choix) {
            return champs.responsable_nom.value === (choix.dataset.nom || '')
                && champs.responsable_tel.value === (choix.dataset.tel || '')
                && champs.responsable_mail.value === (choix.dataset.mail || '');
        });
    }

    liste.addEventListener('change', function (evenement) {
        var choix = evenement.target;
        if (!choix || choix.type !== 'radio') {
            return;
        }
        if (choix.value === '') {
            if (viennentDeLaListe()) {
                champs.responsable_nom.value = '';
                champs.responsable_tel.value = '';
                champs.responsable_mail.value = '';
            }
            champs.responsable_nom.focus();
            return;
        }
        champs.responsable_nom.value = choix.dataset.nom || '';
        champs.responsable_tel.value = choix.dataset.tel || '';
        champs.responsable_mail.value = choix.dataset.mail || '';
    });
});

// Fenêtre « Modifier la fiche » : la partie « Responsable adulte » (liste des
// adultes de la famille et champs) n'est montrée que si la date de naissance
// saisie est celle d'une personne mineure. Elle apparaît ou disparaît dès que
// la date change ; les champs cachés gardent leur valeur (une date corrigée
// les fait revenir) et le serveur écarte le responsable d'une personne majeure.
document.addEventListener('DOMContentLoaded', function () {
    var bloc = document.querySelector('[data-responsable-selon-naissance]');
    var formulaire = bloc ? bloc.closest('form') : null;
    var champDate = formulaire ? formulaire.elements.date_naissance : null;
    if (!bloc || !champDate) {
        return;
    }
    var majorite = parseInt(bloc.dataset.responsableSelonNaissance, 10) || 18;

    function suivreLaDate() {
        var parties = /^(\d{4})-(\d{2})-(\d{2})$/.exec(champDate.value);
        if (!parties) {
            // Date vide ou en cours de saisie : la partie reste comme elle est.
            return;
        }
        var naissance = new Date(Number(parties[1]), Number(parties[2]) - 1, Number(parties[3]));
        var aujourdHui = new Date();
        // Dernière date de naissance d'une personne majeure aujourd'hui.
        var limite = new Date(aujourdHui.getFullYear() - majorite, aujourdHui.getMonth(), aujourdHui.getDate());
        bloc.classList.toggle('d-none', naissance <= limite);
    }

    champDate.addEventListener('input', suivreLaDate);
    champDate.addEventListener('change', suivreLaDate);
});

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
