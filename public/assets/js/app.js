// public/assets/js/app.js
// Shared jQuery helpers — populated in later tasks (carosello, preview loader).

$(function () {
    $(document).on('click', '.riga-preview', function () {
        var src = $(this).data('src');
        $('#preview-frame').attr('src', src);
    });

    // Mostra/nasconde i campi specifici busta paga (etichetta, mese) in base
    // al tipo documento selezionato. Delegato su document cosi' funziona sia
    // nella pagina standalone che nel form dentro il modale "Nuovo caricamento".
    $(document).on('change', '.tipo-documento-select', function () {
        var $campiBustaPaga = $(this).closest('form').find('.campi-busta-paga');
        $campiBustaPaga.toggle($(this).val() === 'busta_paga');
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

    // Submit via fetch dei form azione-dipendente (aggiorna/reset_password/
    // attiva/disattiva) nel modale "Modifica dipendente": niente redirect,
    // solo toast di conferma/errore e aggiornamento della riga in tabella.
    // La password generata ha un trattamento diverso: apre un modale
    // dedicato che resta finche' l'admin non lo chiude esplicitamente.
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
            }
        }).fail(function () {
            mostraToast('Errore di comunicazione con il server. Riprova.', 'errore');
        });
    });

    // Pulsante "Copia" accanto a un campo password temporanea (usato sia nel
    // modale "Nuovo dipendente" che nel modale "Password temporanea" del
    // reset password) — il target e' indicato dall'attributo data-target.
    $(document).on('click', '.btn-copia-password', function () {
        var $campo = $('#' + $(this).data('target'));
        $campo.get(0).select();
        navigator.clipboard.writeText($campo.val()).catch(function () {
            document.execCommand('copy');
        });
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
