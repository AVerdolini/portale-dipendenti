<?php
// src/ElaboraCaricamento.php
require_once __DIR__ . '/PdfExtractor.php';
require_once __DIR__ . '/PdfSplitter.php';
require_once __DIR__ . '/Caricamento.php';
require_once __DIR__ . '/Documento.php';
require_once __DIR__ . '/PaginaNonAssociata.php';
require_once __DIR__ . '/Utente.php';
require_once __DIR__ . '/db.php';

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

        try {
            foreach ($blocchi as $blocco) {
                db()->beginTransaction();

                try {
                    $utente = $blocco['cf'] !== null ? Utente::findByCodiceFiscale($blocco['cf']) : null;

                    if ($utente === null) {
                        PaginaNonAssociata::create([
                            'caricamento_id' => $caricamentoId,
                            'pagina_da' => $blocco['pagina_da'],
                            'pagina_a' => $blocco['pagina_a'],
                            'cf_estratto' => $blocco['cf'],
                        ]);
                        $ciSonoErrori = true;
                        db()->commit();
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
                        db()->commit();
                        continue;
                    }

                    $nomeFile = sprintf('doc_%d_%d.pdf', $caricamentoId, $utente['id']);
                    $percorsoDestinazione = rtrim($cartellaStorageDocumenti, '/\\') . DIRECTORY_SEPARATOR . $nomeFile;

                    // Nota: la scrittura su disco e' un side-effect che non viene
                    // annullato da un rollback della transazione DB. In caso di
                    // fallimento a meta' ciclo il file di questo blocco puo' restare
                    // orfano: rischio residuo accettato, cio' che conta e' che lo
                    // stato del DB resti coerente.
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

                    db()->commit();
                } catch (\Throwable $e) {
                    if (db()->inTransaction()) {
                        db()->rollBack();
                    }
                    throw $e;
                }
            }
        } catch (\Throwable $e) {
            // Stato del caricamento riflette il fallimento: nessun blocco parziale
            // resta committato nel DB (il blocco in corso e' stato annullato dal
            // rollback), ma i blocchi gia' committati nei giri precedenti restano
            // validi. Non e' possibile ripartire da zero via UI, quindi segnaliamo
            // errore invece di lasciare lo stato bloccato su "elaborazione".
            Caricamento::setStato($caricamentoId, 'con_errori');
            error_log('ElaboraCaricamento::esegui fallito per caricamento ' . $caricamentoId . ': ' . $e->getMessage());
            throw $e;
        }

        Caricamento::setStato($caricamentoId, $ciSonoErrori ? 'con_errori' : 'completato');
    }
}
