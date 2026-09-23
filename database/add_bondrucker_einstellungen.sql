-- EKassa360: Ein-Zeilen-Einstellungstabelle für den Netzwerk-Bondrucker (ESC/POS über TCP,
-- meist Port 9100) für Verkaufsrechnungen - siehe includes/bondrucker.php.
SET NAMES utf8mb4;

CREATE TABLE `bondrucker_einstellungen` (
  `id` INT PRIMARY KEY,
  `ip_adresse` VARCHAR(100) DEFAULT NULL,
  `port` INT NOT NULL DEFAULT 9100,
  `papierbreite` ENUM('58mm','80mm') NOT NULL DEFAULT '80mm'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bondrucker_einstellungen` (`id`, `ip_adresse`, `port`, `papierbreite`) VALUES (1, NULL, 9100, '80mm');
