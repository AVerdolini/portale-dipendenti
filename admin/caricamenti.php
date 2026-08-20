<?php
// admin/caricamenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

// Gestione submit del form "Nuovo caricamento" quando inviato dal modale di
// questa pagina (stessa logica di validazione/creazione di nuovo-caricamento.php,
// duplicata qui perche' l'azione del form del modale punta a questa pagina cosi'
// che, in caso di errore, il modale possa riaprirsi con il messaggio senza un
// redirect intermedio che perderebbe lo stato).
require_once __DIR__ . '/../src/PdfExtractor.php';

$erroreCaricamento = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione_caricamento'])) {
    csrf_verify();
    $tipoDocumento = $_POST['tipo_documento'] ?? '';
    $etichetta = $_POST['etichetta'] ?? null;
    $mese = ($_POST['mese'] ?? '') !== '' ? (int) $_POST['mese'] : null;
    $anno = (int) ($_POST['anno'] ?? 0);

    if (!in_array($tipoDocumento, ['busta_paga', 'cu'], true)) {
        $erroreCaricamento = 'Seleziona un tipo di documento valido.';
    } elseif ($tipoDocumento === 'busta_paga' && !in_array($etichetta, ['Cedolino', '13a mensilita', '14a mensilita'], true)) {
        $erroreCaricamento = 'Seleziona un\'etichetta valida per la busta paga.';
    } elseif ($tipoDocumento === 'busta_paga' && ($mese < 1 || $mese > 12)) {
        $erroreCaricamento = 'Seleziona un mese valido.';
    } elseif ($anno < 2000 || $anno > 2100) {
        $erroreCaricamento = 'Anno non valido.';
    } elseif (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $erroreCaricamento = 'Carica un file PDF valido.';
    } elseif (strtolower(pathinfo($_FILES['pdf']['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        $erroreCaricamento = 'Il file deve essere un PDF.';
    } elseif ((new finfo(FILEINFO_MIME_TYPE))->file($_FILES['pdf']['tmp_name']) !== 'application/pdf') {
        $erroreCaricamento = 'Il file non è un PDF valido.';
    } else {
        $cartellaOriginali = __DIR__ . '/../storage/originali';
        if (!is_dir($cartellaOriginali)) {
            mkdir($cartellaOriginali, 0755, true);
        }
        $nomeFile = uniqid('originale_', true) . '.pdf';
        $percorsoDestinazione = $cartellaOriginali . '/' . $nomeFile;

        if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $percorsoDestinazione)) {
            $erroreCaricamento = 'Impossibile salvare il file caricato. Riprova.';
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

$erroreModaleApri = $erroreCaricamento !== null;

layout_admin_inizio('Caricamenti', 'caricamenti');
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-semibold">Storico caricamenti</h1>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('modale-nuovo-caricamento').showModal()">
        Nuovo caricamento
    </button>
</div>

<div class="collapse collapse-arrow bg-base-100 shadow mb-4" <?= $filtriAttivi ? 'open' : '' ?>>
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
                    <a href="/portale-dipendenti/admin/caricamenti.php" class="btn btn-sm btn-ghost">Azzera</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Data</th><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Stato</th><th>File originale</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($caricamentiFiltrati as $c): ?>
        <tr class="hover">
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
            <td>
                <a href="/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=<?= $c['id'] ?>" class="btn btn-xs">Apri</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($caricamentiFiltrati)): ?>
        <tr><td colspan="7" class="text-base-content/60"><?= $filtriAttivi ? 'Nessun caricamento corrisponde ai filtri.' : 'Nessun caricamento effettuato.' ?></td></tr>
    <?php endif; ?>
    </tbody>
</table>

<dialog id="modale-nuovo-caricamento" class="modal" <?= $erroreModaleApri ? 'open' : '' ?>>
    <div class="modal-box">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-semibold text-lg mb-4">Nuovo caricamento</h3>
        <?php
        $errore = $erroreCaricamento;
        $formId = 'form-caricamento-modale';
        $action = '/portale-dipendenti/admin/caricamenti.php';
        $campoExtra = '<input type="hidden" name="azione_caricamento" value="1">';
        include __DIR__ . '/../templates/partials/form-nuovo-caricamento.php';
        ?>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>chiudi</button>
    </form>
</dialog>
<?php
layout_admin_fine();
