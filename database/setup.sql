-- ============================================
-- Buchhaltungs-App Datenbank Setup
-- Version 1.1 - Vollständig für Österreich
-- ============================================

CREATE DATABASE IF NOT EXISTS buchhaltung_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE buchhaltung_db;

-- ============================================
-- TABELLEN LÖSCHEN (für Neuinstallation)
-- Kommentiere diese Zeilen aus wenn du Daten behalten willst!
-- ============================================
DROP TABLE IF EXISTS afa_buchungen;
DROP TABLE IF EXISTS anlagegueter;
DROP TABLE IF EXISTS rechnungen;
DROP TABLE IF EXISTS einkommensteuer;
DROP TABLE IF EXISTS ust_voranmeldungen;
DROP TABLE IF EXISTS kategorien;
DROP TABLE IF EXISTS ust_saetze;
DROP TABLE IF EXISTS firma;

-- ============================================
-- FIRMENDATEN
-- ============================================
CREATE TABLE firma (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    strasse VARCHAR(255),
    plz VARCHAR(10),
    ort VARCHAR(100),
    telefon VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    uid_nummer VARCHAR(20),
    steuernummer VARCHAR(50),
    finanzamt VARCHAR(100),
    iban VARCHAR(34),
    bic VARCHAR(11),
    bank VARCHAR(100),
    geschaeftsjahr_beginn VARCHAR(5) DEFAULT '01-01',
    ust_periode ENUM('monatlich', 'quartalsweise') DEFAULT 'monatlich',
    kleinunternehmer TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- UST-SÄTZE
-- ============================================
CREATE TABLE ust_saetze (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bezeichnung VARCHAR(100) NOT NULL,
    satz DECIMAL(5,2) NOT NULL,
    u30_kennzahl_bemessung VARCHAR(10) COMMENT 'U30 Kennzahl für Bemessungsgrundlage',
    u30_kennzahl_steuer VARCHAR(10) COMMENT 'U30 Kennzahl für Steuer',
    aktiv TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- KATEGORIEN
-- ============================================
CREATE TABLE kategorien (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    typ ENUM('einnahme', 'ausgabe') NOT NULL,
    e1a_kennzahl VARCHAR(10) DEFAULT NULL COMMENT 'Zuordnung zur E1a Kennzahl',
    beschreibung TEXT,
    farbe VARCHAR(7) DEFAULT '#6c757d',
    aktiv TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- RECHNUNGEN (Einnahmen und Ausgaben)
-- ============================================
CREATE TABLE rechnungen (
    id INT PRIMARY KEY AUTO_INCREMENT,
    typ ENUM('einnahme', 'ausgabe') NOT NULL,
    rechnungsnummer VARCHAR(50),
    datum DATE NOT NULL,
    faellig_am DATE,
    kunde_lieferant VARCHAR(255) DEFAULT '',
    beschreibung TEXT,
    netto_betrag DECIMAL(12,2) NOT NULL,
    ust_satz_id INT,
    ust_betrag DECIMAL(12,2) DEFAULT 0,
    brutto_betrag DECIMAL(12,2) NOT NULL,
    kategorie_id INT,
    bezahlt TINYINT(1) DEFAULT 0,
    bezahlt_am DATE,
    dokument_pfad VARCHAR(500),
    notizen TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ust_satz_id) REFERENCES ust_saetze(id) ON DELETE SET NULL,
    FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE SET NULL,
    INDEX idx_datum (datum),
    INDEX idx_typ (typ),
    INDEX idx_bezahlt (bezahlt)
);

-- ============================================
-- ANLAGEGÜTER (für AfA)
-- ============================================
CREATE TABLE anlagegueter (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bezeichnung VARCHAR(255) NOT NULL,
    kategorie VARCHAR(100) DEFAULT 'Sonstige',
    anschaffungsdatum DATE NOT NULL,
    anschaffungswert DECIMAL(12,2) NOT NULL,
    nutzungsdauer INT NOT NULL COMMENT 'Jahre',
    afa_methode ENUM('linear', 'degressiv') DEFAULT 'linear',
    restwert DECIMAL(12,2) DEFAULT 1.00 COMMENT 'Erinnerungswert',
    status ENUM('aktiv', 'ausgeschieden') DEFAULT 'aktiv',
    ausscheidungsdatum DATE,
    ausscheidungsgrund VARCHAR(255),
    notizen TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_datum (anschaffungsdatum)
);

-- ============================================
-- AFA-BUCHUNGEN (pro Jahr)
-- ============================================
CREATE TABLE afa_buchungen (
    id INT PRIMARY KEY AUTO_INCREMENT,
    anlagegut_id INT NOT NULL,
    jahr INT NOT NULL,
    afa_betrag DECIMAL(12,2) NOT NULL,
    restwert_vor DECIMAL(12,2) NOT NULL,
    restwert_nach DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (anlagegut_id) REFERENCES anlagegueter(id) ON DELETE CASCADE,
    UNIQUE KEY unique_afa (anlagegut_id, jahr),
    INDEX idx_jahr (jahr)
);

-- ============================================
-- UST-VORANMELDUNGEN (U30)
-- ============================================
CREATE TABLE ust_voranmeldungen (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jahr INT NOT NULL,
    monat INT NOT NULL COMMENT '1-12 für Monat, 1-4 für Quartale',
    zeitraum_typ ENUM('monat', 'quartal') DEFAULT 'monat',
    -- Lieferungen und Leistungen
    kz000 DECIMAL(12,2) DEFAULT 0 COMMENT 'Gesamtbetrag Lieferungen',
    kz001 DECIMAL(12,2) DEFAULT 0 COMMENT 'Eigenverbrauch',
    kz021 DECIMAL(12,2) DEFAULT 0 COMMENT 'Innergemeinschaftliche Lieferungen (steuerfrei)',
    -- 20% USt
    kz022 DECIMAL(12,2) DEFAULT 0 COMMENT 'Bemessungsgrundlage 20%',
    kz029 DECIMAL(12,2) DEFAULT 0 COMMENT 'USt 20%',
    -- 10% USt
    kz025 DECIMAL(12,2) DEFAULT 0 COMMENT 'Bemessungsgrundlage 10%',
    kz027 DECIMAL(12,2) DEFAULT 0 COMMENT 'USt 10%',
    -- 13% USt
    kz035 DECIMAL(12,2) DEFAULT 0 COMMENT 'Bemessungsgrundlage 13%',
    kz052 DECIMAL(12,2) DEFAULT 0 COMMENT 'USt 13%',
    -- Weitere Kennzahlen
    kz070 DECIMAL(12,2) DEFAULT 0 COMMENT 'Innergemeinschaftliche Erwerbe',
    kz071 DECIMAL(12,2) DEFAULT 0 COMMENT 'USt ig Erwerbe',
    -- Vorsteuer
    kz060 DECIMAL(12,2) DEFAULT 0 COMMENT 'Vorsteuer gesamt',
    kz065 DECIMAL(12,2) DEFAULT 0 COMMENT 'Vorsteuer aus ig Erwerb',
    kz066 DECIMAL(12,2) DEFAULT 0 COMMENT 'Einfuhrumsatzsteuer',
    kz082 DECIMAL(12,2) DEFAULT 0 COMMENT 'Vorsteuerberichtigung',
    -- Ergebnis
    kz095 DECIMAL(12,2) DEFAULT 0 COMMENT 'Zahllast/Gutschrift',
    zahllast DECIMAL(12,2) DEFAULT 0 COMMENT 'Positiv = Zahllast, Negativ = Gutschrift',
    eingereicht TINYINT(1) DEFAULT 0,
    eingereicht_am DATE,
    notizen TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_periode (jahr, monat, zeitraum_typ),
    INDEX idx_jahr (jahr)
);

-- ============================================
-- EINKOMMENSTEUER (E1a)
-- ============================================
CREATE TABLE einkommensteuer (
    id INT PRIMARY KEY AUTO_INCREMENT,
    jahr INT NOT NULL UNIQUE,
    -- Betriebseinnahmen
    kz9040 DECIMAL(12,2) DEFAULT 0 COMMENT 'Erlöse aus Waren/Erzeugnissen',
    kz9050 DECIMAL(12,2) DEFAULT 0 COMMENT 'Erlöse aus Dienstleistungen',
    -- Betriebsausgaben
    kz9100 DECIMAL(12,2) DEFAULT 0 COMMENT 'Wareneinkauf/Rohstoffe',
    kz9110 DECIMAL(12,2) DEFAULT 0 COMMENT 'Personalaufwand',
    kz9120 DECIMAL(12,2) DEFAULT 0 COMMENT 'Abschreibungen (AfA)',
    kz9130 DECIMAL(12,2) DEFAULT 0 COMMENT 'Fremdleistungen',
    kz9140 DECIMAL(12,2) DEFAULT 0 COMMENT 'Betriebsräumlichkeiten',
    kz9150 DECIMAL(12,2) DEFAULT 0 COMMENT 'Sonstige Betriebsausgaben',
    -- Ergebnis
    gewinn_verlust DECIMAL(12,2) DEFAULT 0,
    eingereicht TINYINT(1) DEFAULT 0,
    eingereicht_am DATE,
    notizen TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- STANDARDDATEN EINFÜGEN
-- ============================================

-- USt-Sätze Österreich
INSERT INTO ust_saetze (bezeichnung, satz, u30_kennzahl_bemessung, u30_kennzahl_steuer, aktiv) VALUES
('Normalsteuersatz 20%', 20.00, '022', '029', 1),
('Ermäßigt 10%', 10.00, '025', '027', 1),
('Ermäßigt 13%', 13.00, '035', '052', 1),
('Steuerfrei 0%', 0.00, NULL, NULL, 1),
('Innergemeinschaftlich', 0.00, '021', NULL, 1),
('Reverse Charge', 0.00, NULL, NULL, 1);

-- Kategorien Einnahmen
INSERT INTO kategorien (name, typ, e1a_kennzahl, beschreibung, farbe) VALUES
('Umsatzerlöse Waren', 'einnahme', '9040', 'Verkauf von Waren und Erzeugnissen', '#28a745'),
('Umsatzerlöse Dienstleistungen', 'einnahme', '9050', 'Einnahmen aus Dienstleistungen', '#20c997'),
('Sonstige Einnahmen', 'einnahme', '9050', 'Provisionen, Nebenerlöse etc.', '#17a2b8');

-- Kategorien Ausgaben
INSERT INTO kategorien (name, typ, e1a_kennzahl, beschreibung, farbe) VALUES
('Wareneinkauf', 'ausgabe', '9100', 'Einkauf von Waren und Materialien', '#dc3545'),
('Rohstoffe', 'ausgabe', '9100', 'Rohstoffe und Hilfsstoffe', '#e83e8c'),
('Personalkosten', 'ausgabe', '9110', 'Löhne, Gehälter, Sozialabgaben', '#fd7e14'),
('Fremdleistungen', 'ausgabe', '9130', 'Externe Dienstleister, Subunternehmer', '#6f42c1'),
('Miete Büro/Lager', 'ausgabe', '9140', 'Miete für Betriebsräume', '#6c757d'),
('Betriebskosten', 'ausgabe', '9140', 'Strom, Heizung, Wasser', '#795548'),
('Büromaterial', 'ausgabe', '9150', 'Bürobedarf, Druckerkosten', '#607d8b'),
('Telefon/Internet', 'ausgabe', '9150', 'Kommunikationskosten', '#00bcd4'),
('Versicherungen', 'ausgabe', '9150', 'Betriebliche Versicherungen', '#9c27b0'),
('Werbung/Marketing', 'ausgabe', '9150', 'Werbung, Marketing, Website', '#ff5722'),
('Reisekosten', 'ausgabe', '9150', 'Dienstreisen, Hotel, Verpflegung', '#4caf50'),
('KFZ-Kosten', 'ausgabe', '9150', 'Treibstoff, Reparatur, Versicherung', '#2196f3'),
('Fortbildung', 'ausgabe', '9150', 'Kurse, Seminare, Fachliteratur', '#9e9e9e'),
('Bankspesen', 'ausgabe', '9150', 'Kontoführung, Überweisungen', '#455a64'),
('Sonstige Ausgaben', 'ausgabe', '9150', 'Sonstige betriebliche Ausgaben', '#78909c');

-- Beispiel-Firmendaten
INSERT INTO firma (name, strasse, plz, ort, telefon, email, uid_nummer, steuernummer, finanzamt, ust_periode) VALUES
('Meine Firma GmbH', 'Musterstraße 1', '1010', 'Wien', '+43 1 234 5678', 'office@meinefirma.at', 'ATU12345678', '12 345/6789', 'Finanzamt Wien 1/23', 'monatlich');

-- ============================================
-- BEISPIELDATEN (optional - zum Testen)
-- ============================================

-- Beispiel-Rechnungen 2024
INSERT INTO rechnungen (typ, rechnungsnummer, datum, kunde_lieferant, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, kategorie_id, bezahlt, bezahlt_am) VALUES
-- Einnahmen
('einnahme', 'RE-2024-001', '2024-01-15', 'Kunde A GmbH', 'Beratungsleistungen Jänner', 2500.00, 1, 500.00, 3000.00, 2, 1, '2024-01-25'),
('einnahme', 'RE-2024-002', '2024-02-10', 'Kunde B KG', 'Projektarbeit Februar', 4200.00, 1, 840.00, 5040.00, 2, 1, '2024-02-28'),
('einnahme', 'RE-2024-003', '2024-03-20', 'Kunde C', 'Warenlieferung', 1800.00, 1, 360.00, 2160.00, 1, 1, '2024-04-05'),
('einnahme', 'RE-2024-004', '2024-04-05', 'Kunde A GmbH', 'Beratung Q1 Abschluss', 3500.00, 1, 700.00, 4200.00, 2, 0, NULL),
-- Ausgaben
('ausgabe', 'ER-2024-001', '2024-01-05', 'Büro Meier', 'Miete Jänner', 800.00, 1, 160.00, 960.00, 8, 1, '2024-01-05'),
('ausgabe', 'ER-2024-002', '2024-01-10', 'A1 Telekom', 'Internet Jänner', 45.00, 1, 9.00, 54.00, 11, 1, '2024-01-15'),
('ausgabe', 'ER-2024-003', '2024-02-01', 'Büro Meier', 'Miete Februar', 800.00, 1, 160.00, 960.00, 8, 1, '2024-02-01'),
('ausgabe', 'ER-2024-004', '2024-02-15', 'Lieferant X', 'Wareneinkauf', 1200.00, 1, 240.00, 1440.00, 4, 1, '2024-02-20'),
('ausgabe', 'ER-2024-005', '2024-03-01', 'Büro Meier', 'Miete März', 800.00, 1, 160.00, 960.00, 8, 1, '2024-03-01');

-- Beispiel Anlagegüter
INSERT INTO anlagegueter (bezeichnung, kategorie, anschaffungsdatum, anschaffungswert, nutzungsdauer, afa_methode, restwert, status) VALUES
('MacBook Pro 16"', 'EDV', '2024-01-15', 2800.00, 3, 'linear', 1.00, 'aktiv'),
('Büromöbel Set', 'Einrichtung', '2024-02-01', 1500.00, 10, 'linear', 1.00, 'aktiv'),
('Drucker Canon', 'EDV', '2023-06-15', 450.00, 3, 'linear', 1.00, 'aktiv');

-- AfA-Buchungen für Beispieldaten
INSERT INTO afa_buchungen (anlagegut_id, jahr, afa_betrag, restwert_vor, restwert_nach) VALUES
-- MacBook 2024 (Halbjahresregel: ab Jänner = volles Jahr)
(1, 2024, 933.00, 2800.00, 1867.00),
-- Büromöbel 2024 (ab Februar = volles Jahr)
(2, 2024, 150.00, 1500.00, 1350.00),
-- Drucker 2023 (ab Juni = halbes Jahr)
(3, 2023, 75.00, 450.00, 375.00),
(3, 2024, 150.00, 375.00, 225.00);

-- ============================================
-- ENDE SETUP
-- ============================================
