<?php
// admin/dipendenti-esporta.php
// Esporta l'anagrafica dipendenti in un file .xlsx, stesse colonne che poi
// admin/dipendenti-importa.php si aspetta in ingresso (round-trip export -> modifica -> import).
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_admin();

$dipendenti = Utente::all();

$spreadsheet = new Spreadsheet();
$foglio = $spreadsheet->getActiveSheet();
$foglio->setTitle('Dipendenti');

$intestazioni = ['Nome', 'Cognome', 'Email', 'Codice Fiscale', 'Stato'];
$foglio->fromArray($intestazioni, null, 'A1');
$foglio->getStyle('A1:E1')->getFont()->setBold(true);

$riga = 2;
foreach ($dipendenti as $d) {
    $foglio->fromArray([
        $d['nome'],
        $d['cognome'],
        $d['email'],
        $d['codice_fiscale'],
        $d['attivo'] ? 'Attivo' : 'Disattivato',
    ], null, 'A' . $riga);
    $riga++;
}

foreach (range('A', 'E') as $colonna) {
    $foglio->getColumnDimension($colonna)->setAutoSize(true);
}

$nomeFile = 'dipendenti_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nomeFile . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
