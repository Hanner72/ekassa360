-- EKassa360 v1.6 - Saubere Neuinstallation
-- Für Österreichische Kleinunternehmer
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Tabellen-Struktur
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `benutzer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `benutzername` varchar(50) NOT NULL,
  `passwort_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `vorname` varchar(50) DEFAULT NULL,
  `nachname` varchar(50) DEFAULT NULL,
  `rolle` enum('admin','benutzer') DEFAULT 'benutzer',
  `aktiv` tinyint(1) DEFAULT 1,
  `passwort_muss_geaendert` tinyint(1) DEFAULT 0,
  `letzter_login` datetime DEFAULT NULL,
  `fehlversuche` int DEFAULT 0,
  `gesperrt_bis` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `benutzername` (`benutzername`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `firma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `strasse` varchar(255) DEFAULT NULL,
  `plz` varchar(10) DEFAULT NULL,
  `ort` varchar(100) DEFAULT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `uid_nummer` varchar(20) DEFAULT NULL,
  `steuernummer` varchar(30) DEFAULT NULL,
  `finanzamt` varchar(100) DEFAULT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `bic` varchar(11) DEFAULT NULL,
  `bank` varchar(100) DEFAULT NULL,
  `geschaeftsjahr_beginn` date DEFAULT NULL,
  `ust_periode` enum('monatlich','quartalsweise') DEFAULT 'monatlich',
  `kleinunternehmer` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ust_saetze` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bezeichnung` varchar(100) NOT NULL,
  `satz` decimal(5,2) NOT NULL,
  `u30_kennzahl_bemessung` varchar(10) DEFAULT NULL,
  `u30_kennzahl_steuer` varchar(10) DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `kategorien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `typ` enum('einnahme','ausgabe') NOT NULL,
  `e1a_kennzahl` varchar(10) DEFAULT NULL,
  `beschreibung` text DEFAULT NULL,
  `farbe` varchar(7) DEFAULT '#6c757d',
  `aktiv` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `rechnungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `buchungsnummer` int DEFAULT NULL,
  `datum` date NOT NULL,
  `beschreibung` varchar(500) NOT NULL,
  `netto_betrag` decimal(12,2) NOT NULL,
  `ust_satz_id` int DEFAULT NULL,
  `ust_betrag` decimal(12,2) DEFAULT 0.00,
  `brutto_betrag` decimal(12,2) NOT NULL,
  `typ` enum('einnahme','ausgabe') NOT NULL,
  `kategorie_id` int DEFAULT NULL,
  `kunde_lieferant` varchar(255) DEFAULT NULL,
  `rechnungsnummer_extern` varchar(100) DEFAULT NULL,
  `zahlungsstatus` enum('offen','bezahlt','storniert') DEFAULT 'bezahlt',
  `zahlungsdatum` date DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `beleg_pfad` varchar(500) DEFAULT NULL,
  `erstellt_von` int DEFAULT NULL,
  `geaendert_von` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_typ` (`typ`),
  KEY `idx_kategorie` (`kategorie_id`),
  KEY `idx_ust_satz` (`ust_satz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `anlagegueter` (
  `id` int NOT NULL AUTO_INCREMENT,
  `buchungsnummer` int DEFAULT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `kategorie` varchar(100) DEFAULT 'Sonstige',
  `anschaffungsdatum` date NOT NULL,
  `anschaffungswert` decimal(12,2) NOT NULL,
  `netto_betrag` decimal(12,2) DEFAULT NULL,
  `ust_satz_id` int DEFAULT NULL,
  `ust_betrag` decimal(12,2) DEFAULT 0.00,
  `nutzungsdauer` int NOT NULL COMMENT 'Jahre',
  `afa_methode` enum('linear','degressiv') DEFAULT 'linear',
  `e1a_kennzahl` enum('9130','9134','9135') DEFAULT '9130',
  `restwert` decimal(12,2) DEFAULT 1.00,
  `status` enum('aktiv','ausgeschieden') DEFAULT 'aktiv',
  `ausscheidungsdatum` date DEFAULT NULL,
  `ausscheidungsgrund` varchar(255) DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `erstellt_von` int DEFAULT NULL,
  `geaendert_von` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `afa_buchungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `anlagegut_id` int NOT NULL,
  `jahr` int NOT NULL,
  `afa_betrag` decimal(12,2) NOT NULL,
  `restwert_vor` decimal(12,2) NOT NULL,
  `restwert_nach` decimal(12,2) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_anlagegut` (`anlagegut_id`),
  KEY `idx_jahr` (`jahr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `ust_voranmeldungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jahr` int NOT NULL,
  `monat` int NOT NULL,
  `zeitraum_typ` enum('monat','quartal') DEFAULT 'monat',
  `kz000` decimal(12,2) DEFAULT 0.00 COMMENT 'Gesamtbetrag Lieferungen/Leistungen',
  `kz001` decimal(12,2) DEFAULT 0.00 COMMENT 'Eigenverbrauch',
  `kz021` decimal(12,2) DEFAULT 0.00 COMMENT 'Nicht steuerbare Auslandsums.',
  `kz022` decimal(12,2) DEFAULT 0.00 COMMENT 'Bemessungsgrundlage 20%',
  `kz029` decimal(12,2) DEFAULT 0.00 COMMENT 'Steuer 10%',
  `kz025` decimal(12,2) DEFAULT 0.00 COMMENT 'Steuer 19%',
  `kz027` decimal(12,2) DEFAULT 0.00 COMMENT 'Steuer 10% (aus 022)',
  `kz035` decimal(12,2) DEFAULT 0.00 COMMENT 'Steuer 13%',
  `kz052` decimal(12,2) DEFAULT 0.00 COMMENT 'USt Sonstige',
  `kz060` decimal(12,2) DEFAULT 0.00 COMMENT 'Vorsteuer',
  `kz061` decimal(12,2) DEFAULT 0.00 COMMENT 'Einfuhr-USt Drittland',
  `kz065` decimal(12,2) DEFAULT 0.00 COMMENT 'VSt ig Erwerb',
  `kz066` decimal(12,2) DEFAULT 0.00 COMMENT 'VSt Reverse Charge',
  `kz070` decimal(12,2) DEFAULT 0.00 COMMENT 'ig Erwerbe',
  `kz072` decimal(12,2) DEFAULT 0.00 COMMENT 'Erwerbsteuer 20%',
  `kz082` decimal(12,2) DEFAULT 0.00 COMMENT 'VSt Berichtigung',
  `kz095` decimal(12,2) DEFAULT 0.00 COMMENT 'Zahllast/Gutschrift',
  `zahllast` decimal(12,2) DEFAULT 0.00,
  `eingereicht` tinyint(1) DEFAULT 0,
  `eingereicht_am` date DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_periode` (`jahr`, `monat`, `zeitraum_typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `einkommensteuer` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jahr` int NOT NULL,
  `kz9040` decimal(12,2) DEFAULT 0.00 COMMENT 'Erlöse Waren',
  `kz9050` decimal(12,2) DEFAULT 0.00 COMMENT 'Erlöse Dienstleistungen',
  `kz9100` decimal(12,2) DEFAULT 0.00 COMMENT 'Wareneinkauf',
  `kz9110` decimal(12,2) DEFAULT 0.00 COMMENT 'Fremdleistungen',
  `kz9120` decimal(12,2) DEFAULT 0.00 COMMENT 'Personalaufwand',
  `kz9130` decimal(12,2) DEFAULT 0.00 COMMENT 'AfA normal',
  `kz9134` decimal(12,2) DEFAULT 0.00 COMMENT 'AfA degressiv',
  `kz9135` decimal(12,2) DEFAULT 0.00 COMMENT 'AfA Gebäude',
  `kz9140` decimal(12,2) DEFAULT 0.00 COMMENT 'Betriebsräumlichkeiten',
  `kz9150` decimal(12,2) DEFAULT 0.00 COMMENT 'Sonstige Ausgaben',
  `weitere_kennzahlen` json DEFAULT NULL COMMENT 'Dynamische Kennzahlen als JSON',
  `gewinn_verlust` decimal(12,2) DEFAULT 0.00,
  `eingereicht` tinyint(1) DEFAULT 0,
  `eingereicht_am` date DEFAULT NULL,
  `notizen` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_jahr` (`jahr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `aenderungsprotokoll` (
  `id` int NOT NULL AUTO_INCREMENT,
  `benutzer_id` int DEFAULT NULL,
  `benutzer_name` varchar(50) DEFAULT NULL,
  `tabelle` varchar(50) NOT NULL,
  `datensatz_id` int DEFAULT NULL,
  `aktion` enum('erstellt','geaendert','geloescht','login','logout','passwort_geaendert') NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `alte_werte` json DEFAULT NULL,
  `neue_werte` json DEFAULT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_benutzer` (`benutzer_id`),
  KEY `idx_tabelle` (`tabelle`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Standard-Daten: USt-Sätze
-- --------------------------------------------------------

INSERT INTO `ust_saetze` (`bezeichnung`, `satz`, `u30_kennzahl_bemessung`, `u30_kennzahl_steuer`, `aktiv`) VALUES
('20% Normalsteuersatz', 20.00, '022', '029', 1),
('10% ermäßigt', 10.00, NULL, '027', 1),
('13% ermäßigt', 13.00, NULL, '035', 1),
('0% steuerfrei', 0.00, '021', NULL, 1),
('0% ig Lieferung (EU B2B)', 0.00, '017', NULL, 1),
('0% Reverse Charge (EU B2B)', 0.00, '021', NULL, 1),
('20% innergemeinschaftl. Erwerb', 20.00, '070', '072', 1),
('0% Drittland Export', 0.00, '021', NULL, 1),
('20% Drittland Import', 20.00, NULL, '061', 1);

-- --------------------------------------------------------
-- Standard-Daten: Kategorien
-- --------------------------------------------------------

INSERT INTO `kategorien` (`name`, `typ`, `e1a_kennzahl`, `beschreibung`, `farbe`, `aktiv`) VALUES
-- Einnahmen
('Warenverkauf', 'einnahme', '9040', 'Verkauf von Waren und Produkten', '#28a745', 1),
('Dienstleistungen', 'einnahme', '9050', 'IT-Dienstleistungen, Beratung, Entwicklung', '#20c997', 1),
('Provisionen', 'einnahme', '9050', 'Vermittlungsprovisionen', '#17a2b8', 1),
('Sonstige Einnahmen', 'einnahme', '9050', 'Andere betriebliche Einnahmen', '#6c757d', 1),
-- Ausgaben
('Wareneinkauf', 'ausgabe', '9100', 'Einkauf von Waren zum Weiterverkauf', '#dc3545', 1),
('Fremdleistungen', 'ausgabe', '9110', 'Subunternehmer, externe Dienstleister', '#fd7e14', 1),
('Personalkosten', 'ausgabe', '9120', 'Gehälter, Löhne, Lohnnebenkosten', '#e83e8c', 1),
('Bürokosten', 'ausgabe', '9150', 'Büromaterial, Porto, Telefon', '#6f42c1', 1),
('Miete/Betriebsräume', 'ausgabe', '9140', 'Miete, Strom, Heizung, Reinigung', '#007bff', 1),
('KFZ-Kosten', 'ausgabe', '9150', 'Treibstoff, Versicherung, Reparaturen', '#ffc107', 1),
('Reisekosten', 'ausgabe', '9160', 'Reisekosten, Kilometergeld, Diäten', '#795548', 1),
('Werbung', 'ausgabe', '9170', 'Marketing, Online-Werbung, Drucksachen', '#ff5722', 1),
('Versicherungen', 'ausgabe', '9180', 'Betriebliche Versicherungen', '#9e9e9e', 1),
('Fortbildung', 'ausgabe', '9190', 'Schulungen, Kurse, Fachliteratur', '#4caf50', 1),
('Beratungskosten', 'ausgabe', '9200', 'Steuerberater, Rechtsanwalt', '#3f51b5', 1),
('SVS-Beiträge', 'ausgabe', '9225', 'Sozialversicherung der Selbständigen', '#9c27b0', 1),
('Bankspesen', 'ausgabe', '9230', 'Kontoführung, Überweisungsgebühren', '#00bcd4', 1),
('Sonstige Ausgaben', 'ausgabe', '9230', 'Andere betriebliche Ausgaben', '#6c757d', 1);
