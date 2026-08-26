<?php
// admin/scarica-originale.php
// Endpoint solo-admin per scaricare il PDF cumulativo originale di un
// caricamento (non un singolo documento per dipendente, che invece passa da
// scarica-documento.php). Sotto admin/ e con require_admin() perche' il file
// originale contiene le buste paga di piu' dipendenti insieme.
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';

require_admin();

$caricamentoId = (int) ($_GET['id'] ?? 0);
$modo = ($_GET['modo'] ?? 'allegato') === 'inline' ? 'inline' : 'allegato';

$caricamento = Caricamento::findById($caricamentoId);

if ($caricamento === null) {
    http_response_code(404);
    exit('Caricamento non trovato.');
}

if (!file_exists($caricamento['percorso_file_originale'])) {
    http_response_code(404);
    exit('File non disponibile.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $modo . '; filename="' . basename($caricamento['nome_file_originale']) . '"');
header('Content-Length: ' . filesize($caricamento['percorso_file_originale']));
readfile($caricamento['percorso_file_originale']);
exit;
