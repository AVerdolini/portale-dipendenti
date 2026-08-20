<?php
// admin/dipendente-modifica.php
// Gestisce solo la logica POST delle azioni sul dipendente (aggiorna dati,
// reset password, attiva/disattiva); la presentazione vive interamente nel
// modale di admin/dipendenti.php, dove si viene sempre reindirizzati dopo
// ogni azione con l'esito da mostrare.
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';

require_admin();

$dipendenteId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    http_response_code(404);
    exit('Dipendente non trovato.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Nessuna vista standalone: si arriva qui solo via submit del modale.
    redirect('/portale-dipendenti/admin/dipendenti.php');
}

csrf_verify();
$azione = $_POST['azione'] ?? '';

$esito = 'ok';
$messaggio = null;

if ($azione === 'aggiorna') {
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $codiceFiscale = trim($_POST['codice_fiscale'] ?? '');

    $utenteConStessaEmail = Utente::findByEmail($email);
    $utenteConStessoCf = Utente::findByCodiceFiscale($codiceFiscale);

    if ($utenteConStessaEmail !== null && (int) $utenteConStessaEmail['id'] !== $dipendenteId) {
        $esito = 'errore';
        $messaggio = 'Esiste gia\' un utente con questa email.';
    } elseif ($utenteConStessoCf !== null && (int) $utenteConStessoCf['id'] !== $dipendenteId) {
        $esito = 'errore';
        $messaggio = 'Esiste gia\' un utente con questo codice fiscale.';
    } else {
        Utente::update($dipendenteId, $nome, $cognome, $email, $codiceFiscale);
        $messaggio = 'Dati aggiornati.';
    }
} elseif ($azione === 'reset_password') {
    $nuovaPassword = generaPasswordTemporanea();
    Utente::setPassword($dipendenteId, $nuovaPassword, true);
    $esito = 'password';
    $messaggio = $nuovaPassword;
} elseif ($azione === 'attiva') {
    Utente::setAttivo($dipendenteId, true);
    $messaggio = 'Dipendente riattivato.';
} elseif ($azione === 'disattiva') {
    Utente::setAttivo($dipendenteId, false);
    $messaggio = 'Dipendente disattivato.';
}

$queryEsito = http_build_query([
    'modifica_esito' => $esito,
    'modifica_messaggio' => $messaggio,
    'modifica_id' => $dipendenteId,
]);
redirect('/portale-dipendenti/admin/dipendenti.php?' . $queryEsito);
