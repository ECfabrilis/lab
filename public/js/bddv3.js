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

    // Boutons utiles seulement quand JavaScript est désactivé.
    document.querySelectorAll('[data-masquer-si-js]').forEach(function (bouton) {
        bouton.classList.add('d-none');
    });
});
