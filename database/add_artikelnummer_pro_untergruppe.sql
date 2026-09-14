-- EKassa360: Artikelnummer-Zähler laufen jetzt PRO Artikelgruppe bzw. PRO Artikeluntergruppe
-- (statt eines einzigen globalen Zählers über den generischen nummernkreise-Mechanismus).
-- Das Format ist bewusst NICHT mehr einstellbar (siehe zieheArtikelnummer() in
-- includes/verkauf_functions.php) - nur die Kurzbezeichnungen von Artikelgruppe/-untergruppe
-- bleiben über die Artikelgruppen-Verwaltung konfigurierbar.
SET NAMES utf8mb4;

ALTER TABLE `artikelgruppen` ADD COLUMN `naechste_nummer` INT NOT NULL DEFAULT 1;
ALTER TABLE `artikeluntergruppen` ADD COLUMN `naechste_nummer` INT NOT NULL DEFAULT 1;

-- Fallback-Zähler für den (unüblichen) Fall, dass ein Artikel weder einer Gruppe noch einer
-- Untergruppe zugeordnet ist.
CREATE TABLE `artikel_zaehler_ohne_gruppe` (
  `id` INT PRIMARY KEY,
  `naechste_nummer` INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `artikel_zaehler_ohne_gruppe` (`id`, `naechste_nummer`) VALUES (1, 1);

-- Bereits real vergebene Artikelnummern (aus dem bisherigen globalen Zähler) berücksichtigen,
-- damit die neuen Pro-Untergruppe-Zähler nicht wieder bei 1 anfangen und bereits vergebene
-- laufende Nummern der jeweiligen Untergruppe wiederholen. Betrifft nur diese konkrete
-- Datenbank (auf einer frischen Installation ohne diese Artikel/Untergruppen sind es No-Ops).
UPDATE `artikeluntergruppen` SET `naechste_nummer` = 2 WHERE `id` = 6; -- "Grafik anpassen": Artikel 110001 existiert bereits
UPDATE `artikeluntergruppen` SET `naechste_nummer` = 8 WHERE `id` = 2; -- "punchen": Artikel 310006/310007 existieren bereits

-- Der bisherige generische Nummernkreis "artikel" wird nicht mehr verwendet.
DELETE FROM `nummernkreise` WHERE `schluessel` = 'artikel';
ALTER TABLE `nummernkreise` MODIFY COLUMN `schluessel` ENUM('rechnung','angebot','auftrag','kunde') NOT NULL;
