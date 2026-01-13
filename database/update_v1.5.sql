-- ============================================
-- EKassa360 Update v1.5
-- Benutzerverwaltung und Änderungsprotokoll
-- ============================================
-- 
-- WICHTIG: Führen Sie stattdessen install.php aus!
-- Diese Datei ist nur als Referenz gedacht.
-- install.php generiert den korrekten Passwort-Hash.
--
-- ============================================

-- Benutzer-Tabelle
CREATE TABLE IF NOT EXISTS benutzer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    benutzername VARCHAR(50) NOT NULL UNIQUE,
    passwort_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    vorname VARCHAR(50),
    nachname VARCHAR(50),
    rolle ENUM('admin', 'benutzer') DEFAULT 'benutzer',
    aktiv TINYINT(1) DEFAULT 1,
    passwort_muss_geaendert TINYINT(1) DEFAULT 0,
    letzter_login DATETIME,
    fehlversuche INT DEFAULT 0,
    gesperrt_bis DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Änderungsprotokoll
CREATE TABLE IF NOT EXISTS aenderungsprotokoll (
    id INT PRIMARY KEY AUTO_INCREMENT,
    benutzer_id INT,
    benutzer_name VARCHAR(50),
    tabelle VARCHAR(50) NOT NULL,
    datensatz_id INT,
    aktion ENUM('erstellt', 'geaendert', 'geloescht', 'login', 'logout', 'passwort_geaendert') NOT NULL,
    beschreibung TEXT,
    alte_werte JSON,
    neue_werte JSON,
    ip_adresse VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_benutzer (benutzer_id),
    INDEX idx_tabelle (tabelle),
    INDEX idx_created (created_at)
);

-- Admin wird über install.php erstellt (generiert korrekten Hash)

-- Spalten für Änderungsverfolgung hinzufügen
ALTER TABLE rechnungen ADD COLUMN IF NOT EXISTS erstellt_von INT DEFAULT NULL;
ALTER TABLE rechnungen ADD COLUMN IF NOT EXISTS geaendert_von INT DEFAULT NULL;
ALTER TABLE anlagegueter ADD COLUMN IF NOT EXISTS erstellt_von INT DEFAULT NULL;
ALTER TABLE anlagegueter ADD COLUMN IF NOT EXISTS geaendert_von INT DEFAULT NULL;
