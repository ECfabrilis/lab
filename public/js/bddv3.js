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

    // Boutons utiles seulement quand JavaScript est désactivé.
    document.querySelectorAll('[data-masquer-si-js]').forEach(function (bouton) {
        bouton.classList.add('d-none');
    });
});
