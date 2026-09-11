-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ekassa360
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `aenderungsprotokoll`
--

DROP TABLE IF EXISTS `aenderungsprotokoll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aenderungsprotokoll` (
  `id` int NOT NULL AUTO_INCREMENT,
  `benutzer_id` int DEFAULT NULL,
  `benutzer_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tabelle` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `datensatz_id` int DEFAULT NULL,
  `aktion` enum('erstellt','geaendert','geloescht','login','logout','passwort_geaendert') COLLATE utf8mb4_general_ci NOT NULL,
  `beschreibung` text COLLATE utf8mb4_general_ci,
  `alte_werte` json DEFAULT NULL,
  `neue_werte` json DEFAULT NULL,
  `ip_adresse` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_benutzer` (`benutzer_id`),
  KEY `idx_tabelle` (`tabelle`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `afa_buchungen`
--

DROP TABLE IF EXISTS `afa_buchungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `afa_buchungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `anlagegut_id` int NOT NULL,
  `jahr` int NOT NULL,
  `afa_betrag` decimal(12,2) NOT NULL,
  `restwert_vor` decimal(12,2) NOT NULL,
  `restwert_nach` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_afa` (`anlagegut_id`,`jahr`),
  KEY `idx_jahr` (`jahr`),
  CONSTRAINT `afa_buchungen_ibfk_1` FOREIGN KEY (`anlagegut_id`) REFERENCES `anlagegueter` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `anlagegueter`
--

DROP TABLE IF EXISTS `anlagegueter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anlagegueter` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `geaendert_von` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_datum` (`anschaffungsdatum`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `artikel`
--

DROP TABLE IF EXISTS `artikel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `artikel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artikelnummer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bezeichnung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `einheit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Stk',
  `einzelpreis_netto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ust_satz_id` int DEFAULT NULL,
  `kategorie_id` int DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_artikelnummer` (`artikelnummer`),
  KEY `idx_aktiv` (`aktiv`),
  KEY `artikel_ibfk_1` (`ust_satz_id`),
  KEY `artikel_ibfk_2` (`kategorie_id`),
  CONSTRAINT `artikel_ibfk_1` FOREIGN KEY (`ust_satz_id`) REFERENCES `ust_saetze` (`id`) ON DELETE SET NULL,
  CONSTRAINT `artikel_ibfk_2` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `benutzer`
--

DROP TABLE IF EXISTS `benutzer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `benutzer` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `benutzername` (`benutzername`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `benutzer`
--

LOCK TABLES `benutzer` WRITE;
/*!40000 ALTER TABLE `benutzer` DISABLE KEYS */;
INSERT INTO `benutzer` VALUES (1,'admin','$2y$10$2mw3fsL7Vfj62Cz7/wJNkOXUN6q/A95T1MzbcKJMXxNLHWSdxhHva','admin@admin.admin','System','Administrator','admin',1,0,'2026-09-10 11:41:19',0,NULL,'2026-01-14 06:37:45','2026-09-10 09:41:19');
/*!40000 ALTER TABLE `benutzer` ENABLE KEYS */;
UNLOCK TABLES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `einkommensteuer`
--

DROP TABLE IF EXISTS `einkommensteuer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `einkommensteuer` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jahr` (`jahr`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `firma`
--

DROP TABLE IF EXISTS `firma`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `firma` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `strasse` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plz` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ort` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefon` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `logo_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `logo_mime` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `firma`
--

INSERT INTO `firma` VALUES (1,'Meine Firma','Musterstraße 1','1010','Wien',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'01-01','monatlich',0,'2026-01-14 06:37:30','2026-01-14 06:37:30');

--
-- Table structure for table `kategorien`
--

DROP TABLE IF EXISTS `kategorien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kategorien` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `typ` enum('einnahme','ausgabe') COLLATE utf8mb4_general_ci NOT NULL,
  `e1a_kennzahl` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `beschreibung` text COLLATE utf8mb4_general_ci,
  `farbe` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#6c757d',
  `aktiv` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategorien`
--

LOCK TABLES `kategorien` WRITE;
/*!40000 ALTER TABLE `kategorien` DISABLE KEYS */;
INSERT INTO `kategorien` VALUES (1,'Umsatzerlöse Waren','einnahme','9040','Verkauf von Waren und Erzeugnissen','#28a745',1,'2026-01-14 06:37:30'),(2,'Umsatzerlöse Dienstleistungen','einnahme','9050','Einnahmen aus Dienstleistungen','#20c997',1,'2026-01-14 06:37:30'),(3,'Sonstige Einnahmen','einnahme','9040','Provisionen, Nebenerlöse etc.','#17a2b8',1,'2026-01-14 06:37:30'),(4,'Wareneinkauf','ausgabe','9100','Einkauf von Waren und Materialien','#dc3545',1,'2026-01-14 06:37:30'),(5,'Rohstoffe','ausgabe','9100','Rohstoffe und Hilfsstoffe','#e83e8c',1,'2026-01-14 06:37:30'),(6,'Personalkosten','ausgabe','9120','Löhne, Gehälter, Sozialabgaben','#fd7e14',1,'2026-01-14 06:37:30'),(7,'Miete Büro/Lager','ausgabe','9140','Miete für Betriebsräume','#6c757d',1,'2026-01-14 06:37:30'),(8,'Betriebskosten','ausgabe','9140','Strom, Heizung, Wasser','#795548',1,'2026-01-14 06:37:30'),(9,'Büromaterial','ausgabe','9150','Bürobedarf, Druckerkosten','#607d8b',1,'2026-01-14 06:37:30'),(10,'Telefon/Internet','ausgabe','9150','Kommunikationskosten','#00bcd4',1,'2026-01-14 06:37:30'),(11,'Versicherungen','ausgabe','9150','Betriebliche Versicherungen','#9c27b0',1,'2026-01-14 06:37:30'),(12,'Werbung/Marketing','ausgabe','9200','Werbung, Marketing, Website','#ff5722',1,'2026-01-14 06:37:30'),(13,'Reisekosten','ausgabe','9160','Dienstreisen, Hotel, Verpflegung','#4caf50',1,'2026-01-14 06:37:30'),(14,'KFZ-Kosten','ausgabe','9150','Treibstoff, Reparatur, Versicherung','#2196f3',1,'2026-01-14 06:37:30'),(15,'Fortbildung','ausgabe','9150','Kurse, Seminare, Fachliteratur','#9e9e9e',1,'2026-01-14 06:37:30'),(16,'Bankspesen','ausgabe','9220','Kontoführung, Überweisungen','#455a64',1,'2026-01-14 06:37:30'),(17,'Sonstige Ausgaben','ausgabe','9150','Sonstige betriebliche Ausgaben','#78909c',1,'2026-01-14 06:37:30'),(18,'Fremdleistungen','ausgabe','9110','Beigestelltes Personal und Fremdleistungen','#e5a61f',1,'2026-01-14 06:37:30'),(19,'Verkaufserlöse','einnahme','9040','Erlöse aus Angeboten/Aufträgen/Rechnungen des Verkauf-Moduls','#0d6efd',1,'2026-09-10 09:10:36');
/*!40000 ALTER TABLE `kategorien` ENABLE KEYS */;
UNLOCK TABLES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `kunden`
--

DROP TABLE IF EXISTS `kunden`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kunden` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kundennummer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `firma_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `anrede` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vorname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nachname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `strasse` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plz` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ort` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `land` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Österreich',
  `uid_nummer` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `notizen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `paperless_correspondent_id` int DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT '1',
  `erstellt_von` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_kundennummer` (`kundennummer`),
  KEY `idx_aktiv` (`aktiv`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nummernkreise`
--

DROP TABLE IF EXISTS `nummernkreise`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nummernkreise` (
  `id` int NOT NULL AUTO_INCREMENT,
  `schluessel` enum('rechnung','angebot','auftrag') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jahr` int NOT NULL,
  `format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `naechste_nummer` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_schluessel_jahr` (`schluessel`,`jahr`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nummernkreise`
--

LOCK TABLES `nummernkreise` WRITE;
/*!40000 ALTER TABLE `nummernkreise` DISABLE KEYS */;
INSERT INTO `nummernkreise` VALUES (1,'rechnung',2026,'RE{JJJJ}{NNN}',4),(2,'angebot',2026,'AN{JJJJ}{NNN}',2),(3,'auftrag',2026,'AU{JJJJ}{NNN}',1);
/*!40000 ALTER TABLE `nummernkreise` ENABLE KEYS */;
UNLOCK TABLES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pdf_vorlagen`
--

DROP TABLE IF EXISTS `pdf_vorlagen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pdf_vorlagen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `typ` enum('angebot','auftrag','rechnung','lieferschein') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `vorlage` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `geaendert_von` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_typ` (`typ`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rechnungen`
--

DROP TABLE IF EXISTS `rechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rechnungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `verkaufsdokument_id` int DEFAULT NULL,
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
  `zahlungsart` enum('bankueberweisung','bar','sonstige') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'bankueberweisung',
  `buchungsart` enum('inland','eu_ige','eu_b2c','drittland','drittland_import') COLLATE utf8mb4_general_ci DEFAULT 'inland',
  `lieferant_land` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lieferant_uid` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ausland_ust_satz` decimal(5,2) DEFAULT NULL,
  `ausland_ust_betrag` decimal(10,2) DEFAULT NULL,
  `dokument_pfad` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paperless_document_id` int DEFAULT NULL,
  `notizen` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `buchungsnummer` int DEFAULT NULL,
  `erstellt_von` int DEFAULT NULL,
  `geaendert_von` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ust_satz_id` (`ust_satz_id`),
  KEY `kategorie_id` (`kategorie_id`),
  KEY `idx_datum` (`datum`),
  KEY `idx_typ` (`typ`),
  KEY `idx_bezahlt` (`bezahlt`),
  KEY `idx_verkaufsdokument` (`verkaufsdokument_id`),
  CONSTRAINT `rechnungen_ibfk_1` FOREIGN KEY (`ust_satz_id`) REFERENCES `ust_saetze` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rechnungen_ibfk_2` FOREIGN KEY (`kategorie_id`) REFERENCES `kategorien` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rechnungen_ibfk_3` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ust_saetze`
--

DROP TABLE IF EXISTS `ust_saetze`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ust_saetze` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bezeichnung` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `satz` decimal(5,2) NOT NULL,
  `u30_kennzahl_bemessung` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `u30_kennzahl_steuer` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ust_saetze`
--

LOCK TABLES `ust_saetze` WRITE;
/*!40000 ALTER TABLE `ust_saetze` DISABLE KEYS */;
INSERT INTO `ust_saetze` VALUES (1,'Normalsteuersatz 20%',20.00,'022',NULL,1,'2026-01-14 06:37:30'),(2,'Ermäßigt 10%',10.00,'029',NULL,1,'2026-01-14 06:37:30'),(3,'Ermäßigt 13%',13.00,'006',NULL,1,'2026-01-14 06:37:30'),(4,'Steuerfrei 0%',0.00,NULL,NULL,1,'2026-01-14 06:37:30'),(5,'Innergemeinschaftlich',0.00,'070','065',1,'2026-01-14 06:37:30'),(6,'Reverse Charge',0.00,NULL,NULL,1,'2026-01-14 06:37:30'),(7,'EU-Erwerb (igE) 20%',20.00,'070','065',1,'2026-01-14 06:37:30'),(8,'Einfuhr-USt Drittland',20.00,NULL,'061',1,'2026-01-14 06:37:30'),(9,'Ausland (kein VSt-Abzug)',0.00,NULL,NULL,1,'2026-01-14 06:37:30');
/*!40000 ALTER TABLE `ust_saetze` ENABLE KEYS */;
UNLOCK TABLES;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ust_voranmeldungen`
--

DROP TABLE IF EXISTS `ust_voranmeldungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ust_voranmeldungen` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_periode` (`jahr`,`monat`,`zeitraum_typ`),
  KEY `idx_jahr` (`jahr`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `verkaufsdokument_positionen`
--

DROP TABLE IF EXISTS `verkaufsdokument_positionen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verkaufsdokument_positionen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `verkaufsdokument_id` int NOT NULL,
  `position` int NOT NULL DEFAULT '1',
  `artikel_id` int DEFAULT NULL,
  `bezeichnung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `beschreibung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `menge` decimal(10,2) NOT NULL DEFAULT '1.00',
  `einheit` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Stk',
  `einzelpreis_netto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ust_satz_id` int DEFAULT NULL,
  `rabatt_prozent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `netto_summe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ust_summe` decimal(12,2) NOT NULL DEFAULT '0.00',
  `brutto_summe` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_verkaufsdokument` (`verkaufsdokument_id`),
  KEY `verkaufsdokument_positionen_ibfk_2` (`artikel_id`),
  KEY `verkaufsdokument_positionen_ibfk_3` (`ust_satz_id`),
  CONSTRAINT `verkaufsdokument_positionen_ibfk_1` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verkaufsdokument_positionen_ibfk_2` FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokument_positionen_ibfk_3` FOREIGN KEY (`ust_satz_id`) REFERENCES `ust_saetze` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `verkaufsdokumente`
--

DROP TABLE IF EXISTS `verkaufsdokumente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verkaufsdokumente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `typ` enum('angebot','auftrag','rechnung') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('entwurf','versendet','angenommen','abgelehnt','abgeschlossen','storniert') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'entwurf',
  `nummer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kunde_id` int DEFAULT NULL,
  `vorgaenger_id` int DEFAULT NULL,
  `storno_von_id` int DEFAULT NULL,
  `wiederkehrend_id` int DEFAULT NULL,
  `datum` date NOT NULL,
  `leistungsdatum` date DEFAULT NULL,
  `gueltig_bis` date DEFAULT NULL,
  `faellig_am` date DEFAULT NULL,
  `betreff` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `einleitungstext` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `schlusstext` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gesamtrabatt_prozent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `netto_gesamt` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ust_gesamt` decimal(12,2) NOT NULL DEFAULT '0.00',
  `brutto_gesamt` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pdf_pfad` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paperless_document_id` int DEFAULT NULL,
  `notizen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `erstellt_von` int DEFAULT NULL,
  `geaendert_von` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_typ` (`typ`),
  KEY `idx_status` (`status`),
  KEY `idx_nummer` (`nummer`),
  KEY `idx_kunde` (`kunde_id`),
  KEY `verkaufsdokumente_ibfk_2` (`vorgaenger_id`),
  KEY `verkaufsdokumente_ibfk_3` (`storno_von_id`),
  KEY `verkaufsdokumente_ibfk_4` (`wiederkehrend_id`),
  CONSTRAINT `verkaufsdokumente_ibfk_1` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokumente_ibfk_2` FOREIGN KEY (`vorgaenger_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokumente_ibfk_3` FOREIGN KEY (`storno_von_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verkaufsdokumente_ibfk_4` FOREIGN KEY (`wiederkehrend_id`) REFERENCES `wiederkehrende_rechnungen` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiederkehrende_rechnungen`
--

DROP TABLE IF EXISTS `wiederkehrende_rechnungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiederkehrende_rechnungen` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bezeichnung` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kunde_id` int DEFAULT NULL,
  `vorlage_verkaufsdokument_id` int DEFAULT NULL,
  `rhythmus` enum('monatlich','quartalsweise','halbjaehrlich','jaehrlich') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'monatlich',
  `start_datum` date NOT NULL,
  `naechstes_datum` date NOT NULL,
  `end_datum` date DEFAULT NULL,
  `max_anzahl` int DEFAULT NULL,
  `anzahl_erstellt` int NOT NULL DEFAULT '0',
  `aktiv` tinyint(1) DEFAULT '1',
  `automatisch_pdf_versenden` tinyint(1) DEFAULT '0',
  `versand_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `letzte_ausfuehrung` datetime DEFAULT NULL,
  `letzte_erstellte_rechnung_id` int DEFAULT NULL,
  `notizen` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `erstellt_von` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aktiv_naechstes` (`aktiv`,`naechstes_datum`),
  KEY `wiederkehrende_rechnungen_ibfk_1` (`kunde_id`),
  KEY `wiederkehrende_rechnungen_ibfk_2` (`vorlage_verkaufsdokument_id`),
  KEY `wiederkehrende_rechnungen_ibfk_3` (`letzte_erstellte_rechnung_id`),
  CONSTRAINT `wiederkehrende_rechnungen_ibfk_1` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wiederkehrende_rechnungen_ibfk_2` FOREIGN KEY (`vorlage_verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wiederkehrende_rechnungen_ibfk_3` FOREIGN KEY (`letzte_erstellte_rechnung_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wiederkehrende_rechnungen_log`
--

DROP TABLE IF EXISTS `wiederkehrende_rechnungen_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wiederkehrende_rechnungen_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `wiederkehrend_id` int NOT NULL,
  `verkaufsdokument_id` int DEFAULT NULL,
  `lauf_datum` date NOT NULL,
  `status` enum('erstellt','fehler') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fehlermeldung` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lauf` (`wiederkehrend_id`,`lauf_datum`),
  KEY `wiederkehrende_rechnungen_log_ibfk_2` (`verkaufsdokument_id`),
  CONSTRAINT `wiederkehrende_rechnungen_log_ibfk_1` FOREIGN KEY (`wiederkehrend_id`) REFERENCES `wiederkehrende_rechnungen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wiederkehrende_rechnungen_log_ibfk_2` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-10 14:25:34
