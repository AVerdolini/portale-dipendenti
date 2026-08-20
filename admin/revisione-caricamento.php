<?php
// admin/revisione-caricamento.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$caricamentoId = (int) ($_GET['caricamento_id'] ?? 0);
$caricamento = Caricamento::findById($caricamentoId);

if ($caricamento === null) {
    http_response_code(404);
    exit('Caricamento non trovato.');
}

$documentiAssociati = Documento::perCaricamento($caricamentoId);
$paginePendenti = PaginaNonAssociata::perCaricamento($caricamentoId, 'in_attesa');
$dipendenti = Utente::all();

// Separa le pagine pendenti in "conflitti" (CF noto con documento gia' esistente
// per lo stesso periodo) da "da rivedere" (CF ignoto o non trovato).
$conflitti = [];
$daRivedere = [];
foreach ($paginePendenti as $pagina) {
    $utenteMatch = $pagina['cf_estratto'] !== null ? Utente::findByCodiceFiscale($pagina['cf_estratto']) : null;
    $inConflitto = $utenteMatch !== null && Documento::esisteAssociato(
        (int) $utenteMatch['id'],
        $caricamento['tipo_documento'],
        $caricamento['etichetta'],
        $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
        (int) $caricamento['anno']
    ) !== null;

    if ($inConflitto) {
        $pagina['utente_match'] = $utenteMatch;
        $conflitti[] = $pagina;
    } else {
        $daRivedere[] = $pagina;
    }
}

layout_admin_inizio('Revisione caricamento', 'nuovo-caricamento');
?>
<ul class="steps w-full mb-6">
    <li class="step step-primary">Tipo, periodo e file</li>
    <li class="step step-primary">Elaborazione</li>
    <li class="step step-primary">Revisione</li>
</ul>

<div class="grid grid-cols-2 gap-6">
    <div class="flex flex-col gap-6 overflow-y-auto" style="max-height: 75vh">

        <div>
            <h2 class="font-semibold mb-2">Documenti associati (<?= count($documentiAssociati) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Dipendente</th><th>Pagine</th><th>Netto</th></tr></thead>
                <tbody>
                <?php foreach ($documentiAssociati as $doc): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>&modo=inline">
                        <td><?= htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']) ?></td>
                        <td><?= $doc['pagina_da'] ?>-<?= $doc['pagina_a'] ?></td>
                        <td><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentiAssociati)): ?>
                    <tr><td colspan="3" class="text-base-content/60">Nessun documento associato.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div>
            <h2 class="font-semibold mb-2">Da rivedere (<?= count($daRivedere) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Pagine</th><th>CF</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($daRivedere as $pagina): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/anteprima-pagine.php?caricamento_id=<?= $caricamentoId ?>&pagina_da=<?= $pagina['pagina_da'] ?>&pagina_a=<?= $pagina['pagina_a'] ?>">
                        <td><?= $pagina['pagina_da'] ?>-<?= $pagina['pagina_a'] ?></td>
                        <td><?= htmlspecialchars($pagina['cf_estratto'] ?? '(non trovato)') ?></td>
                        <td onclick="event.stopPropagation()">
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="flex gap-2 items-center">
                                <input type="hidden" name="azione" value="assegna">
                                <input type="hidden" name="pagina_id" value="<?= $pagina['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <select name="utente_id" class="select select-bordered select-xs">
                                    <?php foreach ($dipendenti as $d): ?>
                                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['cognome'] . ' ' . $d['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-xs btn-primary">Assegna</button>
                            </form>
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="azione" value="scarta_pagina">
                                <input type="hidden" name="pagina_id" value="<?= $pagina['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs">Scarta</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($daRivedere)): ?>
                    <tr><td colspan="3" class="text-base-content/60">Nessuna pagina da rivedere.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($conflitti)): ?>
        <div>
            <h2 class="font-semibold mb-2">Conflitti (<?= count($conflitti) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Pagine</th><th>Dipendente</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($conflitti as $conflitto): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/anteprima-pagine.php?caricamento_id=<?= $caricamentoId ?>&pagina_da=<?= $conflitto['pagina_da'] ?>&pagina_a=<?= $conflitto['pagina_a'] ?>">
                        <td><?= $conflitto['pagina_da'] ?>-<?= $conflitto['pagina_a'] ?></td>
                        <td><?= htmlspecialchars($conflitto['utente_match']['cognome'] . ' ' . $conflitto['utente_match']['nome']) ?></td>
                        <td onclick="event.stopPropagation()">
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="azione" value="sovrascrivi">
                                <input type="hidden" name="pagina_id" value="<?= $conflitto['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs btn-warning">Sovrascrivi</button>
                            </form>
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="azione" value="ignora"><input type="hidden" name="pagina_id" value="<?= $conflitto['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs">Ignora</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

    <div>
        <div class="mockup-window border bg-base-100" style="height: 75vh">
            <iframe id="preview-frame" src="about:blank" class="w-full h-full"></iframe>
        </div>
    </div>
</div>

<?php
layout_admin_fine();
