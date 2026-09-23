-- EKassa360: Aufgabenverwaltung mit Zuweisung an einen Benutzer, optional verknüpft mit
-- einem Kunden oder einem Verkaufsdokument (Angebot/Auftrag/Rechnung) - siehe
-- includes/aufgaben_functions.php und aufgaben.php.
SET NAMES utf8mb4;

CREATE TABLE `aufgaben` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `titel` VARCHAR(255) NOT NULL,
  `beschreibung` TEXT DEFAULT NULL,
  `zugewiesen_an` INT DEFAULT NULL,
  `erstellt_von` INT DEFAULT NULL,
  `kunde_id` INT DEFAULT NULL,
  `verkaufsdokument_id` INT DEFAULT NULL,
  `faellig_am` DATE DEFAULT NULL,
  `prioritaet` ENUM('niedrig','normal','hoch') NOT NULL DEFAULT 'normal',
  `status` ENUM('offen','in_arbeit','erledigt') NOT NULL DEFAULT 'offen',
  `erledigt_am` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_zugewiesen` (`zugewiesen_an`, `status`),
  KEY `idx_kunde` (`kunde_id`),
  KEY `idx_verkaufsdokument` (`verkaufsdokument_id`),
  CONSTRAINT `fk_aufgaben_zugewiesen` FOREIGN KEY (`zugewiesen_an`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aufgaben_erstellt` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aufgaben_kunde` FOREIGN KEY (`kunde_id`) REFERENCES `kunden` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aufgaben_verkaufsdokument` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
