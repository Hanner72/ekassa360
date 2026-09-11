-- EKassa360: Verkauf-Modul (Kunden, Artikel, Angebote/Aufträge/Rechnungen)
-- Führt Kundenstamm, Artikelstamm und Verkaufsdokumente mit Positionen ein.
-- Diese Tabellen sind bewusst getrennt von der Buchhaltungs-Ledger-Tabelle
-- `rechnungen` (U30/E1a-Grundlage) - siehe add_paperless_columns.sql für die Verknüpfung.

-- Wichtig: erzwingt UTF-8 auf der Verbindung, damit Umlaute (Österreich, Verkaufserlöse)
-- unabhängig vom Client-Default-Charset korrekt gespeichert werden (siehe ekassa360.sql).
SET NAMES utf8mb4;

CREATE TABLE `kunden` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `kundennummer` VARCHAR(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `firma_name` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `anrede` VARCHAR(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vorname` VARCHAR(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nachname` VARCHAR(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `strasse` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plz` VARCHAR(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ort` VARCHAR(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `land` VARCHAR(100) COLLATE utf8mb4_general_ci DEFAULT 'Österreich',
  `uid_nummer` VARCHAR(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefon` VARCHAR(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notizen` TEXT COLLATE utf8mb4_general_ci,
  `paperless_correspondent_id` INT DEFAULT NULL,
  `aktiv` TINYINT(1) DEFAULT '1',
  `erstellt_von` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_kundennummer` (`kundennummer`),
  KEY `idx_aktiv` (`aktiv`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `artikel` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `artikelnummer` VARCHAR(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bezeichnung` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  `beschreibung` TEXT COLLATE utf8mb4_general_ci,
  `einheit` VARCHAR(20) COLLATE utf8mb4_general_ci DEFAULT 'Stk',
  `einzelpreis_netto` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `ust_satz_id` INT DEFAULT NULL,
  `kategorie_id` INT DEFAULT NULL,
  `aktiv` TINYINT(1) DEFAULT '1',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_artikelnummer` (`artikelnummer`),
  KEY `idx_aktiv` (`aktiv`),
  CONSTRAINT `artikel_ibfk_1` FOREIGN KEY (`ust_satz_id`) REFERENCES `ust_saetze` (`id`) ON DELETE SET NULL,
  CONSTRAINT `artikel_ibfk_2` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `nummernkreise` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `schluessel` ENUM('rechnung','angebot','auftrag') COLLATE utf8mb4_general_ci NOT NULL,
  `jahr` INT NOT NULL,
  `prefix` VARCHAR(10) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `naechste_nummer` INT NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_schluessel_jahr` (`schluessel`,`jahr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `verkaufsdokumente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `typ` ENUM('angebot','auftrag','rechnung') COLLATE utf8mb4_general_ci NOT NULL,
  `status` ENUM('entwurf','versendet','angenommen','abgelehnt','abgeschlossen','storniert') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'entwurf',
  `nummer` VARCHAR(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kunde_id` INT DEFAULT NULL,
  `vorgaenger_id` INT DEFAULT NULL,
  `storno_von_id` INT DEFAULT NULL,
  `wiederkehrend_id` INT DEFAULT NULL,
  `datum` DATE NOT NULL,
  `leistungsdatum` DATE DEFAULT NULL,
  `gueltig_bis` DATE DEFAULT NULL,
  `faellig_am` DATE DEFAULT NULL,
  `betreff` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `einleitungstext` TEXT COLLATE utf8mb4_general_ci,
  `schlusstext` TEXT COLLATE utf8mb4_general_ci,
  `netto_gesamt` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `ust_gesamt` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `brutto_gesamt` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `pdf_pfad` VARCHAR(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paperless_document_id` INT DEFAULT NULL,
  `notizen` TEXT COLLATE utf8mb4_general_ci,
  `erstellt_von` INT DEFAULT NULL,
  `geaendert_von` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_status` (`status`),
  KEY `idx_nummer` (`nummer`),
  KEY `idx_kunde` (`kunde_id`),
  CONSTRAINT `verkaufsdokumente_ibfk_1` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokumente_ibfk_2` FOREIGN KEY (`vorgaenger_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokumente_ibfk_3` FOREIGN KEY (`storno_von_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `verkaufsdokument_positionen` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `verkaufsdokument_id` INT NOT NULL,
  `position` INT NOT NULL DEFAULT '1',
  `artikel_id` INT DEFAULT NULL,
  `bezeichnung` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  `beschreibung` TEXT COLLATE utf8mb4_general_ci,
  `menge` DECIMAL(10,2) NOT NULL DEFAULT '1.00',
  `einheit` VARCHAR(20) COLLATE utf8mb4_general_ci DEFAULT 'Stk',
  `einzelpreis_netto` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `ust_satz_id` INT DEFAULT NULL,
  `rabatt_prozent` DECIMAL(5,2) NOT NULL DEFAULT '0.00',
  `netto_summe` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `ust_summe` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `brutto_summe` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_verkaufsdokument` (`verkaufsdokument_id`),
  CONSTRAINT `verkaufsdokument_positionen_ibfk_1` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verkaufsdokument_positionen_ibfk_2` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokument_positionen_ibfk_3` FOREIGN KEY (`ust_satz_id`) REFERENCES `ust_saetze` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed: Nummernkreise für das laufende Jahr
INSERT INTO `nummernkreise` (`schluessel`, `jahr`, `prefix`, `naechste_nummer`) VALUES
('rechnung', YEAR(CURDATE()), 'RE-', 1),
('angebot', YEAR(CURDATE()), 'AN-', 1),
('auftrag', YEAR(CURDATE()), 'AU-', 1);

-- Seed: Default-Kategorie für Verkaufserlöse aus dem neuen Modul
INSERT INTO `kategorien` (`name`, `typ`, `e1a_kennzahl`, `beschreibung`, `farbe`, `aktiv`) VALUES
('Verkaufserlöse', 'einnahme', '9040', 'Erlöse aus Angeboten/Aufträgen/Rechnungen des Verkauf-Moduls', '#0d6efd', 1);
