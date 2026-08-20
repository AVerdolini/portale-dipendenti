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
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="min-h-screen bg-base-200">
    <div class="flex">
        <?php include __DIR__ . '/partials/nav-admin.php'; ?>
        <div class="flex-1">
            <div class="navbar bg-base-100 shadow-sm px-6">
                <div class="flex-1 font-semibold"><?= htmlspecialchars($titolo) ?></div>
                <div class="flex-none flex items-center gap-3">
                    <span class="text-sm"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></span>
                    <a href="/portale-dipendenti/logout.php" class="btn btn-ghost btn-sm">Esci</a>
                </div>
            </div>
            <main class="p-6">
    <?php
}

function layout_admin_fine(): void
{
    ?>
            </main>
        </div>
    </div>
    <script src="/portale-dipendenti/public/assets/js/app.js"></script>
</body>
</html>
    <?php
}
