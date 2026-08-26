<?php
// admin/caricamento-elimina.php
// Endpoint JSON per l'eliminazione definitiva di un caricamento (documenti,
// pagine non associate, file fisici inclusi), chiamato via fetch da
// admin/caricamenti.php e admin/revisione-caricamento.php.
// Nessuna vista propria: risponde sempre con un JSON { ok, ... }.
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';

require_admin();

header('Content-Type: application/json; charset=UTF-8');

function rispondi(array $dati, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($dati);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rispondi(['ok' => false, 'messaggio' => 'Metodo non consentito.'], 405);
}

csrf_verify();

$caricamentoId = (int) ($_POST['id'] ?? 0);
$caricamento = Caricamento::findById($caricamentoId);

if ($caricamento === null) {
    rispondi(['ok' => false, 'messaggio' => 'Caricamento non trovato.'], 404);
}

$conferma = trim($_POST['conferma'] ?? '');

if ($conferma !== 'CANCELLA') {
    rispondi(['ok' => false, 'messaggio' => 'Devi scrivere CANCELLA per confermare.']);
}

Caricamento::delete($caricamentoId);
rispondi(['ok' => true, 'id' => $caricamentoId, 'messaggio' => 'Caricamento eliminato.']);
