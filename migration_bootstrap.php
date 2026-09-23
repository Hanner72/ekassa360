<?php
/**
 * EINMAL-WERKZEUG: markiert bereits im Live-Schema vorhandene add_*.sql-Migrationen in der
 * `migrationen`-Tabelle als erledigt, OHNE ihr SQL erneut auszuführen - für den Fall, dass die
 * Datenbank den Stand schon hat (z.B. über einen ekassa360.sql-Import), die neue
 * migrationen-Tabelle das aber noch nicht weiß (siehe includes/migrations.php).
 *
 * Verwendet BEWUSST KEINE eigenen Zugangsdaten - SKIP_AUTO_MIGRATION überspringt nur den
 * automatischen Migrationslauf beim Einbinden von config/database.php, nutzt aber dieselbe
 * echte DB-Verbindung (db()), die auf diesem Server bereits korrekt konfiguriert ist. So
 * landen niemals Zugangsdaten in dieser Datei.
 *
 * Aufruf:
 *   1. https://DOMAIN/migration_bootstrap.php?token=ekassa360-diag-7f3a2c
 *      -> zeigt nur den Status jeder Migration, ändert nichts.
 *   2. https://DOMAIN/migration_bootstrap.php?token=ekassa360-diag-7f3a2c&apply=1
 *      -> markiert die als "bereits vorhanden" erkannten Migrationen tatsächlich als erledigt.
 *   3. https://DOMAIN/migration_bootstrap.php?token=ekassa360-diag-7f3a2c&repair=DATEINAME.sql
 *      -> für eine Migration, die laut Fehlermeldung nur TEILWEISE angewendet wurde (z.B.
 *         "Duplicate column" beim erneuten Versuch): führt die Datei Anweisung für Anweisung
 *         erneut aus, überspringt aber Anweisungen, die mit einem "bereits vorhanden"-Fehler
 *         scheitern (Spalte/Tabelle/Schlüssel existiert schon, Zeile schon eingefügt), und
 *         bricht bei jedem ANDEREN Fehler sofort ab, ohne die Migration als erledigt zu
 *         markieren. Erst anzeigen (ohne &confirm=1), dann mit &confirm=1 wirklich ausführen.
 *
 * WICHTIG: Nach erfolgreicher Anwendung diese Datei vom Server löschen (Diagnose-Werkzeug,
 * kein Dauerbetrieb).
 */

define('SKIP_AUTO_MIGRATION', true);
require_once __DIR__ . '/config/database.php';

if (!isset($_GET['token']) || !hash_equals(MIGRATION_DEBUG_TOKEN, (string)$_GET['token'])) {
    http_response_code(403);
    exit('Kein Zugriff.');
}

$db = db();

$db->exec("CREATE TABLE IF NOT EXISTS migrationen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dateiname VARCHAR(255) NOT NULL UNIQUE,
    angewendet_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

function tabelleExistiert($db, $tabelle) {
    $stmt = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$tabelle]);
    return (bool)$stmt->fetchColumn();
}
function spalteExistiert($db, $tabelle, $spalte) {
    $stmt = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tabelle, $spalte]);
    return (bool)$stmt->fetchColumn();
}
function enumEnthaelt($db, $tabelle, $spalte, $wert) {
    $stmt = $db->prepare("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$tabelle, $spalte]);
    $typ = $stmt->fetchColumn();
    return $typ !== false && str_contains($typ, "'$wert'");
}
function zeileExistiert($db, $tabelle, $where, $params) {
    try {
        $stmt = $db->prepare("SELECT 1 FROM `$tabelle` WHERE $where LIMIT 1");
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// "Bereits vorhanden"-Fehlerklassen, die im Reparatur-Modus übersprungen werden dürfen -
// alles andere (z.B. Syntaxfehler, fehlende Tabelle für eine Referenz) bricht sofort ab.
const TOLERIERTE_SQLSTATES_REPARATUR = [
    '42S21', // Duplicate column name
    '42S01', // Table already exists
    '42000', // z.B. Duplicate key name (ADD KEY/INDEX)
    '23000', // Duplicate entry (INSERT einer bereits vorhandenen Zeile)
];

// Erkennungsmerkmal je Migration: eine sichere, spezifische Schema-Eigenschaft, die NUR
// existiert, wenn diese Migration bereits vollständig durchgelaufen ist. Bei Fehlern (z.B.
// referenzierte Tabelle existiert noch gar nicht) gilt die Migration automatisch als "fehlt".
$marker = [
    'add_verkauf_module.sql' => fn($db) => tabelleExistiert($db, 'kunden'),
    'add_firma_logo.sql' => fn($db) => spalteExistiert($db, 'firma', 'logo_data'),
    'add_nummernkreis_format.sql' => fn($db) => spalteExistiert($db, 'nummernkreise', 'format'),
    'add_kunden_nummernkreis.sql' => fn($db) => zeileExistiert($db, 'nummernkreise', 'schluessel = ?', ['kunde']),
    'add_paperless_columns.sql' => fn($db) => spalteExistiert($db, 'rechnungen', 'verkaufsdokument_id'),
    'add_wiederkehrende_rechnungen.sql' => fn($db) => tabelleExistiert($db, 'wiederkehrende_rechnungen'),
    'add_gesamtrabatt.sql' => fn($db) => spalteExistiert($db, 'verkaufsdokumente', 'gesamtrabatt_prozent'),
    'add_email_versand.sql' => fn($db) => spalteExistiert($db, 'verkaufsdokumente', 'versendet_am'),
    'add_email_vorlagen.sql' => fn($db) => tabelleExistiert($db, 'email_vorlagen'),
    'add_pdf_vorlagen.sql' => fn($db) => tabelleExistiert($db, 'pdf_vorlagen'),
    'add_lieferschein_vorlage.sql' => fn($db) => enumEnthaelt($db, 'pdf_vorlagen', 'typ', 'lieferschein'),
    // Kategorie-Kurzbezeichnung wurde von add_artikelgruppen.sql wieder entfernt (Rückbau) -
    // deren DROP COLUMN würde ohne diese Migration selbst fehlschlagen, d.h. "artikelgruppen
    // existiert" beweist zuverlässig, dass diese Migration vorher bereits gelaufen ist.
    'add_artikel_kategorie_nummernkreis.sql' => fn($db) => tabelleExistiert($db, 'artikelgruppen'),
    'add_artikelgruppen.sql' => fn($db) => tabelleExistiert($db, 'artikelgruppen'),
    'add_artikeluntergruppen.sql' => fn($db) => tabelleExistiert($db, 'artikeluntergruppen'),
    'add_artikelnummer_pro_untergruppe.sql' => fn($db) => tabelleExistiert($db, 'artikel_zaehler_ohne_gruppe'),
    'add_angebot_status_erweiterung.sql' => fn($db) => enumEnthaelt($db, 'verkaufsdokumente', 'status', 'auftrag_erstellt'),
    'add_auftrag_status_erweiterung.sql' => fn($db) => enumEnthaelt($db, 'verkaufsdokumente', 'status', 'rechnung_erstellt'),
    'add_firmenprofile.sql' => fn($db) => tabelleExistiert($db, 'firmenprofile'),
    'add_firmenprofil_farben.sql' => fn($db) => spalteExistiert($db, 'firmenprofile', 'farbe1'),
    'add_automatisierung_einstellungen.sql' => fn($db) => tabelleExistiert($db, 'automatisierung_einstellungen'),
    'add_bondrucker_einstellungen.sql' => fn($db) => tabelleExistiert($db, 'bondrucker_einstellungen'),
    'add_zahlungsbedingungen.sql' => fn($db) => tabelleExistiert($db, 'zahlungsbedingungen'),
    'add_zahlungsbedingung_skonto.sql' => fn($db) => spalteExistiert($db, 'zahlungsbedingungen', 'skonto_prozent'),
    'add_paperless_tag_einstellungen.sql' => fn($db) => tabelleExistiert($db, 'paperless_tag_einstellungen'),
    'add_paperless_einstellungen.sql' => fn($db) => tabelleExistiert($db, 'paperless_einstellungen'),
    'add_nachrichten.sql' => fn($db) => tabelleExistiert($db, 'nachrichten'),
    'add_aufgaben.sql' => fn($db) => tabelleExistiert($db, 'aufgaben'),
    'add_nummernkreis_einmalig.sql' => fn($db) => (bool) $db->query(
        "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'nummernkreise' AND index_name = 'unique_schluessel'"
    )->fetchColumn(),
];

echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Migrations-Bootstrap</title>'
    . '<style>body{font-family:sans-serif;max-width:50rem;margin:2rem auto;} table{border-collapse:collapse;width:100%;} td,th{border:1px solid #ccc;padding:0.4rem 0.6rem;text-align:left;} .ok{color:green;} .fehlt{color:#b00;} .schon{color:#888;} pre{background:#f4f4f4;border:1px solid #ccc;padding:0.75rem;overflow-x:auto;white-space:pre-wrap;}</style>'
    . '</head><body><h1>Migrations-Bootstrap</h1>';

// --- Reparatur-Modus für eine einzelne, teilweise angewendete Migration ---
if (isset($_GET['repair'])) {
    $name = basename((string)$_GET['repair']); // basename() gegen Pfad-Traversal
    $pfad = __DIR__ . '/database/' . $name;

    if (!in_array($name, MIGRATIONS_REIHENFOLGE, true) || !file_exists($pfad)) {
        echo '<p><strong>Unbekannte Migrationsdatei: ' . htmlspecialchars($name) . '</strong></p></body></html>';
        exit;
    }

    $bereitsMarkiert = $db->prepare("SELECT 1 FROM migrationen WHERE dateiname = ?");
    $bereitsMarkiert->execute([$name]);
    if ($bereitsMarkiert->fetchColumn()) {
        echo '<p>' . htmlspecialchars($name) . ' ist bereits als erledigt markiert - keine Reparatur nötig.</p></body></html>';
        exit;
    }

    $bestaetigt = isset($_GET['confirm']) && $_GET['confirm'] === '1';
    $inhalt = file_get_contents($pfad);
    $anweisungen = array_values(array_filter(array_map('trim', explode(';', $inhalt))));

    echo '<h2>Reparatur: ' . htmlspecialchars($name) . '</h2>';
    echo '<table><tr><th>#</th><th>Anweisung</th><th>Ergebnis</th></tr>';

    $abgebrochen = false;
    foreach ($anweisungen as $i => $anweisung) {
        $nr = $i + 1;
        $kurz = htmlspecialchars(strlen($anweisung) > 100 ? substr($anweisung, 0, 100) . '…' : $anweisung);

        if ($abgebrochen) {
            echo '<tr><td>' . $nr . '</td><td><code>' . $kurz . '</code></td><td>-</td></tr>';
            continue;
        }
        if (!$bestaetigt) {
            echo '<tr><td>' . $nr . '</td><td><code>' . $kurz . '</code></td><td>würde ausgeführt (mit &confirm=1)</td></tr>';
            continue;
        }

        try {
            $db->exec($anweisung);
            echo '<tr><td>' . $nr . '</td><td><code>' . $kurz . '</code></td><td class="ok">ausgeführt</td></tr>';
        } catch (PDOException $e) {
            $sqlstate = $e->getCode();
            if (in_array($sqlstate, TOLERIERTE_SQLSTATES_REPARATUR, true)) {
                echo '<tr><td>' . $nr . '</td><td><code>' . $kurz . '</code></td><td class="schon">übersprungen (bereits vorhanden): ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
            } else {
                echo '<tr><td>' . $nr . '</td><td><code>' . $kurz . '</code></td><td class="fehlt">ABGEBROCHEN: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                $abgebrochen = true;
            }
        }
    }
    echo '</table>';

    if (!$bestaetigt) {
        echo '<p>Nur Anzeige, es wurde nichts geändert. Zum Ausführen <code>&amp;confirm=1</code> an die URL anhängen.</p>';
    } elseif ($abgebrochen) {
        echo '<p><strong>Abgebrochen bei einer unerwarteten Fehlermeldung - ' . htmlspecialchars($name) . ' wurde NICHT als erledigt markiert.</strong> Bitte die abgebrochene Zeile prüfen, bevor es weitergeht.</p>';
    } else {
        $stmt = $db->prepare("INSERT INTO migrationen (dateiname) VALUES (?)");
        $stmt->execute([$name]);
        echo '<p><strong>Alle Anweisungen erledigt (ausgeführt oder bereits vorhanden) - ' . htmlspecialchars($name) . ' ist jetzt als erledigt markiert.</strong></p>';
    }
    echo '<p><a href="?token=' . urlencode($_GET['token']) . '">Zurück zur Übersicht</a></p></body></html>';
    exit;
}

// --- Übersicht: Status aller Migrationen ---
$bereitsMarkiert = array_flip((array)$db->query("SELECT dateiname FROM migrationen")->fetchAll(PDO::FETCH_COLUMN));
$anwenden = isset($_GET['apply']) && $_GET['apply'] === '1';

echo '<table><tr><th>Migration</th><th>Status</th><th>Aktion</th></tr>';

$neuMarkiert = 0;
$fehlend = [];
foreach (MIGRATIONS_REIHENFOLGE as $name) {
    $pfad = __DIR__ . '/database/' . $name;
    if (!file_exists($pfad)) {
        echo '<tr><td>' . htmlspecialchars($name) . '</td><td colspan="2">Datei fehlt auf diesem Server</td></tr>';
        continue;
    }
    if (isset($bereitsMarkiert[$name])) {
        echo '<tr><td>' . htmlspecialchars($name) . '</td><td class="schon">bereits in migrationen-Tabelle</td><td>-</td></tr>';
        continue;
    }

    $vorhanden = isset($marker[$name]) ? $marker[$name]($db) : false;
    if ($vorhanden) {
        $aktion = '-';
        if ($anwenden) {
            $stmt = $db->prepare("INSERT IGNORE INTO migrationen (dateiname) VALUES (?)");
            $stmt->execute([$name]);
            $neuMarkiert++;
            $aktion = 'als erledigt markiert';
        } else {
            $aktion = 'würde als erledigt markiert (mit &apply=1)';
        }
        echo '<tr><td>' . htmlspecialchars($name) . '</td><td class="ok">Schema bereits vorhanden</td><td>' . $aktion . '</td></tr>';
    } else {
        $fehlend[] = $name;
        $repairLink = '<a href="?token=' . urlencode($_GET['token']) . '&repair=' . urlencode($name) . '">Reparatur-Modus</a>';
        echo '<tr><td>' . htmlspecialchars($name) . '</td><td class="fehlt">fehlt noch</td><td>läuft normal beim nächsten Seitenaufruf, oder ' . $repairLink . ' falls das mit "already exists" fehlschlägt</td></tr>';
    }
}
echo '</table>';

if ($anwenden) {
    echo '<p><strong>' . $neuMarkiert . ' Migration(en) als erledigt markiert.</strong></p>';
    if (empty($fehlend)) {
        echo '<p>Alle Migrationen sind jetzt erledigt. Die Seite sollte wieder normal funktionieren.</p>';
    } else {
        echo '<p>Noch offen: ' . htmlspecialchars(implode(', ', $fehlend)) . '</p>';
    }
} else {
    echo '<p>Nur Anzeige, es wurde nichts geändert. Zum Anwenden <code>&amp;apply=1</code> an die URL anhängen.</p>';
}

echo '<p><strong>Nach erfolgreicher Reparatur: diese Datei (migration_bootstrap.php) vom Server löschen.</strong></p>';
echo '</body></html>';
