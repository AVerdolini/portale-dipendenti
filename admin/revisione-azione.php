<?php
// admin/revisione-azione.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Caricamento.php';
require_once __DIR__ . '/../src/Documento.php';
require_once __DIR__ . '/../src/PaginaNonAssociata.php';
require_once __DIR__ . '/../src/PdfSplitter.php';
require_once __DIR__ . '/../src/Utente.php';

$admin = require_admin();

$azione = $_POST['azione'] ?? '';
$caricamentoId = (int) ($_POST['caricamento_id'] ?? 0);
$paginaId = (int) ($_POST['pagina_id'] ?? 0);

$caricamento = Caricamento::findById($caricamentoId);
$pagina = PaginaNonAssociata::findById($paginaId);

if ($caricamento === null || $pagina === null) {
    http_response_code(404);
    exit('Risorsa non trovata.');
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
        'netto_in_busta' => null,
        'stato' => 'associato',
    ]);
}

switch ($azione) {
    case 'assegna':
        $utenteId = (int) ($_POST['utente_id'] ?? 0);
        estraiEAssocia($caricamento, $pagina, $utenteId);
        PaginaNonAssociata::risolvi($paginaId, (int) $admin['id']);
        break;

    case 'scarta_pagina':
        PaginaNonAssociata::scarta($paginaId, (int) $admin['id']);
        break;

    case 'sovrascrivi':
        $utenteMatch = Utente::findByCodiceFiscale((string) $pagina['cf_estratto']);
        if ($utenteMatch !== null) {
            $esistente = Documento::esisteAssociato(
                (int) $utenteMatch['id'],
                $caricamento['tipo_documento'],
                $caricamento['etichetta'],
                $caricamento['mese'] !== null ? (int) $caricamento['mese'] : null,
                (int) $caricamento['anno']
            );
            $nomeFile = sprintf('doc_%d_%d_%s.pdf', $caricamento['id'], $utenteMatch['id'], uniqid());
            $percorsoDestinazione = __DIR__ . '/../storage/documenti/' . $nomeFile;
            PdfSplitter::estraiPagine(
                $caricamento['percorso_file_originale'],
                (int) $pagina['pagina_da'],
                (int) $pagina['pagina_a'],
                $percorsoDestinazione
            );
            if ($esistente !== null) {
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
                    'netto_in_busta' => null,
                    'stato' => 'associato',
                ]);
            }
        }
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
