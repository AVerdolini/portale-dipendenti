<?php
// src/Utente.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

class Utente
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function verifyPassword(array $utente, string $password): bool
    {
        return password_verify($password, $utente['password_hash']);
    }

    public static function setPassword(int $id, string $newPassword, bool $mustChange = false): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = db()->prepare('UPDATE utenti SET password_hash = ?, deve_cambiare_password = ? WHERE id = ?');
        $stmt->execute([$hash, $mustChange ? 1 : 0, $id]);
    }

    public static function create(string $nome, string $cognome, string $email, string $codiceFiscale, string $ruolo = 'dipendente'): array
    {
        $passwordTemporanea = generaPasswordTemporanea();
        $hash = password_hash($passwordTemporanea, PASSWORD_BCRYPT);
        $stmt = db()->prepare(
            'INSERT INTO utenti (nome, cognome, email, codice_fiscale, password_hash, ruolo, deve_cambiare_password, attivo)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)'
        );
        $stmt->execute([$nome, $cognome, $email, strtoupper($codiceFiscale), $hash, $ruolo]);
        return [
            'id' => (int) db()->lastInsertId(),
            'password_temporanea' => $passwordTemporanea,
        ];
    }

    public static function findByCodiceFiscale(string $cf): ?array
    {
        $stmt = db()->prepare('SELECT * FROM utenti WHERE codice_fiscale = ?');
        $stmt->execute([strtoupper($cf)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM utenti WHERE ruolo = "dipendente" ORDER BY cognome, nome')->fetchAll();
    }

    public static function update(int $id, string $nome, string $cognome, string $email, string $codiceFiscale): void
    {
        $stmt = db()->prepare('UPDATE utenti SET nome = ?, cognome = ?, email = ?, codice_fiscale = ? WHERE id = ?');
        $stmt->execute([$nome, $cognome, $email, strtoupper($codiceFiscale), $id]);
    }

    public static function setAttivo(int $id, bool $attivo): void
    {
        $stmt = db()->prepare('UPDATE utenti SET attivo = ? WHERE id = ?');
        $stmt->execute([$attivo ? 1 : 0, $id]);
    }

    public static function haDocumenti(int $id): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM documenti WHERE utente_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() !== false;
    }

    public static function delete(int $id): void
    {
        // La FK documenti.utente_id non ha ON DELETE CASCADE: un dipendente
        // con documenti caricati va disattivato, non cancellato, per non
        // perdere lo storico delle buste paga. Il chiamante deve verificare
        // haDocumenti() prima di invocare questo metodo.
        $stmt = db()->prepare('DELETE FROM utenti WHERE id = ?');
        $stmt->execute([$id]);
    }

    // Soglie del blocco temporaneo dopo troppi tentativi di login falliti
    // sulla stessa email (protezione anti-brute-force). Il blocco si
    // sospende da solo allo scadere di DURATA_BLOCCO_MINUTI, senza bisogno
    // di intervento admin — l'admin puo' comunque sbloccare prima a mano
    // (vedi sbloccaLogin()) se un dipendente chiama e non vuole aspettare.
    private const MAX_TENTATIVI_FALLITI = 5;
    private const DURATA_BLOCCO_MINUTI = 15;

    public static function isBloccato(array $utente): bool
    {
        return $utente['bloccato_fino'] !== null && strtotime($utente['bloccato_fino']) > time();
    }

    public static function minutiBloccoResidui(array $utente): int
    {
        if (!self::isBloccato($utente)) {
            return 0;
        }
        return (int) ceil((strtotime($utente['bloccato_fino']) - time()) / 60);
    }

    /**
     * Registra un tentativo di login fallito per l'utente indicato: incrementa
     * il contatore e, se raggiunge la soglia, imposta bloccato_fino. Chiamato
     * solo dopo aver gia' verificato che l'utente esiste (altrimenti non c'e'
     * una riga su cui incrementare — un'email inesistente non rivela nulla
     * di diverso da una password sbagliata, il messaggio d'errore resta
     * identico in entrambi i casi).
     */
    public static function registraTentativoFallito(int $id): void
    {
        // Nota MySQL: dentro un singolo UPDATE, ogni espressione della SET
        // vede gia' i valori assegnati dalle espressioni precedenti sulla
        // stessa riga (valutazione da sinistra a destra) — quindi qui il
        // CASE vede gia' "tentativi_falliti + 1" e non deve sommarlo di
        // nuovo, altrimenti il blocco scatterebbe un tentativo prima del
        // dovuto (verificato: senza questo fix scattava al 4° tentativo
        // invece che al 5°).
        $stmt = db()->prepare(
            'UPDATE utenti SET
                tentativi_falliti = tentativi_falliti + 1,
                bloccato_fino = CASE
                    WHEN tentativi_falliti >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    ELSE bloccato_fino
                END
             WHERE id = ?'
        );
        $stmt->execute([self::MAX_TENTATIVI_FALLITI, self::DURATA_BLOCCO_MINUTI, $id]);
    }

    public static function resetTentativiFalliti(int $id): void
    {
        $stmt = db()->prepare('UPDATE utenti SET tentativi_falliti = 0, bloccato_fino = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    // Alias esplicito per l'azione amministrativa "sblocca ora" — stessa
    // query di resetTentativiFalliti(), nome separato per leggibilita' nel
    // punto di chiamata (endpoint admin vs reset dopo login riuscito).
    public static function sbloccaLogin(int $id): void
    {
        self::resetTentativiFalliti($id);
    }
}
