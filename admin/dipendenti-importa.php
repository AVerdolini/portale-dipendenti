<?php
// admin/dipendenti-importa.php
// Import massivo dell'anagrafica dipendenti da un file .xlsx con le stesse
// colonne prodotte da admin/dipendenti-esporta.php (Nome, Cognome, Email,
// Codice Fiscale, Stato). Upsert per codice fiscale: riga con CF esistente
// aggiorna il dipendente (incluso stato attivo/disattivato), CF non
// esistente crea un nuovo dipendente con password temporanea generata.
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/Utente.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

require_admin();

const PATTERN_CF = '/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/';

$errore = null;
$risultato = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errore = 'Nessun file caricato o upload fallito.';
    } else {
        try {
            $spreadsheet = IOFactory::load($_FILES['file']['tmp_name']);
        } catch (Throwable $e) {
            $spreadsheet = null;
            $errore = 'Il file non e\' un foglio Excel valido.';
        }

        if ($spreadsheet !== null) {
            $righe = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            // Prima riga = intestazioni, si salta.
            array_shift($righe);

            $creati = [];
            $aggiornati = 0;
            $erroriRighe = [];
            $numeroRiga = 1; // per i messaggi: la riga 1 e' l'intestazione, i dati iniziano da 2

            foreach ($righe as $cella) {
                $numeroRiga++;

                $nome = trim((string) ($cella[0] ?? ''));
                $cognome = trim((string) ($cella[1] ?? ''));
                $email = trim((string) ($cella[2] ?? ''));
                $codiceFiscale = strtoupper(trim((string) ($cella[3] ?? '')));
                $statoTesto = strtolower(trim((string) ($cella[4] ?? '')));

                if ($nome === '' && $cognome === '' && $email === '' && $codiceFiscale === '') {
                    continue; // riga vuota, si ignora silenziosamente
                }

                if ($nome === '' || $cognome === '' || $email === '' || $codiceFiscale === '') {
                    $erroriRighe[] = "Riga $numeroRiga: campi obbligatori mancanti.";
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $erroriRighe[] = "Riga $numeroRiga: email \"$email\" non valida.";
                    continue;
                }

                if (!preg_match(PATTERN_CF, $codiceFiscale)) {
                    $erroriRighe[] = "Riga $numeroRiga: codice fiscale \"$codiceFiscale\" non valido.";
                    continue;
                }

                $attivo = $statoTesto !== 'disattivato';

                $esistente = Utente::findByCodiceFiscale($codiceFiscale);

                if ($esistente !== null) {
                    $utenteConStessaEmail = Utente::findByEmail($email);
                    if ($utenteConStessaEmail !== null && (int) $utenteConStessaEmail['id'] !== (int) $esistente['id']) {
                        $erroriRighe[] = "Riga $numeroRiga: email \"$email\" gia\' usata da un altro dipendente.";
                        continue;
                    }
                    Utente::update((int) $esistente['id'], $nome, $cognome, $email, $codiceFiscale);
                    Utente::setAttivo((int) $esistente['id'], $attivo);
                    $aggiornati++;
                } else {
                    $utenteConStessaEmail = Utente::findByEmail($email);
                    if ($utenteConStessaEmail !== null) {
                        $erroriRighe[] = "Riga $numeroRiga: email \"$email\" gia\' usata da un altro dipendente.";
                        continue;
                    }
                    $nuovo = Utente::create($nome, $cognome, $email, $codiceFiscale);
                    if (!$attivo) {
                        Utente::setAttivo($nuovo['id'], false);
                    }
                    $creati[] = [
                        'nome_completo' => "$nome $cognome",
                        'password_temporanea' => $nuovo['password_temporanea'],
                    ];
                }
            }

            $risultato = [
                'creati' => $creati,
                'aggiornati' => $aggiornati,
                'errori' => $erroriRighe,
            ];
        }
    }
}

require_once __DIR__ . '/../templates/layout-admin.php';
layout_admin_inizio('Import dipendenti', 'dipendenti');
?>
<div class="flex items-center gap-2 mb-6">
    <a href="/admin/dipendenti.php" class="btn btn-ghost btn-sm btn-square" aria-label="Torna ai dipendenti">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 6l-6 6 6 6" />
        </svg>
    </a>
    <h1 class="text-xl font-semibold">Import dipendenti da Excel</h1>
</div>

<?php if ($errore): ?>
    <div class="alert alert-error mb-6 text-sm max-w-xl"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<?php if ($risultato === null): ?>
    <form method="post" enctype="multipart/form-data" class="flex flex-col gap-3 max-w-xl bg-base-100 rounded-2xl shadow-[0_2px_10px_-4px_rgba(0,0,0,0.15)] p-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <p class="text-sm text-base-content/60">
            Carica un file .xlsx con le colonne Nome, Cognome, Email, Codice Fiscale, Stato
            (lo stesso formato prodotto da "Esporta"). Un codice fiscale gia' presente
            aggiorna il dipendente esistente, altrimenti ne viene creato uno nuovo.
        </p>
        <input type="file" name="file" accept=".xlsx" required class="file-input file-input-bordered w-full">
        <button type="submit" class="btn btn-primary">Importa</button>
    </form>
<?php else: ?>
    <div class="max-w-2xl flex flex-col gap-6">
        <div class="stats shadow bg-base-100">
            <div class="stat">
                <div class="stat-title">Creati</div>
                <div class="stat-value text-success"><?= count($risultato['creati']) ?></div>
            </div>
            <div class="stat">
                <div class="stat-title">Aggiornati</div>
                <div class="stat-value"><?= $risultato['aggiornati'] ?></div>
            </div>
            <div class="stat">
                <div class="stat-title">Errori</div>
                <div class="stat-value <?= empty($risultato['errori']) ? '' : 'text-error' ?>"><?= count($risultato['errori']) ?></div>
            </div>
        </div>

        <?php if (!empty($risultato['creati'])): ?>
            <div>
                <h2 class="font-medium mb-2">Password temporanee dei nuovi dipendenti</h2>
                <p class="text-xs text-base-content/60 mb-3">Comunicale ai dipendenti: non saranno mostrate di nuovo.</p>
                <table class="table bg-base-100 rounded-xl shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)]">
                    <thead><tr><th>Dipendente</th><th>Password temporanea</th></tr></thead>
                    <tbody>
                    <?php foreach ($risultato['creati'] as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nome_completo']) ?></td>
                            <td class="font-mono"><?= htmlspecialchars($c['password_temporanea']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($risultato['errori'])): ?>
            <div>
                <h2 class="font-medium mb-2">Righe con errori</h2>
                <ul class="text-sm text-error list-disc list-inside bg-base-100 rounded-xl shadow-[0_1px_6px_-2px_rgba(0,0,0,0.12)] p-4">
                    <?php foreach ($risultato['errori'] as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="/admin/dipendenti.php" class="btn btn-primary w-fit">Torna ai dipendenti</a>
    </div>
<?php endif; ?>
<?php
layout_admin_fine();
