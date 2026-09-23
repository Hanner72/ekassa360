-- EKassa360: Editierbare E-Mail-Vorlagen (Betreff/Text pro Dokumenttyp) + wiederverwendbare
-- Signaturen. Analog zum HTML/CSS-Vorlagen-Editor der PDF-Dokumente (pdf_vorlagen), aber
-- als einfache {{platzhalter}}-Ersetzung auf Klartext (E-Mails werden aktuell als Plain-Text
-- versendet, siehe includes/mail.php).
SET NAMES utf8mb4;

CREATE TABLE email_signaturen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  inhalt TEXT NOT NULL,
  ist_standard TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE email_vorlagen (
  id INT AUTO_INCREMENT PRIMARY KEY,
  typ ENUM('angebot','auftrag','rechnung') NOT NULL,
  betreff VARCHAR(255) NOT NULL,
  nachricht TEXT NOT NULL,
  standard_signatur_id INT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_typ (typ),
  CONSTRAINT fk_email_vorlagen_signatur FOREIGN KEY (standard_signatur_id) REFERENCES email_signaturen(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO email_signaturen (name, inhalt, ist_standard) VALUES
('Standard', 'Mit freundlichen Grüßen\n{{firma_name}}', 1);

INSERT INTO email_vorlagen (typ, betreff, nachricht, standard_signatur_id) VALUES
('angebot', '{{typ_label}} {{nummer}} - {{firma_name}}', 'Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie unser Angebot {{nummer}} vom {{datum}}.', 1),
('auftrag', '{{typ_label}} {{nummer}} - {{firma_name}}', 'Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie die Auftragsbestätigung {{nummer}} vom {{datum}}.', 1),
('rechnung', '{{typ_label}} {{nummer}} - {{firma_name}}', 'Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie die Rechnung {{nummer}} vom {{datum}}.', 1);
