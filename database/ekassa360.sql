-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 15, 2026 at 03:25 PM
-- Server version: 8.0.30
-- PHP Version: 8.3.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ekassa360`
--

-- --------------------------------------------------------

--
-- Table structure for table `aenderungsprotokoll`
--

CREATE TABLE `aenderungsprotokoll` (
  `id` int NOT NULL,
  `benutzer_id` int DEFAULT NULL,
  `benutzer_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tabelle` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `datensatz_id` int DEFAULT NULL,
  `aktion` enum('erstellt','geaendert','geloescht','login','logout','passwort_geaendert') COLLATE utf8mb4_general_ci NOT NULL,
  `beschreibung` text COLLATE utf8mb4_general_ci,
  `alte_werte` json DEFAULT NULL,
  `neue_werte` json DEFAULT NULL,
  `ip_adresse` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `aenderungsprotokoll`
--

INSERT INTO `aenderungsprotokoll` (`id`, `benutzer_id`, `benutzer_name`, `tabelle`, `datensatz_id`, `aktion`, `beschreibung`, `alte_werte`, `neue_werte`, `ip_adresse`, `created_at`) VALUES
(46, 1, 'admin', 'login', 1, 'logout', 'Abgemeldet: admin', NULL, NULL, '::1', '2026-01-15 15:24:44'),
(47, 1, 'admin', 'login', 1, 'login', 'Erfolgreich: admin', NULL, NULL, '::1', '2026-01-15 15:24:49');

-- --------------------------------------------------------

--
-- Table structure for table `afa_buchungen`
--

CREATE TABLE `afa_buchungen` (
  `id` int NOT NULL,
  `anlagegut_id` int NOT NULL,
  `jahr` int NOT NULL,
  `afa_betrag` decimal(12,2) NOT NULL,
  `restwert_vor` decimal(12,2) NOT NULL,
  `restwert_nach` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `anlagegueter`
--

CREATE TABLE `anlagegueter` (
  `id` int NOT NULL,
  `bezeichnung` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `kategorie` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Sonstige',
  `anschaffungsdatum` date NOT NULL,
  `anschaffungswert` decimal(12,2) NOT NULL,
  `nutzungsdauer` int NOT NULL,
  `afa_methode` enum('linear','degressiv') COLLATE utf8mb4_general_ci DEFAULT 'linear',
  `e1a_kennzahl` enum('9130','9134','9135') COLLATE utf8mb4_general_ci DEFAULT '9130',
  `restwert` decimal(12,2) DEFAULT '1.00',
  `status` enum('aktiv','ausgeschieden') COLLATE utf8mb4_general_ci DEFAULT 'aktiv',
  `ausscheidungsdatum` date DEFAULT NULL,
  `ausscheidungsgrund` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notizen` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `buchungsnummer` int DEFAULT NULL,
  `ust_satz_id` int DEFAULT NULL,
  `ust_betrag` decimal(12,2) DEFAULT '0.00',
  `netto_betrag` decimal(12,2) DEFAULT NULL,
  `erstellt_von` int DEFAULT NULL,
  `geaendert_von` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `benutzer`
--

CREATE TABLE `benutzer` (
  `id` int NOT NULL,
  `benutzername` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `passwort_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vorname` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nachname` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rolle` enum('admin','benutzer') COLLATE utf8mb4_general_ci DEFAULT 'benutzer',
  `aktiv` tinyint(1) DEFAULT '1',
  `passwort_muss_geaendert` tinyint(1) DEFAULT '0',
  `letzter_login` datetime DEFAULT NULL,
  `fehlversuche` int DEFAULT '0',
  `gesperrt_bis` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `benutzer`
--

INSERT INTO `benutzer` (`id`, `benutzername`, `passwort_hash`, `email`, `vorname`, `nachname`, `rolle`, `aktiv`, `passwort_muss_geaendert`, `letzter_login`, `fehlversuche`, `gesperrt_bis`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$2mw3fsL7Vfj62Cz7/wJNkOXUN6q/A95T1MzbcKJMXxNLHWSdxhHva', 'admin@admin.admin', 'System', 'Administrator', 'admin', 1, 0, '2026-01-15 16:24:49', 0, NULL, '2026-01-14 06:37:45', '2026-01-15 15:24:49');

-- --------------------------------------------------------

--
-- Table structure for table `einkommensteuer`
--

CREATE TABLE `einkommensteuer` (
  `id` int NOT NULL,
  `jahr` int NOT NULL,
  `kz9040` decimal(12,2) DEFAULT '0.00',
  `kz9050` decimal(12,2) DEFAULT '0.00',
  `kz9100` decimal(12,2) DEFAULT '0.00',
  `kz9110` decimal(12,2) DEFAULT '0.00',
  `kz9120` decimal(12,2) DEFAULT '0.00',
  `kz9130` decimal(12,2) DEFAULT '0.00',
  `kz9134` decimal(12,2) DEFAULT '0.00',
  `kz9135` decimal(12,2) DEFAULT '0.00',
  `kz9140` decimal(12,2) DEFAULT '0.00',
  `kz9150` decimal(12,2) DEFAULT '0.00',
  `weitere_kennzahlen` json DEFAULT NULL,
  `gewinn_verlust` decimal(12,2) DEFAULT '0.00',
  `eingereicht` tinyint(1) DEFAULT '0',
  `eingereicht_am` date DEFAULT NULL,
  `notizen` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `firma`
--

CREATE TABLE `firma` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `strasse` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plz` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ort` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uid_nummer` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `steuernummer` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `finanzamt` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iban` varchar(34) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bic` varchar(11) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bank` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `geschaeftsjahr_beginn` varchar(5) COLLATE utf8mb4_general_ci DEFAULT '01-01',
  `ust_periode` enum('monatlich','quartalsweise') COLLATE utf8mb4_general_ci DEFAULT 'monatlich',
  `kleinunternehmer` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `firma`
--

INSERT INTO `firma` (`id`, `name`, `strasse`, `plz`, `ort`, `telefon`, `email`, `website`, `uid_nummer`, `steuernummer`, `finanzamt`, `iban`, `bic`, `bank`, `geschaeftsjahr_beginn`, `ust_periode`, `kleinunternehmer`, `created_at`, `updated_at`) VALUES
(1, 'Meine Firma', 'Musterstraße 1', '1010', 'Wien', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '01-01', 'monatlich', 0, '2026-01-14 06:37:30', '2026-01-14 06:37:30');

-- --------------------------------------------------------

--
-- Table structure for table `kategorien`
--

CREATE TABLE `kategorien` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `typ` enum('einnahme','ausgabe') COLLATE utf8mb4_general_ci NOT NULL,
  `e1a_kennzahl` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `beschreibung` text COLLATE utf8mb4_general_ci,
  `farbe` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#6c757d',
  `aktiv` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategorien`
--

INSERT INTO `kategorien` (`id`, `name`, `typ`, `e1a_kennzahl`, `beschreibung`, `farbe`, `aktiv`, `created_at`) VALUES
(1, 'Umsatzerlöse Waren', 'einnahme', '9040', 'Verkauf von Waren und Erzeugnissen', '#28a745', 1, '2026-01-14 06:37:30'),
(2, 'Umsatzerlöse Dienstleistungen', 'einnahme', '9050', 'Einnahmen aus Dienstleistungen', '#20c997', 1, '2026-01-14 06:37:30'),
(3, 'Sonstige Einnahmen', 'einnahme', '9040', 'Provisionen, Nebenerlöse etc.', '#17a2b8', 1, '2026-01-14 06:37:30'),
(4, 'Wareneinkauf', 'ausgabe', '9100', 'Einkauf von Waren und Materialien', '#dc3545', 1, '2026-01-14 06:37:30'),
(5, 'Rohstoffe', 'ausgabe', '9100', 'Rohstoffe und Hilfsstoffe', '#e83e8c', 1, '2026-01-14 06:37:30'),
(6, 'Personalkosten', 'ausgabe', '9120', 'Löhne, Gehälter, Sozialabgaben', '#fd7e14', 1, '2026-01-14 06:37:30'),
(7, 'Miete Büro/Lager', 'ausgabe', '9140', 'Miete für Betriebsräume', '#6c757d', 1, '2026-01-14 06:37:30'),
(8, 'Betriebskosten', 'ausgabe', '9140', 'Strom, Heizung, Wasser', '#795548', 1, '2026-01-14 06:37:30'),
(9, 'Büromaterial', 'ausgabe', '9150', 'Bürobedarf, Druckerkosten', '#607d8b', 1, '2026-01-14 06:37:30'),
(10, 'Telefon/Internet', 'ausgabe', '9150', 'Kommunikationskosten', '#00bcd4', 1, '2026-01-14 06:37:30'),
(11, 'Versicherungen', 'ausgabe', '9150', 'Betriebliche Versicherungen', '#9c27b0', 1, '2026-01-14 06:37:30'),
(12, 'Werbung/Marketing', 'ausgabe', '9200', 'Werbung, Marketing, Website', '#ff5722', 1, '2026-01-14 06:37:30'),
(13, 'Reisekosten', 'ausgabe', '9160', 'Dienstreisen, Hotel, Verpflegung', '#4caf50', 1, '2026-01-14 06:37:30'),
(14, 'KFZ-Kosten', 'ausgabe', '9150', 'Treibstoff, Reparatur, Versicherung', '#2196f3', 1, '2026-01-14 06:37:30'),
(15, 'Fortbildung', 'ausgabe', '9150', 'Kurse, Seminare, Fachliteratur', '#9e9e9e', 1, '2026-01-14 06:37:30'),
(16, 'Bankspesen', 'ausgabe', '9220', 'Kontoführung, Überweisungen', '#455a64', 1, '2026-01-14 06:37:30'),
(17, 'Sonstige Ausgaben', 'ausgabe', '9150', 'Sonstige betriebliche Ausgaben', '#78909c', 1, '2026-01-14 06:37:30'),
(18, 'Fremdleistungen', 'ausgabe', '9110', 'Beigestelltes Personal und Fremdleistungen', '#e5a61f', 1, '2026-01-14 06:37:30');

-- --------------------------------------------------------

--
-- Table structure for table `rechnungen`
--

CREATE TABLE `rechnungen` (
  `id` int NOT NULL,
  `typ` enum('einnahme','ausgabe') COLLATE utf8mb4_general_ci NOT NULL,
  `rechnungsnummer` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `datum` date NOT NULL,
  `faellig_am` date DEFAULT NULL,
  `kunde_lieferant` varchar(255) COLLATE utf8mb4_general_ci DEFAULT '',
  `beschreibung` text COLLATE utf8mb4_general_ci,
  `netto_betrag` decimal(12,2) NOT NULL,
  `ust_satz_id` int DEFAULT NULL,
  `ust_betrag` decimal(12,2) DEFAULT '0.00',
  `brutto_betrag` decimal(12,2) NOT NULL,
  `kategorie_id` int DEFAULT NULL,
  `bezahlt` tinyint(1) DEFAULT '0',
  `bezahlt_am` date DEFAULT NULL,
  `buchungsart` enum('inland','eu_ige','eu_b2c','drittland','drittland_import') COLLATE utf8mb4_general_ci DEFAULT 'inland',
  `lieferant_land` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lieferant_uid` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ausland_ust_satz` decimal(5,2) DEFAULT NULL,
  `ausland_ust_betrag` decimal(10,2) DEFAULT NULL,
  `dokument_pfad` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notizen` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `buchungsnummer` int DEFAULT NULL,
  `erstellt_von` int DEFAULT NULL,
  `geaendert_von` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ust_saetze`
--

CREATE TABLE `ust_saetze` (
  `id` int NOT NULL,
  `bezeichnung` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `satz` decimal(5,2) NOT NULL,
  `u30_kennzahl_bemessung` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `u30_kennzahl_steuer` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ust_saetze`
--

INSERT INTO `ust_saetze` (`id`, `bezeichnung`, `satz`, `u30_kennzahl_bemessung`, `u30_kennzahl_steuer`, `aktiv`, `created_at`) VALUES
(1, 'Normalsteuersatz 20%', '20.00', '022', NULL, 1, '2026-01-14 06:37:30'),
(2, 'Ermäßigt 10%', '10.00', '029', NULL, 1, '2026-01-14 06:37:30'),
(3, 'Ermäßigt 13%', '13.00', '006', NULL, 1, '2026-01-14 06:37:30'),
(4, 'Steuerfrei 0%', '0.00', NULL, NULL, 1, '2026-01-14 06:37:30'),
(5, 'Innergemeinschaftlich', '0.00', '070', '065', 1, '2026-01-14 06:37:30'),
(6, 'Reverse Charge', '0.00', NULL, NULL, 1, '2026-01-14 06:37:30'),
(7, 'EU-Erwerb (igE) 20%', '20.00', '070', '065', 1, '2026-01-14 06:37:30'),
(8, 'Einfuhr-USt Drittland', '20.00', NULL, '061', 1, '2026-01-14 06:37:30'),
(9, 'Ausland (kein VSt-Abzug)', '0.00', NULL, NULL, 1, '2026-01-14 06:37:30');

-- --------------------------------------------------------

--
-- Table structure for table `ust_voranmeldungen`
--

CREATE TABLE `ust_voranmeldungen` (
  `id` int NOT NULL,
  `jahr` int NOT NULL,
  `monat` int NOT NULL,
  `zeitraum_typ` enum('monat','quartal') COLLATE utf8mb4_general_ci DEFAULT 'monat',
  `kz000` decimal(12,2) DEFAULT '0.00',
  `kz001` decimal(12,2) DEFAULT '0.00',
  `kz021` decimal(12,2) DEFAULT '0.00',
  `kz022` decimal(12,2) DEFAULT '0.00',
  `kz029` decimal(12,2) DEFAULT '0.00',
  `kz025` decimal(12,2) DEFAULT '0.00',
  `kz027` decimal(12,2) DEFAULT '0.00',
  `kz035` decimal(12,2) DEFAULT '0.00',
  `kz052` decimal(12,2) DEFAULT '0.00',
  `kz070` decimal(12,2) DEFAULT '0.00',
  `kz072` decimal(12,2) DEFAULT '0.00',
  `kz060` decimal(12,2) DEFAULT '0.00',
  `kz061` decimal(12,2) DEFAULT '0.00',
  `kz065` decimal(12,2) DEFAULT '0.00',
  `kz066` decimal(12,2) DEFAULT '0.00',
  `kz082` decimal(12,2) DEFAULT '0.00',
  `kz095` decimal(12,2) DEFAULT '0.00',
  `zahllast` decimal(12,2) DEFAULT '0.00',
  `eingereicht` tinyint(1) DEFAULT '0',
  `eingereicht_am` date DEFAULT NULL,
  `notizen` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `aenderungsprotokoll`
--
ALTER TABLE `aenderungsprotokoll`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_benutzer` (`benutzer_id`),
  ADD KEY `idx_tabelle` (`tabelle`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `afa_buchungen`
--
ALTER TABLE `afa_buchungen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_afa` (`anlagegut_id`,`jahr`),
  ADD KEY `idx_jahr` (`jahr`);

--
-- Indexes for table `anlagegueter`
--
ALTER TABLE `anlagegueter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_datum` (`anschaffungsdatum`);

--
-- Indexes for table `benutzer`
--
ALTER TABLE `benutzer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `benutzername` (`benutzername`);

--
-- Indexes for table `einkommensteuer`
--
ALTER TABLE `einkommensteuer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jahr` (`jahr`);

--
-- Indexes for table `firma`
--
ALTER TABLE `firma`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kategorien`
--
ALTER TABLE `kategorien`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rechnungen`
--
ALTER TABLE `rechnungen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ust_satz_id` (`ust_satz_id`),
  ADD KEY `kategorie_id` (`kategorie_id`),
  ADD KEY `idx_datum` (`datum`),
  ADD KEY `idx_typ` (`typ`),
  ADD KEY `idx_bezahlt` (`bezahlt`);

--
-- Indexes for table `ust_saetze`
--
ALTER TABLE `ust_saetze`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ust_voranmeldungen`
--
ALTER TABLE `ust_voranmeldungen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_periode` (`jahr`,`monat`,`zeitraum_typ`),
  ADD KEY `idx_jahr` (`jahr`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aenderungsprotokoll`
--
ALTER TABLE `aenderungsprotokoll`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `afa_buchungen`
--
ALTER TABLE `afa_buchungen`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `anlagegueter`
--
ALTER TABLE `anlagegueter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `benutzer`
--
ALTER TABLE `benutzer`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `einkommensteuer`
--
ALTER TABLE `einkommensteuer`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `firma`
--
ALTER TABLE `firma`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kategorien`
--
ALTER TABLE `kategorien`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `rechnungen`
--
ALTER TABLE `rechnungen`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ust_saetze`
--
ALTER TABLE `ust_saetze`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `ust_voranmeldungen`
--
ALTER TABLE `ust_voranmeldungen`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `afa_buchungen`
--
ALTER TABLE `afa_buchungen`
  ADD CONSTRAINT `afa_buchungen_ibfk_1` FOREIGN KEY (`anlagegut_id`) REFERENCES `anlagegueter` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rechnungen`
--
ALTER TABLE `rechnungen`
  ADD CONSTRAINT `rechnungen_ibfk_1` FOREIGN KEY (`ust_satz_id`) REFERENCES `ust_saetze` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rechnungen_ibfk_2` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
