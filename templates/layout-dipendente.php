<?php

function layout_dipendente_inizio(string $titolo, string $paginaAttiva): void
{
    ?>
<!DOCTYPE html>
<html lang="it" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titolo) ?> — Portale Dipendenti</title>
    <link rel="stylesheet" href="/portale-dipendenti/public/assets/css/output.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="min-h-screen bg-base-200 pb-20">
    <main class="max-w-md mx-auto p-4">
    <?php
}

function layout_dipendente_fine(string $paginaAttiva): void
{
    ?>
    </main>
    <?php include __DIR__ . '/partials/nav-dipendente.php'; ?>
    <script src="/portale-dipendenti/public/assets/js/app.js"></script>
</body>
</html>
    <?php
}
