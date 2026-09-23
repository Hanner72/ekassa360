-- EKassa360: Nummernkreis + automatische Kundennummer-Vergabe.
-- Kundennummern sind (anders als Angebot/Auftrag/Rechnung) nicht jahresgebunden - sie laufen
-- über die gesamte Kundenhistorie fortlaufend weiter. Dafür wird der bestehende
-- nummernkreise-Mechanismus wiederverwendet, aber mit einem festen Jahr-Wert 0 als
-- "kein Jahresbezug"-Sentinel (siehe zieheKundennummer() in includes/verkauf_functions.php).
SET NAMES utf8mb4;

ALTER TABLE `nummernkreise`
  MODIFY COLUMN `schluessel` ENUM('rechnung','angebot','auftrag','kunde') NOT NULL;

INSERT INTO `nummernkreise` (`schluessel`, `jahr`, `format`, `naechste_nummer`)
VALUES ('kunde', 0, 'K-{NNNN}', 1);
