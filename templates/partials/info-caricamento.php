<?php
/**
 * Box informativo "Come funziona il caricamento", riusato sia accanto al
 * form nella pagina standalone (admin/nuovo-caricamento.php) sia sopra il
 * form nel modale "Nuovo caricamento" (admin/caricamenti.php), dove lo
 * spazio orizzontale non basta per affiancarlo.
 */
?>
<div class="text-sm flex flex-col gap-3">
    <h2 class="font-semibold text-base">Come funziona il caricamento</h2>
    <ul class="list-disc list-inside space-y-2 text-base-content/80">
        <li>Il PDF cumulativo viene diviso automaticamente in un documento per dipendente, riconoscendo il Codice Fiscale su ogni pagina.</li>
        <li>Le pagine completamente bianche (es. l'ultima pagina di stampa) vengono scartate in automatico, senza bisogno di intervento.</li>
        <li>Le pagine con un CF non riconosciuto, o gia' associate a un documento esistente per lo stesso periodo, finiscono nella coda "Da rivedere" nello step successivo.</li>
        <li>Da li' puoi assegnarle manualmente al dipendente corretto, oppure scartarle se non pertinenti.</li>
    </ul>
</div>
