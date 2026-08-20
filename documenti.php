<?php
// documenti.php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/Documento.php';
require_once __DIR__ . '/templates/layout-dipendente.php';

$utente = require_login();

$tuttiIDocumenti = Documento::perUtente((int) $utente['id']);

$anniDisponibili = array_values(array_unique(array_map(fn($d) => (int) $d['anno'], $tuttiIDocumenti)));
rsort($anniDisponibili);

$annoSelezionato = isset($_GET['anno']) ? (int) $_GET['anno'] : ($anniDisponibili[0] ?? (int) date('Y'));

$documentiFiltrati = array_filter($tuttiIDocumenti, fn($d) => (int) $d['anno'] === $annoSelezionato);
// Ordine decrescente (piu' recente prima) per la vista archivio.
usort($documentiFiltrati, fn($a, $b) => ($b['mese'] <=> $a['mese']) ?: ($b['id'] <=> $a['id']));

layout_dipendente_inizio('Documenti', 'documenti');
?>
<h1 class="text-lg font-semibold mb-4">Documenti</h1>

<?php if (!empty($anniDisponibili)): ?>
<div class="tabs tabs-boxed mb-4">
    <?php foreach ($anniDisponibili as $anno): ?>
        <a href="?anno=<?= $anno ?>" class="tab <?= $anno === $annoSelezionato ? 'tab-active' : '' ?>"><?= $anno ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<ul class="flex flex-col gap-2">
    <?php foreach ($documentiFiltrati as $doc): ?>
        <li class="card bg-base-100 shadow-sm">
            <div class="card-body p-3 flex-row justify-between items-center">
                <div>
                    <div class="font-medium">
                        <?= $doc['tipo_documento'] === 'cu' ? 'CU' : htmlspecialchars($doc['etichetta'] ?? 'Busta paga') ?>
                    </div>
                    <div class="text-xs text-base-content/60">
                        <?= $doc['mese'] !== null ? formatMese((int) $doc['mese']) . ' ' : '' ?><?= $doc['anno'] ?>
                    </div>
                </div>
                <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs btn-outline">Scarica</a>
            </div>
        </li>
    <?php endforeach; ?>
    <?php if (empty($documentiFiltrati)): ?>
        <li class="text-sm text-base-content/60">Nessun documento per l'anno selezionato.</li>
    <?php endif; ?>
</ul>
<?php
layout_dipendente_fine('documenti');
