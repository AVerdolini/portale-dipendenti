<?php
// src/PdfExtractor.php
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

class PdfExtractor
{
    // Standard Italian Codice Fiscale format: 6 letters, 2 digits, 1 letter,
    // 2 digits, 1 letter, 3 digits, 1 letter.
    private const PATTERN_CF = '/\b([A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z])\b/';

    // Retuned against a real cumulative PDF (Zucchetti cedolino layout) — see
    // "Punti da verificare con il primo PDF reale" in the design spec.
    // In this layout the "NETTO DEL MESE" label and its value are printed in
    // separate columns and end up far apart in the linearized page text, so
    // matching the label directly doesn't work. The value itself is reliably
    // recognizable instead: it's the one euro amount on the page printed
    // alone on its own line as "1.901,00€" (amount immediately followed by
    // the € sign, no other text on that line), just above the IBAN line.
    private const PATTERN_NETTO = '/^[\t ]*([\d.]+,\d{2})€[\t ]*$/mu';

    public static function estraiTestoPerPagina(string $percorsoPdf): array
    {
        $parser = new Parser();
        $documento = $parser->parseFile($percorsoPdf);
        $pagine = $documento->getPages();

        $risultato = [];
        foreach ($pagine as $indice => $pagina) {
            $risultato[$indice + 1] = $pagina->getText();
        }
        return $risultato;
    }

    public static function paginaVuota(string $testoPagina): bool
    {
        // "Vuota" qui significa priva di qualunque contenuto testuale utile:
        // niente lettere ne' cifre, solo spazi/interruzioni di riga (com'e'
        // il testo estratto da una pagina completamente bianca del PDF
        // cumulativo). Non usiamo trim() da solo perche' alcuni PDF lasciano
        // whitespace non standard (es. \xA0) che trim() non rimuove.
        return preg_match('/[\p{L}\p{N}]/u', $testoPagina) !== 1;
    }

    public static function estraiCodiceFiscale(string $testoPagina): ?string
    {
        $testoNormalizzato = strtoupper($testoPagina);
        if (preg_match(self::PATTERN_CF, $testoNormalizzato, $match)) {
            return $match[1];
        }
        return null;
    }

    public static function estraiNettoInBusta(string $testoPagina): ?float
    {
        if (preg_match(self::PATTERN_NETTO, $testoPagina, $match)) {
            // Italian number format: thousands "." decimal ",".
            $numeroNormalizzato = str_replace('.', '', $match[1]);
            $numeroNormalizzato = str_replace(',', '.', $numeroNormalizzato);
            return (float) $numeroNormalizzato;
        }
        return null;
    }

    public static function raggruppaPerCf(array $testoPerPagina): array
    {
        $blocchi = [];
        $cfCorrente = null;
        $paginaInizio = null;
        $nettoCorrente = null;

        $chiudiBlocco = function () use (&$blocchi, &$cfCorrente, &$paginaInizio, &$nettoCorrente, &$paginaPrecedente) {
            if ($paginaInizio !== null) {
                $blocchi[] = [
                    'cf' => $cfCorrente,
                    'pagina_da' => $paginaInizio,
                    'pagina_a' => $paginaPrecedente,
                    'netto' => $nettoCorrente,
                ];
            }
        };

        $paginaPrecedente = null;

        foreach ($testoPerPagina as $numeroPagina => $testo) {
            $cf = self::estraiCodiceFiscale($testo);
            $netto = self::estraiNettoInBusta($testo);

            if ($paginaInizio === null) {
                // Prima pagina in assoluto.
                $cfCorrente = $cf;
                $paginaInizio = $numeroPagina;
                $nettoCorrente = $netto;
            } elseif ($cf === $cfCorrente) {
                // Stesso CF (o entrambi null): continua il blocco corrente.
                if ($netto !== null) {
                    $nettoCorrente = $netto;
                }
            } else {
                // CF cambiato: chiudi il blocco corrente, aprine uno nuovo.
                $chiudiBlocco();
                $cfCorrente = $cf;
                $paginaInizio = $numeroPagina;
                $nettoCorrente = $netto;
            }

            $paginaPrecedente = $numeroPagina;
        }

        $chiudiBlocco();

        return $blocchi;
    }
}
