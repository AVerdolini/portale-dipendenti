<?php
// profilo.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

layout_dipendente_inizio('Menu', 'menu');
?>
<h1 class="text-lg font-semibold mb-4">Il mio profilo</h1>

<div class="card bg-base-100 shadow mb-4">
    <div class="card-body p-4">
        <dl class="flex flex-col gap-2 text-sm">
            <div><dt class="text-base-content/60">Nome</dt><dd class="font-medium"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></dd></div>
            <div><dt class="text-base-content/60">Email</dt><dd class="font-medium"><?= htmlspecialchars($utente['email']) ?></dd></div>
            <div><dt class="text-base-content/60">Codice Fiscale</dt><dd class="font-medium"><?= htmlspecialchars($utente['codice_fiscale']) ?></dd></div>
        </dl>
    </div>
</div>

<div class="flex flex-col gap-2">
    <a href="/portale-dipendenti/cambia-password.php" class="btn btn-outline w-full">Cambia password</a>
    <a href="/portale-dipendenti/logout.php" class="btn btn-error btn-outline w-full">Esci</a>
</div>
<?php
layout_dipendente_fine('menu');
