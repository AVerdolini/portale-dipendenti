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

layout_admin_inizio('Dipendenti', 'dipendenti');
?>
<h1 class="text-xl font-semibold mb-6">Dipendenti</h1>

<?php if ($passwordGenerata): ?>
    <div class="alert alert-success mb-4">
        Dipendente <?= htmlspecialchars($nomeNuovoDipendente) ?> creato. Password temporanea:
        <strong><?= htmlspecialchars($passwordGenerata) ?></strong>
        — comunicala fuori banda, non verra' mostrata di nuovo.
    </div>
<?php endif; ?>
<?php if ($errore): ?>
    <div class="alert alert-error mb-4"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<div class="grid grid-cols-3 gap-6">
    <table class="table bg-base-100 shadow col-span-2">
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
                    <a href="/portale-dipendenti/admin/dipendente-modifica.php?id=<?= $d['id'] ?>" class="btn btn-xs">Modifica</a>
                    <a href="/portale-dipendenti/admin/dipendente-documenti.php?id=<?= $d['id'] ?>" class="btn btn-xs">Documenti</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($dipendenti)): ?>
            <tr><td colspan="5" class="text-base-content/60">Nessun dipendente registrato.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <form method="post" class="card bg-base-100 shadow p-6 flex flex-col gap-3">
        <h2 class="font-semibold">Nuovo dipendente</h2>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="azione" value="crea">
        <input type="text" name="nome" placeholder="Nome" required class="input input-bordered w-full">
        <input type="text" name="cognome" placeholder="Cognome" required class="input input-bordered w-full">
        <input type="email" name="email" placeholder="Email" required class="input input-bordered w-full">
        <input type="text" name="codice_fiscale" placeholder="Codice Fiscale" required maxlength="16" class="input input-bordered w-full">
        <button type="submit" class="btn btn-primary">Crea</button>
    </form>
</div>
<?php
layout_admin_fine();
