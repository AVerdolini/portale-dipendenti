<?php
// src/PdfExtractor.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/OcrExtractor.php';

use Smalot\PdfParser\Parser;

class PdfExtractor
{
    // Standard Italian Codice Fiscale format: 6 letters, 2 digits, 1 letter,
    // 2 digits, 1 letter, 3 digits, 1 letter.
    private const PATTERN_CF = '/\b([A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z])\b/';

    // Verificato su CU reali (Certificazione Unica): la prima pagina anagrafica
    // riporta DUE codici fiscali — quello del datore di lavoro/sostituto
    // d'imposta (se persona fisica, es. titolare di ditta individuale) PRIMA,
    // quello del dipendente/percipiente DOPO. Cercare "il primo CF nel testo"
    // (comportamento di base, corretto per i cedolini busta paga dove di norma
    // c'e' solo il CF del dipendente) prende quindi il CF sbagliato su questo
    // documento. La label "DATI RELATIVI AL DIPENDENTE," (nel blocco anagrafico,
    // sempre seguita da virgola: "AL DIPENDENTE,\nPENSIONATO O\nALTRO...")
    // precede sempre il CF del dipendente in questo caso. Deve includere la
    // virgola: "AL DIPENDENTE" da solo (senza virgola) compare anche altrove nel
    // documento con tutt'altro significato (es. "SPESA RIMBORSATA RIFERITA AL
    // DIPENDENTE" nella sezione rimborsi, verificato su CU reale) e prenderebbe
    // un CF sbagliato in quel contesto. Sulle pagine successive del CU la label
    // non compare (li' la testata e' gia' "Codice fiscale del percipiente [CF]"
    // con un solo CF rilevante per primo), quindi la cerchiamo solo come
    // raffinamento e non sostituiamo il comportamento di base quando e' assente.
    private const ETICHETTA_DIPENDENTE_CU = 'AL DIPENDENTE,';

    // Retuned against a real cumulative PDF (Zucchetti cedolino layout) — see
    // "Punti da verificare con il primo PDF reale" in the design spec.
    // In this layout the "NETTO DEL MESE" label and its value are printed in
    // separate columns and end up far apart in the linearized page text, so
    // matching the label directly doesn't work. The value itself is reliably
    // recognizable instead: it's the one euro amount on the page printed
    // alone on its own line as "1.901,00€" (amount immediately followed by
    // the € sign, no other text on that line), just above the IBAN line.
    private const PATTERN_NETTO_ZUCCHETTI = '/^[\t ]*([\d.]+,\d{2})€[\t ]*$/mu';

    // Layout TeamSystem: verificato su due cedolini reali (uno con testo nativo,
    // uno da scansione via OCR). In questo tracciato "NETTO BUSTA" e' l'etichetta
    // di colonna di un blocco tabellare i cui valori numerici stanno su una riga
    // separata (nel testo linearizzato da pdftotext -layout, dopo l'etichetta ma
    // prima della prossima riga di sole etichette). Non c'e' simbolo di valuta.
    // A differenza di Zucchetti, l'etichetta precede sempre il valore nel testo
    // (mai il contrario), ma la distanza in caratteri/righe varia da tracciato a
    // tracciato dello studio paghe: cerchiamo quindi il primo importo dopo
    // l'etichetta entro una finestra ragionevole, non su una riga fissa.
    private const ETICHETTA_NETTO_TEAMSYSTEM = 'NETTO BUSTA';
    private const FINESTRA_NETTO_TEAMSYSTEM = 800; // caratteri dopo l'etichetta
    private const PATTERN_IMPORTO = '/([\d.]+,\d{2})/';
    // Separatore inserito tra la sezione di testo con layout preservato
    // (pdftotext -layout, quando disponibile) e quella Smalot (sempre presente)
    // dentro estraiTestoPerPagina. Serve a estraiNettoTeamSystem per non
    // "sconfinare" mai nella sezione Smalot: quel testo non ha le colonne
    // allineate su cui l'euristica si basa e, se la ricerca vi opera per
    // errore, produce un netto SBAGLIATO invece di non trovarne uno (bug reale
    // riscontrato in produzione quando pdftotext non era raggiungibile dal
    // processo PHP e la sezione layout restava vuota: la ricerca continuava
    // silenziosamente nella sezione Smalot sottostante e prendeva un importo a
    // caso). Una stringa improbabile nei cedolini reali, cosi' non rischia mai
    // di comparire per caso nel testo e alterare la ricerca di CF/altri pattern.
    private const SEPARATORE_SEZIONI = "\n\x00---FINE-SEZIONE-LAYOUT---\x00\n";
    // Stessa forma di PATTERN_IMPORTO ma senza gruppo di cattura: serve solo per
    // verificare che un'intera riga sia composta esclusivamente da importi
    // (eventualmente ripetuti), dove un gruppo catturante ripetuto con "+"
    // manterrebbe solo l'ultima occorrenza invece di validare l'intera riga.
    private const PATTERN_IMPORTO_SENZA_GRUPPO = '(?:[\d.]+,\d{2})';

    /**
     * @return array<int,string> testo per numero di pagina (1-based)
     */
    public static function estraiTestoPerPagina(string $percorsoPdf): array
    {
        $testoSmalot = self::estraiConSmalot($percorsoPdf);
        $testoLayout = self::estraiConPdftotextLayout($percorsoPdf);

        $numPagine = max(count($testoSmalot), count($testoLayout));

        $risultato = [];
        for ($numeroPagina = 1; $numeroPagina <= $numPagine; $numeroPagina++) {
            $daSmalot = $testoSmalot[$numeroPagina] ?? '';
            $daLayout = $testoLayout[$numeroPagina] ?? '';

            if (trim($daSmalot) !== '' || trim($daLayout) !== '') {
                // Pagina con testo nativo (indipendentemente dalla fonte): niente
                // OCR, e' un fallback costoso da riservare alle scansioni pure.
                // Concateniamo entrambe le versioni, separate da un marcatore
                // univoco (vedi SEPARATORE_SEZIONI), cosi' i pattern di entrambi
                // i formati (Zucchetti su Smalot, TeamSystem su layout allineato)
                // possono essere provati sullo stesso blob di testo SENZA che la
                // ricerca del netto TeamSystem possa mai sconfinare nella sezione
                // Smalot (dove darebbe un risultato sbagliato, non solo "nessun
                // risultato" — vedi commento su SEPARATORE_SEZIONI). Il layout va
                // PRIMA cosi' resta comunque la sezione preferita quando presente.
                $risultato[$numeroPagina] = $daLayout . self::SEPARATORE_SEZIONI . $daSmalot;
                continue;
            }

            // Nessuna delle due estrazioni testuali ha trovato nulla: probabile
            // pagina scansionata/fotografata senza layer di testo. Fallback OCR.
            $risultato[$numeroPagina] = OcrExtractor::estraiTestoPagina($percorsoPdf, $numeroPagina);
        }

        return $risultato;
    }

    private static function estraiConSmalot(string $percorsoPdf): array
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

    /**
     * pdftotext -layout (Poppler) preserva l'allineamento a colonne del testo
     * cosi' come appare visivamente nella pagina, a differenza di Smalot che
     * linearizza nell'ordine del content stream del PDF (spesso "tutte le
     * etichette, poi tutti i valori" per PDF generati da tracciati tabellari
     * come TeamSystem). Serve percio' per l'euristica sul netto TeamSystem;
     * per Zucchetti il pattern esistente su Smalot resta invariato e preferito.
     *
     * Ritorna array vuoto se il binario non e' disponibile o il PDF non ha
     * testo estraibile: il chiamante tratta questo come "nessun testo da
     * questa fonte", non come errore.
     */
    private static function estraiConPdftotextLayout(string $percorsoPdf): array
    {
        $comando = [self::comandoPdftotext(), '-layout', $percorsoPdf, '-'];
        $comandoEscaped = implode(' ', array_map('escapeshellarg', $comando));

        $descrittori = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $processo = @proc_open($comandoEscaped, $descrittori, $pipe);
        if (!\is_resource($processo)) {
            return [];
        }

        fclose($pipe[0]);
        $output = stream_get_contents($pipe[1]);
        stream_get_contents($pipe[2]);
        fclose($pipe[1]);
        fclose($pipe[2]);
        $codiceUscita = proc_close($processo);

        if ($codiceUscita !== 0 || $output === false || $output === '') {
            return [];
        }

        // pdftotext separa le pagine con un form-feed (\x0c); l'ultimo elemento
        // dopo lo split e' una stringa vuota successiva al form-feed finale.
        $pagine = explode("\x0c", $output);
        if (end($pagine) === '') {
            array_pop($pagine);
        }

        $risultato = [];
        foreach ($pagine as $indice => $testo) {
            $risultato[$indice + 1] = $testo;
        }
        return $risultato;
    }

    /**
     * Percorso del binario pdftotext. Su Ubuntu/Docker il default (null, che
     * risolve al comando semplice via PATH) basta perche' poppler-utils
     * installato via apt finisce gia' nel PATH di qualunque processo. Su
     * Windows in sviluppo locale il PATH del processo Apache si e' dimostrato
     * inaffidabile, quindi il percorso assoluto va impostato in
     * config/pdf-tools.php — stesso meccanismo di OcrExtractor per
     * pdftoppm/tesseract, vedi il commento in cima a quella classe.
     */
    private static function comandoPdftotext(): string
    {
        static $config = null;
        if ($config === null) {
            $percorsoConfig = __DIR__ . '/../config/pdf-tools.php';
            $config = is_file($percorsoConfig) ? require $percorsoConfig : [];
        }
        return $config['pdftotext'] ?? 'pdftotext';
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

        $posizioneEtichettaDipendente = mb_stripos($testoNormalizzato, self::ETICHETTA_DIPENDENTE_CU);
        if ($posizioneEtichettaDipendente !== false) {
            $testoDaEtichetta = mb_substr($testoNormalizzato, $posizioneEtichettaDipendente);
            if (preg_match(self::PATTERN_CF, $testoDaEtichetta, $match)) {
                return $match[1];
            }
            // Etichetta trovata ma nessun CF dopo (raro, non dovrebbe succedere
            // sui CU reali visti finora): ripiega sul comportamento di base
            // sotto, invece di ritornare null e perdere un CF che magari
            // precede l'etichetta per qualche variante di layout non ancora
            // vista.
        }

        if (preg_match(self::PATTERN_CF, $testoNormalizzato, $match)) {
            return $match[1];
        }
        return null;
    }

    public static function estraiNettoInBusta(string $testoPagina): ?float
    {
        if (preg_match(self::PATTERN_NETTO_ZUCCHETTI, $testoPagina, $match)) {
            return self::normalizzaImporto($match[1]);
        }

        // L'euristica TeamSystem richiede il testo con layout a colonne
        // preservato (pdftotext -layout): opera SOLO sulla sezione prima del
        // separatore, mai su quella Smalot che segue (vedi SEPARATORE_SEZIONI).
        // Se il separatore non c'e' (testo non passato da estraiTestoPerPagina,
        // es. nei test) l'intero testo e' trattato come sezione layout.
        $sezioneLayout = strstr($testoPagina, self::SEPARATORE_SEZIONI, true);
        if ($sezioneLayout === false) {
            $sezioneLayout = $testoPagina;
        }

        $nettoTeamSystem = self::estraiNettoTeamSystem($sezioneLayout);
        if ($nettoTeamSystem !== null) {
            return $nettoTeamSystem;
        }

        return null;
    }

    /**
     * Layout TeamSystem (verificato su cedolini reali, sia testo nativo che
     * OCR): "NETTO BUSTA" e' l'ultima di una riga di etichette (es. "IRPEF
     * ERARIO / ADDIZIONALE REGIONALE / ADDIZIONALE COMUNALE / ARROTONDAMENTO /
     * NETTO BUSTA" — 5 colonne). I valori corrispondenti arrivano dopo, come
     * sequenza di importi: nel testo con layout preservato (pdftotext -layout)
     * sono tutti sulla stessa riga fisica (5 numeri su una riga); nel testo OCR
     * (sparse text, ogni frammento riconosciuto va su una riga propria) la
     * stessa sequenza di importi appare invece su righe separate consecutive.
     * In entrambi i casi il netto e' l'ULTIMO importo della sequenza, prima che
     * ricominci testo non numerico (la prossima etichetta). Per questo non ci
     * fermiamo alla prima riga con un importo: continuiamo ad accumulare
     * finche' le righe (saltando quelle vuote) contengono solo importi, e
     * prendiamo l'ultimo trovato.
     */
    private static function estraiNettoTeamSystem(string $testoPagina): ?float
    {
        $posizioneEtichetta = mb_stripos($testoPagina, self::ETICHETTA_NETTO_TEAMSYSTEM);
        if ($posizioneEtichetta === false) {
            return null;
        }

        $inizioFinestra = $posizioneEtichetta + mb_strlen(self::ETICHETTA_NETTO_TEAMSYSTEM);
        $finestra = mb_substr($testoPagina, $inizioFinestra, self::FINESTRA_NETTO_TEAMSYSTEM);

        $ultimoImportoTrovato = null;
        $sequenzaIniziata = false;

        foreach (preg_split('/\R/u', $finestra) as $riga) {
            $rigaSenzaSpazi = trim($riga);

            if ($rigaSenzaSpazi === '') {
                // Riga vuota: separatore tipico tra blocchi OCR, non interrompe
                // una sequenza di importi gia' iniziata.
                continue;
            }

            $eRigaDiSoliImporti = preg_match(
                '/^(?:' . self::PATTERN_IMPORTO_SENZA_GRUPPO . '[\t ]*)+$/u',
                $rigaSenzaSpazi
            ) === 1;

            if ($eRigaDiSoliImporti) {
                preg_match_all(self::PATTERN_IMPORTO, $rigaSenzaSpazi, $matches);
                $ultimoImportoTrovato = end($matches[1]);
                $sequenzaIniziata = true;
                continue;
            }

            if ($sequenzaIniziata) {
                // La sequenza di importi e' finita (rigata di testo/etichette):
                // il netto e' l'ultimo importo visto.
                break;
            }
            // Ancora nessun importo incontrato: probabile riga di etichette
            // intermedia (es. "ARROTONDAMENTO ... ATTUALE" su riga propria),
            // continuiamo a scendere.
        }

        return $ultimoImportoTrovato !== null ? self::normalizzaImporto($ultimoImportoTrovato) : null;
    }

    private static function normalizzaImporto(string $importo): float
    {
        // Italian number format: thousands "." decimal ",".
        $numeroNormalizzato = str_replace('.', '', $importo);
        $numeroNormalizzato = str_replace(',', '.', $numeroNormalizzato);
        return (float) $numeroNormalizzato;
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

            // Una pagina senza CF riconoscibile ma CON contenuto (es. pagina di
            // servizio/allegato dentro un documento multi-pagina come un CU: ha
            // testo ma non ripete il CF in un punto che il pattern riconosce)
            // continua il blocco corrente invece di interromperlo — altrimenti
            // un CU cosi' fatto si spezza in piu' blocchi e le pagine successive,
            // che tornano ad avere lo stesso CF di prima, risultano in falso
            // conflitto con il blocco gia' associato. Una pagina VUOTA invece
            // (nessun testo, tipica pagina di separazione stampata tra due
            // cedolini diversi in un cumulativo) resta un blocco a se' come gia'
            // oggi: e' il segnale che serve per non fondere per errore due
            // dipendenti diversi separati da un foglio bianco.
            $continuaBloccoCorrente = $cf === $cfCorrente
                || ($cf === null && !self::paginaVuota($testo));

            if ($paginaInizio === null) {
                // Prima pagina in assoluto.
                $cfCorrente = $cf;
                $paginaInizio = $numeroPagina;
                $nettoCorrente = $netto;
            } elseif ($continuaBloccoCorrente) {
                if ($netto !== null) {
                    $nettoCorrente = $netto;
                }
            } else {
                // CF cambiato verso un altro CF valido, oppure pagina vuota di
                // separazione: chiudi il blocco corrente, aprine uno nuovo.
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
