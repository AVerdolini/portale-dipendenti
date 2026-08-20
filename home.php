<?php
// home.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

$documentiBustaPaga = Documento::perUtente((int) $utente['id'], 'busta_paga');

layout_dipendente_inizio('Home', 'home');

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
$valoriGrafico = array_values($puntiPerMese);
$eticheteGrafico = array_keys($puntiPerMese);

$larghezzaSvg = 300;
$altezzaSvg = 60;
$puntiSvg = [];
$numPunti = count($valoriGrafico);
if ($numPunti > 1) {
    $min = min($valoriGrafico);
    $max = max($valoriGrafico);
    $range = ($max - $min) ?: 1;
    foreach ($valoriGrafico as $i => $valore) {
        $x = ($i / ($numPunti - 1)) * $larghezzaSvg;
        $y = $altezzaSvg - (($valore - $min) / $range) * ($altezzaSvg - 10) - 5;
        $puntiSvg[] = round($x, 1) . ',' . round($y, 1);
    }
}
?>
<div class="card bg-base-100 shadow mb-4">
    <div class="card-body p-4">
        <?php if (count($puntiSvg) > 1): ?>
            <svg width="100%" height="<?= $altezzaSvg ?>" viewBox="0 0 <?= $larghezzaSvg ?> <?= $altezzaSvg ?>" preserveAspectRatio="none">
                <polyline points="<?= implode(' ', $puntiSvg) ?>" fill="none" stroke="currentColor" stroke-width="2" />
            </svg>
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

<script>
var documentiBustaPaga = <?= json_encode(array_map(fn($d) => [
    'id' => $d['id'],
    'etichetta' => $d['etichetta'],
    'mese' => $d['mese'],
    'anno' => $d['anno'],
], $documentiBustaPaga)) ?>;

$(function () {
    function aggiornaIndicatore(indice) {
        $('.indicatore-punto').removeClass('bg-primary').addClass('bg-base-300');
        $('.indicatore-punto[data-indice="' + indice + '"]').removeClass('bg-base-300').addClass('bg-primary');

        var doc = documentiBustaPaga[indice];
        if (doc) {
            $('#dettaglio-documento-corrente').html(
                '<a class="btn btn-primary btn-sm w-full" href="/portale-dipendenti/scarica-documento.php?id=' + doc.id + '">' +
                'Scarica ' + (doc.etichetta || 'documento') + ' ' + doc.mese + '-' + doc.anno +
                '</a>'
            );
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
layout_dipendente_fine('home');
