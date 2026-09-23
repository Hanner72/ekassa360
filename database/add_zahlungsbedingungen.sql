-- EKassa360: Zahlungsbedingungen (z.B. "Sofort nach Erhalt der Rechnung", "14 Tage netto")
-- für Verkaufsrechnungen - auswählbar pro Rechnung, mit frei gestaltbarem Zahlungshinweis-Text
-- fürs PDF (siehe {{zahlungshinweis}} in includes/verkauf_pdf.php). `tage_bis_faellig` steuert
-- optional die automatische Vorbelegung von faellig_am im Formular (bleibt manuell änderbar).
SET NAMES utf8mb4;

CREATE TABLE `zahlungsbedingungen` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bezeichnung` VARCHAR(150) NOT NULL,
  `tage_bis_faellig` INT DEFAULT NULL,
  `zahlungshinweis_text` TEXT,
  `ist_standard` TINYINT(1) NOT NULL DEFAULT 0,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `verkaufsdokumente`
  ADD COLUMN `zahlungsbedingung_id` INT DEFAULT NULL AFTER `firmenprofil_id`,
  ADD CONSTRAINT `fk_verkaufsdokumente_zahlungsbedingung` FOREIGN KEY (`zahlungsbedingung_id`) REFERENCES `zahlungsbedingungen` (`id`) ON DELETE SET NULL;

INSERT INTO `zahlungsbedingungen` (`bezeichnung`, `tage_bis_faellig`, `zahlungshinweis_text`, `ist_standard`, `aktiv`) VALUES
('Sofort nach Erhalt der Rechnung', 0, 'Bitte überweisen Sie den Rechnungsbetrag sofort nach Erhalt dieser Rechnung auf IBAN {{iban}}{{bic_hinweis}}.', 1, 1);
