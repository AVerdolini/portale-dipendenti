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
}
