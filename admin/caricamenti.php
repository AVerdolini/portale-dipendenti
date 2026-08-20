<?php
// admin/caricamenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$tuttiICaricamenti = Caricamento::all();

layout_admin_inizio('Caricamenti', 'caricamenti');
?>
<h1 class="text-xl font-semibold mb-6">Storico caricamenti</h1>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Data</th><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Stato</th><th>File originale</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tuttiICaricamenti as $c): ?>
        <tr class="hover">
            <td><?= htmlspecialchars($c['caricato_il']) ?></td>
            <td><?= $c['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($c['etichetta'] ?? '—') ?></td>
            <td><?= $c['mese'] !== null ? formatMese((int) $c['mese']) . ' ' : '' ?><?= $c['anno'] ?></td>
            <td>
                <span class="badge <?= $c['stato'] === 'completato' ? 'badge-success' : ($c['stato'] === 'con_errori' ? 'badge-warning' : 'badge-ghost') ?>">
                    <?= htmlspecialchars($c['stato']) ?>
                </span>
            </td>
            <td><?= htmlspecialchars($c['nome_file_originale']) ?></td>
            <td>
                <a href="/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=<?= $c['id'] ?>" class="btn btn-xs">Apri</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($tuttiICaricamenti)): ?>
        <tr><td colspan="7" class="text-base-content/60">Nessun caricamento effettuato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
