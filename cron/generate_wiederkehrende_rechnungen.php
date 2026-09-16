<?php
/**
 * Cron-Einstiegspunkt: erzeugt fällige wiederkehrende Rechnungen.
 *
 * OB und WANN tatsächlich etwas passiert, wird NICHT hier im Crontab festgelegt, sondern in
 * Einstellungen -> Wartung -> "Automatische wiederkehrende Rechnungen" (Tabelle
 * automatisierung_einstellungen, siehe sollWiederkehrendeAutomatischLaufen() in
 * includes/wiederkehrend_functions.php). Der Crontab selbst läuft deshalb bewusst fix und
 * häufig (alle 15 Minuten) und muss nach der Ersteinrichtung nie wieder angefasst werden - eine
 * geänderte Uhrzeit oder ein Deaktivieren wirkt sofort, rein über die Einstellungen-Seite.
 *
 * Linux-Cron (Prod, Proxmox), EINMALIG per SSH einrichten (crontab -e) - die genaue,
 * copy-paste-fertige Zeile steht in Einstellungen -> Wartung -> "Automatische wiederkehrende
 * Rechnungen" (dort ohne die PHP-Kommentar-Escaping-Probleme, die eine Stern-Schrägstrich-
 * Zeitangabe in diesem Docblock hier verursachen würde).
 *
 * Windows Task Scheduler (Dev), alle 15 Minuten wiederholen:
 *   php.exe C:\...\ekassa360\cron\generate_wiederkehrende_rechnungen.php
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

// Läuft alle 15 Minuten, soll aber nur einmal täglich zur konfigurierten Uhrzeit tatsächlich
// etwas tun - in allen anderen Läufen still (kein Output) beenden, sonst spammt der Crontab
// bei jedem Lauf eine Mail (die meisten Systeme mailen nicht-leeren Cron-Output).
if (!sollWiederkehrendeAutomatischLaufen()) {
    exit(0);
}

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
