-- EKassa360: Ein-Zeilen-Einstellungstabelle für die automatische Erstellung fälliger
-- wiederkehrender Rechnungen. Der eigentliche Cron-Job (cron/generate_wiederkehrende_rechnungen.php)
-- läuft serverseitig fix und häufig (z.B. alle 15 Minuten) - OB und WANN er tatsächlich etwas
-- tut, wird aus dieser Tabelle gesteuert, damit es in den Einstellungen änderbar ist, ohne den
-- Server-Crontab anfassen zu müssen.
SET NAMES utf8mb4;

CREATE TABLE `automatisierung_einstellungen` (
  `id` INT PRIMARY KEY,
  `wiederkehrend_aktiv` TINYINT(1) NOT NULL DEFAULT 1,
  `wiederkehrend_uhrzeit` TIME NOT NULL DEFAULT '03:00:00',
  `wiederkehrend_zuletzt_ausgefuehrt` DATE DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `automatisierung_einstellungen` (`id`, `wiederkehrend_aktiv`, `wiederkehrend_uhrzeit`) VALUES (1, 1, '03:00:00');
