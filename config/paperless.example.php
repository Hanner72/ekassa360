<?php
/**
 * paperless-ngx Konfiguration (Vorlage)
 *
 * Kopieren nach config/paperless.php und mit echten Werten befüllen.
 * config/paperless.php ist in .gitignore - Token nicht committen!
 */

// Integration global aktivieren/deaktivieren
define('PAPERLESS_ENABLED', false);

// Basis-URL der paperless-ngx-Instanz, ohne trailing slash, z.B. https://paperless.example.internal
define('PAPERLESS_BASE_URL', '');

// API-Token: paperless-ngx -> Einstellungen -> Mein Profil -> API-Token
define('PAPERLESS_API_TOKEN', '');

// Bei selbstsigniertem Zertifikat auf false setzen (nicht empfohlen)
define('PAPERLESS_VERIFY_SSL', true);

// Zusätzliche Tags, die JEDEM beim Finalisieren archivierten Dokument mitgegeben werden
// (zusätzlich zum automatischen Dokumenttyp-Tag "Angebot"/"Auftrag"/"Rechnung"/"Lieferschein").
// z.B. der eigene Firmen-/Markenname, damit man in paperless-ngx danach filtern kann.
define('PAPERLESS_EXTRA_TAGS', []);
