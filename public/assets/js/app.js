// public/assets/js/app.js
// Shared jQuery helpers — populated in later tasks (carosello, preview loader).

$(function () {
    // Le celle azioni dentro una .riga-preview usano onclick="event.
    // stopPropagation()" per evitare che un click su form/bottoni al loro
    // interno apra anche l'anteprima PDF sul <tr>. Quello stop pero' blocca
    // l'evento prima che risalga fino a document, dove sono agganciati gli
    // handler delegati sotto (es. .btn-modifica-netto) — quindi controlliamo
    // qui l'origine del click invece di affidarci allo stop nel markup.
    $(document).on('click', '.riga-preview', function (e) {
        if ($(e.target).closest('button, a, input, select, form').length > 0) {
            return;
        }
        var src = $(this).data('src');
        $('#preview-frame').attr('src', src);
    });

    // Toggle lettura/modifica del netto in busta in revisione-caricamento.php:
    // di default mostra solo il valore + matita, il click sulla matita rivela
    // il form (submit classico POST -> redirect, coerente con le altre azioni
    // di questa pagina), "Annulla" lo richiude senza inviare nulla.
    $(document).on('click', '.btn-modifica-netto', function () {
        var $riga = $(this).closest('.riga-netto');
        $riga.find('.valore-netto, .btn-modifica-netto').addClass('hidden');
        $riga.find('.form-modifica-netto').removeClass('hidden');
        $riga.find('input[name="netto"]').trigger('focus').trigger('select');
    });
    $(document).on('click', '.btn-annulla-netto', function () {
        var $riga = $(this).closest('.riga-netto');
        $riga.find('.form-modifica-netto').addClass('hidden');
        $riga.find('.valore-netto, .btn-modifica-netto').removeClass('hidden');
    });

    // Mostra/nasconde i campi specifici busta paga (etichetta, mese) in base
    // al tipo documento selezionato. Delegato su document cosi' funziona sia
    // nella pagina standalone che nel form dentro il modale "Nuovo caricamento".
    $(document).on('change', '.tipo-documento-select', function () {
        var $campiBustaPaga = $(this).closest('form').find('.campi-busta-paga');
        $campiBustaPaga.toggle($(this).val() === 'busta_paga');
    });

    // Il submit di "Nuovo caricamento" e' un classico POST -> redirect ->
    // redirect lato server (salva il file, poi elabora-caricamento.php
    // esegue l'estrazione/split PDF prima di arrivare a revisione-
    // caricamento.php): tutto sincrono, senza feedback via AJAX. Per PDF con
    // molte pagine puo' richiedere qualche secondo, durante i quali la
    // pagina resterebbe apparentemente "congelata" senza spiegazioni.
    // Mostriamo quindi un overlay a schermo intero al submit stesso (prima
    // ancora che il browser inizi a navigare), cosi' l'admin capisce che il
    // caricamento sta procedendo e non deve ricaricare o richiudere la scheda.
    $(document).on('submit', '.form-nuovo-caricamento', function () {
        var $overlay = $(this).siblings('.overlay-elaborazione');
        // Lo style inline (non una classe Tailwind) e' voluto: "hidden" e
        // "flex" generano entrambi una dichiarazione "display" con la
        // stessa specificita', quindi vince quella dichiarata per ultima
        // nel CSS compilato — non quella aggiunta per ultima all'elemento.
        // Lo style inline invece ha sempre priorita' garantita, a prescindere
        // dall'ordine di generazione del foglio.
        $overlay.css('display', 'flex');
        // Non e' necessario disabilitare il pulsante "Avanti": l'overlay
        // copre l'intero form con z-50 e intercetta i click, quindi un
        // secondo submit accidentale e' gia' impedito dall'interfaccia.
    });

    // Se l'admin naviga "indietro" dopo un submit, alcuni browser (Chrome,
    // Firefox) ripristinano la pagina dalla bfcache esattamente com'era
    // nel DOM al momento di lasciarla — overlay incluso, gia' mostrato da
    // sopra — invece di ricaricarla da zero. Senza questo listener l'admin
    // resterebbe bloccato a guardare lo spinner per sempre. "pageshow"
    // scatta sia sul ripristino da bfcache (event.persisted === true) sia
    // su un normale caricamento fresco, quindi nascondere sempre l'overlay
    // qui e' corretto in entrambi i casi.
    $(window).on('pageshow', function () {
        $('.overlay-elaborazione').css('display', 'none');
    });

    // Toast di conferma/errore, si chiude da solo dopo qualche secondo.
    function mostraToast(messaggio, tipo) {
        var classeAlert = tipo === 'errore' ? 'alert-error' : 'alert-success';
        var $toast = $('<div class="alert ' + classeAlert + ' shadow-lg"><span></span></div>');
        $toast.find('span').text(messaggio);
        $('#toast-container').append($toast);
        setTimeout(function () {
            $toast.fadeOut(200, function () { $(this).remove(); });
        }, 4000);
    }

    // I pulsanti "Elimina dipendente"/"Elimina caricamento" restano
    // disabilitati finche' l'admin non scrive esattamente "CANCELLA" nel
    // campo di conferma accanto — una barriera volontaria in piu' oltre al
    // normale submit, dato che l'azione e' distruttiva e irreversibile.
    $(document).on('input', '.form-elimina-dipendente input[name="conferma"], .form-elimina-caricamento input[name="conferma"]', function () {
        var $form = $(this).closest('form');
        $form.find('button[type="submit"]').prop('disabled', $(this).val() !== 'CANCELLA');
    });

    // Submit via fetch dei form azione-dipendente (aggiorna/reset_password/
    // attiva/disattiva/elimina) nel modale "Modifica dipendente": niente
    // redirect, solo toast di conferma/errore e aggiornamento della riga in
    // tabella. La password generata ha un trattamento diverso: apre un
    // modale dedicato che resta finche' l'admin non lo chiude esplicitamente.
    $(document).on('submit', '.form-azione-dipendente', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $modaleModifica = $form.closest('dialog.modal');
        var successo = $form.data('successo');

        $.ajax({
            url: '/portale-dipendenti/admin/dipendente-modifica.php',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (risposta) {
            if (!risposta.ok) {
                mostraToast(risposta.messaggio || 'Operazione non riuscita.', 'errore');
                return;
            }

            if (successo === 'password') {
                $modaleModifica.get(0).close();
                $('#valore-password-generata').val(risposta.password);
                document.getElementById('modale-password-generata').showModal();
                return;
            }

            if (successo === 'elimina') {
                $modaleModifica.get(0).close();
                mostraToast(risposta.messaggio || 'Dipendente eliminato.', 'successo');
                // A differenza di aggiorna/attiva/disattiva la riga non va
                // aggiornata ma rimossa: il dipendente non esiste piu'.
                $('#riga-dipendente-' + risposta.id).fadeOut(200, function () { $(this).remove(); });
                $modaleModifica.remove();
                return;
            }

            // successo === 'chiudi': chiude il modale e mostra il toast.
            $modaleModifica.get(0).close();
            mostraToast(risposta.messaggio || 'Operazione completata.', 'successo');

            // Aggiorna la riga della tabella per riflettere i nuovi dati,
            // senza ricaricare la pagina.
            var idDipendente = $form.find('input[name="id"]').val();
            var $riga = $('#riga-dipendente-' + idDipendente);

            if (risposta.azione === 'aggiorna') {
                $riga.find('.cella-nome').text($form.find('[name="cognome"]').val() + ' ' + $form.find('[name="nome"]').val());
                $riga.find('.cella-email').text($form.find('[name="email"]').val());
                $riga.find('.cella-cf').text($form.find('[name="codice_fiscale"]').val());
            } else if (risposta.azione === 'attiva' || risposta.azione === 'disattiva') {
                var attivo = risposta.azione === 'attiva';
                $riga.find('.cella-stato .badge')
                    .toggleClass('badge-success', attivo)
                    .toggleClass('badge-ghost', !attivo)
                    .text(attivo ? 'Attivo' : 'Disattivato');
                // Il pulsante attiva/disattiva nel modale va ricostruito per
                // la prossima apertura: la via piu' semplice e robusta e'
                // ricaricare la sola pagina alla prossima apertura del
                // modale, ma per restare senza reload aggiorniamo qui il
                // testo/azione del pulsante toggle.
                var $formToggle = $modaleModifica.find('.form-toggle-stato');
                var nuovaAzione = attivo ? 'disattiva' : 'attiva';
                $formToggle.attr('data-azione', nuovaAzione);
                $formToggle.find('input[name="azione"]').val(nuovaAzione);
                $formToggle.find('button')
                    .toggleClass('btn-error', !attivo)
                    .toggleClass('btn-success', attivo)
                    .text(attivo ? 'Riattiva' : 'Disattiva');
            } else if (risposta.azione === 'sblocca_login') {
                // Il badge "Bloccato" e il form "Sblocca accesso ora" sono
                // condizionali lato server (Utente::isBloccato()): non c'e'
                // un "toggle" da fare come per attiva/disattiva, vanno solo
                // rimossi dal DOM perche' non tornerebbero comunque a
                // riapparire da soli finche' il dipendente non fallisce di
                // nuovo il login abbastanza volte.
                $riga.find('.cella-stato .badge-warning').remove();
                $modaleModifica.find('.form-sblocca-login').remove();
            }
        }).fail(function () {
            mostraToast('Errore di comunicazione con il server. Riprova.', 'errore');
        });
    });

    // Submit via fetch del form "Il mio profilo" (modale nella navbar
    // admin, vedi templates/layout-admin.php): a differenza del modale
    // "Modifica dipendente" non c'e' una riga di tabella da aggiornare, ma
    // il nome mostrato nella navbar si' — altrimenti resterebbe quello
    // vecchio finche' l'admin non ricarica la pagina. L'esito si mostra
    // dentro al modale stesso (non nel toast globale) perche' questo
    // modale e' disponibile anche su pagine senza #toast-container, es.
    // admin/dashboard.php.
    $(document).on('submit', '#form-profilo-admin', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $modale = $form.closest('dialog.modal');
        var $messaggio = $modale.find('.messaggio-azione');

        $.ajax({
            url: '/portale-dipendenti/admin/profilo-modifica.php',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (risposta) {
            if (!risposta.ok) {
                $messaggio.html('<div class="alert alert-error text-sm mb-3">' + risposta.messaggio + '</div>');
                return;
            }

            $messaggio.html('<div class="alert alert-success text-sm mb-3">' + risposta.messaggio + '</div>');
            $('#nome-utente-navbar').text(risposta.nome + ' ' + risposta.cognome);
        }).fail(function () {
            $messaggio.html('<div class="alert alert-error text-sm mb-3">Errore di comunicazione con il server. Riprova.</div>');
        });
    });

    // Submit via fetch del form "Elimina caricamento" nel modale di
    // conferma (admin/caricamenti.php e admin/revisione-caricamento.php):
    // niente redirect, solo toast di conferma/errore. Nella pagina
    // caricamenti la riga sparisce dalla tabella; in revisione-caricamento
    // (dove non c'e' una tabella di caricamenti da aggiornare) si torna
    // invece allo storico, dato che la pagina che si sta guardando non
    // esiste piu'.
    $(document).on('submit', '.form-elimina-caricamento', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $modale = $form.closest('dialog.modal');

        $.ajax({
            url: '/portale-dipendenti/admin/caricamento-elimina.php',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (risposta) {
            if (!risposta.ok) {
                mostraToast(risposta.messaggio || 'Operazione non riuscita.', 'errore');
                return;
            }

            var $riga = $('#riga-caricamento-' + risposta.id);
            if ($riga.length) {
                if ($modale.length) {
                    $modale.get(0).close();
                }
                mostraToast(risposta.messaggio || 'Caricamento eliminato.', 'successo');
                $riga.fadeOut(200, function () { $(this).remove(); });
                if ($modale.length) {
                    $modale.remove();
                }
            } else {
                // Siamo in revisione-caricamento.php: la pagina corrente
                // riguarda il caricamento appena eliminato, quindi non ha
                // piu' senso restarci — si torna allo storico.
                window.location.href = '/portale-dipendenti/admin/caricamenti.php';
            }
        }).fail(function () {
            mostraToast('Errore di comunicazione con il server. Riprova.', 'errore');
        });
    });

    // Pulsante "Copia" accanto a un campo password temporanea (usato sia nel
    // modale "Nuovo dipendente" che nel modale "Password temporanea" del
    // reset password) — il target e' indicato dall'attributo data-target.
    $(document).on('click', '.btn-copia-password', function () {
        var $bottone = $(this);
        var $campo = $('#' + $bottone.data('target'));
        var testoOriginale = $bottone.text();

        function segnalaCopiaRiuscita() {
            $bottone.text('Copiato!');
            setTimeout(function () {
                $bottone.text(testoOriginale);
            }, 1500);
        }

        // navigator.clipboard esiste solo in un contesto sicuro (HTTPS o
        // localhost) — su http://portale-dipendenti.local (HTTP semplice)
        // e' undefined, quindi va sempre verificato prima di chiamarlo:
        // chiamare .writeText() su undefined lancia un TypeError sincrono
        // che una .catch() (pensata per una Promise rifiutata) non
        // intercetta, interrompendo lo script silenziosamente.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText($campo.val()).then(segnalaCopiaRiuscita).catch(function () {
                copiaConSelezione();
            });
        } else {
            copiaConSelezione();
        }

        function copiaConSelezione() {
            $campo.get(0).select();
            $campo.get(0).setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                segnalaCopiaRiuscita();
            } catch (e) {
                $bottone.text('Copia manualmente');
            }
        }
    });

    // Il modale "Nuovo dipendente" mostra l'esito (password generata o
    // errore) di un submit PHP classico (POST -> redirect -> GET), quindi
    // quel markup e' fisso finche' la pagina non si ricarica per intero.
    // Se pero' l'admin chiude il modale e lo riapre SENZA ricaricare la
    // pagina (es. dopo aver fatto altre azioni via AJAX nel frattempo, che
    // non toccano questo HTML), il vecchio esito resterebbe visibile.
    // Alla chiusura del modale rimuoviamo quindi l'esito dal DOM e
    // ripristiniamo il form vuoto, cosi' una riapertura mostra sempre un
    // form pulito a meno che non sia appena arrivato un nuovo esito reale
    // (nel qual caso il modale si apre gia' con l'attributo "open" al
    // caricamento della pagina, prima che questo listener possa intervenire).
    var modaleNuovoDipendente = document.getElementById('modale-nuovo-dipendente');
    if (modaleNuovoDipendente) {
        modaleNuovoDipendente.addEventListener('close', function () {
            var $modale = $(modaleNuovoDipendente);
            $modale.find('.esito-creazione-dipendente').remove();
            $modale.find('.form-nuovo-dipendente').show().get(0).reset();
        });
    }
});
