<?php
// profilo.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

layout_dipendente_inizio('Menu', 'menu');
?>
<h1 class="text-lg font-semibold mb-4">Il mio profilo</h1>

<div class="rounded-2xl bg-base-100 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.15)] mb-4 p-4">
    <div class="flex items-center gap-3 mb-4">
        <div class="w-11 h-11 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold shrink-0">
            <?= htmlspecialchars(mb_strtoupper(mb_substr($utente['nome'], 0, 1) . mb_substr($utente['cognome'], 0, 1))) ?>
        </div>
        <div>
            <div class="font-medium"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
            <div class="text-xs text-base-content/60"><?= htmlspecialchars($utente['email']) ?></div>
        </div>
    </div>
    <dl class="flex flex-col gap-2 text-sm border-t border-base-200 pt-3">
        <div class="flex justify-between"><dt class="text-base-content/60">Codice Fiscale</dt><dd class="font-medium font-mono tracking-wide"><?= htmlspecialchars($utente['codice_fiscale']) ?></dd></div>
    </dl>
</div>

<div class="flex flex-col gap-2">
    <a href="/portale-dipendenti/cambia-password.php" class="btn btn-outline w-full transition-all duration-150 active:scale-[0.98]">Cambia password</a>
    <a href="/portale-dipendenti/logout.php" class="btn btn-error btn-outline w-full transition-all duration-150 active:scale-[0.98]">Esci</a>
</div>
<?php
layout_dipendente_fine('menu');
