<?php
// admin/dashboard.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../templates/layout-admin.php';

$utente = require_admin();

layout_admin_inizio('Dashboard', 'dashboard');
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold"><?= 'Ciao, ' . htmlspecialchars(trim($utente['nome'])) ?></h1>
    <p class="text-sm text-base-content/60 mt-1">Da qui gestisci caricamenti e anagrafica dipendenti.</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <a href="/portale-dipendenti/admin/nuovo-caricamento.php"
       class="group relative overflow-hidden rounded-2xl bg-primary text-primary-content p-6 flex flex-col justify-between min-h-[160px] md:col-span-2 shadow-[0_8px_24px_-8px_rgba(37,26,242,0.45)] transition-all duration-200 hover:shadow-[0_12px_32px_-8px_rgba(37,26,242,0.55)] hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.99]">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 opacity-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3v12" />
            <path d="M7 8l5-5 5 5" />
            <path d="M5 21h14a2 2 0 0 0 2-2v-4" />
            <path d="M3 15v4a2 2 0 0 0 2 2" />
        </svg>
        <div>
            <h2 class="text-lg font-semibold">Nuovo caricamento</h2>
            <p class="text-sm text-primary-content/75 mt-1">Carica un PDF cumulativo di buste paga o CU</p>
        </div>
    </a>

    <div class="grid grid-rows-2 gap-4">
        <a href="/portale-dipendenti/admin/caricamenti.php"
           class="group rounded-2xl bg-base-100 p-5 flex items-center gap-4 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.15)] transition-all duration-200 hover:shadow-[0_6px_18px_-6px_rgba(0,0,0,0.2)] hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.99]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 4h6l1 2h3a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1h3z" />
                <path d="M9 12h6" />
                <path d="M9 16h4" />
            </svg>
            <div>
                <h2 class="font-medium leading-tight">Caricamenti</h2>
                <p class="text-xs text-base-content/60 mt-0.5">Storico completo</p>
            </div>
        </a>

        <a href="/portale-dipendenti/admin/dipendenti.php"
           class="group rounded-2xl bg-base-100 p-5 flex items-center gap-4 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.15)] transition-all duration-200 hover:shadow-[0_6px_18px_-6px_rgba(0,0,0,0.2)] hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.99]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" />
                <circle cx="10" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <div>
                <h2 class="font-medium leading-tight">Dipendenti</h2>
                <p class="text-xs text-base-content/60 mt-0.5">Anagrafica e documenti</p>
            </div>
        </a>
    </div>
</div>
<?php
layout_admin_fine();
