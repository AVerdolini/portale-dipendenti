-- sql/schema.sql
CREATE DATABASE IF NOT EXISTS portale_dipendenti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portale_dipendenti;

CREATE TABLE utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    codice_fiscale VARCHAR(16) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('admin', 'dipendente') NOT NULL DEFAULT 'dipendente',
    deve_cambiare_password TINYINT(1) NOT NULL DEFAULT 1,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    tentativi_falliti INT NOT NULL DEFAULT 0,
    bloccato_fino DATETIME NULL,
    creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE caricamenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_documento ENUM('busta_paga', 'cu') NOT NULL,
    etichetta ENUM('Cedolino', '13a mensilita', '14a mensilita') NULL,
    mese TINYINT NULL,
    anno SMALLINT NOT NULL,
    nome_file_originale VARCHAR(255) NOT NULL,
    percorso_file_originale VARCHAR(500) NOT NULL,
    caricato_da INT NOT NULL,
    caricato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    stato ENUM('elaborazione', 'completato', 'con_errori') NOT NULL DEFAULT 'elaborazione',
    FOREIGN KEY (caricato_da) REFERENCES utenti(id)
) ENGINE=InnoDB;

CREATE TABLE documenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caricamento_id INT NOT NULL,
    utente_id INT NULL,
    tipo_documento ENUM('busta_paga', 'cu') NOT NULL,
    etichetta VARCHAR(50) NULL,
    mese TINYINT NULL,
    anno SMALLINT NOT NULL,
    percorso_file VARCHAR(500) NOT NULL,
    pagina_da INT NOT NULL,
    pagina_a INT NOT NULL,
    netto_in_busta DECIMAL(10,2) NULL,
    stato ENUM('associato', 'da_rivedere', 'scartato') NOT NULL DEFAULT 'associato',
    creato_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    utente_id_se_associato INT AS (IF(stato = 'associato', utente_id, NULL)) VIRTUAL,
    FOREIGN KEY (caricamento_id) REFERENCES caricamenti(id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id),
    INDEX idx_utente_id (utente_id),
    UNIQUE KEY uq_documento_periodo (utente_id_se_associato, tipo_documento, etichetta, mese, anno)
) ENGINE=InnoDB;

CREATE TABLE pagine_non_associate (
    id INT AUTO_INCREMENT PRIMARY KEY,
    caricamento_id INT NOT NULL,
    pagina_da INT NOT NULL,
    pagina_a INT NOT NULL,
    cf_estratto VARCHAR(16) NULL,
    stato ENUM('in_attesa', 'risolta', 'scartata') NOT NULL DEFAULT 'in_attesa',
    risolta_da INT NULL,
    risolta_il DATETIME NULL,
    FOREIGN KEY (caricamento_id) REFERENCES caricamenti(id),
    FOREIGN KEY (risolta_da) REFERENCES utenti(id)
) ENGINE=InnoDB;
