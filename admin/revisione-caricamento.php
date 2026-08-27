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
    $documentoEsistente = $utenteMatch !== null ? Documento::esisteAssociatoConOrigine(
        (int) $utenteMatch['id'],
        $caricamento['tipo_documento'],
        $caricamento['etichetta'],
        $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
        (int) $caricamento['anno']
    ) : null;

    if ($documentoEsistente !== null) {
        $pagina['utente_match'] = $utenteMatch;
        $pagina['documento_esistente'] = $documentoEsistente;
        $conflitti[] = $pagina;
    } else {
        $daRivedere[] = $pagina;
    }
}

layout_admin_inizio('Revisione caricamento', 'nuovo-caricamento');
?>
<div class="rounded-2xl bg-base-100 shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] p-4 mb-6">
    <div class="flex justify-between items-start">
        <ul class="steps w-full">
            <li class="step step-primary">Caricamento</li>
            <li class="step step-primary">Revisione</li>
        </ul>
        <div class="flex gap-2 shrink-0 ml-4">
            <a href="/portale-dipendenti/admin/scarica-originale.php?id=<?= $caricamentoId ?>" class="btn btn-sm btn-outline">Scarica originale</a>
            <button type="button" class="btn btn-sm btn-outline btn-error" onclick="document.getElementById('modale-elimina-caricamento').showModal()">Elimina caricamento</button>
        </div>
    </div>
</div>

<?php if (($_GET['errore'] ?? '') === 'sovrascrivi_fallito'): ?>
    <div class="alert alert-error mb-4">Impossibile sovrascrivere: il conflitto non e' piu' valido (probabilmente gia' risolto). La pagina resta in attesa di revisione.</div>
<?php endif; ?>
<?php if (($_GET['errore'] ?? '') === 'assegna_conflitto'): ?>
    <div class="alert alert-error mb-4">Impossibile assegnare: il dipendente selezionato ha gia' un documento per lo stesso periodo. La pagina resta in attesa di revisione.</div>
<?php endif; ?>
<?php if (($_GET['errore'] ?? '') === 'assegna_dipendente_non_valido'): ?>
    <div class="alert alert-error mb-4">Impossibile assegnare: il dipendente selezionato non e' valido o non e' piu' attivo. La pagina resta in attesa di revisione.</div>
<?php endif; ?>
<?php if (($_GET['errore'] ?? '') === 'elaborazione_fallita'): ?>
    <div class="alert alert-error mb-4">Si e' verificato un errore durante l'elaborazione del file caricato (potrebbe essere danneggiato o non valido). Il caricamento e' stato marcato come "con errori".</div>
<?php endif; ?>
<?php if (($_GET['errore'] ?? '') === 'netto_non_valido'): ?>
    <div class="alert alert-error mb-4">Importo non valido: usa un numero (es. 1234,56). Il netto non e' stato modificato.</div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-6">
    <div class="flex flex-col gap-6 overflow-y-auto" style="max-height: 75vh">

        <?php $mostraNetto = $caricamento['tipo_documento'] === 'busta_paga'; ?>
        <div class="rounded-2xl bg-base-100 shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] p-4">
            <h2 class="font-semibold mb-2">Documenti associati (<?= count($documentiAssociati) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Dipendente</th><th>Pagine</th><?php if ($mostraNetto): ?><th>Netto</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($documentiAssociati as $doc): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>&modo=inline">
                        <td><?= htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']) ?></td>
                        <td><?= $doc['pagina_da'] ?>-<?= $doc['pagina_a'] ?></td>
                        <?php if ($mostraNetto): ?>
                        <td>
                            <div class="flex items-center gap-2 riga-netto" data-modalita="lettura">
                                <span class="valore-netto"><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></span>
                                <button type="button" class="btn btn-2xs btn-ghost btn-modifica-netto" title="Modifica netto">✎</button>
                                <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="form-modifica-netto hidden flex items-center gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="azione" value="modifica_netto">
                                    <input type="hidden" name="documento_id" value="<?= $doc['id'] ?>">
                                    <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                    <input
                                        type="text"
                                        name="netto"
                                        class="input input-bordered input-xs w-24"
                                        placeholder="es. 1234,56"
                                        value="<?= $doc['netto_in_busta'] !== null ? htmlspecialchars(number_format((float) $doc['netto_in_busta'], 2, ',', '.')) : '' ?>"
                                    >
                                    <button type="submit" class="btn btn-2xs btn-primary">Salva</button>
                                    <button type="button" class="btn btn-2xs btn-outline btn-annulla-netto">Annulla</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($documentiAssociati)): ?>
                    <tr><td colspan="<?= $mostraNetto ? 3 : 2 ?>" class="text-base-content/60">Nessun documento associato.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl bg-base-100 shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] p-4">
            <h2 class="font-semibold mb-2">Da rivedere (<?= count($daRivedere) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Pagine</th><th>CF</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($daRivedere as $pagina): ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/anteprima-pagine.php?caricamento_id=<?= $caricamentoId ?>&pagina_da=<?= $pagina['pagina_da'] ?>&pagina_a=<?= $pagina['pagina_a'] ?>">
                        <td><?= $pagina['pagina_da'] ?>-<?= $pagina['pagina_a'] ?></td>
                        <td><?= htmlspecialchars($pagina['cf_estratto'] ?? '(non trovato)') ?></td>
                        <td onclick="event.stopPropagation()">
                            <div class="flex gap-2 items-center">
                                <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="flex gap-2 items-center">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="azione" value="assegna">
                                    <input type="hidden" name="pagina_id" value="<?= $pagina['id'] ?>">
                                    <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                    <select name="utente_id" class="select select-bordered select-xs" required>
                                        <option value="" selected disabled>Seleziona dipendente...</option>
                                        <?php foreach ($dipendenti as $d): ?>
                                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['cognome'] . ' ' . $d['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-xs btn-primary">Assegna</button>
                                </form>
                                <div class="divider divider-horizontal mx-0"></div>
                                <form method="post" action="/portale-dipendenti/admin/revisione-azione.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="azione" value="scarta_pagina">
                                    <input type="hidden" name="pagina_id" value="<?= $pagina['id'] ?>">
                                    <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                    <button type="submit" class="btn btn-xs btn-outline">Scarta</button>
                                </form>
                            </div>
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
        <div class="rounded-2xl bg-base-100 shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] p-4">
            <h2 class="font-semibold mb-2">Conflitti (<?= count($conflitti) ?>)</h2>
            <table class="table table-sm">
                <thead><tr><th>Pagine</th><th>Dipendente</th><th>Documento esistente</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($conflitti as $conflitto): ?>
                    <?php $doc = $conflitto['documento_esistente']; ?>
                    <tr class="hover cursor-pointer riga-preview" data-src="/portale-dipendenti/anteprima-pagine.php?caricamento_id=<?= $caricamentoId ?>&pagina_da=<?= $conflitto['pagina_da'] ?>&pagina_a=<?= $conflitto['pagina_a'] ?>">
                        <td><?= $conflitto['pagina_da'] ?>-<?= $conflitto['pagina_a'] ?></td>
                        <td><?= htmlspecialchars($conflitto['utente_match']['cognome'] . ' ' . $conflitto['utente_match']['nome']) ?></td>
                        <td onclick="event.stopPropagation()">
                            <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>&modo=inline" target="_blank" rel="noopener" class="link link-primary text-xs" title="Apri il documento gia' presente in una nuova scheda">
                                <?= htmlspecialchars($doc['caricamento_nome_file']) ?>
                            </a>
                            <div class="text-xs text-base-content/60">caricato il <?= htmlspecialchars($doc['caricamento_caricato_il']) ?></div>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="azione" value="sovrascrivi">
                                <input type="hidden" name="pagina_id" value="<?= $conflitto['id'] ?>">
                                <input type="hidden" name="caricamento_id" value="<?= $caricamentoId ?>">
                                <button type="submit" class="btn btn-xs btn-warning">Sovrascrivi</button>
                            </form>
                            <form method="post" action="/portale-dipendenti/admin/revisione-azione.php" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
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

<dialog id="modale-elimina-caricamento" class="modal">
    <div class="modal-box">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-semibold text-lg mb-4">Elimina caricamento</h3>
        <p class="text-sm text-error mb-3">
            Elimina definitivamente questo caricamento (<?= htmlspecialchars($caricamento['nome_file_originale']) ?>): tutti i documenti generati, le pagine in attesa di revisione e i file PDF sul disco vengono rimossi. Non e' reversibile.
        </p>
        <form class="form-elimina-caricamento flex flex-col gap-2" data-azione="elimina">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $caricamentoId ?>">
            <input type="text" name="conferma" placeholder="Scrivi CANCELLA per confermare" autocomplete="off" class="input input-bordered input-error w-full">
            <button type="submit" class="btn btn-error w-full" disabled>Elimina caricamento</button>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>chiudi</button>
    </form>
</dialog>

<div id="toast-container" class="toast toast-end z-50"></div>
<?php
layout_admin_fine();
