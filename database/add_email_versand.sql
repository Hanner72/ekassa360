-- EKassa360: E-Mail-Versand für Angebot/Auftrag/Rechnung (manuell + automatisch bei
-- wiederkehrenden Rechnungen). Speichert nur den letzten Versand-Status zur Anzeige
-- ("Zuletzt versendet am ... an ..."), kein Versandprotokoll (dafür genügt bereits
-- aenderungsprotokoll über logAction()).

ALTER TABLE `verkaufsdokumente`
  ADD COLUMN `versendet_am` DATETIME DEFAULT NULL AFTER `paperless_document_id`,
  ADD COLUMN `versendet_an` VARCHAR(255) COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `versendet_am`;
