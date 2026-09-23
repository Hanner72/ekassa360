-- EKassa360: Neue Status-Semantik für Aufträge (analog zu Angeboten, siehe
-- add_angebot_status_erweiterung.sql) - nur Aufträge betroffen, Rechnungen behalten ihre
-- eigene Bedeutung von 'abgeschlossen' (= finalisiert mit Ledger-Zeilen) unverändert:
--   'erstellt'         = finalisiert/nummeriert (bisher 'versendet')
--   'versendet'        = mindestens einmal per E-Mail versendet
--   'rechnung_erstellt' = in eine Rechnung umgewandelt (bisher 'abgeschlossen')
SET NAMES utf8mb4;

ALTER TABLE `verkaufsdokumente` MODIFY COLUMN `status`
  ENUM('entwurf','versendet','abgelehnt','abgeschlossen','storniert','erstellt','auftrag_erstellt','rechnung_erstellt')
  NOT NULL DEFAULT 'entwurf';

UPDATE `verkaufsdokumente` SET `status` = 'erstellt' WHERE `typ` = 'auftrag' AND `status` = 'versendet';
UPDATE `verkaufsdokumente` SET `status` = 'rechnung_erstellt' WHERE `typ` = 'auftrag' AND `status` = 'abgeschlossen';
