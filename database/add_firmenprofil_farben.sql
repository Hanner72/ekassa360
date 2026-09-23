-- EKassa360: Zwei Hauptfarben pro Firmenprofil (z.B. für unterschiedlich gebrandete
-- Firmenzweige), als Hex-Werte, in den PDF-Vorlagen per {{firma_farbe1}}/{{firma_farbe2}}
-- verwendbar (siehe baueDokumentPlatzhalter() in includes/verkauf_pdf.php).
SET NAMES utf8mb4;

ALTER TABLE `firmenprofile`
  ADD COLUMN `farbe1` VARCHAR(7) NOT NULL DEFAULT '#0d6efd' AFTER `logo_mime`,
  ADD COLUMN `farbe2` VARCHAR(7) NOT NULL DEFAULT '#6c757d' AFTER `farbe1`;
