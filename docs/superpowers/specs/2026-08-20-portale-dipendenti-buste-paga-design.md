# Portale Dipendenti — Buste Paga e CU — Design

Data: 2026-08-20

## Obiettivo

Portale web per la gestione e distribuzione di buste paga e Certificazioni Uniche (CU) ai dipendenti. L'admin carica un PDF cumulativo mensile (contenente le buste paga di tutti i dipendenti); il sistema estrae automaticamente la singola busta paga di ciascun dipendente e la rende disponibile per il download tramite un'area riservata. L'area dipendente è fruibile comodamente da smartphone; l'area admin è pensata per l'uso da desktop.

## Stack tecnologico

- **Backend**: PHP puro (MVC leggero fatto in casa, no framework)
- **Frontend**: jQuery + DaisyUI (Tailwind)
  - Area dipendente: mobile-first, bottom navigation bar
  - Area admin: desktop-first, sidebar laterale (l'admin opera da desktop)
- **Database**: MySQL
- **Ambiente**: XAMPP locale (PHP + MySQL + Apache)
- **Librerie PHP (Composer)**:
  - `smalot/pdfparser` — estrazione testo dalle pagine del PDF
  - `setasign/fpdi` + `fpdf`/`tcpdf` — split del PDF cumulativo in singoli PDF per dipendente

Tutta l'estrazione (lettura testo, riconoscimento CF/netto, split del PDF) avviene interamente in locale, nel processo PHP sul server XAMPP: nessuna libreria o servizio esterno/cloud coinvolto, i file non lasciano mai il server.

## Fuori scope (in questa fase)

- OCR per PDF scansionati/immagine (si assume PDF testuale; da verificare sul primo file reale — se risultasse un'immagine, l'OCR sarà una fase successiva separata)
- Invio email (creazione utenze, notifiche, recupero password) — nessuna dipendenza SMTP in questa fase
- Estrazione automatica di dettagli economici oltre al Netto in busta (competenze, trattenute, arretrati, acconto restano visibili solo aprendo il PDF)
- Test automatici formali (PHPUnit) — si useranno checklist manuali e script di validazione standalone
- Funzionalità diverse da buste paga/CU (es. ferie, permessi, bacheca) — possibili estensioni future del portale, non di questo spec

## Modello dati (MySQL)

### `utenti`
| Campo | Tipo | Note |
|---|---|---|
| id | INT PK | |
| nome | VARCHAR | |
| cognome | VARCHAR | |
| email | VARCHAR, UNIQUE | usata per login |
| codice_fiscale | VARCHAR, UNIQUE | usato per matching PDF |
| password_hash | VARCHAR | bcrypt via `password_hash()` |
| ruolo | ENUM(`admin`,`dipendente`) | |
| deve_cambiare_password | BOOLEAN | forza cambio al primo login |
| attivo | BOOLEAN | soft-disable, mai cancellazione |
| creato_il | DATETIME | |

### `caricamenti`
| Campo | Tipo | Note |
|---|---|---|
| id | INT PK | |
| tipo_documento | ENUM(`busta_paga`,`cu`) | |
| etichetta | ENUM(`Cedolino`,`13ª mensilità`,`14ª mensilità`) NULLABLE | null per CU |
| mese | TINYINT NULLABLE | null per CU |
| anno | SMALLINT | |
| nome_file_originale | VARCHAR | |
| percorso_file_originale | VARCHAR | fuori webroot |
| caricato_da | INT FK → utenti.id | |
| caricato_il | DATETIME | |
| stato | ENUM(`elaborazione`,`completato`,`con_errori`) | |

Il PDF cumulativo originale (`percorso_file_originale`) viene sempre conservato dopo l'elaborazione, fuori dalla webroot, accessibile solo all'admin — funge da backup/audit e permette di ri-processare in caso di bug del parser scoperti in seguito, senza dover richiedere all'admin di ricaricare il file.

### `documenti`
| Campo | Tipo | Note |
|---|---|---|
| id | INT PK | |
| caricamento_id | INT FK → caricamenti.id | |
| utente_id | INT FK → utenti.id, NULLABLE | null finché non associato |
| tipo_documento | ENUM(`busta_paga`,`cu`) | denormalizzato per query semplici |
| etichetta | VARCHAR NULLABLE | |
| mese | TINYINT NULLABLE | |
| anno | SMALLINT | |
| percorso_file | VARCHAR | PDF estratto, fuori webroot |
| pagina_da | INT | riferimento a pagine nel PDF originale |
| pagina_a | INT | |
| netto_in_busta | DECIMAL(10,2) NULLABLE | usato per grafico e cifra dashboard |
| stato | ENUM(`associato`,`da_rivedere`,`scartato`) | |
| creato_il | DATETIME | |

Vincolo applicativo di unicità: non può esistere più di un documento con la stessa combinazione (`utente_id`, `tipo_documento`, `etichetta`, `mese`, `anno`) con stato `associato`. Un nuovo caricamento che collide con questa combinazione viene segnalato come conflitto in fase di revisione (l'admin sceglie se sovrascrivere o ignorare il blocco).

### `pagine_non_associate`
| Campo | Tipo | Note |
|---|---|---|
| id | INT PK | |
| caricamento_id | INT FK → caricamenti.id | |
| pagina_da | INT | |
| pagina_a | INT | |
| cf_estratto | VARCHAR NULLABLE | se un CF è stato letto ma non corrisponde a nessun utente |
| stato | ENUM(`in_attesa`,`risolta`,`scartata`) | |
| risolta_da | INT FK → utenti.id, NULLABLE | |
| risolta_il | DATETIME NULLABLE | |

## Flusso di estrazione

1. L'admin, in "Nuovo caricamento", sceglie: tipo documento (busta paga/CU); se busta paga, anche etichetta (Cedolino/13ª/14ª mensilità) e mese; sempre l'anno; carica il PDF cumulativo.
2. Il sistema crea un record `caricamenti` (stato `elaborazione`) e salva il PDF originale fuori dalla webroot.
3. Elaborazione sincrona (nella stessa richiesta HTTP, volume atteso basso — decine/centinaia di dipendenti):
   - Estrae il testo di ogni pagina con `Smalot/PdfParser`.
   - Scansiona le pagine in ordine, cerca il Codice Fiscale con un pattern regex (formato CF italiano standard).
   - Raggruppa pagine consecutive con lo stesso CF in blocchi (un blocco = potenziale busta paga di un dipendente, 1 o più pagine).
   - Per ogni blocco cerca anche il pattern testuale del "Netto in busta" (pattern configurabile — da tarare sul primo PDF reale; se non trovato, il campo resta `NULL` e il documento è comunque valido).
   - Per ogni blocco con CF riconosciuto e corrispondente a un utente esistente:
     - Se esiste già un documento `associato` con la stessa combinazione (utente, tipo, etichetta, mese, anno) → segnala come conflitto in fase di revisione.
     - Altrimenti: estrae le pagine del blocco in un nuovo PDF con FPDI, lo salva in `/storage/documenti/`, crea il record `documenti` con stato `associato`.
   - Per ogni blocco con CF non trovato o non corrispondente a nessun utente: crea un record in `pagine_non_associate` con stato `in_attesa`.
4. Al termine, `caricamenti.stato` passa a `completato` (nessuna pagina da rivedere/conflitto) o `con_errori` (presenti pagine da rivedere e/o conflitti).
5. L'admin viene indirizzato alla pagina di riepilogo del caricamento.

## Revisione admin (pagina di riepilogo caricamento)

Layout a split-view: tabelle a sinistra, area di preview PDF a destra (coerente con l'area admin desktop-first). Cliccando su una riga di una qualsiasi delle tabelle, il PDF corrispondente si apre nella preview a destra (iframe puntato all'endpoint sicuro di download — stesso controllo di sessione/autorizzazione descritto in "Sicurezza e accesso ai file"), senza ricaricare la pagina o perdere la posizione nella tabella. Questo permette all'admin di scorrere e verificare rapidamente molti documenti in sequenza dopo ogni caricamento.

- **Tabella "Documenti associati"**: Dipendente | Pagine | Netto estratto | Stato. Click su riga → preview del PDF estratto per quel dipendente.
- **Tabella "Da rivedere"**: Pagine | CF estratto (se presente) | Azioni. Click su riga → preview delle pagine in questione (estratte dal PDF originale al volo, o del blocco corrispondente) per aiutare l'admin a riconoscere visivamente il dipendente corretto.
  - *Assegna a dipendente* — ricerca/seleziona un dipendente esistente, crea il documento associato.
  - *Scarta* — la pagina non è una busta paga valida (es. copertina, riepilogo aziendale).
- **Conflitti** (stessa combinazione già esistente): click su riga → preview del nuovo documento in conflitto, per confronto. Azione *Sovrascrivi* (il nuovo documento sostituisce il precedente) o *Ignora questo blocco* (mantiene il documento esistente, scarta il nuovo).

## Autenticazione

- Login unica per admin e dipendenti: email + password.
- Redirect post-login in base al ruolo: `admin` → pannello admin, `dipendente` → home dashboard.
- Creazione dipendente (da admin): nome, cognome, CF, email. Il sistema genera una password temporanea casuale, mostrata all'admin per la comunicazione fuori banda (nessun invio email in questa fase). Il campo `deve_cambiare_password` è impostato a vero.
- Al primo login il dipendente è forzato a impostare una nuova password prima di accedere al resto del portale.
- Se un dipendente perde la password, l'admin genera un reset dalla scheda utente (nessun flusso self-service "password dimenticata").
- Sessioni PHP native (`session_start()`); password hashate con `password_hash()` (bcrypt).

## Area dipendente

**Home (dashboard)**:
- Riepilogo dell'ultimo documento busta paga: grafico andamento del netto (mesi con dati disponibili), cifra "Netto in busta" in evidenza, etichetta e periodo di riferimento, pulsante download PDF.
- Navigazione a carosello (swipe orizzontale) tra i documenti busta paga in ordine cronologico. Quando più documenti condividono lo stesso mese/anno (es. Cedolino + 13ª mensilità), la label mostra "Dicembre 2024, 1 di 2" / "2 di 2"; i puntini indicatori rappresentano i singoli documenti, non i mesi solari. Il grafico resta ancorato ai netti mensili e non si ridisegna passando da 1/2 a 2/2 dello stesso mese.
- Sezione separata "CU": lista delle CU disponibili per anno (un solo documento per anno per dipendente), ciascuna scaricabile.

**Bottom navigation bar (mobile)**: Home | Documenti | Menu
- **Documenti**: archivio completo, filtrabile per anno, di tutte le buste paga e CU del dipendente.
- **Menu**: profilo (dati anagrafici in sola lettura), cambio password, logout.

## Area admin

Desktop-first: sidebar laterale fissa (Dashboard, Nuovo caricamento, Caricamenti, Dipendenti) + header in alto con utente/logout. L'admin lavora da desktop, non da mobile.

- **Dashboard admin**: elenco caricamenti recenti con stato, accesso rapido a "Nuovo caricamento".
- **Nuovo caricamento**: wizard a 3 step:
  1. **Tipo documento + periodo + upload** — tipo documento (busta paga/CU), se busta paga anche etichetta (Cedolino/13ª/14ª mensilità) e mese, anno, selezione file PDF.
  2. **Elaborazione** — stato di avanzamento dell'estrazione (sincrona lato server, questo step copre l'attesa della risposta).
  3. **Revisione risultati** — tabella documenti associati, tabella pagine da rivedere con azioni (assegna/scarta), eventuali conflitti con azioni (sovrascrivi/ignora), come descritto in "Revisione admin".
- **Caricamenti**: storico di tutti i caricamenti effettuati, con possibilità di riaprire il riepilogo/revisione di un caricamento passato e di scaricare il PDF cumulativo originale conservato.
- **Gestione dipendenti**: lista (nome, email, CF, stato attivo/disattivo); creazione; modifica dati; reset password; disattivazione soft (lo storico documenti resta collegato).
- Accesso in sola lettura, dalla scheda dipendente, ai documenti di ogni dipendente (supporto/verifica).

## Sicurezza e accesso ai file

- Tutti i PDF (originali e quelli estratti per singolo dipendente) risiedono fuori dalla webroot pubblica, mai raggiungibili da URL diretto.
- Endpoint dedicato (es. `scarica-documento.php?id=X`) che:
  1. Verifica la sessione attiva.
  2. Verifica che il documento richiesto appartenga all'utente in sessione, oppure che l'utente sia admin.
  3. Se autorizzato, invia il file (`Content-Disposition: inline` per la preview in iframe, `attachment` per il download esplicito).
- Endpoint dedicato solo-admin per la preview delle pagine "da rivedere" (es. `anteprima-pagine.php?caricamento_id=X&pagina_da=Y&pagina_a=Z`): verifica sessione + ruolo admin, estrae al volo le pagine richieste dal PDF cumulativo originale conservato (con FPDI) e le invia inline, senza salvarle su disco.

## Testing

- Test manuali guidati da checklist per i flussi critici (upload → estrazione → revisione → download) prima di ogni rilascio.
- Script PHP standalone per validare la logica di estrazione/raggruppamento CF su PDF di esempio, utili soprattutto nella fase iniziale di taratura dei pattern regex (CF, Netto in busta) sul primo PDF reale.
- Nessun framework di test automatico (PHPUnit) in questa fase iniziale, per restare aderenti allo stack semplice richiesto; valutabile in futuro se il progetto cresce.

## Punti da verificare con il primo PDF reale

Questi punti sono stati esplicitamente rimandati alla disponibilità di un PDF cumulativo reale, e vanno risolti prima/durante l'implementazione dell'estrazione:

1. Se il PDF è testuale (testo selezionabile) o una scansione/immagine — determina se serve OCR (fuori scope se necessario, da valutare a parte).
2. Il pattern esatto (dicitura + posizione) con cui il Codice Fiscale compare in ogni pagina.
3. Il pattern esatto (dicitura + posizione) con cui il "Netto in busta" compare nel testo.
