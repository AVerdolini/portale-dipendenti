<?php
// src/Caricamento.php
require_once __DIR__ . '/db.php';

class Caricamento
{
    public static function create(array $dati): int
    {
        $stmt = db()->prepare(
            'INSERT INTO caricamenti
                (tipo_documento, etichetta, mese, anno, nome_file_originale, percorso_file_originale, caricato_da, stato)
             VALUES (?, ?, ?, ?, ?, ?, ?, "elaborazione")'
        );
        $stmt->execute([
            $dati['tipo_documento'],
            $dati['etichetta'] ?? null,
            $dati['mese'] ?? null,
            $dati['anno'],
            $dati['nome_file_originale'],
            $dati['percorso_file_originale'],
            $dati['caricato_da'],
        ]);
        return (int) db()->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM caricamenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setStato(int $id, string $stato): void
    {
        $stmt = db()->prepare('UPDATE caricamenti SET stato = ? WHERE id = ?');
        $stmt->execute([$stato, $id]);
    }

    public static function all(): array
    {
        return db()->query('SELECT * FROM caricamenti ORDER BY caricato_il DESC')->fetchAll();
    }
}
