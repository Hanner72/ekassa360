-- EKassa360: Wiederkehrende Rechnungen (Regeln + Ausführungsprotokoll)

CREATE TABLE `wiederkehrende_rechnungen` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bezeichnung` VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  `kunde_id` INT DEFAULT NULL,
  `vorlage_verkaufsdokument_id` INT DEFAULT NULL,
  `rhythmus` ENUM('monatlich','quartalsweise','halbjaehrlich','jaehrlich') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'monatlich',
  `start_datum` DATE NOT NULL,
  `naechstes_datum` DATE NOT NULL,
  `end_datum` DATE DEFAULT NULL,
  `max_anzahl` INT DEFAULT NULL,
  `anzahl_erstellt` INT NOT NULL DEFAULT '0',
  `aktiv` TINYINT(1) DEFAULT '1',
  `automatisch_pdf_versenden` TINYINT(1) DEFAULT '0',
  `versand_email` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `letzte_ausfuehrung` DATETIME DEFAULT NULL,
  `letzte_erstellte_rechnung_id` INT DEFAULT NULL,
  `notizen` TEXT COLLATE utf8mb4_general_ci,
  `erstellt_von` INT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aktiv_naechstes` (`aktiv`,`naechstes_datum`),
  CONSTRAINT `wiederkehrende_rechnungen_ibfk_1` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wiederkehrende_rechnungen_ibfk_2` FOREIGN KEY (`vorlage_verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL,
  CONSTRAINT `wiederkehrende_rechnungen_ibfk_3` FOREIGN KEY (`letzte_erstellte_rechnung_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `wiederkehrende_rechnungen_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `wiederkehrend_id` INT NOT NULL,
  `verkaufsdokument_id` INT DEFAULT NULL,
  `lauf_datum` DATE NOT NULL,
  `status` ENUM('erstellt','fehler') COLLATE utf8mb4_general_ci NOT NULL,
  `fehlermeldung` TEXT COLLATE utf8mb4_general_ci,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lauf` (`wiederkehrend_id`,`lauf_datum`),
  CONSTRAINT `wiederkehrende_rechnungen_log_ibfk_1` FOREIGN KEY (`wiederkehrend_id`) REFERENCES `wiederkehrende_rechnungen` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wiederkehrende_rechnungen_log_ibfk_2` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Jetzt existiert wiederkehrende_rechnungen: verkaufsdokumente.wiederkehrend_id kann verknüpft werden
ALTER TABLE `verkaufsdokumente`
  ADD CONSTRAINT `verkaufsdokumente_ibfk_4` FOREIGN KEY (`wiederkehrend_id`) REFERENCES `wiederkehrende_rechnungen` (`id`) ON DELETE SET NULL;
