<?php
// login.php
require_once __DIR__ . '/src/auth.php';

if (current_user() !== null) {
    redirect('/portale-dipendenti/index.php');
}

$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $utente = Utente::findByEmail($email);

    if ($utente !== null && Utente::isBloccato($utente)) {
        $errore = 'Troppi tentativi falliti. Riprova tra ' . Utente::minutiBloccoResidui($utente) . ' minuti.';
    } elseif ($utente === null || !$utente['attivo'] || !Utente::verifyPassword($utente, $password)) {
        // Il messaggio resta identico sia per email inesistente che per
        // password sbagliata (non si rivela quale delle due e' corretta),
        // ma il contatore tentativi si incrementa solo se l'utente esiste
        // davvero — altrimenti non c'e' una riga su cui incrementarlo.
        if ($utente !== null) {
            Utente::registraTentativoFallito((int) $utente['id']);
        }
        $errore = 'Email o password non corretti.';
    } else {
        Utente::resetTentativiFalliti((int) $utente['id']);
        login_utente($utente);
        redirect('/portale-dipendenti/index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-sm bg-base-100 shadow-xl">
        <div class="card-body">
            <h1 class="card-title">Portale Dipendenti</h1>
            <?php if ($errore): ?>
                <div class="alert alert-error text-sm"><?= htmlspecialchars($errore) ?></div>
            <?php endif; ?>
            <form method="post" class="flex flex-col gap-3">
                <input type="email" name="email" placeholder="Email" required class="input input-bordered w-full">
                <input type="password" name="password" placeholder="Password" required class="input input-bordered w-full">
                <button type="submit" class="btn btn-primary w-full">Accedi</button>
            </form>
        </div>
    </div>
</body>
</html>
