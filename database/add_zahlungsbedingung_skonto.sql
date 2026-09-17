-- EKassa360: Skonto (Preisnachlass bei früher Zahlung) je Zahlungsbedingung - beide Felder
-- NULL = kein Skonto für diese Zahlungsbedingung. Siehe {{skonto_*}}-Platzhalter in
-- includes/verkauf_pdf.php.
SET NAMES utf8mb4;

ALTER TABLE `zahlungsbedingungen`
  ADD COLUMN `skonto_prozent` DECIMAL(5,2) DEFAULT NULL AFTER `tage_bis_faellig`,
  ADD COLUMN `skonto_tage` INT DEFAULT NULL AFTER `skonto_prozent`;
