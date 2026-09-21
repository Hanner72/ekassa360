-- EKassa360: interner Chat zwischen Benutzern ("Nachrichten"). Einfache 1:1-Konversation
-- zwischen zwei Benutzern, die Gruppierung nach Gesprächspartner passiert im PHP-Code
-- (includes/nachrichten_functions.php), nicht über ein eigenes Konversations-Objekt.
SET NAMES utf8mb4;

CREATE TABLE `nachrichten` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `von_benutzer_id` INT NOT NULL,
  `an_benutzer_id` INT NOT NULL,
  `inhalt` TEXT NOT NULL,
  `gelesen` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_von` (`von_benutzer_id`),
  KEY `idx_an_gelesen` (`an_benutzer_id`, `gelesen`),
  CONSTRAINT `fk_nachrichten_von` FOREIGN KEY (`von_benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nachrichten_an` FOREIGN KEY (`an_benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
