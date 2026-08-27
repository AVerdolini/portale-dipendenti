<?php
// config/pdf-tools.example.php
//
// Percorsi dei binari esterni usati da PdfExtractor e OcrExtractor per
// l'estrazione testo/OCR dai cedolini TeamSystem e dai PDF scansionati
// (pdftotext, pdftoppm da Poppler; tesseract da Tesseract OCR).
//
// 'null' (default) significa "cerca nel PATH di sistema del processo che
// esegue PHP" — comportamento corretto su Docker/Ubuntu, dove i pacchetti
// apt (poppler-utils, tesseract-ocr) finiscono gia' in /usr/bin, sempre nel
// PATH di qualunque processo nell'immagine. Su quegli ambienti questo file
// va copiato in config/pdf-tools.php SENZA modifiche.
//
// Su Windows in sviluppo locale il PATH del processo Apache/XAMPP si e'
// dimostrato inaffidabile (eredita il PATH di quando xampp-control.exe e'
// stato avviato, non si aggiorna in modo prevedibile nemmeno riavviandolo):
// copia questo file in config/pdf-tools.php (ignorato da git, specifico della
// tua macchina) e sostituisci i valori null con i percorsi assoluti dei
// binari installati (es. via winget: oschwartz10612.Poppler e
// UB-Mannheim.TesseractOCR).
//
// Il language pack italiano non e' incluso nell'installer Windows di
// Tesseract: va scaricato a parte (tessdata/ita.traineddata, vedi il
// commento in cima a src/OcrExtractor.php) e messo in una cartella
// scrivibile — di norma tools/tessdata/ nel repo, gia' il default sotto.
return [
    'pdftotext' => null, // es. 'C:\\...\\poppler-25.07.0\\Library\\bin\\pdftotext.exe'
    'pdftoppm' => null, // es. 'C:\\...\\poppler-25.07.0\\Library\\bin\\pdftoppm.exe'
    'tesseract' => null, // es. 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe'
    'tessdata_path' => __DIR__ . '/../tools/tessdata',
];
