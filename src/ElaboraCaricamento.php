<?php
// src/ElaboraCaricamento.php
require_once __DIR__ . '/PdfExtractor.php';
require_once __DIR__ . '/PdfSplitter.php';
require_once __DIR__ . '/Caricamento.php';
require_once __DIR__ . '/Documento.php';
require_once __DIR__ . '/PaginaNonAssociata.php';
require_once __DIR__ . '/Utente.php';

class ElaboraCaricamento
{
    public static function esegui(int $caricamentoId, ?string $cartellaStorageDocumenti = null): void
    {
        $caricamento = Caricamento::findById($caricamentoId);
        if ($caricamento === null) {
            throw new InvalidArgumentException("Caricamento $caricamentoId non trovato");
        }

        $cartellaStorageDocumenti ??= __DIR__ . '/../storage/documenti';

        $testoPerPagina = PdfExtractor::estraiTestoPerPagina($caricamento['percorso_file_originale']);
        $blocchi = PdfExtractor::raggruppaPerCf($testoPerPagina);

        $ciSonoErrori = false;

        foreach ($blocchi as $blocco) {
            $utente = $blocco['cf'] !== null ? Utente::findByCodiceFiscale($blocco['cf']) : null;

            if ($utente === null) {
                PaginaNonAssociata::create([
                    'caricamento_id' => $caricamentoId,
                    'pagina_da' => $blocco['pagina_da'],
                    'pagina_a' => $blocco['pagina_a'],
                    'cf_estratto' => $blocco['cf'],
                ]);
                $ciSonoErrori = true;
                continue;
            }

            $esistente = Documento::esisteAssociato(
                (int) $utente['id'],
                $caricamento['tipo_documento'],
                $caricamento['etichetta'],
                $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
                (int) $caricamento['anno']
            );

            if ($esistente !== null) {
                // Conflitto: registrato come pagina da rivedere con il CF noto,
                // cosi' l'admin lo vede nella coda di revisione e decide se sovrascrivere.
                PaginaNonAssociata::create([
                    'caricamento_id' => $caricamentoId,
                    'pagina_da' => $blocco['pagina_da'],
                    'pagina_a' => $blocco['pagina_a'],
                    'cf_estratto' => $blocco['cf'],
                ]);
                $ciSonoErrori = true;
                continue;
            }

            $nomeFile = sprintf('doc_%d_%d.pdf', $caricamentoId, $utente['id']);
            $percorsoDestinazione = rtrim($cartellaStorageDocumenti, '/\\') . DIRECTORY_SEPARATOR . $nomeFile;

            PdfSplitter::estraiPagine(
                $caricamento['percorso_file_originale'],
                $blocco['pagina_da'],
                $blocco['pagina_a'],
                $percorsoDestinazione
            );

            Documento::create([
                'caricamento_id' => $caricamentoId,
                'utente_id' => $utente['id'],
                'tipo_documento' => $caricamento['tipo_documento'],
                'etichetta' => $caricamento['etichetta'],
                'mese' => $caricamento['mese'],
                'anno' => $caricamento['anno'],
                'percorso_file' => $percorsoDestinazione,
                'pagina_da' => $blocco['pagina_da'],
                'pagina_a' => $blocco['pagina_a'],
                'netto_in_busta' => $blocco['netto'],
                'stato' => 'associato',
            ]);
        }

        Caricamento::setStato($caricamentoId, $ciSonoErrori ? 'con_errori' : 'completato');
    }
}
