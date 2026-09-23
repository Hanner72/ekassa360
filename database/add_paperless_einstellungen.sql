-- EKassa360: paperless-ngx-Konfiguration (aktiv-Schalter, Basis-URL, API-Token, SSL-Prüfung)
-- in die Datenbank/Einstellungen-Seite verschoben - ersetzt die bisherigen Konstanten aus
-- config/paperless.php (lokal, nicht in Git, war nur per Dateizugriff änderbar). Bewusst ohne
-- reale Werte geseedet (dieses Repository ist öffentlich) - ein einmaliger Auto-Import aus einer
-- eventuell vorhandenen alten config/paperless.php passiert zur Laufzeit in
-- getPaperlessEinstellungen() (includes/paperless.php), nicht in dieser Migration.
SET NAMES utf8mb4;

CREATE TABLE `paperless_einstellungen` (
  `id` INT PRIMARY KEY,
  `aktiv` TINYINT(1) NOT NULL DEFAULT 0,
  `base_url` VARCHAR(255) DEFAULT NULL,
  `api_token` VARCHAR(255) DEFAULT NULL,
  `verify_ssl` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `paperless_einstellungen` (`id`, `aktiv`, `base_url`, `api_token`, `verify_ssl`) VALUES (1, 0, NULL, NULL, 1);
