<?php
// src/DocumentDownload.php
require_once __DIR__ . '/db.php';

class DocumentDownload
{
    public static function registra(int $documentoId, int $utenteId): void
    {
        $stmt = db()->prepare(
            'INSERT INTO document_downloads (documento_id, utente_id) VALUES (?, ?)'
        );
        $stmt->execute([$documentoId, $utenteId]);
    }

    /**
     * Ultimo scaricamento per ciascuno dei documenti indicati.
     * Ritorna una mappa documento_id => scaricato_il (string), assente se mai scaricato.
     */
    public static function ultimoPerDocumenti(array $documentoIds): array
    {
        if (empty($documentoIds)) {
            return [];
        }
        $placeholder = implode(',', array_fill(0, count($documentoIds), '?'));
        $stmt = db()->prepare(
            "SELECT documento_id, MAX(scaricato_il) AS scaricato_il
             FROM document_downloads
             WHERE documento_id IN ($placeholder)
             GROUP BY documento_id"
        );
        $stmt->execute($documentoIds);
        $mappa = [];
        foreach ($stmt->fetchAll() as $riga) {
            $mappa[(int) $riga['documento_id']] = $riga['scaricato_il'];
        }
        return $mappa;
    }

    public static function recenti(int $limite = 8): array
    {
        $limite = max(1, $limite);
        $stmt = db()->query(
            "SELECT dd.scaricato_il, d.tipo_documento, d.etichetta, d.mese, d.anno, u.id AS utente_id, u.nome, u.cognome
             FROM document_downloads dd
             JOIN documenti d ON d.id = dd.documento_id
             JOIN utenti u ON u.id = dd.utente_id
             ORDER BY dd.scaricato_il DESC
             LIMIT $limite"
        );
        return $stmt->fetchAll();
    }
}
