<?php
// scarica-documento.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';
require_once __DIR__ . '/src/DocumentDownload.php';

$utente = require_login();

$documentoId = (int) ($_GET['id'] ?? 0);
$modo = ($_GET['modo'] ?? 'allegato') === 'inline' ? 'inline' : 'allegato';

$documento = Documento::findById($documentoId);

if ($documento === null || $documento['stato'] !== 'associato') {
    http_response_code(404);
    exit('Documento non trovato.');
}

$isProprietario = (int) $documento['utente_id'] === (int) $utente['id'];
$autorizzato = $utente['ruolo'] === 'admin' || $isProprietario;
if (!$autorizzato) {
    http_response_code(403);
    exit('Accesso negato.');
}

if (!file_exists($documento['percorso_file'])) {
    http_response_code(404);
    exit('File non disponibile.');
}

if ($utente['ruolo'] !== 'admin' && $isProprietario) {
    DocumentDownload::registra($documentoId, (int) $utente['id']);
}

$nomeScaricato = sprintf(
    '%s_%d%s.pdf',
    $documento['tipo_documento'] === 'cu' ? 'CU' : ($documento['etichetta'] ?? 'Documento'),
    $documento['anno'],
    $documento['mese'] !== null ? '-' . str_pad($documento['mese'], 2, '0', STR_PAD_LEFT) : ''
);

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $modo . '; filename="' . $nomeScaricato . '"');
header('Content-Length: ' . filesize($documento['percorso_file']));
readfile($documento['percorso_file']);
exit;
