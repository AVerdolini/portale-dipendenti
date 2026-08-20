<?php
// src/PaginaNonAssociata.php
require_once __DIR__ . '/db.php';

class PaginaNonAssociata
{
    public static function create(array $dati): int
    {
        $stmt = db()->prepare(
            'INSERT INTO pagine_non_associate (caricamento_id, pagina_da, pagina_a, cf_estratto, stato)
             VALUES (?, ?, ?, ?, "in_attesa")'
        );
        $stmt->execute([
            $dati['caricamento_id'],
            $dati['pagina_da'],
            $dati['pagina_a'],
            $dati['cf_estratto'] ?? null,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function perCaricamento(int $caricamentoId, ?string $stato = null): array
    {
        if ($stato !== null) {
            $stmt = db()->prepare('SELECT * FROM pagine_non_associate WHERE caricamento_id = ? AND stato = ? ORDER BY pagina_da');
            $stmt->execute([$caricamentoId, $stato]);
        } else {
            $stmt = db()->prepare('SELECT * FROM pagine_non_associate WHERE caricamento_id = ? ORDER BY pagina_da');
            $stmt->execute([$caricamentoId]);
        }
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM pagine_non_associate WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function risolvi(int $id, int $risoltaDa): void
    {
        $stmt = db()->prepare('UPDATE pagine_non_associate SET stato = "risolta", risolta_da = ?, risolta_il = NOW() WHERE id = ?');
        $stmt->execute([$risoltaDa, $id]);
    }

    public static function scarta(int $id, int $risoltaDa): void
    {
        $stmt = db()->prepare('UPDATE pagine_non_associate SET stato = "scartata", risolta_da = ?, risolta_il = NOW() WHERE id = ?');
        $stmt->execute([$risoltaDa, $id]);
    }
}
