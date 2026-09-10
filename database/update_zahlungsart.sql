-- EKassa360: Zahlungsart-Feld hinzufügen
ALTER TABLE `rechnungen`
  ADD COLUMN `zahlungsart` ENUM('bankueberweisung','bar','sonstige') NOT NULL DEFAULT 'bankueberweisung' AFTER `bezahlt_am`;
