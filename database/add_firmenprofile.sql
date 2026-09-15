-- EKassa360: Firmenprofile (mehrere Marken-Namen + Logos, z.B. für zwei Firmenzweige mit
-- eigenem Auftritt) - auswählbar pro Angebot/Auftrag/Rechnung. Adresse/UID/IBAN/Bank bleiben
-- bewusst bei der einzigen `firma`-Tabelle (steuerlich eine einzige Firma), nur Name+Logo
-- werden pro Profil überschrieben (siehe baueDokumentPlatzhalter() in includes/verkauf_pdf.php).
SET NAMES utf8mb4;

CREATE TABLE `firmenprofile` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `logo_data` LONGTEXT DEFAULT NULL,
  `logo_mime` VARCHAR(50) DEFAULT NULL,
  `ist_standard` TINYINT(1) NOT NULL DEFAULT 0,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `verkaufsdokumente`
  ADD COLUMN `firmenprofil_id` INT DEFAULT NULL AFTER `kunde_id`,
  ADD CONSTRAINT `fk_verkaufsdokumente_firmenprofil` FOREIGN KEY (`firmenprofil_id`) REFERENCES `firmenprofile` (`id`) ON DELETE SET NULL;

-- Bestehenden Namen/Logo aus der Firma-Tabelle als erstes ("Standard") Profil übernehmen,
-- damit sich am aktuellen Ausdruck ohne weiteres Zutun nichts ändert. Bewusst per
-- INSERT...SELECT (nicht als literaler Wert), damit hier keine echten Firmendaten im
-- SQL-Code landen - dieses Repository ist öffentlich.
INSERT INTO `firmenprofile` (`name`, `logo_data`, `logo_mime`, `ist_standard`, `aktiv`)
SELECT `name`, `logo_data`, `logo_mime`, 1, 1 FROM `firma` LIMIT 1;
