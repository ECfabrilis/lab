// Propositions de code postal et d'adresse dans les formulaires.
//
// Un champ <input data-suggestions="code_postal|adresse" data-url="..."> reçoit,
// pendant la saisie, une liste de propositions demandée au serveur. Choisir une
// proposition remplit les champs adresse, code_postal et ville du même formulaire.
// Sans JavaScript, le formulaire fonctionne quand même : l'adresse est vérifiée
// à l'enregistrement.

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[data-suggestions]').forEach(function (champ) {
        var formulaire = champ.form;
        if (!formulaire) {
            return;
        }
        var type = champ.dataset.suggestions;
        var longueurMin = type === 'adresse' ? 4 : 2;
        var attente = null;
        var demande = null;

        var liste = document.createElement('div');
        liste.className = 'list-group position-absolute start-0 end-0 mx-2 shadow z-3 d-none';
        liste.setAttribute('role', 'listbox');
        champ.parentNode.appendChild(liste);

        function fermer() {
            liste.classList.add('d-none');
            liste.replaceChildren();
        }

        function remplir(nom, valeur) {
            var cible = formulaire.elements[nom];
            if (cible && valeur) {
                cible.value = valeur;
            }
        }

        function afficher(resultats) {
            liste.replaceChildren();
            resultats.forEach(function (resultat) {
                var bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'list-group-item list-group-item-action';
                bouton.setAttribute('role', 'option');
                bouton.textContent = type === 'adresse'
                    ? resultat.libelle
                    : resultat.code_postal + ' ' + resultat.ville;
                // mousedown : avant que le champ ne perde le focus et ne ferme la liste.
                bouton.addEventListener('mousedown', function (evenement) {
                    evenement.preventDefault();
                    choisir(resultat);
                });
                bouton.addEventListener('click', function () {
                    choisir(resultat);
                });
                liste.appendChild(bouton);
            });
            liste.classList.toggle('d-none', resultats.length === 0);
        }

        function choisir(resultat) {
            if (type === 'adresse') {
                remplir('adresse', resultat.adresse);
            }
            remplir('code_postal', resultat.code_postal);
            remplir('ville', resultat.ville);
            fermer();
        }

        function chercher() {
            var texte = champ.value.trim();
            if (texte.length < longueurMin) {
                fermer();
                return;
            }
            if (demande) {
                demande.abort();
            }
            demande = new AbortController();
            var donnees = new FormData();
            donnees.append('csrf', formulaire.elements.csrf ? formulaire.elements.csrf.value : '');
            donnees.append('q', texte);
            if (type === 'adresse' && formulaire.elements.code_postal) {
                donnees.append('code_postal', formulaire.elements.code_postal.value.trim());
            }
            fetch(champ.dataset.url, { method: 'POST', body: donnees, credentials: 'same-origin', signal: demande.signal })
                .then(function (reponse) {
                    return reponse.ok ? reponse.json() : { resultats: [] };
                })
                .then(function (json) {
                    afficher(Array.isArray(json.resultats) ? json.resultats : []);
                })
                .catch(function () {
                    // Demande annulée ou service indisponible : pas de proposition.
                });
        }

        champ.addEventListener('input', function () {
            clearTimeout(attente);
            attente = setTimeout(chercher, 300);
        });
        champ.addEventListener('blur', function () {
            setTimeout(fermer, 150);
        });
        champ.addEventListener('keydown', function (evenement) {
            if (evenement.key === 'Escape') {
                fermer();
            }
        });
    });
});
