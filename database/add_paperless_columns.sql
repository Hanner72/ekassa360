-- EKassa360: Verknüpfung der Buchhaltungs-Ledger-Tabelle mit dem Verkauf-Modul und paperless-ngx
-- `dokument_pfad` bleibt unangetastet (bisher ungenutztes Altfeld für lokale Belegablage).
-- `paperless_document_id` ist eine dedizierte Spalte, kein Overload von `dokument_pfad`.

ALTER TABLE `rechnungen`
  ADD COLUMN `verkaufsdokument_id` INT DEFAULT NULL AFTER `id`,
  ADD COLUMN `paperless_document_id` INT DEFAULT NULL AFTER `dokument_pfad`;

ALTER TABLE `rechnungen`
  ADD KEY `idx_verkaufsdokument` (`verkaufsdokument_id`),
  ADD CONSTRAINT `rechnungen_ibfk_3` FOREIGN KEY (`verkaufsdokument_id`) REFERENCES `verkaufsdokumente` (`id`) ON DELETE SET NULL;
