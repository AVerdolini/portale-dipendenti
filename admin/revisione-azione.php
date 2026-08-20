<?php
// admin/revisione-azione.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';
require_once __DIR__ . '/../src/PdfSplitter.php';
require_once __DIR__ . '/../src/PdfExtractor.php';
require_once __DIR__ . '/../src/Utente.php';

$admin = require_admin();

csrf_verify();

$azione = $_POST['azione'] ?? '';
$caricamentoId = (int) ($_POST['caricamento_id'] ?? 0);
$paginaId = (int) ($_POST['pagina_id'] ?? 0);

$caricamento = Caricamento::findById($caricamentoId);
$pagina = PaginaNonAssociata::findById($paginaId);

if ($caricamento === null || $pagina === null) {
    http_response_code(404);
    exit('Risorsa non trovata.');
}

function estraiNettoPerRange(string $percorsoFileOriginale, int $paginaDa, int $paginaA): ?float
{
    $testoPerPagina = PdfExtractor::estraiTestoPerPagina($percorsoFileOriginale);
    $netto = null;
    for ($p = $paginaDa; $p <= $paginaA; $p++) {
        if (!isset($testoPerPagina[$p])) {
            continue;
        }
        $trovato = PdfExtractor::estraiNettoInBusta($testoPerPagina[$p]);
        if ($trovato !== null) {
            $netto = $trovato;
        }
    }
    return $netto;
}

function estraiEAssocia(array $caricamento, array $pagina, int $utenteId): int
{
    $nomeFile = sprintf('doc_%d_%d_%s.pdf', $caricamento['id'], $utenteId, uniqid());
    $cartellaStorageDocumenti = __DIR__ . '/../storage/documenti';
    $percorsoDestinazione = $cartellaStorageDocumenti . '/' . $nomeFile;

    PdfSplitter::estraiPagine(
        $caricamento['percorso_file_originale'],
        (int) $pagina['pagina_da'],
        (int) $pagina['pagina_a'],
        $percorsoDestinazione
    );

    $netto = estraiNettoPerRange(
        $caricamento['percorso_file_originale'],
        (int) $pagina['pagina_da'],
        (int) $pagina['pagina_a']
    );

    return Documento::create([
        'caricamento_id' => $caricamento['id'],
        'utente_id' => $utenteId,
        'tipo_documento' => $caricamento['tipo_documento'],
        'etichetta' => $caricamento['etichetta'],
        'mese' => $caricamento['mese'],
        'anno' => $caricamento['anno'],
        'percorso_file' => $percorsoDestinazione,
        'pagina_da' => (int) $pagina['pagina_da'],
        'pagina_a' => (int) $pagina['pagina_a'],
        'netto_in_busta' => $netto,
        'stato' => 'associato',
    ]);
}

switch ($azione) {
    case 'assegna':
        $utenteId = (int) ($_POST['utente_id'] ?? 0);
        $dipendenteAssegnato = Utente::findById($utenteId);

        if ($dipendenteAssegnato === null || !$dipendenteAssegnato['attivo']) {
            // Il dipendente selezionato non esiste piu' o e' stato disattivato
            // nel frattempo: non estrarre nulla, la pagina resta "in_attesa".
            redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId . '&errore=assegna_dipendente_non_valido');
        }

        $conflittoAssegna = Documento::esisteAssociato(
            $utenteId,
            $caricamento['tipo_documento'],
            $caricamento['etichetta'],
            $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
            (int) $caricamento['anno']
        );

        if ($conflittoAssegna !== null) {
            // Il dipendente selezionato ha gia' un documento per lo stesso
            // periodo: non estrarre il PDF e non marcare la pagina come
            // risolta, resta "in_attesa" cosi' l'admin puo' rivalutarla
            // (es. usando "sovrascrivi" dalla coda conflitti).
            redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId . '&errore=assegna_conflitto');
        }

        estraiEAssocia($caricamento, $pagina, $utenteId);
        PaginaNonAssociata::risolvi($paginaId, (int) $admin['id']);
        break;

    case 'scarta_pagina':
        PaginaNonAssociata::scarta($paginaId, (int) $admin['id']);
        break;

    case 'sovrascrivi':
        $utenteMatch = Utente::findByCodiceFiscale((string) $pagina['cf_estratto']);
        $esistente = $utenteMatch !== null ? Documento::esisteAssociato(
            (int) $utenteMatch['id'],
            $caricamento['tipo_documento'],
            $caricamento['etichetta'],
            $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
            (int) $caricamento['anno']
        ) : null;

        if ($utenteMatch === null || $esistente === null) {
            // Il conflitto non e' piu' valido al momento della POST (es. un altro
            // admin ha gia' risolto il documento esistente, o doppia sottomissione).
            // Non estrarre il PDF e non marcare la pagina come risolta: resta
            // "in_attesa" cosi' l'admin puo' rivalutarla.
            redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId . '&errore=sovrascrivi_fallito');
        }

        $nomeFile = sprintf('doc_%d_%d_%s.pdf', $caricamento['id'], $utenteMatch['id'], uniqid());
        $percorsoDestinazione = __DIR__ . '/../storage/documenti/' . $nomeFile;
        PdfSplitter::estraiPagine(
            $caricamento['percorso_file_originale'],
            (int) $pagina['pagina_da'],
            (int) $pagina['pagina_a'],
            $percorsoDestinazione
        );
        $nettoSovrascrivi = estraiNettoPerRange(
            $caricamento['percorso_file_originale'],
            (int) $pagina['pagina_da'],
            (int) $pagina['pagina_a']
        );
        Documento::sovrascrivi($esistente['id'], [
            'caricamento_id' => $caricamento['id'],
            'utente_id' => $utenteMatch['id'],
            'tipo_documento' => $caricamento['tipo_documento'],
            'etichetta' => $caricamento['etichetta'],
            'mese' => $caricamento['mese'],
            'anno' => $caricamento['anno'],
            'percorso_file' => $percorsoDestinazione,
            'pagina_da' => (int) $pagina['pagina_da'],
            'pagina_a' => (int) $pagina['pagina_a'],
            'netto_in_busta' => $nettoSovrascrivi,
            'stato' => 'associato',
        ]);
        PaginaNonAssociata::risolvi($paginaId, (int) $admin['id']);
        break;

    case 'ignora':
        PaginaNonAssociata::scarta($paginaId, (int) $admin['id']);
        break;

    default:
        http_response_code(400);
        exit('Azione non riconosciuta.');
}

// Se non restano piu' pagine pendenti, il caricamento e' completato.
$paginePendentiRimaste = PaginaNonAssociata::perCaricamento($caricamentoId, 'in_attesa');
if (empty($paginePendentiRimaste)) {
    Caricamento::setStato($caricamentoId, 'completato');
}

redirect('/portale-dipendenti/admin/revisione-caricamento.php?caricamento_id=' . $caricamentoId);
