-- sql/migration-document-downloads.sql
-- Da applicare manualmente sul DB di produzione gia' esistente
-- (schema.sql viene eseguito solo alla creazione iniziale del container db).
USE portale_dipendenti;

CREATE TABLE IF NOT EXISTS document_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    utente_id INT NOT NULL,
    scaricato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (documento_id) REFERENCES documenti(id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id),
    INDEX idx_documento_id (documento_id),
    INDEX idx_scaricato_il (scaricato_il)
) ENGINE=InnoDB;
