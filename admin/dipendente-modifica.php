<?php
// admin/dipendente-modifica.php
// Endpoint JSON per le azioni sul dipendente (aggiorna dati, reset password,
// attiva/disattiva), chiamato via fetch dal modale in admin/dipendenti.php.
// Nessuna vista propria: risponde sempre con un JSON { ok, azione, ... }.
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';

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

$dipendenteId = (int) ($_POST['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    rispondi(['ok' => false, 'messaggio' => 'Dipendente non trovato.'], 404);
}

$azione = $_POST['azione'] ?? '';

if ($azione === 'aggiorna') {
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $codiceFiscale = trim($_POST['codice_fiscale'] ?? '');

    $utenteConStessaEmail = Utente::findByEmail($email);
    $utenteConStessoCf = Utente::findByCodiceFiscale($codiceFiscale);

    if ($utenteConStessaEmail !== null && (int) $utenteConStessaEmail['id'] !== $dipendenteId) {
        rispondi(['ok' => false, 'messaggio' => 'Esiste gia\' un utente con questa email.']);
    } elseif ($utenteConStessoCf !== null && (int) $utenteConStessoCf['id'] !== $dipendenteId) {
        rispondi(['ok' => false, 'messaggio' => 'Esiste gia\' un utente con questo codice fiscale.']);
    }

    Utente::update($dipendenteId, $nome, $cognome, $email, $codiceFiscale);
    rispondi(['ok' => true, 'azione' => 'aggiorna', 'messaggio' => 'Dati aggiornati.']);
} elseif ($azione === 'reset_password') {
    $nuovaPassword = generaPasswordTemporanea();
    Utente::setPassword($dipendenteId, $nuovaPassword, true);
    rispondi(['ok' => true, 'azione' => 'reset_password', 'password' => $nuovaPassword]);
} elseif ($azione === 'attiva') {
    Utente::setAttivo($dipendenteId, true);
    rispondi(['ok' => true, 'azione' => 'attiva', 'messaggio' => 'Dipendente riattivato.']);
} elseif ($azione === 'disattiva') {
    Utente::setAttivo($dipendenteId, false);
    rispondi(['ok' => true, 'azione' => 'disattiva', 'messaggio' => 'Dipendente disattivato.']);
} elseif ($azione === 'sblocca_login') {
    Utente::sbloccaLogin($dipendenteId);
    rispondi(['ok' => true, 'azione' => 'sblocca_login', 'messaggio' => 'Accesso sbloccato.']);
} elseif ($azione === 'elimina') {
    $conferma = trim($_POST['conferma'] ?? '');

    if ($conferma !== 'CANCELLA') {
        rispondi(['ok' => false, 'messaggio' => 'Devi scrivere CANCELLA per confermare.']);
    }

    if (Utente::haDocumenti($dipendenteId)) {
        rispondi(['ok' => false, 'messaggio' => 'Impossibile eliminare: il dipendente ha documenti caricati. Disattivalo invece.']);
    }

    Utente::delete($dipendenteId);
    rispondi(['ok' => true, 'azione' => 'elimina', 'id' => $dipendenteId, 'messaggio' => 'Dipendente eliminato.']);
}

rispondi(['ok' => false, 'messaggio' => 'Azione non riconosciuta.'], 400);
