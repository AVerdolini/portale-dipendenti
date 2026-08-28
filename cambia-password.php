<?php
// cambia-password.php
require_once __DIR__ . '/src/auth.php';

$utente = require_login();
$errore = null;
$obbligatorio = (bool) $utente['deve_cambiare_password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nuova = $_POST['nuova_password'] ?? '';
    $conferma = $_POST['conferma_password'] ?? '';

    if (strlen($nuova) < 8) {
        $errore = 'La nuova password deve avere almeno 8 caratteri.';
    } elseif ($nuova !== $conferma) {
        $errore = 'Le due password non coincidono.';
    } else {
        Utente::setPassword((int) $utente['id'], $nuova, false);
        redirect('/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia password — Portale Dipendenti</title>
    <link rel="stylesheet" href="/public/assets/css/output.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="w-full max-w-sm">
        <div class="flex items-center gap-2 mb-6 justify-center">
            <div class="w-9 h-9 rounded-lg bg-primary text-primary-content flex items-center justify-center font-semibold shadow-[0_4px_14px_-4px_rgba(37,26,242,0.5)]">P</div>
            <span class="font-semibold">Portale Dipendenti</span>
        </div>
        <div class="card w-full bg-base-100 shadow-[0_2px_16px_-4px_rgba(0,0,0,0.12)]">
            <div class="card-body">
                <h1 class="card-title">Cambia password</h1>
                <?php if ($obbligatorio): ?>
                    <div class="alert alert-warning text-sm">Devi impostare una nuova password prima di continuare.</div>
                <?php endif; ?>
                <?php if ($errore): ?>
                    <div class="alert alert-error text-sm"><?= htmlspecialchars($errore) ?></div>
                <?php endif; ?>
                <form method="post" class="flex flex-col gap-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="password" name="nuova_password" placeholder="Nuova password" required minlength="8" class="input input-bordered w-full transition-shadow duration-200 focus:shadow-[0_0_0_3px_rgba(37,26,242,0.15)]">
                    <input type="password" name="conferma_password" placeholder="Conferma password" required minlength="8" class="input input-bordered w-full transition-shadow duration-200 focus:shadow-[0_0_0_3px_rgba(37,26,242,0.15)]">
                    <button type="submit" class="btn btn-primary w-full transition-transform duration-150 active:scale-[0.98]">Salva</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
