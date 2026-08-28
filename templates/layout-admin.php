<?php
require_once __DIR__ . '/../src/auth.php';

function layout_admin_inizio(string $titolo, string $paginaAttiva): void
{
    $utente = current_user();
    ?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titolo) ?> — Admin — Portale Dipendenti</title>
    <link rel="stylesheet" href="/public/assets/css/output.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="min-h-screen bg-base-200">
    <div class="navbar bg-base-100 shadow-sm px-6">
        <div class="flex-1 flex items-center gap-4">
            <a href="/admin/dashboard.php" class="font-semibold text-lg">Portale Dipendenti</a>
            <?php if ($paginaAttiva !== 'dashboard'): ?>
                <span class="text-base-content/40">/</span>
                <span class="text-base-content/70"><?= htmlspecialchars($titolo) ?></span>
            <?php endif; ?>
        </div>
        <div class="flex-none flex items-center gap-3">
            <button type="button" id="nome-utente-navbar" class="text-sm hover:text-primary transition-colors duration-150" onclick="document.getElementById('modale-profilo-admin').showModal()">
                <?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?>
            </button>
            <a href="/logout.php" class="btn btn-ghost btn-sm">Esci</a>
        </div>
    </div>
    <main class="p-6">
    <?php
}

function layout_admin_fine(): void
{
    $utente = current_user();
    ?>
    </main>

    <dialog id="modale-profilo-admin" class="modal">
        <div class="modal-box">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-semibold text-lg mb-4">Il mio profilo</h3>
            <div class="messaggio-azione"></div>

            <form id="form-profilo-admin" class="flex flex-col gap-3 mb-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="text" name="nome" value="<?= htmlspecialchars($utente['nome']) ?>" placeholder="Nome" required class="input input-bordered w-full">
                <input type="text" name="cognome" value="<?= htmlspecialchars($utente['cognome']) ?>" placeholder="Cognome" required class="input input-bordered w-full">
                <input type="email" name="email" value="<?= htmlspecialchars($utente['email']) ?>" placeholder="Email" required class="input input-bordered w-full">
                <input type="text" name="codice_fiscale" value="<?= htmlspecialchars($utente['codice_fiscale']) ?>" placeholder="Codice Fiscale" required maxlength="16" class="input input-bordered w-full">
                <button type="submit" class="btn btn-primary transition-transform duration-150 active:scale-[0.98]">Salva</button>
            </form>

            <a href="/cambia-password.php" class="btn btn-outline w-full">Cambia password</a>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>chiudi</button>
        </form>
    </dialog>

    <script src="/public/assets/js/app.js"></script>
</body>
</html>
    <?php
}
