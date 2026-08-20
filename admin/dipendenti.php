<?php
// admin/dipendenti.php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../templates/layout-admin.php';

require_admin();

$errore = null;
$passwordGenerata = null;
$nomeNuovoDipendente = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'crea') {
    csrf_verify();
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $codiceFiscale = trim($_POST['codice_fiscale'] ?? '');

    if ($nome === '' || $cognome === '' || $email === '' || $codiceFiscale === '') {
        $errore = 'Tutti i campi sono obbligatori.';
    } elseif (Utente::findByEmail($email) !== null) {
        $errore = 'Esiste gia\' un utente con questa email.';
    } elseif (Utente::findByCodiceFiscale($codiceFiscale) !== null) {
        $errore = 'Esiste gia\' un utente con questo codice fiscale.';
    } else {
        $risultato = Utente::create($nome, $cognome, $email, $codiceFiscale, 'dipendente');
        $passwordGenerata = $risultato['password_temporanea'];
        $nomeNuovoDipendente = "$nome $cognome";
    }
}

$dipendenti = Utente::all();

// Il modale "Nuovo dipendente" si riapre automaticamente se il submit ha
// prodotto un esito (successo o errore) da mostrare all'admin.
$riapriModaleCreazione = $passwordGenerata !== null || $errore !== null;

// Esito di un'azione fatta nel modale "Modifica dipendente" (redirect da
// admin/dipendente-modifica.php): se presente, riapre il modale del
// dipendente interessato mostrando il messaggio.
$modificaEsito = $_GET['modifica_esito'] ?? null;
$modificaMessaggio = $_GET['modifica_messaggio'] ?? null;
$modificaId = isset($_GET['modifica_id']) ? (int) $_GET['modifica_id'] : null;

layout_admin_inizio('Dipendenti', 'dipendenti');
?>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-semibold">Dipendenti</h1>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('modale-nuovo-dipendente').showModal()">
        Nuovo dipendente
    </button>
</div>

<table class="table bg-base-100 shadow">
    <thead><tr><th>Nome</th><th>Email</th><th>CF</th><th>Stato</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($dipendenti as $d): ?>
        <tr class="hover">
            <td><?= htmlspecialchars($d['cognome'] . ' ' . $d['nome']) ?></td>
            <td><?= htmlspecialchars($d['email']) ?></td>
            <td><?= htmlspecialchars($d['codice_fiscale']) ?></td>
            <td>
                <span class="badge <?= $d['attivo'] ? 'badge-success' : 'badge-ghost' ?>">
                    <?= $d['attivo'] ? 'Attivo' : 'Disattivato' ?>
                </span>
            </td>
            <td class="flex gap-2">
                <button type="button" class="btn btn-xs" onclick="document.getElementById('modale-modifica-<?= $d['id'] ?>').showModal()">Modifica</button>
                <a href="/portale-dipendenti/admin/dipendente-documenti.php?id=<?= $d['id'] ?>" class="btn btn-xs">Documenti</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($dipendenti)): ?>
        <tr><td colspan="5" class="text-base-content/60">Nessun dipendente registrato.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<dialog id="modale-nuovo-dipendente" class="modal" <?= $riapriModaleCreazione ? 'open' : '' ?>>
    <div class="modal-box">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-semibold text-lg mb-4">Nuovo dipendente</h3>

        <?php if ($passwordGenerata): ?>
            <div class="alert alert-success mb-4 text-sm">
                Dipendente <?= htmlspecialchars($nomeNuovoDipendente) ?> creato. Password temporanea:
                <strong><?= htmlspecialchars($passwordGenerata) ?></strong>
                — comunicala fuori banda, non verra' mostrata di nuovo.
            </div>
        <?php endif; ?>
        <?php if ($errore): ?>
            <div class="alert alert-error mb-4 text-sm"><?= htmlspecialchars($errore) ?></div>
        <?php endif; ?>

        <?php if (!$passwordGenerata): ?>
        <form method="post" class="flex flex-col gap-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="azione" value="crea">
            <input type="text" name="nome" placeholder="Nome" required class="input input-bordered w-full">
            <input type="text" name="cognome" placeholder="Cognome" required class="input input-bordered w-full">
            <input type="email" name="email" placeholder="Email" required class="input input-bordered w-full">
            <input type="text" name="codice_fiscale" placeholder="Codice Fiscale" required maxlength="16" class="input input-bordered w-full">
            <button type="submit" class="btn btn-primary">Crea</button>
        </form>
        <?php endif; ?>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>chiudi</button>
    </form>
</dialog>

<?php foreach ($dipendenti as $d): ?>
    <?php $mostraEsitoQui = $modificaId === (int) $d['id']; ?>
    <dialog id="modale-modifica-<?= $d['id'] ?>" class="modal" <?= $mostraEsitoQui ? 'open' : '' ?>>
        <div class="modal-box">
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
            </form>
            <h3 class="font-semibold text-lg mb-4">Modifica dipendente</h3>

            <?php if ($mostraEsitoQui && $modificaEsito === 'password'): ?>
                <div class="alert alert-success mb-4 text-sm">
                    Nuova password temporanea: <strong><?= htmlspecialchars($modificaMessaggio) ?></strong>
                    — comunicala fuori banda, non verra' mostrata di nuovo.
                </div>
            <?php elseif ($mostraEsitoQui && $modificaEsito === 'errore'): ?>
                <div class="alert alert-error mb-4 text-sm"><?= htmlspecialchars($modificaMessaggio) ?></div>
            <?php elseif ($mostraEsitoQui && $modificaEsito === 'ok' && $modificaMessaggio): ?>
                <div class="alert alert-success mb-4 text-sm"><?= htmlspecialchars($modificaMessaggio) ?></div>
            <?php endif; ?>

            <form method="post" action="/portale-dipendenti/admin/dipendente-modifica.php" class="flex flex-col gap-3 mb-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <input type="hidden" name="azione" value="aggiorna">
                <input type="text" name="nome" value="<?= htmlspecialchars($d['nome']) ?>" required class="input input-bordered w-full">
                <input type="text" name="cognome" value="<?= htmlspecialchars($d['cognome']) ?>" required class="input input-bordered w-full">
                <input type="email" name="email" value="<?= htmlspecialchars($d['email']) ?>" required class="input input-bordered w-full">
                <input type="text" name="codice_fiscale" value="<?= htmlspecialchars($d['codice_fiscale']) ?>" required maxlength="16" class="input input-bordered w-full">
                <button type="submit" class="btn btn-primary">Salva</button>
            </form>

            <form method="post" action="/portale-dipendenti/admin/dipendente-modifica.php" class="mb-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <input type="hidden" name="azione" value="reset_password">
                <button type="submit" class="btn btn-outline w-full">Genera nuova password</button>
            </form>

            <?php if ($d['attivo']): ?>
                <form method="post" action="/portale-dipendenti/admin/dipendente-modifica.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="azione" value="disattiva">
                    <button type="submit" class="btn btn-error btn-outline w-full">Disattiva</button>
                </form>
            <?php else: ?>
                <form method="post" action="/portale-dipendenti/admin/dipendente-modifica.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="azione" value="attiva">
                    <button type="submit" class="btn btn-success btn-outline w-full">Riattiva</button>
                </form>
            <?php endif; ?>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>chiudi</button>
        </form>
    </dialog>
<?php endforeach; ?>
<?php
layout_admin_fine();
