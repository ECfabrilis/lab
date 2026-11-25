// Contrôle de protection de la partie privée (assistant d'installation et page
// « État du système »).
//
// Le navigateur tente de lire un fichier témoin de la partie privée. S'il y
// trouve le texte attendu, la partie privée est visible depuis le web : les
// journaux et les sauvegardes de la base le sont donc aussi.
//
// Bloc attendu :
//   <div data-protection-url="../includes/temoin_protection.txt" data-protection-marqueur="…">
//     <div data-protection-etat="attente">…</div>
//     <div data-protection-etat="ok" class="d-none">…</div>
//     <div data-protection-etat="expose" class="d-none">…</div>
//     <div data-protection-etat="inconnu" class="d-none">…</div>
//   </div>
// Facultatif, dans la page : un champ caché [data-protection-champ], qui reçoit
// le résultat, et un bouton [data-protection-bouton], qui reste désactivé tant
// que la partie privée est visible.

document.addEventListener('DOMContentLoaded', function () {
    var bloc = document.querySelector('[data-protection-url]');
    if (!bloc) {
        return;
    }
    var champ = document.querySelector('[data-protection-champ]');
    var bouton = document.querySelector('[data-protection-bouton]');

    function afficher(etat) {
        bloc.querySelectorAll('[data-protection-etat]').forEach(function (element) {
            element.classList.toggle('d-none', element.dataset.protectionEtat !== etat);
        });
        if (champ) {
            // Un contrôle impossible (réseau) ne bloque pas : seul un fichier réellement lu bloque.
            champ.value = etat === 'expose' ? 'expose' : 'ok';
        }
        if (bouton) {
            bouton.disabled = etat === 'expose';
        }
    }

    fetch(bloc.dataset.protectionUrl, { cache: 'no-store', credentials: 'omit' })
        .then(function (reponse) {
            return reponse.ok ? reponse.text() : '';
        })
        .then(function (texte) {
            afficher(texte.indexOf(bloc.dataset.protectionMarqueur) !== -1 ? 'expose' : 'ok');
        })
        .catch(function () {
            afficher('inconnu');
        });
});
