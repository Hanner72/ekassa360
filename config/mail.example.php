<?php
/**
 * E-Mail-Versand (SMTP) Konfiguration (Vorlage)
 *
 * Kopieren nach config/mail.php und mit echten Werten befüllen.
 * config/mail.php ist in .gitignore - Zugangsdaten nicht committen!
 */

// Versand global aktivieren/deaktivieren
define('MAIL_ENABLED', false);

// SMTP-Zugangsdaten
define('MAIL_SMTP_HOST', '');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl' oder '' für keine Verschlüsselung
define('MAIL_SMTP_USERNAME', '');
define('MAIL_SMTP_PASSWORD', '');

// Absenderadresse/-name für ausgehende E-Mails
define('MAIL_FROM_ADDRESS', '');
define('MAIL_FROM_NAME', '');
