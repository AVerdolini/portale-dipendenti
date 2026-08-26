<?php
// home.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

$documentiBustaPaga = Documento::perUtente((int) $utente['id'], 'busta_paga');

layout_dipendente_inizio('Home', 'home');

$nomeVisualizzato = trim($utente['nome']);
?>
<div class="card bg-base-100 shadow mb-4">
    <div class="card-body p-4">
        <p class="text-lg">Ciao, <span class="font-semibold"><?= htmlspecialchars($nomeVisualizzato) ?></span> 👋</p>
    </div>
</div>
<?php

if (empty($documentiBustaPaga)) {
    echo '<div class="alert">Nessuna busta paga disponibile al momento.</div>';
    layout_dipendente_fine('home');
    exit;
}

// Costruisce i punti del grafico solo dai documenti con un netto noto,
// aggregando per mese/anno (se ci sono piu' documenti nello stesso mese,
// usa l'ultimo caricato per il punto del grafico).
$puntiPerMese = [];
foreach ($documentiBustaPaga as $doc) {
    if ($doc['netto_in_busta'] !== null) {
        $chiave = $doc['anno'] . '-' . str_pad((string) $doc['mese'], 2, '0', STR_PAD_LEFT);
        $puntiPerMese[$chiave] = (float) $doc['netto_in_busta'];
    }
}
ksort($puntiPerMese);
$chiaviGrafico = array_keys($puntiPerMese);
$valoriGrafico = array_values($puntiPerMese);
// Etichette leggibili per l'asse ("Lug 2026" invece della chiave "2026-07"
// usata solo per l'ordinamento).
$eticheteGrafico = array_map(function ($chiave) {
    [$anno, $mese] = explode('-', $chiave);
    return mb_substr(formatMese((int) $mese), 0, 3) . ' ' . $anno;
}, $chiaviGrafico);

// Il carosello scorre su $documentiBustaPaga (un elemento per documento,
// puo' averne piu' di uno nello stesso mese es. 13a/14a mensilita'), mentre
// il grafico ha un punto per mese/anno aggregato: gli indici dei due non
// coincidono. Questa mappa traduce "indice nel carosello" -> "indice nel
// grafico dello stesso mese/anno", cosi' lo slide puo' evidenziare il punto
// giusto anche quando i due elenchi non sono allineati 1 a 1.
$mappaCarosolloVersoGrafico = array_map(function ($doc) use ($chiaviGrafico) {
    $chiave = $doc['anno'] . '-' . str_pad((string) $doc['mese'], 2, '0', STR_PAD_LEFT);
    $indice = array_search($chiave, $chiaviGrafico, true);
    return $indice !== false ? $indice : null;
}, $documentiBustaPaga);
?>
<div class="card bg-base-100 shadow mb-4">
    <div class="card-body p-4">
        <?php if (count($valoriGrafico) > 1): ?>
            <?php
            // Chart.js con responsive:true + maintainAspectRatio:false ridisegna
            // il canvas alla dimensione del suo contenitore: senza un'altezza CSS
            // esplicita sul contenitore stesso (l'attributo height sul <canvas>
            // viene ignorato in questa modalita'), il contenitore si adatta al
            // contenuto e il canvas si adatta al contenitore in un loop di resize
            // infinito — il grafico cresce senza fine, come nello screenshot.
            ?>
            <div style="height: 220px">
                <canvas id="grafico-netto"></canvas>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto snap-x snap-mandatory flex" id="carosello-buste-paga" style="scroll-snap-type: x mandatory;">
            <?php foreach ($documentiBustaPaga as $indice => $doc): ?>
                <?php
                $documentiStessoMese = array_values(array_filter($documentiBustaPaga, fn($d) => $d['mese'] === $doc['mese'] && $d['anno'] === $doc['anno']));
                $posizioneNelMese = array_search($doc, $documentiStessoMese) + 1;
                $totaleNelMese = count($documentiStessoMese);
                ?>
                <div class="snap-center shrink-0 w-full text-center py-4" style="scroll-snap-align: center;" data-doc-id="<?= $doc['id'] ?>">
                    <div class="text-sm text-primary">
                        <?= formatMese((int) $doc['mese']) ?> <?= $doc['anno'] ?><?= $totaleNelMese > 1 ? ", $posizioneNelMese di $totaleNelMese" : '' ?>
                    </div>
                    <div class="text-4xl font-bold mt-1"><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></div>
                    <div class="text-xs text-base-content/60 tracking-wide mt-1">NETTO IN BUSTA</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-center gap-1 mt-2" id="indicatori-carosello">
            <?php foreach ($documentiBustaPaga as $indice => $doc): ?>
                <span class="w-1.5 h-1.5 rounded-full bg-base-300 indicatore-punto" data-indice="<?= $indice ?>"></span>
            <?php endforeach; ?>
        </div>

        <div class="divider my-2"></div>

        <div id="dettaglio-documento-corrente"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
var documentiBustaPaga = <?= json_encode(array_map(fn($d) => [
    'id' => $d['id'],
    'etichetta' => $d['etichetta'],
    'mese' => $d['mese'],
    'anno' => $d['anno'],
], $documentiBustaPaga)) ?>;

<?php if (count($valoriGrafico) > 1): ?>
var eticheteGraficoNetto = <?= json_encode($eticheteGrafico) ?>;
var valoriGraficoNetto = <?= json_encode($valoriGrafico) ?>;
// Traduce l'indice di un documento nel carosello nell'indice del punto
// corrispondente nel grafico (i due elenchi possono non coincidere, vedi
// commento PHP sopra su $mappaCarosolloVersoGrafico).
var mappaCarosolloVersoGrafico = <?= json_encode($mappaCarosolloVersoGrafico) ?>;
<?php endif; ?>

$(function () {
    <?php if (count($valoriGrafico) > 1): ?>
    var coloreBase = '#4f39f6';
    var coloreEvidenziato = '#f97316';
    var numeroPunti = valoriGraficoNetto.length;

    // Chart.js gestisce da solo lo scaling e disegna marker sempre visibili
    // (a differenza di un SVG a mano stirato con preserveAspectRatio="none",
    // dove un cerchio col raggio in unita' di viewBox si deformava in
    // un'ellisse quasi invisibile). pointRadius/pointBackgroundColor sono
    // passati come array (uno per punto, non un valore singolo) apposta:
    // e' quello che permette a evidenziaPuntoGrafico() di ingrandire e
    // ricolorare solo il marker del mese in vista nel carosello.
    var graficoNetto = new Chart(document.getElementById('grafico-netto'), {
        type: 'line',
        data: {
            labels: eticheteGraficoNetto,
            datasets: [{
                data: valoriGraficoNetto,
                borderColor: coloreBase,
                backgroundColor: coloreBase,
                pointBackgroundColor: new Array(numeroPunti).fill(coloreBase),
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: new Array(numeroPunti).fill(5),
                pointHoverRadius: 8,
                tension: 0.2,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (contesto) {
                            return contesto.parsed.y.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
                        }
                    }
                }
            },
            scales: {
                y: { display: false },
                x: { ticks: { font: { size: 10 } } }
            }
        }
    });

    function evidenziaPuntoGrafico(indiceGrafico) {
        var dataset = graficoNetto.data.datasets[0];
        dataset.pointBackgroundColor = new Array(numeroPunti).fill(coloreBase);
        dataset.pointRadius = new Array(numeroPunti).fill(5);

        if (indiceGrafico !== null && indiceGrafico !== undefined) {
            dataset.pointBackgroundColor[indiceGrafico] = coloreEvidenziato;
            dataset.pointRadius[indiceGrafico] = 8;
        }
        graficoNetto.update();
    }
    <?php endif; ?>

    function aggiornaIndicatore(indice) {
        $('.indicatore-punto').removeClass('bg-primary').addClass('bg-base-300');
        $('.indicatore-punto[data-indice="' + indice + '"]').removeClass('bg-base-300').addClass('bg-primary');

        <?php if (count($valoriGrafico) > 1): ?>
        evidenziaPuntoGrafico(mappaCarosolloVersoGrafico[indice]);
        <?php endif; ?>

        var doc = documentiBustaPaga[indice];
        if (doc) {
            var $link = $('<a>', {
                'class': 'btn btn-primary btn-sm w-full',
                'href': '/portale-dipendenti/scarica-documento.php?id=' + encodeURIComponent(doc.id),
                'text': 'Scarica ' + (doc.etichetta || 'documento') + ' ' + doc.mese + '-' + doc.anno
            });
            $('#dettaglio-documento-corrente').empty().append($link);
        }
    }

    var $carosello = $('#carosello-buste-paga');
    $carosello.on('scroll', function () {
        var larghezzaElemento = $carosello.width();
        var indice = Math.round($carosello.scrollLeft() / larghezzaElemento);
        aggiornaIndicatore(indice);
    });

    // Mostra l'ultimo documento (piu' recente) all'apertura della pagina.
    var ultimoIndice = documentiBustaPaga.length - 1;
    $carosello.scrollLeft($carosello.width() * ultimoIndice);
    aggiornaIndicatore(ultimoIndice);
});
</script>
<?php
$documentiCu = Documento::perUtente((int) $utente['id'], 'cu');
?>
<div class="card bg-base-100 shadow">
    <div class="card-body p-4">
        <h2 class="font-semibold mb-2">CU</h2>
        <?php if (empty($documentiCu)): ?>
            <p class="text-sm text-base-content/60">Nessuna CU disponibile.</p>
        <?php else: ?>
            <ul class="flex flex-col gap-2">
                <?php foreach ($documentiCu as $doc): ?>
                    <li class="flex justify-between items-center">
                        <span>CU <?= $doc['anno'] ?></span>
                        <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs btn-outline">Scarica</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php
layout_dipendente_fine('home');
