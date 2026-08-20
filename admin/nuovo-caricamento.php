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

<div class="card bg-base-100 shadow p-6 max-w-lg">
    <?php
    $formId = 'form-caricamento';
    $action = '/portale-dipendenti/admin/nuovo-caricamento.php';
    include __DIR__ . '/../templates/partials/form-nuovo-caricamento.php';
    ?>
</div>
<?php
layout_admin_fine();
