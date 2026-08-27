<?php
// src/Documento.php
require_once __DIR__ . '/db.php';

class Documento
{
    public static function create(array $dati): int
    {
        $stmt = db()->prepare(
            'INSERT INTO documenti
                (caricamento_id, utente_id, tipo_documento, etichetta, mese, anno, percorso_file, pagina_da, pagina_a, netto_in_busta, stato)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $dati['caricamento_id'],
            $dati['utente_id'],
            $dati['tipo_documento'],
            $dati['etichetta'] ?? null,
            $dati['mese'] ?? null,
            $dati['anno'],
            $dati['percorso_file'],
            $dati['pagina_da'],
            $dati['pagina_a'],
            $dati['netto_in_busta'] ?? null,
            $dati['stato'] ?? 'associato',
        ]);
        return (int) db()->lastInsertId();
    }

    public static function esisteAssociato(int $utenteId, string $tipoDocumento, ?string $etichetta, ?int $mese, int $anno): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM documenti
             WHERE utente_id = ? AND tipo_documento = ? AND etichetta <=> ? AND mese <=> ? AND anno = ? AND stato = "associato"'
        );
        $stmt->execute([$utenteId, $tipoDocumento, $etichetta, $mese, $anno]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Come esisteAssociato(), ma include anche il nome del file originale e
     * la data del caricamento da cui il documento in conflitto proviene —
     * serve in revisione-caricamento.php per mostrare all'admin CON QUALE
     * file esistente sta andando in conflitto, non solo che c'e' un conflitto.
     */
    public static function esisteAssociatoConOrigine(int $utenteId, string $tipoDocumento, ?string $etichetta, ?int $mese, int $anno): ?array
    {
        $stmt = db()->prepare(
            'SELECT d.*, c.nome_file_originale AS caricamento_nome_file, c.caricato_il AS caricamento_caricato_il
             FROM documenti d
             JOIN caricamenti c ON c.id = d.caricamento_id
             WHERE d.utente_id = ? AND d.tipo_documento = ? AND d.etichetta <=> ? AND d.mese <=> ? AND d.anno = ? AND d.stato = "associato"'
        );
        $stmt->execute([$utenteId, $tipoDocumento, $etichetta, $mese, $anno]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function perCaricamento(int $caricamentoId): array
    {
        $stmt = db()->prepare('SELECT d.*, u.nome, u.cognome FROM documenti d
                                JOIN utenti u ON u.id = d.utente_id
                                WHERE d.caricamento_id = ? AND d.stato = "associato"
                                ORDER BY u.cognome, u.nome');
        $stmt->execute([$caricamentoId]);
        return $stmt->fetchAll();
    }

    public static function perUtente(int $utenteId, ?string $tipoDocumento = null): array
    {
        if ($tipoDocumento !== null) {
            $stmt = db()->prepare(
                'SELECT * FROM documenti WHERE utente_id = ? AND tipo_documento = ? AND stato = "associato"
                 ORDER BY anno, mese, id'
            );
            $stmt->execute([$utenteId, $tipoDocumento]);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM documenti WHERE utente_id = ? AND stato = "associato"
                 ORDER BY anno, mese, id'
            );
            $stmt->execute([$utenteId]);
        }
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM documenti WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function scarta(int $id): void
    {
        $stmt = db()->prepare('UPDATE documenti SET stato = "scartato" WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Correzione manuale dell'admin per quando l'estrazione automatica del
     * netto in busta ha fallito o ha preso un valore sbagliato (es. layout del
     * cedolino non riconosciuto, OCR impreciso). $netto null azzera il valore
     * (torna a "non disponibile" invece di mostrare un dato errato).
     */
    public static function aggiornaNetto(int $id, ?float $netto): void
    {
        $stmt = db()->prepare('UPDATE documenti SET netto_in_busta = ? WHERE id = ?');
        $stmt->execute([$netto, $id]);
    }

    public static function sovrascrivi(int $vecchioId, array $datiNuovo): int
    {
        self::scarta($vecchioId);
        return self::create($datiNuovo);
    }
}
