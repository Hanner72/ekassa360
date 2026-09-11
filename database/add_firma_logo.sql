-- EKassa360: Firmenlogo für den Einsatz in PDF-Vorlagen (Angebot/Auftrag/Rechnung)
-- Wird als Base64 in der DB gespeichert (nicht als Datei), damit es Teil des
-- normalen DB-Backups/Dumps ist (database/ekassa360.sql) statt eines separaten
-- Datei-Zustands, der bei einer Neuinstallation sonst verloren ginge.

ALTER TABLE `firma`
  ADD COLUMN `logo_data` LONGTEXT COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `website`,
  ADD COLUMN `logo_mime` VARCHAR(50) COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `logo_data`;
