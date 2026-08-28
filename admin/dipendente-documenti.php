<?php
// admin/dipendente-documenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/DocumentDownload.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$dipendenteId = (int) ($_GET['id'] ?? 0);
$dipendente = Utente::findById($dipendenteId);

if ($dipendente === null) {
    http_response_code(404);
    exit('Dipendente non trovato.');
}

$documenti = Documento::perUtente($dipendenteId);
$ultimiDownload = DocumentDownload::ultimoPerDocumenti(array_column($documenti, 'id'));

layout_admin_inizio('Documenti di ' . $dipendente['nome'], 'dipendenti');
?>
<div class="flex items-center gap-2 mb-6">
    <a href="/admin/dipendenti.php" class="btn btn-ghost btn-sm btn-square" aria-label="Torna ai dipendenti">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 6l-6 6 6 6" />
        </svg>
    </a>
    <h1 class="text-xl font-semibold">
        Documenti — <?= htmlspecialchars($dipendente['cognome'] . ' ' . $dipendente['nome']) ?>
    </h1>
</div>

<table class="table bg-base-100 rounded-xl shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)]">
    <thead><tr><th>Tipo</th><th>Etichetta</th><th>Periodo</th><th>Netto</th><th>Ultimo download</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documenti as $doc): ?>
        <tr class="hover">
            <td><?= $doc['tipo_documento'] === 'cu' ? 'CU' : 'Busta paga' ?></td>
            <td><?= htmlspecialchars($doc['etichetta'] ?? '—') ?></td>
            <td><?= $doc['mese'] !== null ? formatMese((int) $doc['mese']) . ' ' : '' ?><?= $doc['anno'] ?></td>
            <td><?= formatEuro($doc['netto_in_busta'] !== null ? (float) $doc['netto_in_busta'] : null) ?></td>
            <td>
                <?php if (isset($ultimiDownload[$doc['id']])): ?>
                    <span title="<?= htmlspecialchars($ultimiDownload[$doc['id']]) ?>"><?= formatTempoFa($ultimiDownload[$doc['id']]) ?></span>
                <?php else: ?>
                    <span class="text-base-content/50">Mai scaricato</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs">Scarica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($documenti)): ?>
        <tr><td colspan="6" class="text-base-content/60">Nessun documento disponibile.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
layout_admin_fine();
