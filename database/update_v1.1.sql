-- ============================================
-- Buchhaltungs-App Datenbank UPDATE
-- Von Version 1.0 auf 1.1
-- ACHTUNG: Backup vor dem Ausführen machen!
-- ============================================

USE buchhaltung_db;

-- ============================================
-- FIRMA: Neue Felder hinzufügen
-- ============================================
ALTER TABLE firma 
    CHANGE COLUMN firmenname name VARCHAR(255) NOT NULL,
    ADD COLUMN IF NOT EXISTS telefon VARCHAR(50) AFTER ort,
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) AFTER telefon,
    ADD COLUMN IF NOT EXISTS website VARCHAR(255) AFTER email,
    ADD COLUMN IF NOT EXISTS iban VARCHAR(34) AFTER finanzamt,
    ADD COLUMN IF NOT EXISTS bic VARCHAR(11) AFTER iban,
    ADD COLUMN IF NOT EXISTS bank VARCHAR(100) AFTER bic,
    ADD COLUMN IF NOT EXISTS geschaeftsjahr_beginn VARCHAR(5) DEFAULT '01-01' AFTER bank,
    ADD COLUMN IF NOT EXISTS ust_periode ENUM('monatlich', 'quartalsweise') DEFAULT 'monatlich' AFTER geschaeftsjahr_beginn,
    ADD COLUMN IF NOT EXISTS kleinunternehmer TINYINT(1) DEFAULT 0 AFTER ust_periode;

-- ============================================
-- UST_SAETZE: Felder umbenennen
-- ============================================
ALTER TABLE ust_saetze 
    CHANGE COLUMN name bezeichnung VARCHAR(100) NOT NULL,
    CHANGE COLUMN prozent satz DECIMAL(5,2) NOT NULL;

-- ============================================
-- KATEGORIEN: Farbe hinzufügen
-- ============================================
ALTER TABLE kategorien 
    ADD COLUMN IF NOT EXISTS farbe VARCHAR(7) DEFAULT '#6c757d' AFTER beschreibung;

-- Standardfarben setzen
UPDATE kategorien SET farbe = '#28a745' WHERE typ = 'einnahme' AND farbe = '#6c757d';
UPDATE kategorien SET farbe = '#dc3545' WHERE typ = 'ausgabe' AND farbe = '#6c757d';

-- ============================================
-- ANLAGEGUETER: Felder anpassen
-- ============================================
ALTER TABLE anlagegueter 
    ADD COLUMN IF NOT EXISTS kategorie VARCHAR(100) DEFAULT 'Sonstige' AFTER bezeichnung,
    CHANGE COLUMN anschaffungskosten anschaffungswert DECIMAL(12,2) NOT NULL,
    CHANGE COLUMN nutzungsdauer_jahre nutzungsdauer INT NOT NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('aktiv', 'ausgeschieden') DEFAULT 'aktiv' AFTER restwert,
    ADD COLUMN IF NOT EXISTS ausscheidungsgrund VARCHAR(255) AFTER ausscheidungsdatum;

-- Status aus ausgeschieden-Feld migrieren (falls vorhanden)
UPDATE anlagegueter SET status = 'ausgeschieden' WHERE ausgeschieden = 1;

-- Alte Spalte entfernen (optional - erst wenn Migration erfolgreich)
-- ALTER TABLE anlagegueter DROP COLUMN IF EXISTS ausgeschieden;

-- ============================================
-- AFA_BUCHUNGEN: Felder umbenennen
-- ============================================
ALTER TABLE afa_buchungen 
    CHANGE COLUMN buchwert_anfang restwert_vor DECIMAL(12,2) NOT NULL,
    CHANGE COLUMN buchwert_ende restwert_nach DECIMAL(12,2) NOT NULL;

-- ============================================
-- UST_VORANMELDUNGEN: Neue Kennzahlen
-- ============================================
ALTER TABLE ust_voranmeldungen 
    ADD COLUMN IF NOT EXISTS kz070 DECIMAL(12,2) DEFAULT 0 COMMENT 'Innergemeinschaftliche Erwerbe' AFTER kz052,
    ADD COLUMN IF NOT EXISTS kz071 DECIMAL(12,2) DEFAULT 0 COMMENT 'USt ig Erwerbe' AFTER kz070,
    ADD COLUMN IF NOT EXISTS kz082 DECIMAL(12,2) DEFAULT 0 COMMENT 'Vorsteuerberichtigung' AFTER kz066,
    ADD COLUMN IF NOT EXISTS kz095 DECIMAL(12,2) DEFAULT 0 COMMENT 'Zahllast/Gutschrift' AFTER kz082,
    ADD COLUMN IF NOT EXISTS notizen TEXT AFTER eingereicht_am;

-- ============================================
-- Neue Kategorien einfügen (falls nicht vorhanden)
-- ============================================
INSERT IGNORE INTO kategorien (name, typ, e1a_kennzahl, beschreibung, farbe) VALUES
('Rohstoffe', 'ausgabe', '9100', 'Rohstoffe und Hilfsstoffe', '#e83e8c'),
('Betriebskosten', 'ausgabe', '9140', 'Strom, Heizung, Wasser', '#795548'),
('Fortbildung', 'ausgabe', '9150', 'Kurse, Seminare, Fachliteratur', '#9e9e9e'),
('Bankspesen', 'ausgabe', '9150', 'Kontoführung, Überweisungen', '#455a64');

-- ============================================
-- INDIZES hinzufügen (falls nicht vorhanden)
-- ============================================
-- Rechnungen
CREATE INDEX IF NOT EXISTS idx_datum ON rechnungen(datum);
CREATE INDEX IF NOT EXISTS idx_typ ON rechnungen(typ);
CREATE INDEX IF NOT EXISTS idx_bezahlt ON rechnungen(bezahlt);

-- Anlagegüter
CREATE INDEX IF NOT EXISTS idx_status ON anlagegueter(status);
CREATE INDEX IF NOT EXISTS idx_ansch_datum ON anlagegueter(anschaffungsdatum);

-- AfA-Buchungen
CREATE INDEX IF NOT EXISTS idx_jahr ON afa_buchungen(jahr);

-- USt-Voranmeldungen
CREATE INDEX IF NOT EXISTS idx_ust_jahr ON ust_voranmeldungen(jahr);

-- ============================================
-- FERTIG
-- ============================================
SELECT 'Datenbank-Update erfolgreich!' AS Status;
