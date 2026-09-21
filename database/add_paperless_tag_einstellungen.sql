-- EKassa360: Frei einstellbare paperless-ngx-Tags pro Verkaufsdokument-Typ (Angebot/Auftrag/
-- Rechnung), ersetzt die bisherige feste PAPERLESS_EXTRA_TAGS-Konstante in
-- config/paperless.php (galt bisher pauschal für alle Typen und war nur per Dateizugriff
-- änderbar) - siehe archiviereVerkaufsdokumentInPaperless() in includes/paperless.php.
-- Bewusst nur mit dem generischen Typ-Namen als Default geseedet (kein Rückgriff auf die
-- bisherige PAPERLESS_EXTRA_TAGS-Konstante, deren Inhalt reale, nicht-öffentliche Firmendaten
-- sein können - dieses Repository ist öffentlich) - ein zusätzlicher eigener Tag kann nach dem
-- Deploy einfach über die Einstellungen-Seite ergänzt werden.
SET NAMES utf8mb4;

CREATE TABLE `paperless_tag_einstellungen` (
  `typ` ENUM('angebot','auftrag','rechnung') NOT NULL PRIMARY KEY,
  `tags` VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `paperless_tag_einstellungen` (`typ`, `tags`) VALUES
('angebot', 'Angebot'),
('auftrag', 'Auftrag'),
('rechnung', 'Rechnung');
