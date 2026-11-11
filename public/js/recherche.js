// Recherche d'adhérents : dès 3 caractères saisis, une liste de propositions
// (nom ou prénom) s'affiche sous le champ. Cliquer sur une proposition ouvre
// la fiche. Le bouton « Rechercher » reste disponible pour la liste complète.
//
// Champ attendu : <input data-recherche-url="..." data-recherche-fiche="...">

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[data-recherche-url]').forEach(function (champ) {
        var formulaire = champ.form;
        if (!formulaire) {
            return;
        }
        var attente = null;
        var demandeEnCours = null;

        var liste = document.createElement('div');
        liste.className = 'list-group liste-propositions position-absolute start-0 end-0 shadow z-3 d-none';
        liste.setAttribute('role', 'listbox');
        champ.parentNode.classList.add('position-relative');
        // Juste après le champ, pour s'ouvrir sous lui (et non sous son texte d'aide).
        champ.insertAdjacentElement('afterend', liste);

        function fermer() {
            liste.classList.add('d-none');
            liste.replaceChildren();
        }

        function afficher(resultats) {
            liste.replaceChildren();
            resultats.forEach(function (resultat) {
                var lien = document.createElement('a');
                lien.className = 'list-group-item list-group-item-action';
                lien.setAttribute('role', 'option');
                lien.href = champ.dataset.rechercheFiche + encodeURIComponent(resultat.id);

                var nom = document.createElement('strong');
                nom.textContent = resultat.nom;
                lien.appendChild(nom);

                var detail = document.createElement('span');
                detail.className = 'd-block small text-body-secondary';
                detail.textContent = resultat.detail;
                lien.appendChild(detail);

                // mousedown : le clic ne doit pas être perdu quand le champ perd le focus.
                lien.addEventListener('mousedown', function (evenement) {
                    evenement.preventDefault();
                });
                liste.appendChild(lien);
            });
            liste.classList.toggle('d-none', resultats.length === 0);
        }

        function chercher() {
            var texte = champ.value.trim();
            if (texte.length < 3) {
                fermer();
                return;
            }
            if (demandeEnCours) {
                demandeEnCours.abort();
            }
            demandeEnCours = new AbortController();
            var donnees = new FormData();
            donnees.append('csrf', formulaire.elements.csrf ? formulaire.elements.csrf.value : '');
            donnees.append('q', texte);
            donnees.append('perimetre', formulaire.elements.perimetre ? formulaire.elements.perimetre.value : 'saison');
            fetch(champ.dataset.rechercheUrl, { method: 'POST', body: donnees, credentials: 'same-origin', signal: demandeEnCours.signal })
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

        champ.addEventListener('input', function () {
            clearTimeout(attente);
            attente = setTimeout(chercher, 250);
        });
        champ.addEventListener('focus', chercher);
        champ.addEventListener('blur', function () {
            setTimeout(fermer, 150);
        });
        champ.addEventListener('keydown', function (evenement) {
            if (evenement.key === 'Escape') {
                fermer();
            }
        });
        if (formulaire.elements.perimetre) {
            formulaire.elements.perimetre.addEventListener('change', chercher);
        }
    });
});
