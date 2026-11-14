// Nouveau paiement : selon le montant saisi et le reste à payer,
//   - le choix « les deux » (adhésion et activités) apparaît quand le montant
//     couvre tout le reste à payer ;
//   - un message prévient quand le montant dépasse le reste à payer : le
//     surplus sera enregistré en don.
// Ces règles sont revérifiées par le serveur à l'enregistrement.

document.addEventListener('DOMContentLoaded', function () {
    var groupe = document.querySelector('fieldset[data-paiement-reste]');
    if (!groupe || !groupe.form) {
        return;
    }
    var champMontant = groupe.form.elements.montant;
    var choixDeux = groupe.querySelector('[data-paiement-deux]');
    var message = groupe.querySelector('[data-paiement-surplus]');
    var reste = parseInt(groupe.dataset.paiementReste, 10) || 0;
    if (!champMontant || !choixDeux || !message) {
        return;
    }

    // Montant saisi (« 12,50 », « 12.5 », « 12 € ») en centimes ; 0 s'il n'est pas lisible.
    function centimesSaisis() {
        var texte = champMontant.value.replace(/[\s  €]/g, '').replace(',', '.');
        return /^\d{1,6}(\.\d{1,2})?$/.test(texte) ? Math.round(parseFloat(texte) * 100) : 0;
    }

    function euros(centimes) {
        return (centimes / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
    }

    function objetChoisi() {
        var coche = groupe.querySelector('input[name="objet"]:checked');
        return coche ? coche.value : '';
    }

    function actualiser() {
        var montant = centimesSaisis();
        var deuxPossible = reste > 0 && montant >= reste;
        choixDeux.classList.toggle('d-none', !deuxPossible);
        if (!deuxPossible && objetChoisi() === 'deux') {
            // « les deux » n'est plus possible : retour au choix proposé au départ.
            var defaut = groupe.querySelector('input[name="objet"][value="' + groupe.dataset.paiementDefaut + '"]');
            if (defaut) {
                defaut.checked = true;
            }
        }
        var objet = objetChoisi();
        if (objet !== '' && objet !== 'don' && montant > reste) {
            message.textContent = reste > 0
                ? 'Le montant dépasse le reste à payer (' + euros(reste) + ') : ' + euros(montant - reste) + ' seront enregistrés en don.'
                : 'Plus rien n\'est dû : les ' + euros(montant) + ' seront enregistrés en don.';
        } else {
            message.textContent = '';
        }
    }

    champMontant.addEventListener('input', actualiser);
    groupe.addEventListener('change', actualiser);
    actualiser();
});
