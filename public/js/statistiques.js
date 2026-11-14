// Graphiques de la page des statistiques (Chart.js, public/js/chart.umd.min.js).
//
// Chaque graphique est un <canvas data-graphique="..."> dont l'attribut décrit,
// en JSON, ce qu'il faut dessiner :
//   { "type": "bar" | "doughnut", "libelles": [...], "series": [{ "nom": "...", "valeurs": [...] }] }
// Un histogramme peut avoir deux séries (la saison et sa comparaison) ; un
// anneau n'en dessine qu'une. Les chiffres sont toujours repris dans un tableau
// sous le graphique : la page reste lisible sans JavaScript.
//
// Bouton d'impression : <button data-imprimer class="d-none"> (affiché ici).

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-imprimer]').forEach(function (bouton) {
        bouton.classList.remove('d-none');
        bouton.addEventListener('click', function () {
            window.print();
        });
    });

    if (typeof Chart === 'undefined') {
        return;
    }

    var style = getComputedStyle(document.documentElement);
    // Palette choisie dans les options des statistiques, transmise par la page
    // (<div class="page-statistiques" data-palette="#rrggbb,#rrggbb,...">).
    // La première couleur sert aux histogrammes, les suivantes aux parts des anneaux.
    var page = document.querySelector('.page-statistiques');
    var palette = ((page && page.dataset.palette) || '').split(',').filter(function (couleur) {
        return /^#[0-9a-f]{6}$/i.test(couleur);
    });
    if (palette.length === 0) {
        palette = [style.getPropertyValue('--bs-primary').trim() || '#0d6efd', '#fd7e14', '#198754', '#6f42c1', '#ffc107', '#20c997', '#dc3545', '#6c757d'];
    }
    var principale = palette[0];
    // Barres de la comparaison (saison précédente) : un gris fixe, quelle que soit la palette.
    var secondaire = '#adb5bd';

    Chart.defaults.font.family = style.getPropertyValue('--bs-body-font-family').trim() || Chart.defaults.font.family;
    Chart.defaults.color = style.getPropertyValue('--bs-body-color').trim() || Chart.defaults.color;

    var graphiques = [];
    document.querySelectorAll('canvas[data-graphique]').forEach(function (canvas) {
        var description;
        try {
            description = JSON.parse(canvas.dataset.graphique);
        } catch (erreur) {
            return;
        }
        if (!description || !Array.isArray(description.libelles) || !Array.isArray(description.series)) {
            return;
        }
        var anneau = description.type === 'doughnut';
        var series = (anneau ? description.series.slice(0, 1) : description.series).map(function (serie, rang) {
            return {
                label: serie.nom,
                data: serie.valeurs,
                backgroundColor: anneau
                    ? description.libelles.map(function (libelle, i) { return palette[i % palette.length]; })
                    : (rang === 0 ? principale : secondaire),
                borderWidth: anneau ? 1 : 0
            };
        });
        graphiques.push(new Chart(canvas, {
            type: anneau ? 'doughnut' : 'bar',
            data: { labels: description.libelles, datasets: series },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: anneau || series.length > 1, position: 'bottom' }
                },
                scales: anneau ? {} : {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        }));
    });

    // À l'impression, la largeur de la page change : les graphiques sont redessinés.
    window.addEventListener('beforeprint', function () {
        graphiques.forEach(function (graphique) {
            graphique.resize();
        });
    });
    window.addEventListener('afterprint', function () {
        graphiques.forEach(function (graphique) {
            graphique.resize();
        });
    });
});
