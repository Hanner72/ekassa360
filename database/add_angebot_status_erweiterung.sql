-- EKassa360: Neue Status-Semantik für Angebote (nur Angebote betroffen - Auftrag/Rechnung
-- behalten ihre bisherige Bedeutung von 'versendet'/'abgeschlossen'):
--   'erstellt'        = finalisiert/nummeriert (bisher missverständlich 'versendet' genannt,
--                        siehe die Terminologie-Kollision, die im E-Mail-Versand-Feature schon
--                        dokumentiert wurde)
--   'versendet'       = mindestens einmal per E-Mail versendet
--   'auftrag_erstellt' = in einen Auftrag umgewandelt (bisher 'angenommen')
SET NAMES utf8mb4;

ALTER TABLE `verkaufsdokumente` MODIFY COLUMN `status`
  ENUM('entwurf','versendet','angenommen','abgelehnt','abgeschlossen','storniert','erstellt','auftrag_erstellt')
  NOT NULL DEFAULT 'entwurf';

UPDATE `verkaufsdokumente` SET `status` = 'erstellt' WHERE `typ` = 'angebot' AND `status` = 'versendet';
UPDATE `verkaufsdokumente` SET `status` = 'auftrag_erstellt' WHERE `typ` = 'angebot' AND `status` = 'angenommen';

-- 'angenommen' war ausschließlich für Angebote in Verwendung und ist jetzt vollständig durch
-- 'auftrag_erstellt' ersetzt - aus der ENUM-Definition entfernen.
ALTER TABLE `verkaufsdokumente` MODIFY COLUMN `status`
  ENUM('entwurf','versendet','abgelehnt','abgeschlossen','storniert','erstellt','auftrag_erstellt')
  NOT NULL DEFAULT 'entwurf';
