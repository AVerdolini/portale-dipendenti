<?php
// src/PdfSplitter.php
require_once __DIR__ . '/../vendor/autoload.php';

use setasign\Fpdi\Fpdi;

class PdfSplitter
{
    public static function estraiPagine(string $percorsoSorgente, int $paginaDa, int $paginaA, string $percorsoDestinazione): void
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
}
