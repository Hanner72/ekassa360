-- EKassa360: Kurzbezeichnung für Kategorien (fließt in den Artikelnummer-Nummernkreis ein)
-- + Nummernkreis "artikel" für automatische Artikelnummer-Vergabe. Wie bei Kundennummern
-- jahresunabhängig (fester Jahr-Sentinel 0), da eine Artikelnummer über die gesamte
-- Katalog-Historie eindeutig bleiben soll (siehe zieheArtikelnummer() in
-- includes/verkauf_functions.php).
SET NAMES utf8mb4;

ALTER TABLE `kategorien`
  ADD COLUMN `kurzbezeichnung` VARCHAR(10) DEFAULT NULL AFTER `name`;

ALTER TABLE `nummernkreise`
  MODIFY COLUMN `schluessel` ENUM('rechnung','angebot','auftrag','kunde','artikel') NOT NULL;

INSERT INTO `nummernkreise` (`schluessel`, `jahr`, `format`, `naechste_nummer`)
VALUES ('artikel', 0, '{KURZ}-{NNNN}', 1);
