<?php
// anteprima-pagine.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Caricamento.php';
require_once __DIR__ . '/src/PdfSplitter.php';

require_admin();

$caricamentoId = (int) ($_GET['caricamento_id'] ?? 0);
$paginaDa = (int) ($_GET['pagina_da'] ?? 0);
$paginaA = (int) ($_GET['pagina_a'] ?? 0);

$caricamento = Caricamento::findById($caricamentoId);
if ($caricamento === null || $paginaDa < 1 || $paginaA < $paginaDa) {
    http_response_code(400);
    exit('Richiesta non valida.');
}

$percorsoTemporaneo = sys_get_temp_dir() . '/anteprima_' . $caricamentoId . '_' . $paginaDa . '_' . $paginaA . '_' . uniqid() . '.pdf';

try {
    PdfSplitter::estraiPagine($caricamento['percorso_file_originale'], $paginaDa, $paginaA, $percorsoTemporaneo);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="anteprima.pdf"');
    header('Content-Length: ' . filesize($percorsoTemporaneo));
    readfile($percorsoTemporaneo);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    exit('Richiesta non valida.');
} finally {
    if (file_exists($percorsoTemporaneo)) {
        unlink($percorsoTemporaneo);
    }
}
exit;
