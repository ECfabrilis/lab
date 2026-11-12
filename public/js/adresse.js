// Aides à la saisie des adresses dans les formulaires.
//
// Un champ <input data-suggestions="code_postal|ville|adresse" data-url="..."> reçoit,
// pendant la saisie, une liste de propositions demandée au serveur. Choisir une
// proposition remplit les champs adresse, code_postal et ville du même formulaire.
//
// Code postal : propositions dès 3 chiffres ; à 4 ou 5 chiffres, s'il ne reste
// qu'un seul code postal et une seule commune, ils sont remplis sans clic.
// Avec les 5 chiffres, toutes les communes du code postal sont proposées.
//
// Commune : propositions dès 3 lettres ; choisir une commune remplit aussi le
// code postal.
//
// Adresse : une indication sous le champ (élément [data-etat-adresse]) dit si
// l'adresse est certifiée, et donne le quartier, le QP et le QPV. Si le service
// d'adresses (Géoplateforme) ne répond pas, l'indication le signale.
//
// Sans JavaScript, le formulaire fonctionne quand même : l'adresse est vérifiée
// par le serveur à l'enregistrement.

document.addEventListener('DOMContentLoaded', function () {
    var MESSAGE_INDISPONIBLE = 'Géoplateforme est indisponible, l\'adresse ne sera pas vérifiée.';

    function jeton(formulaire) {
        return formulaire.elements.csrf ? formulaire.elements.csrf.value : '';
    }

    function demander(url, donnees, signal) {
        return fetch(url, { method: 'POST', body: donnees, credentials: 'same-origin', signal: signal })
            .then(function (reponse) {
                return reponse.ok ? reponse.json() : {};
            });
    }

    // ----- Indication « adresse certifiée » et quartiers --------------------
    function verifierAdresse(formulaire) {
        var etat = formulaire.querySelector('[data-etat-adresse]');
        var champAdresse = formulaire.elements.adresse;
        // Formulaire en lecture seule (rôle lecteur) : rien à vérifier.
        if (!etat || !champAdresse || champAdresse.matches(':disabled')) {
            return;
        }
        var adresse = champAdresse.value.trim();
        var codePostal = formulaire.elements.code_postal ? formulaire.elements.code_postal.value.trim() : '';
        var ville = formulaire.elements.ville ? formulaire.elements.ville.value.trim() : '';
        if (adresse.length < 4 || !/^\d{5}$/.test(codePostal)) {
            etat.textContent = '';
            return;
        }
        var cle = adresse + '|' + codePostal + '|' + ville;
        if (etat.dataset.verifie === cle) {
            return;
        }
        etat.dataset.verifie = cle;
        etat.className = 'form-text';
        etat.textContent = 'Vérification de l\'adresse…';

        var donnees = new FormData();
        donnees.append('csrf', jeton(formulaire));
        donnees.append('verifier', '1');
        donnees.append('adresse', adresse);
        donnees.append('code_postal', codePostal);
        donnees.append('ville', ville);
        demander(champAdresse.dataset.url, donnees)
            .then(function (json) {
                if (etat.dataset.verifie !== cle) {
                    return; // la saisie a changé entre-temps
                }
                if (json.indisponible) {
                    // Le service d'adresses ne répond pas : on le dit, la saisie reste possible.
                    etat.dataset.verifie = '';
                    etat.className = 'form-text text-warning-emphasis';
                    etat.textContent = json.message || MESSAGE_INDISPONIBLE;
                    return;
                }
                if (typeof json.certifiee === 'undefined') {
                    etat.className = 'form-text';
                    etat.textContent = '';
                    return;
                }
                if (json.certifiee) {
                    // L'adresse certifiée corrige l'écriture, le code postal et la commune.
                    champAdresse.value = json.adresse;
                    formulaire.elements.code_postal.value = json.code_postal;
                    formulaire.elements.ville.value = json.ville;
                    etat.dataset.verifie = json.adresse + '|' + json.code_postal + '|' + json.ville;
                    etat.className = 'form-text text-success';
                    etat.textContent = 'Adresse certifiée. ' + (json.quartiers || '');
                } else {
                    etat.className = 'form-text text-danger';
                    etat.textContent = 'Adresse non certifiée : elle n\'a pas été trouvée au numéro près.';
                }
            })
            .catch(function () {
                etat.className = 'form-text';
                etat.textContent = 'La vérification de l\'adresse n\'a pas pu être faite.';
            });
    }

    document.querySelectorAll('form').forEach(function (formulaire) {
        if (!formulaire.querySelector('[data-etat-adresse]')) {
            return;
        }
        ['adresse', 'code_postal', 'ville'].forEach(function (nom) {
            var champ = formulaire.elements[nom];
            if (champ) {
                champ.addEventListener('change', function () {
                    // Laisse le temps à un clic sur une proposition de remplir les champs.
                    setTimeout(function () {
                        verifierAdresse(formulaire);
                    }, 250);
                });
            }
        });
        // Dans une fenêtre (modal), la vérification attend que la fenêtre soit ouverte.
        var fenetre = formulaire.closest('.modal');
        if (fenetre) {
            fenetre.addEventListener('shown.bs.modal', function () {
                verifierAdresse(formulaire);
            });
        } else {
            verifierAdresse(formulaire);
        }
    });

    // ----- Listes de propositions ---------------------------------------------
    document.querySelectorAll('input[data-suggestions]').forEach(function (champ) {
        var formulaire = champ.form;
        if (!formulaire) {
            return;
        }
        var type = champ.dataset.suggestions;
        var longueurMin = type === 'adresse' ? 4 : 3;
        var attente = null;
        var demandeEnCours = null;

        var liste = document.createElement('div');
        liste.className = 'list-group liste-propositions position-absolute start-0 end-0 mx-2 shadow z-3 d-none';
        liste.setAttribute('role', 'listbox');
        // Juste après le champ, pour s'ouvrir sous lui (et non sous son texte d'aide).
        champ.insertAdjacentElement('afterend', liste);

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

        function choisir(resultat) {
            if (type === 'adresse') {
                remplir('adresse', resultat.adresse);
            }
            remplir('code_postal', resultat.code_postal);
            remplir('ville', resultat.ville);
            fermer();
            verifierAdresse(formulaire);
        }

        function afficher(resultats) {
            liste.replaceChildren();
            resultats.forEach(function (resultat) {
                var bouton = document.createElement('button');
                bouton.type = 'button';
                bouton.className = 'list-group-item list-group-item-action';
                bouton.setAttribute('role', 'option');
                if (type === 'adresse') {
                    bouton.textContent = resultat.libelle;
                } else if (type === 'ville') {
                    bouton.textContent = resultat.ville + ' (' + resultat.code_postal + ')';
                } else {
                    bouton.textContent = resultat.code_postal + ' ' + resultat.ville;
                }
                // mousedown : avant que le champ ne perde le focus et ne ferme la liste.
                bouton.addEventListener('mousedown', function (evenement) {
                    evenement.preventDefault();
                    choisir(resultat);
                });
                liste.appendChild(bouton);
            });
            liste.classList.toggle('d-none', resultats.length === 0);
        }

        function chercher() {
            var texte = champ.value.trim();
            if (texte.length < longueurMin) {
                fermer();
                return;
            }
            if (demandeEnCours) {
                demandeEnCours.abort();
            }
            demandeEnCours = new AbortController();
            var donnees = new FormData();
            donnees.append('csrf', jeton(formulaire));
            donnees.append('q', texte);
            if (type === 'adresse' && formulaire.elements.code_postal) {
                donnees.append('code_postal', formulaire.elements.code_postal.value.trim());
            }
            demander(champ.dataset.url, donnees, demandeEnCours.signal)
                .then(function (json) {
                    var resultats = Array.isArray(json.resultats) ? json.resultats : [];
                    var etat = formulaire.querySelector('[data-etat-adresse]');
                    if (type === 'adresse' && json.indisponible && etat) {
                        etat.className = 'form-text text-warning-emphasis';
                        etat.textContent = json.message || MESSAGE_INDISPONIBLE;
                    }
                    // Code postal : à 4 ou 5 chiffres, une seule possibilité est remplie sans clic.
                    if (type === 'code_postal' && texte.length >= 4 && resultats.length === 1 && champ.value.trim() === texte) {
                        choisir(resultats[0]);
                        return;
                    }
                    afficher(resultats);
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
