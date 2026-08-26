<?php
// admin/profilo-modifica.php
// Endpoint JSON per l'admin che modifica i PROPRI dati (nome, cognome,
// email, CF), chiamato via fetch dal modale "Il mio profilo" in
// templates/layout-admin.php. A differenza di dipendente-modifica.php non
// accetta un id dal client: opera sempre e solo sull'utente in sessione,
// cosi' un admin non puo' mai modificare i dati di un altro utente
// passando un id diverso nella richiesta.
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';

$utente = require_admin();

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

$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');
$codiceFiscale = trim($_POST['codice_fiscale'] ?? '');

if ($nome === '' || $cognome === '' || $email === '' || $codiceFiscale === '') {
    rispondi(['ok' => false, 'messaggio' => 'Tutti i campi sono obbligatori.']);
}

$utenteConStessaEmail = Utente::findByEmail($email);
$utenteConStessoCf = Utente::findByCodiceFiscale($codiceFiscale);

if ($utenteConStessaEmail !== null && (int) $utenteConStessaEmail['id'] !== (int) $utente['id']) {
    rispondi(['ok' => false, 'messaggio' => 'Esiste gia\' un utente con questa email.']);
}
if ($utenteConStessoCf !== null && (int) $utenteConStessoCf['id'] !== (int) $utente['id']) {
    rispondi(['ok' => false, 'messaggio' => 'Esiste gia\' un utente con questo codice fiscale.']);
}

Utente::update((int) $utente['id'], $nome, $cognome, $email, $codiceFiscale);

rispondi([
    'ok' => true,
    'messaggio' => 'Dati aggiornati.',
    'nome' => $nome,
    'cognome' => $cognome,
]);
