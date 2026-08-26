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

    /**
     * Cancella un caricamento e tutto cio' che gli appartiene: le righe
     * documenti/pagine_non_associate (nessuna FK ha ON DELETE CASCADE, vanno
     * quindi rimosse esplicitamente prima della riga caricamenti stessa) e,
     * una volta committata la transazione, i file fisici sul disco (PDF
     * originale + ogni documento splittato). I file vengono cancellati dopo
     * il commit apposta: se la cancellazione su disco fallisse a meta',
     * meglio avere file orfani residui che uno stato DB incoerente.
     * Ritorna l'elenco dei percorsi file che il chiamante puo' voler loggare.
     */
    public static function delete(int $id): array
    {
        $caricamento = self::findById($id);
        if ($caricamento === null) {
            return [];
        }

        $stmtDocumenti = db()->prepare('SELECT percorso_file FROM documenti WHERE caricamento_id = ?');
        $stmtDocumenti->execute([$id]);
        $percorsiDocumenti = array_column($stmtDocumenti->fetchAll(), 'percorso_file');

        db()->beginTransaction();
        try {
            db()->prepare('DELETE FROM documenti WHERE caricamento_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM pagine_non_associate WHERE caricamento_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM caricamenti WHERE id = ?')->execute([$id]);
            db()->commit();
        } catch (\Throwable $e) {
            db()->rollBack();
            throw $e;
        }

        $percorsiDaCancellare = array_merge([$caricamento['percorso_file_originale']], $percorsiDocumenti);
        foreach ($percorsiDaCancellare as $percorso) {
            if ($percorso !== null && is_file($percorso)) {
                @unlink($percorso);
            }
        }

        return $percorsiDaCancellare;
    }
}
