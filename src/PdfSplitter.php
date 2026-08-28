<?php
// src/PdfSplitter.php
require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

class PdfSplitter
{
    public static function estraiPagine(string $percorsoSorgente, int $paginaDa, int $paginaA, string $percorsoDestinazione): void
    {
        try {
            self::estraiPaginheConFpdi($percorsoSorgente, $paginaDa, $paginaA, $percorsoDestinazione);
            return;
        } catch (CrossReferenceException $e) {
            // FPDI (versione free) non sa leggere alcune strutture PDF moderne
            // (object streams / cross-reference stream compressi, comuni sui
            // PDF generati da software gestionali come TeamSystem/UNICA — vedi
            // verificato su una CU reale). qpdf normalizza il file "srotolando"
            // gli object streams in oggetti PDF classici (--object-streams=
            // disable): il contenuto resta identico, cambia solo la codifica
            // interna, e FPDI riesce a leggerlo. Tentativo di fallback, non il
            // percorso principale, perche' comporta un processo esterno in piu'
            // per ogni split: la stragrande maggioranza dei PDF (incluso ogni
            // cedolino visto finora) non ne ha bisogno.
        }

        $percorsoNormalizzato = self::normalizzaConQpdf($percorsoSorgente);
        if ($percorsoNormalizzato === null) {
            // qpdf non disponibile o normalizzazione fallita: rilanciamo
            // l'eccezione originale di FPDI, piu' specifica per chi debugga.
            self::estraiPaginheConFpdi($percorsoSorgente, $paginaDa, $paginaA, $percorsoDestinazione);
            return;
        }

        try {
            self::estraiPaginheConFpdi($percorsoNormalizzato, $paginaDa, $paginaA, $percorsoDestinazione);
        } finally {
            @unlink($percorsoNormalizzato);
        }
    }

    private static function estraiPaginheConFpdi(string $percorsoSorgente, int $paginaDa, int $paginaA, string $percorsoDestinazione): void
    {
        $pdf = new Fpdi();
        $numPagineTotali = $pdf->setSourceFile($percorsoSorgente);

        if ($paginaDa < 1 || $paginaA > $numPagineTotali || $paginaDa > $paginaA) {
            throw new InvalidArgumentException(
                "Intervallo pagine non valido: $paginaDa-$paginaA (il documento ha $numPagineTotali pagine)"
            );
        }

        for ($numeroPagina = $paginaDa; $numeroPagina <= $paginaA; $numeroPagina++) {
            $idTemplate = $pdf->importPage($numeroPagina);
            $dimensioni = $pdf->getTemplateSize($idTemplate);
            $pdf->AddPage($dimensioni['orientation'], [$dimensioni['width'], $dimensioni['height']]);
            $pdf->useTemplate($idTemplate);
        }

        $cartellaDestinazione = dirname($percorsoDestinazione);
        if (!is_dir($cartellaDestinazione)) {
            mkdir($cartellaDestinazione, 0755, true);
        }

        $pdf->Output('F', $percorsoDestinazione);
    }

    /**
     * @return string|null percorso del file normalizzato, o null se qpdf non
     *                      e' disponibile o la normalizzazione e' fallita.
     */
    private static function normalizzaConQpdf(string $percorsoSorgente): ?string
    {
        $percorsoOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portale_qpdf_' . bin2hex(random_bytes(8)) . '.pdf';

        $comando = [
            'qpdf',
            '--object-streams=disable',
            $percorsoSorgente,
            $percorsoOutput,
        ];
        $comandoEscaped = implode(' ', array_map('escapeshellarg', $comando));

        $descrittori = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $processo = @proc_open($comandoEscaped, $descrittori, $pipe);
        if (!\is_resource($processo)) {
            return null;
        }

        fclose($pipe[0]);
        stream_get_contents($pipe[1]);
        stream_get_contents($pipe[2]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        $codiceUscita = proc_close($processo);

        // qpdf esce con 3 su "warnings" (es. avvisi non bloccanti su strutture
        // minori del PDF sorgente) pur producendo un output valido e usabile:
        // trattiamo quindi 0 e 3 come successo, qualunque altro codice come
        // fallimento della normalizzazione.
        if (($codiceUscita !== 0 && $codiceUscita !== 3) || !is_file($percorsoOutput)) {
            @unlink($percorsoOutput);
            return null;
        }

        return $percorsoOutput;
    }
}
