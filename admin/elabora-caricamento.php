<?php
// admin/elabora-caricamento.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/ElaboraCaricamento.php';

require_admin();

$caricamentoId = (int) ($_GET['caricamento_id'] ?? 0);
$caricamento = Caricamento::findById($caricamentoId);

if ($caricamento === null) {
    http_response_code(404);
    exit('Caricamento non trovato.');
}

if ($caricamento['stato'] === 'elaborazione') {
    ElaboraCaricamento::esegui($caricamentoId);
}

redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId);
