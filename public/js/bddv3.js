// Scripts communs de l'application (aucun script n'est écrit dans les pages :
// la politique de sécurité du site n'autorise que les fichiers de ce dossier).

document.addEventListener('DOMContentLoaded', function () {
    // Liste déroulante qui envoie son formulaire dès qu'on change de valeur
    // (exemple : choix de la saison dans le menu).
    document.querySelectorAll('[data-envoi-auto]').forEach(function (champ) {
        champ.addEventListener('change', function () {
            if (champ.form) {
                champ.form.submit();
            }
        });
    });

    // Fenêtre (modal) à rouvrir d'elle-même : après une erreur de saisie, le
    // formulaire qu'elle contient est réaffiché avec les valeurs saisies.
    document.querySelectorAll('.modal[data-ouvrir-auto]').forEach(function (fenetre) {
        bootstrap.Modal.getOrCreateInstance(fenetre).show();
    });

    // Case « tout cocher / tout décocher » : coche ou décoche toutes les cases
    // du bloc dont l'identifiant est donné. Elle se coche d'elle-même quand
    // toutes les cases le sont, et passe à l'état intermédiaire quand une
    // partie seulement l'est.
    // Case attendue : <input type="checkbox" data-tout-cocher="idDuBloc">
    document.querySelectorAll('input[type="checkbox"][data-tout-cocher]').forEach(function (generale) {
        var bloc = document.getElementById(generale.dataset.toutCocher);
        if (!bloc) {
            return;
        }
        var cases = Array.prototype.slice.call(bloc.querySelectorAll('input[type="checkbox"]'));

        function mettreAJour() {
            var cochees = cases.filter(function (une) {
                return une.checked;
            }).length;
            generale.checked = cases.length > 0 && cochees === cases.length;
            generale.indeterminate = cochees > 0 && cochees < cases.length;
        }

        generale.addEventListener('change', function () {
            cases.forEach(function (une) {
                une.checked = generale.checked;
            });
            generale.indeterminate = false;
        });
        cases.forEach(function (une) {
            une.addEventListener('change', mettreAJour);
        });
        mettreAJour();
    });

    // Boutons utiles seulement quand JavaScript est désactivé.
    document.querySelectorAll('[data-masquer-si-js]').forEach(function (bouton) {
        bouton.classList.add('d-none');
    });
});
