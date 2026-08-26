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
        <li class="rounded-xl bg-base-100 shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] p-3 flex justify-between items-center gap-3 transition-shadow duration-200 hover:shadow-[0_4px_14px_-4px_rgba(0,0,0,0.18)]">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 4h6l1 2h3a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1h3z" />
                        <path d="M9 12h6" />
                        <path d="M9 16h4" />
                    </svg>
                </div>
                <div>
                    <div class="font-medium">
                        <?= $doc['tipo_documento'] === 'cu' ? 'CU' : htmlspecialchars($doc['etichetta'] ?? 'Busta paga') ?>
                    </div>
                    <div class="text-xs text-base-content/60">
                        <?= $doc['mese'] !== null ? formatMese((int) $doc['mese']) . ' ' : '' ?><?= $doc['anno'] ?>
                    </div>
                </div>
            </div>
            <a href="/portale-dipendenti/scarica-documento.php?id=<?= $doc['id'] ?>" class="btn btn-xs btn-outline">Scarica</a>
        </li>
    <?php endforeach; ?>
    <?php if (empty($documentiFiltrati)): ?>
        <li class="text-sm text-base-content/60">Nessun documento per l'anno selezionato.</li>
    <?php endif; ?>
</ul>
<?php
layout_dipendente_fine('documenti');
