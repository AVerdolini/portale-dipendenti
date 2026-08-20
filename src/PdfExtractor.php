<?php
// src/PdfExtractor.php
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

class PdfExtractor
{
    // Standard Italian Codice Fiscale format: 6 letters, 2 digits, 1 letter,
    // 2 digits, 1 letter, 3 digits, 1 letter.
    private const PATTERN_CF = '/\b([A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z])\b/';

    // PLACEHOLDER pattern — verify and adjust against a real cumulative PDF
    // (see "Punti da verificare con il primo PDF reale" in the design spec).
    // Matches labels like "NETTO IN BUSTA € 1.720,00" or "NETTO A PAGARE 1.720,00".
    private const PATTERN_NETTO = '/NETTO\s+(?:IN\s+BUSTA|A\s+PAGARE)\D{0,10}?([\d.]+,\d{2})/iu';

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
