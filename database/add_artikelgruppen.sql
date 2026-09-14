-- EKassa360: Rückbau des Missverständnisses aus add_artikel_kategorie_nummernkreis.sql -
-- die "Kurzbezeichnung" gehört NICHT zur Buchungs-Kategorie (kategorien, steuert E1a/Ledger),
-- sondern zu einer eigenen, rein organisatorischen Artikelgruppe (z.B. "Punchen", "T-Shirts",
-- "Hoodies", "Besticken" - stickverliebt-Produktgruppen, keine Buchhaltungs-Kategorie).
SET NAMES utf8mb4;

ALTER TABLE `kategorien` DROP COLUMN `kurzbezeichnung`;

CREATE TABLE `artikelgruppen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `kurzbezeichnung` VARCHAR(10) DEFAULT NULL,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `artikel`
  ADD COLUMN `artikelgruppe_id` INT DEFAULT NULL AFTER `kategorie_id`,
  ADD CONSTRAINT `fk_artikel_artikelgruppe` FOREIGN KEY (`artikelgruppe_id`) REFERENCES `artikelgruppen` (`id`) ON DELETE SET NULL;

INSERT INTO `artikelgruppen` (`name`, `kurzbezeichnung`) VALUES
('Punchen', 'PUN'),
('T-Shirts', 'TSH'),
('Hoodies', 'HOD'),
('Besticken', 'BES');
