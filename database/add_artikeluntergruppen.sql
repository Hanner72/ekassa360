-- EKassa360: Artikeluntergruppen (zweite Ebene unter Artikelgruppen, z.B. Gruppe "Textilien"
-- -> Untergruppen "T-Shirts", "Hoodies"). Kurzbezeichnung fließt zusätzlich zur Gruppen-
-- Kurzbezeichnung über den Platzhalter {UKURZ} in den Artikelnummer-Nummernkreis ein
-- (siehe zieheArtikelnummer() in includes/verkauf_functions.php).
SET NAMES utf8mb4;

CREATE TABLE `artikeluntergruppen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `artikelgruppe_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `kurzbezeichnung` VARCHAR(10) DEFAULT NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_artikeluntergruppen_gruppe` FOREIGN KEY (`artikelgruppe_id`) REFERENCES `artikelgruppen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `artikel`
  ADD COLUMN `artikeluntergruppe_id` INT DEFAULT NULL AFTER `artikelgruppe_id`,
  ADD CONSTRAINT `fk_artikel_artikeluntergruppe` FOREIGN KEY (`artikeluntergruppe_id`) REFERENCES `artikeluntergruppen` (`id`) ON DELETE SET NULL;
