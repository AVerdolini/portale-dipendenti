<?php
// admin/dipendente-modifica.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$dipendenteId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    http_response_code(404);
    exit('Dipendente non trovato.');
}

$messaggio = null;
$passwordGenerata = null;
$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'aggiorna') {
        $nome = trim($_POST['nome'] ?? '');
        $cognome = trim($_POST['cognome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $codiceFiscale = trim($_POST['codice_fiscale'] ?? '');

        $utenteConStessaEmail = Utente::findByEmail($email);
        $utenteConStessoCf = Utente::findByCodiceFiscale($codiceFiscale);

        if ($utenteConStessaEmail !== null && (int) $utenteConStessaEmail['id'] !== $dipendenteId) {
            $errore = 'Esiste gia\' un utente con questa email.';
        } elseif ($utenteConStessoCf !== null && (int) $utenteConStessoCf['id'] !== $dipendenteId) {
            $errore = 'Esiste gia\' un utente con questo codice fiscale.';
        } else {
            Utente::update($dipendenteId, $nome, $cognome, $email, $codiceFiscale);
            $messaggio = 'Dati aggiornati.';
        }
    } elseif ($azione === 'reset_password') {
        $nuovaPassword = generaPasswordTemporanea();
        Utente::setPassword($dipendenteId, $nuovaPassword, true);
        $passwordGenerata = $nuovaPassword;
    } elseif ($azione === 'attiva') {
        Utente::setAttivo($dipendenteId, true);
        $messaggio = 'Dipendente riattivato.';
    } elseif ($azione === 'disattiva') {
        Utente::setAttivo($dipendenteId, false);
        $messaggio = 'Dipendente disattivato.';
    }

    $dipendente = Utente::findById($dipendenteId);
}

layout_admin_inizio('Modifica dipendente', 'dipendenti');
?>
<h1 class="text-xl font-semibold mb-6">Modifica dipendente</h1>

<?php if ($messaggio): ?>
    <div class="alert alert-success mb-4"><?= htmlspecialchars($messaggio) ?></div>
<?php endif; ?>
<?php if ($errore): ?>
    <div class="alert alert-error mb-4"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>
<?php if ($passwordGenerata): ?>
    <div class="alert alert-success mb-4">
        Nuova password temporanea: <strong><?= htmlspecialchars($passwordGenerata) ?></strong>
        — comunicala fuori banda, non verra' mostrata di nuovo.
    </div>
<?php endif; ?>

<div class="card bg-base-100 shadow p-6 max-w-lg flex flex-col gap-4">
    <form method="post" class="flex flex-col gap-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
        <input type="hidden" name="azione" value="aggiorna">
        <input type="text" name="nome" value="<?= htmlspecialchars($dipendente['nome']) ?>" required class="input input-bordered w-full">
        <input type="text" name="cognome" value="<?= htmlspecialchars($dipendente['cognome']) ?>" required class="input input-bordered w-full">
        <input type="email" name="email" value="<?= htmlspecialchars($dipendente['email']) ?>" required class="input input-bordered w-full">
        <input type="text" name="codice_fiscale" value="<?= htmlspecialchars($dipendente['codice_fiscale']) ?>" required maxlength="16" class="input input-bordered w-full">
        <button type="submit" class="btn btn-primary">Salva</button>
    </form>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
        <input type="hidden" name="azione" value="reset_password">
        <button type="submit" class="btn btn-outline w-full">Genera nuova password</button>
    </form>

    <?php if ($dipendente['attivo']): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
            <input type="hidden" name="azione" value="disattiva">
            <button type="submit" class="btn btn-error btn-outline w-full">Disattiva</button>
        </form>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $dipendente['id'] ?>">
            <input type="hidden" name="azione" value="attiva">
            <button type="submit" class="btn btn-success btn-outline w-full">Riattiva</button>
        </form>
    <?php endif; ?>
</div>
<?php
layout_admin_fine();
