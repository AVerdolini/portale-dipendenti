<?php
// admin/caricamenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$tuttiICaricamenti = Caricamento::all();

// Filtri (letti da querystring, applicati lato PHP sull'elenco completo).
$filtroTipo = $_GET['tipo'] ?? '';
$filtroStato = $_GET['stato'] ?? '';
$filtroEtichetta = $_GET['etichetta'] ?? '';
$filtroMese = $_GET['mese'] ?? '';
$filtroAnno = $_GET['anno'] ?? '';
$filtriAttivi = $filtroTipo !== '' || $filtroStato !== '' || $filtroEtichetta !== '' || $filtroMese !== '' || $filtroAnno !== '';

$caricamentiFiltrati = array_filter($tuttiICaricamenti, function ($c) use ($filtroTipo, $filtroStato, $filtroEtichetta, $filtroMese, $filtroAnno) {
    if ($filtroTipo !== '' && $c['tipo_documento'] !== $filtroTipo) {
        return false;
    }
    if ($filtroStato !== '' && $c['stato'] !== $filtroStato) {
        return false;
    }
    if ($filtroEtichetta !== '' && ($c['etichetta'] ?? '') !== $filtroEtichetta) {
        return false;
    }
    if ($filtroMese !== '' && (string) $c['mese'] !== $filtroMese) {
        return false;
    }
    if ($filtroAnno !== '' && (string) $c['anno'] !== $filtroAnno) {
        return false;
    }
    return true;
});

layout_admin_inizio('Caricamenti', 'caricamenti');
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-semibold">Storico caricamenti</h1>
    <a href="/admin/nuovo-caricamento.php" class="btn btn-primary transition-transform duration-150 active:scale-[0.98]">
        Nuovo caricamento
    </a>
</div>

<div class="collapse collapse-arrow bg-base-100 rounded-xl shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] mb-4" <?= $filtriAttivi ? 'open' : '' ?>>
    <input type="checkbox" <?= $filtriAttivi ? 'checked' : '' ?>>
    <div class="collapse-title font-medium">
        Filtri<?= $filtriAttivi ? ' (attivi)' : '' ?>
    </div>
    <div class="collapse-content">
        <form method="get" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <div>
                <label class="label"><span class="label-text text-xs">Tipo documento</span></label>
                <select name="tipo" class="select select-bordered select-sm w-full">
                    <option value="">Tutti</option>
                    <option value="busta_paga" <?= $filtroTipo === 'busta_paga' ? 'selected' : '' ?>>Busta paga</option>
                    <option value="cu" <?= $filtroTipo === 'cu' ? 'selected' : '' ?>>CU</option>
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text text-xs">Etichetta</span></label>
                <select name="etichetta" class="select select-bordered select-sm w-full">
                    <option value="">Tutte</option>
                    <option value="Cedolino" <?= $filtroEtichetta === 'Cedolino' ? 'selected' : '' ?>>Cedolino</option>
                    <option value="13a mensilita" <?= $filtroEtichetta === '13a mensilita' ? 'selected' : '' ?>>13ª mensilità</option>
                    <option value="14a mensilita" <?= $filtroEtichetta === '14a mensilita' ? 'selected' : '' ?>>14ª mensilità</option>
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text text-xs">Mese</span></label>
                <select name="mese" class="select select-bordered select-sm w-full">
                    <option value="">Tutti</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $filtroMese === (string) $m ? 'selected' : '' ?>><?= formatMese($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="label"><span class="label-text text-xs">Anno</span></label>
                <input type="number" name="anno" value="<?= htmlspecialchars($filtroAnno) ?>" placeholder="Es. 2024" class="input input-bordered input-sm w-full">
            </div>
            <div>
                <label class="label"><span class="label-text text-xs">Stato</span></label>
                <select name="stato" class="select select-bordered select-sm w-full">
                    <option value="">Tutti</option>
                    <option value="completato" <?= $filtroStato === 'completato' ? 'selected' : '' ?>>Completato</option>
                    <option value="con_errori" <?= $filtroStato === 'con_errori' ? 'selected' : '' ?>>Con errori</option>
                    <option value="elaborazione" <?= $filtroStato === 'elaborazione' ? 'selected' : '' ?>>In elaborazione</option>
                </select>
            </div>
            <div class="col-span-2 md:col-span-5 flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Applica filtri</button>
                <?php if ($filtriAttivi): ?>
                    <a href="/admin/caricamenti.php" class="btn btn-sm btn-ghost">Azzera</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<table class="table bg-base-100 rounded-xl shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)]">
    <thead><tr><th>Data</th><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Stato</th><th>File originale</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($caricamentiFiltrati as $c): ?>
        <tr class="hover" id="riga-caricamento-<?= $c['id'] ?>">
            <td><?= htmlspecialchars($c['caricato_il']) ?></td>
            <td><?= $c['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($c['etichetta'] ?? '—') ?></td>
            <td><?= $c['mese'] !== null ? formatMese((int) $c['mese']) . ' ' : '' ?><?= $c['anno'] ?></td>
            <td>
                <span class="badge <?= $c['stato'] === 'completato' ? 'badge-success' : ($c['stato'] === 'con_errori' ? 'badge-warning' : 'badge-ghost') ?>">
                    <?= htmlspecialchars(formatStatoCaricamento($c['stato'])) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($c['nome_file_originale']) ?></td>
            <td class="flex gap-2">
                <a href="/admin/revisione-caricamento.php?caricamento_id=<?= $c['id'] ?>" class="btn btn-xs">Apri</a>
                <a href="/admin/scarica-originale.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-outline">Scarica originale</a>
                <button type="button" class="btn btn-xs btn-outline btn-error" onclick="document.getElementById('modale-elimina-caricamento-<?= $c['id'] ?>').showModal()">Elimina</button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($caricamentiFiltrati)): ?>
        <tr><td colspan="7" class="text-base-content/60"><?= $filtriAttivi ? 'Nessun caricamento corrisponde ai filtri.' : 'Nessun caricamento effettuato.' ?></td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php foreach ($caricamentiFiltrati as $c): ?>
    <dialog id="modale-elimina-caricamento-<?= $c['id'] ?>" class="modal">
        <div class="modal-box">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-semibold text-lg mb-4">Elimina caricamento</h3>
            <p class="text-sm text-error mb-3">
                Elimina definitivamente questo caricamento (<?= htmlspecialchars($c['nome_file_originale']) ?>): tutti i documenti generati, le pagine in attesa di revisione e i file PDF sul disco vengono rimossi. Non e' reversibile.
            </p>
            <form class="form-elimina-caricamento flex flex-col gap-2" data-azione="elimina">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <input type="text" name="conferma" placeholder="Scrivi CANCELLA per confermare" autocomplete="off" class="input input-bordered input-error w-full">
                <button type="submit" class="btn btn-error w-full" disabled>Elimina caricamento</button>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>chiudi</button>
        </form>
    </dialog>
<?php endforeach; ?>

<div id="toast-container" class="toast toast-end z-50"></div>
<?php
layout_admin_fine();
