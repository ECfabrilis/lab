// Filtre d'une liste pendant la frappe (choix d'une activité à l'inscription) :
// les lignes qui ne correspondent pas disparaissent à chaque caractère saisi,
// sans envoi au serveur. Chaque mot saisi doit se trouver dans le nom, à
// n'importe quel endroit, sans tenir compte des accents ni des majuscules.
//
// Champ attendu : <input data-filtre-liste="idDeLaListe" data-filtre-vide="idDuMessage">
// Lignes de la liste : <... data-filtre-texte="nom à comparer">
// Le message « aucun résultat » (facultatif) porte la classe d-none au départ.

document.addEventListener('DOMContentLoaded', function () {
    // Minuscules, sans accents (é -> e, œ -> oe).
    function simplifier(texte) {
        return texte.toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/œ/g, 'oe').replace(/æ/g, 'ae');
    }

    document.querySelectorAll('input[data-filtre-liste]').forEach(function (champ) {
        var liste = document.getElementById(champ.dataset.filtreListe);
        if (!liste) {
            return;
        }
        var messageVide = document.getElementById(champ.dataset.filtreVide || '');
        var lignes = Array.prototype.map.call(liste.querySelectorAll('[data-filtre-texte]'), function (ligne) {
            return { element: ligne, texte: simplifier(ligne.dataset.filtreTexte) };
        });

        function filtrer() {
            var mots = simplifier(champ.value).split(/\s+/).filter(function (mot) {
                return mot !== '';
            });
            var visibles = 0;
            lignes.forEach(function (ligne) {
                var correspond = mots.every(function (mot) {
                    return ligne.texte.indexOf(mot) !== -1;
                });
                ligne.element.classList.toggle('d-none', !correspond);
                if (correspond) {
                    visibles++;
                }
            });
            liste.classList.toggle('d-none', visibles === 0);
            if (messageVide) {
                messageVide.classList.toggle('d-none', visibles !== 0);
            }
        }

        champ.addEventListener('input', filtrer);
        // Le navigateur a pu garder le texte saisi (retour en arrière).
        filtrer();
    });
});
