<?php
// admin/dashboard.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

layout_admin_inizio('Dashboard', 'dashboard');
?>
<h1 class="text-xl font-semibold mb-6">Dashboard</h1>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <a href="/portale-dipendenti/admin/nuovo-caricamento.php" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
        <div class="card-body items-center text-center">
            <span class="text-4xl mb-2">📤</span>
            <h2 class="card-title">Nuovo caricamento</h2>
            <p class="text-sm text-base-content/60">Carica un nuovo PDF cumulativo di buste paga o CU</p>
        </div>
    </a>

    <a href="/portale-dipendenti/admin/caricamenti.php" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
        <div class="card-body items-center text-center">
            <span class="text-4xl mb-2">📋</span>
            <h2 class="card-title">Caricamenti</h2>
            <p class="text-sm text-base-content/60">Storico completo dei caricamenti effettuati</p>
        </div>
    </a>

    <a href="/portale-dipendenti/admin/dipendenti.php" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
        <div class="card-body items-center text-center">
            <span class="text-4xl mb-2">👥</span>
            <h2 class="card-title">Dipendenti</h2>
            <p class="text-sm text-base-content/60">Gestione anagrafica e documenti dei dipendenti</p>
        </div>
    </a>
</div>
<?php
layout_admin_fine();
