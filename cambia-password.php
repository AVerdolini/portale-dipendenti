<?php
// cambia-password.php
require_once __DIR__ . '/src/auth.php';

$utente = require_login();
$errore = null;
$obbligatorio = (bool) $utente['deve_cambiare_password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuova = $_POST['nuova_password'] ?? '';
    $conferma = $_POST['conferma_password'] ?? '';

    if (strlen($nuova) < 8) {
        $errore = 'La nuova password deve avere almeno 8 caratteri.';
    } elseif ($nuova !== $conferma) {
        $errore = 'Le due password non coincidono.';
    } else {
        Utente::setPassword((int) $utente['id'], $nuova, false);
        redirect('/portale-dipendenti/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia password — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-sm bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title">Cambia password</h1>
            <?php if ($obbligatorio): ?>
                <div class="alert alert-warning text-sm">Devi impostare una nuova password prima di continuare.</div>
            <?php endif; ?>
            <?php if ($errore): ?>
                <div class="alert alert-error text-sm"><?= htmlspecialchars($errore) ?></div>
            <?php endif; ?>
            <form method="post" class="flex flex-col gap-3">
                <input type="password" name="nuova_password" placeholder="Nuova password" required minlength="8" class="input input-bordered w-full">
                <input type="password" name="conferma_password" placeholder="Conferma password" required minlength="8" class="input input-bordered w-full">
                <button type="submit" class="btn btn-primary w-full">Salva</button>
            </form>
        </div>
    </div>
</body>
</html>
