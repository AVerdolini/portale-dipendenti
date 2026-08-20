<?php
// admin/nuovo-caricamento.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $tipoDocumento = $_POST['tipo_documento'] ?? '';
    $etichetta = $_POST['etichetta'] ?? null;
    $mese = ($_POST['mese'] ?? '') !== '' ? (int) $_POST['mese'] : null;
    $anno = (int) ($_POST['anno'] ?? 0);

    if (!in_array($tipoDocumento, ['busta_paga', 'cu'], true)) {
        $errore = 'Seleziona un tipo di documento valido.';
    } elseif ($tipoDocumento === 'busta_paga' && !in_array($etichetta, ['Cedolino', '13a mensilita', '14a mensilita'], true)) {
        $errore = 'Seleziona un\'etichetta valida per la busta paga.';
    } elseif ($tipoDocumento === 'busta_paga' && ($mese < 1 || $mese > 12)) {
        $errore = 'Seleziona un mese valido.';
    } elseif ($anno < 2000 || $anno > 2100) {
        $errore = 'Anno non valido.';
    } elseif (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errore = 'Carica un file PDF valido.';
    } elseif (strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $errore = 'Il file deve essere un PDF.';
    } elseif ((new finfo(FILEINFO_MIME_TYPE))->file($_FILES['pdf']['tmp_name']) !== 'application/pdf') {
        $errore = 'Il file non è un PDF valido.';
    } else {
        $cartellaOriginali = __DIR__ . '/../storage/originali';
        if (!is_dir($cartellaOriginali)) {
            mkdir($cartellaOriginali, 0755, true);
        }
        $nomeFile = uniqid('originale_', true) . '.pdf';
        $percorsoDestinazione = $cartellaOriginali . '/' . $nomeFile;

        if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $percorsoDestinazione)) {
            $errore = 'Impossibile salvare il file caricato. Riprova.';
        } else {
            $utente = current_user();
            $caricamentoId = Caricamento::create([
                'tipo_documento' => $tipoDocumento,
                'etichetta' => $tipoDocumento === 'busta_paga' ? $etichetta : null,
                'mese' => $tipoDocumento === 'busta_paga' ? $mese : null,
                'anno' => $anno,
                'nome_file_originale' => $_FILES['pdf']['name'],
                'percorso_file_originale' => $percorsoDestinazione,
                'caricato_da' => $utente['id'],
            ]);

            redirect('/portale-dipendenti/admin/elabora-caricamento.php?caricamento_id=' . $caricamentoId);
        }
    }
}

layout_admin_inizio('Nuovo caricamento', 'nuovo-caricamento');
?>
<ul class="steps w-full mb-6">
    <li class="step step-primary">Tipo, periodo e file</li>
    <li class="step">Elaborazione</li>
    <li class="step">Revisione</li>
</ul>

<?php if ($errore): ?>
    <div class="alert alert-error mb-4"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card bg-base-100 shadow p-6 max-w-lg flex flex-col gap-4" id="form-caricamento">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div>
        <label class="label"><span class="label-text">Tipo documento</span></label>
        <select name="tipo_documento" id="tipo_documento" class="select select-bordered w-full" required>
            <option value="">Seleziona...</option>
            <option value="busta_paga">Busta paga</option>
            <option value="cu">CU</option>
        </select>
    </div>

    <div id="campi-busta-paga" style="display:none">
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

<script>
$(function () {
    function aggiornaCampiBustaPaga() {
        var tipo = $('#tipo_documento').val();
        $('#campi-busta-paga').toggle(tipo === 'busta_paga');
    }
    $('#tipo_documento').on('change', aggiornaCampiBustaPaga);
    aggiornaCampiBustaPaga();
});
</script>
<?php
layout_admin_fine();
