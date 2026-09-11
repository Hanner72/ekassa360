-- EKassa360: Gesamtrabatt (Dokument-Ebene, zusätzlich zu den bereits vorhandenen
-- Positions-Rabatten) für Angebot/Auftrag/Rechnung.

ALTER TABLE `verkaufsdokumente`
  ADD COLUMN `gesamtrabatt_prozent` DECIMAL(5,2) NOT NULL DEFAULT '0.00' AFTER `schlusstext`;
