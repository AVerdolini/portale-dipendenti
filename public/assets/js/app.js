// public/assets/js/app.js
// Shared jQuery helpers — populated in later tasks (carosello, preview loader).

$(function () {
    $(document).on('click', '.riga-preview', function () {
        var src = $(this).data('src');
        $('#preview-frame').attr('src', src);
    });
});
