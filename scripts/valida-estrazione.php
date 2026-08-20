<?php
// scripts/valida-estrazione.php
// Standalone validation script — run manually against a real cumulative PDF
// to verify CF and netto extraction patterns before relying on them in production.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/PdfExtractor.php';

if ($argc < 2) {
    fwrite(STDERR, "Uso: php scripts/valida-estrazione.php <percorso-pdf>\n");
    exit(1);
}

$percorso = $argv[1];
$testoPerPagina = PdfExtractor::estraiTestoPerPagina($percorso);
echo "Pagine trovate: " . count($testoPerPagina) . "\n\n";

$blocchi = PdfExtractor::raggruppaPerCf($testoPerPagina);
foreach ($blocchi as $i => $blocco) {
    printf(
        "Blocco %d: pagine %d-%d, CF=%s, netto=%s\n",
        $i + 1,
        $blocco['pagina_da'],
        $blocco['pagina_a'],
        $blocco['cf'] ?? '(non trovato)',
        $blocco['netto'] !== null ? number_format($blocco['netto'], 2) : '(non trovato)'
    );
}
