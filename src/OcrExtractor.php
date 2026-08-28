<?php
// src/OcrExtractor.php

/**
 * Fallback OCR per i PDF cumulativi che non contengono testo estraibile
 * (pagine scansionate/fotografate, senza layer di testo nel PDF). Smalot\PdfParser
 * su questi PDF ritorna stringa vuota per ogni pagina: PdfExtractor invoca questa
 * classe solo in quel caso, mai come primo tentativo (l'estrazione da testo nativo
 * e' sempre piu' affidabile e va preferita quando disponibile).
 *
 * Dipende da due binari esterni, non da estensioni PHP (ne' Imagick ne' GD sono
 * disponibili in questo ambiente):
 *  - pdftoppm (Poppler)     per rasterizzare la pagina PDF in PNG
 *  - tesseract (Tesseract OCR, lingua "ita") per il riconoscimento testo
 *
 * Su Windows vanno installati manualmente (es. via winget: oschwartz10612.Poppler
 * e UB-Mannheim.TesseractOCR). Il PATH di sistema si e' dimostrato inaffidabile
 * per il processo Apache/PHP su Windows (eredita il PATH della sessione in cui
 * xampp-control.exe e' partito, non si aggiorna in modo prevedibile nemmeno
 * riavviandolo): i percorsi vanno quindi impostati esplicitamente in
 * config/pdf-tools.php per lo sviluppo locale. Su Ubuntu/Debian (produzione,
 * Docker) i pacchetti di sistema bastano e vanno nel PATH di default di
 * qualunque processo, quindi config/pdf-tools.php puo' restare con valori null:
 *   sudo apt-get install poppler-utils tesseract-ocr tesseract-ocr-ita
 *
 * Il language pack italiano non e' incluso nell'installer Windows di UB-Mannheim:
 * va scaricato a parte (tessdata/ita.traineddata) e va indicata la cartella che lo
 * contiene, che qui e' tools/tessdata/ nel repo cosi' non serve toccare i file di
 * sistema in Program Files (dove l'utente non ha permessi di scrittura).
 */
class OcrExtractor
{
    private const RISOLUZIONE_DPI = 300;

    // Duplica PdfExtractor::PATTERN_CF (stesso formato standard del Codice
    // Fiscale italiano): serve qui solo per decidere se tentare il fallback
    // PSM di default in riconosciTesto(), non per l'estrazione vera e propria
    // (che resta responsabilita' di PdfExtractor::raggruppaPerCf sul testo
    // completo restituito). Duplicazione preferita a un accoppiamento fra le
    // due classi solo per una regex.
    private const PATTERN_CF = '/\b([A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z])\b/';

    /**
     * @return array{pdftotext:?string,pdftoppm:?string,tesseract:?string,tessdata_path:?string}
     */
    private static function config(): array
    {
        static $config = null;
        if ($config === null) {
            $percorsoConfig = __DIR__ . '/../config/pdf-tools.php';
            $config = is_file($percorsoConfig) ? require $percorsoConfig : [];
        }
        return $config;
    }

    public static function disponibile(): bool
    {
        return self::eseguibile(self::comandoPdftoppm()) && self::eseguibile(self::comandoTesseract());
    }

    /**
     * Estrae il testo di una pagina PDF via OCR. Ritorna stringa vuota se i
     * binari OCR non sono disponibili o se la rasterizzazione/riconoscimento
     * falliscono: il chiamante (PdfExtractor) tratta questo esattamente come
     * una pagina senza testo, quindi finisce nella coda di revisione manuale
     * invece di interrompere l'elaborazione dell'intero caricamento.
     */
    public static function estraiTestoPagina(string $percorsoPdf, int $numeroPagina): string
    {
        if (!self::disponibile()) {
            return '';
        }

        $cartellaTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'portale_ocr_' . bin2hex(random_bytes(8));
        if (!mkdir($cartellaTemp, 0755, true) && !is_dir($cartellaTemp)) {
            return '';
        }

        try {
            $prefissoImmagine = $cartellaTemp . DIRECTORY_SEPARATOR . 'pagina';
            $rasterizzata = self::rasterizzaPagina($percorsoPdf, $numeroPagina, $prefissoImmagine);
            if ($rasterizzata === null) {
                return '';
            }

            $immagineDaLeggere = self::correggiOrientamento($rasterizzata, $cartellaTemp) ?? $rasterizzata;

            return self::riconosciTesto($immagineDaLeggere);
        } finally {
            self::rimuoviCartella($cartellaTemp);
        }
    }

    private static function rasterizzaPagina(string $percorsoPdf, int $numeroPagina, string $prefissoOutput): ?string
    {
        $comando = [
            self::comandoPdftoppm(),
            '-r', (string) self::RISOLUZIONE_DPI,
            '-f', (string) $numeroPagina,
            '-l', (string) $numeroPagina,
            '-png',
            $percorsoPdf,
            $prefissoOutput,
        ];

        self::esegui($comando);

        // pdftoppm suffissa il numero pagina; con un solo foglio richiesto il
        // nome esatto puo' variare in base al numero di cifre totali del
        // documento (es. "-1" oppure "-01"), quindi cerchiamo il file prodotto
        // invece di assumere un nome fisso.
        $candidati = glob($prefissoOutput . '*.png');
        return $candidati[0] ?? null;
    }

    /**
     * I PDF scansionati con lo scanner fisico o fotografati col telefono a volte
     * arrivano ruotati di 90/180/270 gradi. Tesseract sa rilevare l'orientamento
     * (OSD, Orientation and Script Detection) ma con bassa affidabilita' su
     * immagini rumorose: applichiamo la rotazione solo quando la confidenza e'
     * ragionevole, altrimenti lasciamo l'immagine com'e' e ci affidiamo al modo
     * di riconoscimento testo (che tollera meglio piccoli errori di rotazione
     * ma non un capovolgimento completo).
     */
    private static function correggiOrientamento(string $percorsoImmagine, string $cartellaTemp): ?string
    {
        $osd = self::esegui([
            self::comandoTesseract(),
            $percorsoImmagine,
            '-',
            '--psm', '0',
            '-l', 'osd',
        ], ['TESSDATA_PREFIX' => self::tessdataPath()]);

        if ($osd === null || !preg_match('/Rotate:\s*(\d+)/', $osd, $match)) {
            return null;
        }

        $gradiRotazione = (int) $match[1];
        if ($gradiRotazione === 0) {
            return null;
        }

        if (!preg_match('/Orientation confidence:\s*([\d.]+)/', $osd, $matchConfidenza)) {
            return null;
        }
        // Soglia bassa: il rilevamento OSD di Tesseract su documenti densi di
        // tabelle (come i cedolini) e' spesso poco sicuro anche quando la
        // rotazione indicata e' corretta (verificato empiricamente su PDF
        // reali: confidenza 9 su rotazione di 180 gradi corretta).
        if ((float) $matchConfidenza[1] < 3.0) {
            return null;
        }

        $percorsoRuotata = $cartellaTemp . DIRECTORY_SEPARATOR . 'ruotata.png';
        if (!self::ruotaPng($percorsoImmagine, $percorsoRuotata, $gradiRotazione)) {
            return null;
        }

        return $percorsoRuotata;
    }

    /**
     * Ruota un PNG senza dipendere da GD/Imagick (non disponibili in questo
     * ambiente): usa direttamente le funzioni immagine di GD se presenti, o in
     * assenza (come qui) tesseract stesso via il suo tool ausiliario non e'
     * un'opzione, quindi ci si affida a pdftoppm con l'opzione di rotazione
     * gia' in fase di rasterizzazione. Vedi nota nel chiamante: se GD manca,
     * la rotazione viene saltata e riconosciTesto() lavora sull'immagine
     * originale.
     */
    private static function ruotaPng(string $sorgente, string $destinazione, int $gradi): bool
    {
        if (!\function_exists('imagecreatefrompng')) {
            return false;
        }

        $immagine = @imagecreatefrompng($sorgente);
        if ($immagine === false) {
            return false;
        }

        // GD ruota in senso antiorario; Tesseract riporta i gradi di rotazione
        // oraria necessari a raddrizzare l'immagine, quindi invertiamo il segno.
        // Le risorse GdImage non vanno liberate esplicitamente da PHP 8.0+ (il
        // garbage collector se ne occupa; imagedestroy() e' deprecata).
        $ruotata = imagerotate($immagine, -$gradi, 0);
        if ($ruotata === false) {
            return false;
        }

        return imagepng($ruotata, $destinazione);
    }

    // Marcatore usato per separare, nel testo OCR restituito, la sezione
    // --psm 6 (preferita per l'euristica del netto, vedi riconosciTesto())
    // da quella di fallback in PSM di default (vedi cercaCfConPsmDefault()).
    // Stesso scopo/pattern di PdfExtractor::SEPARATORE_SEZIONI: i pattern che
    // devono restare sulla sola sezione --psm 6 (netto TeamSystem) tagliano il
    // testo su questo marcatore, quello del CF invece cerca sull'intero blob.
    private const SEPARATORE_SEZIONI = "\n\x00---FINE-SEZIONE-OCR-PSM6---\x00\n";

    private static function riconosciTesto(string $percorsoImmagine): string
    {
        // --psm 6 (singolo blocco di testo uniforme): verificato su cedolini
        // reali dare un riconoscimento molto piu' pulito di --psm 12 (sparse
        // text) su questo layout tabellare fitto — con --psm 12 ogni frammento
        // di testo va su una riga propria e il tasso di errore di lettura sale
        // parecchio (es. "NETTO" letto "NEFFO"). --psm 12 resta utile SOLO per
        // il rilevamento dell'orientamento in correggiOrientamento(), non per
        // il riconoscimento testo vero e proprio: a quel punto l'immagine e'
        // gia' stata raddrizzata se necessario, quindi --psm 6 puo' assumere
        // un blocco di testo coerente.
        $testo = self::esegui([
            self::comandoTesseract(),
            $percorsoImmagine,
            '-',
            '-l', 'ita',
            '--psm', '6',
        ], ['TESSDATA_PREFIX' => self::tessdataPath()]) ?? '';

        // Il CF compare spesso in una riga isolata fuori dal blocco tabellare
        // principale (es. sopra l'intestazione della tabella competenze): il
        // presupposto di --psm 6 (un unico blocco di testo uniforme) puo'
        // "inghiottire" quella riga dentro il blocco tabellare e corromperla
        // nel riconoscimento. Verificato su cedolino reale: --psm 6 non trova
        // il CF che il PSM di default (segmentazione automatica multi-blocco,
        // piu' lento ma piu' fedele su pagine con zone eterogenee) riconosce
        // correttamente. Il secondo tentativo scatta SOLO se il primo non ha
        // gia' un CF valido, per non pagare il costo doppio sul caso comune.
        if (preg_match(self::PATTERN_CF, $testo) === 1) {
            return $testo;
        }

        $testoPsmDefault = self::esegui([
            self::comandoTesseract(),
            $percorsoImmagine,
            '-',
            '-l', 'ita',
        ], ['TESSDATA_PREFIX' => self::tessdataPath()]) ?? '';

        if ($testoPsmDefault === '') {
            return $testo;
        }

        return $testo . self::SEPARATORE_SEZIONI . $testoPsmDefault;
    }

    private static function comandoPdftoppm(): string
    {
        return self::config()['pdftoppm'] ?? 'pdftoppm';
    }

    private static function comandoTesseract(): string
    {
        return self::config()['tesseract'] ?? 'tesseract';
    }

    private static function tessdataPath(): ?string
    {
        // null (nessuna cartella esplicita in config/pdf-tools.php) lascia che
        // tesseract usi il proprio default di sistema — corretto su Docker/Linux,
        // dove tesseract-ocr-ita installa gia' ita.traineddata li'. Il fallback
        // a tools/tessdata/ del repo va SOLO se quel path e' stato esplicitamente
        // configurato (es. Windows, vedi config/pdf-tools.example.php), non come
        // default silenzioso: altrimenti su Docker si punta a una cartella vuota
        // (solo .gitkeep) invece che al path di sistema, e l'OCR fallisce senza
        // errore visibile (proc_open ritorna solo "riuscito o no").
        return self::config()['tessdata_path'] ?? null;
    }

    // pdftoppm (Poppler) non riconosce "--version" (lo interpreta come nome
    // file e fallisce con codice 1): l'opzione corretta e' "-v". tesseract
    // invece accetta solo "--version", non "-v". Nessuna opzione comune ai due
    // binari, quindi ciascuno va sondato con la propria.
    private static function eseguibile(string $comando): bool
    {
        $opzioneVersione = str_contains($comando, 'pdftoppm') ? '-v' : '--version';
        $risultato = self::esegui([$comando, $opzioneVersione]);
        return $risultato !== null;
    }

    /**
     * @param string[] $comando
     * @param array<string,string> $variabiliAmbiente
     */
    private static function esegui(array $comando, array $variabiliAmbiente = []): ?string
    {
        $comandoEscaped = implode(' ', array_map('escapeshellarg', $comando));

        $descrittori = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $ambiente = $variabiliAmbiente + (getenv() ?: []);
        $processo = @proc_open($comandoEscaped, $descrittori, $pipe, null, $ambiente);
        if (!\is_resource($processo)) {
            return null;
        }

        fclose($pipe[0]);
        $stdout = stream_get_contents($pipe[1]);
        $stderr = stream_get_contents($pipe[2]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        $codiceUscita = proc_close($processo);

        if ($codiceUscita !== 0) {
            // tesseract con "--psm 0" su un'immagine senza testo rilevabile
            // esce con codice diverso da zero: non e' un errore di sistema,
            // e' un esito legittimo (nessun testo/orientamento riconoscibile).
            return null;
        }

        // tesseract scrive l'esito OSD su stderr, il testo riconosciuto su stdout.
        return $stdout !== '' ? $stdout : ($stderr !== '' ? $stderr : '');
    }

    private static function rimuoviCartella(string $cartella): void
    {
        if (!is_dir($cartella)) {
            return;
        }
        foreach (glob($cartella . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($cartella);
    }
}
