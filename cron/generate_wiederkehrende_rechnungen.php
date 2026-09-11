<?php
/**
 * Cron-Einstiegspunkt: erzeugt fällige wiederkehrende Rechnungen.
 *
 * Windows Task Scheduler (Dev): php.exe C:\...\ekassa360\cron\generate_wiederkehrende_rechnungen.php
 * Linux-Cron (Prod, Proxmox):   0 3 * * * php /pfad/zu/ekassa360/cron/generate_wiederkehrende_rechnungen.php
 *
 * Nur per CLI ausführbar - kein Login-Kontext vorhanden, daher kein Web-Zugriff erlaubt.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Dieses Skript darf nur per CLI ausgeführt werden.\n");
}

chdir(__DIR__ . '/..');

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';
require_once 'includes/paperless.php';
require_once 'includes/mail.php';
require_once 'includes/wiederkehrend_functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Prüfe fällige wiederkehrende Rechnungen...\n";

$ergebnisse = generateFaelligeWiederkehrendeRechnungen();

if (empty($ergebnisse)) {
    echo "Keine fälligen Regeln gefunden.\n";
    exit(0);
}

$fehler = 0;
foreach ($ergebnisse as $r) {
    switch ($r['status']) {
        case 'erstellt':
            echo "  Regel #{$r['id']}: Rechnung {$r['nummer']} erstellt.\n";
            break;
        case 'uebersprungen':
            echo "  Regel #{$r['id']}: übersprungen ({$r['message']}).\n";
            break;
        case 'beendet':
            echo "  Regel #{$r['id']}: beendet ({$r['message']}).\n";
            break;
        case 'fehler':
        default:
            $fehler++;
            echo "  Regel #{$r['id']}: FEHLER - {$r['message']}\n";
            break;
    }
}

echo "Fertig. " . count($ergebnisse) . " Regel(n) verarbeitet, $fehler Fehler.\n";
exit($fehler > 0 ? 1 : 0);
