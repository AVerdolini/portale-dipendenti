<?php
/**
 * Form dello Step 1 del wizard di caricamento (tipo documento, periodo, file).
 * Riusato sia da admin/nuovo-caricamento.php (pagina standalone) sia dal
 * modale "Nuovo caricamento" in admin/caricamenti.php.
 * Si aspetta in scope: string|null $errore, string $formId, string $action,
 * e opzionalmente string $campoExtra (markup HTML di eventuali hidden input
 * aggiuntivi da inserire nel form, es. per distinguere il submit del modale).
 */
$campoExtra = $campoExtra ?? '';
?>
<?php if ($errore): ?>
    <div class="alert alert-error mb-4 text-sm"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($action) ?>" enctype="multipart/form-data" class="flex flex-col gap-4" id="<?= htmlspecialchars($formId) ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <?= $campoExtra ?>
    <div>
        <label class="label"><span class="label-text">Tipo documento</span></label>
        <select name="tipo_documento" class="select select-bordered w-full tipo-documento-select" required>
            <option value="">Seleziona...</option>
            <option value="busta_paga">Busta paga</option>
            <option value="cu">CU</option>
        </select>
    </div>

    <div class="campi-busta-paga" style="display:none">
        <label class="label"><span class="label-text">Etichetta</span></label>
        <select name="etichetta" class="select select-bordered w-full">
            <option value="Cedolino">Cedolino</option>
            <option value="13a mensilita">13ª mensilità</option>
            <option value="14a mensilita">14ª mensilità</option>
        </select>

        <label class="label mt-2"><span class="label-text">Mese</span></label>
        <select name="mese" class="select select-bordered w-full">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>"><?= formatMese($m) ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div>
        <label class="label"><span class="label-text">Anno</span></label>
        <input type="number" name="anno" class="input input-bordered w-full" value="<?= date('Y') ?>" required>
    </div>

    <div>
        <label class="label"><span class="label-text">File PDF cumulativo</span></label>
        <input type="file" name="pdf" accept="application/pdf" class="file-input file-input-bordered w-full" required>
    </div>

    <button type="submit" class="btn btn-primary">Avanti</button>
</form>
