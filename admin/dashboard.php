<?php
// admin/dashboard.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$caricamentiRecenti = array_slice(Caricamento::all(), 0, 10);

layout_admin_inizio('Dashboard', 'dashboard');
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-semibold">Caricamenti recenti</h1>
    <a href="/portale-dipendenti/admin/nuovo-caricamento.php" class="btn btn-primary">Nuovo caricamento</a>
</div>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Data</th><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Stato</th></tr></thead>
    <tbody>
    <?php foreach ($caricamentiRecenti as $c): ?>
        <tr class="hover cursor-pointer" onclick="window.location='/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=<?= $c['id'] ?>'">
            <td><?= htmlspecialchars($c['caricato_il']) ?></td>
            <td><?= $c['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($c['etichetta'] ?? '—') ?></td>
            <td><?= $c['mese'] !== null ? formatMese((int) $c['mese']) . ' ' : '' ?><?= $c['anno'] ?></td>
            <td>
                <span class="badge <?= $c['stato'] === 'completato' ? 'badge-success' : ($c['stato'] === 'con_errori' ? 'badge-warning' : 'badge-ghost') ?>">
                    <?= htmlspecialchars($c['stato']) ?>
                </span>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($caricamentiRecenti)): ?>
        <tr><td colspan="5" class="text-base-content/60">Nessun caricamento effettuato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
