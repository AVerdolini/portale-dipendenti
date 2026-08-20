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
});
