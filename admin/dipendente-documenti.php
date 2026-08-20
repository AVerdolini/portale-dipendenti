<?php
// admin/dipendente-documenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$dipendenteId = (int) ($_GET['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    http_response_code(404);
    exit('Dipendente non trovato.');
}

$documenti = Documento::perUtente($dipendenteId);

layout_admin_inizio('Documenti di ' . $dipendente['nome'], 'dipendenti');
?>
<h1 class="text-xl font-semibold mb-6">
    Documenti — <?= htmlspecialchars($dipendente['cognome'] . ' ' . $dipendente['nome']) ?>
</h1>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Netto</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documenti as $doc): ?>
        <tr class="hover">
            <td><?= $doc['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($doc['etichetta'] ?? '—') ?></td>
            <td><?= $doc['mese'] !== null ? formatMese((int) $doc['mese']) . ' ' : '' ?><?= $doc['anno'] ?></td>
            <td><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></td>
            <td>
                <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs">Scarica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($documenti)): ?>
        <tr><td colspan="5" class="text-base-content/60">Nessun documento disponibile.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
