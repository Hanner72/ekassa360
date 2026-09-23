<?php
/**
 * Automatischer Migrations-Runner: wird bei jedem Request von config/database.php
 * aufgerufen und prüft, ob seit dem letzten Deploy neue database/add_*.sql-Dateien
 * hinzugekommen sind. Fehlende werden einmalig angewendet und in der Tabelle
 * `migrationen` vermerkt - so reicht ein reiner Datei-Push (FTP/SFTP) auf den Live-Server,
 * ohne manuellen SQL-Schritt.
 *
 * Bewusst nur database/add_*.sql (durchgängige Namenskonvention seit Einführung dieses
 * Runners). Die älteren database/update_*.sql-Dateien und der volle Neuinstallations-Dump
 * database/ekassa360.sql werden NICHT automatisch ausgeführt - deren Live-Status ist nicht
 * bekannt bzw. ekassa360.sql würde bestehende Daten überschreiben.
 *
 * Wichtige Einschränkung: DDL-Statements (CREATE/ALTER TABLE) committen in MySQL sofort und
 * einzeln, auch innerhalb einer Transaktion. Schlägt eine Migration mit mehreren Statements
 * in der Mitte fehl, bleiben die vorherigen Statements bereits angewendet, obwohl die Datei
 * nicht als erledigt vermerkt wird. Ein erneuter Versuch würde dann an einem bereits
 * angewendeten Statement scheitern (z.B. "Column already exists"). In diesem Fall: Live-DB
 * manuell inspizieren, Migration entsprechend reparieren oder die Zeile von Hand in
 * `migrationen` eintragen.
 */

/**
 * Feste, per Hand anhand der tatsächlichen SQL-Abhängigkeiten (Fremdschlüssel, Spalten, die
 * eine spätere Datei voraussetzt, ENUM-Redefinitionen die Daten aus einer früheren Datei
 * überschreiben würden) geprüfte Ausführungsreihenfolge - siehe Kommentar in
 * fuehreAusstehendeMigrationenAus(). Bei einer neuen Migration: Dateinamen hier ergänzen, an
 * der durch ihre Abhängigkeiten vorgegebenen Stelle (i.d.R. ans Ende).
 */
// Geheimes Token, um sich bei einem fehlgeschlagenen Migrations-Update den echten
// Fehlertext anzeigen zu lassen (siehe migrationFehlerAnzeigen()), wenn kein Zugriff auf das
// Server-Error-Log besteht. Aufruf z.B.: https://DEINE-DOMAIN/index.php?migration_debug=ekassa360-diag-7f3a2c
// Ohne dieses Token sehen normale Besucher weiterhin nur die harmlose Wartungsmeldung.
const MIGRATION_DEBUG_TOKEN = 'ekassa360-diag-7f3a2c';

const MIGRATIONS_REIHENFOLGE = [
    'add_verkauf_module.sql',                  // legt kunden/artikel/nummernkreise/verkaufsdokumente an
    'add_firma_logo.sql',                      // firma.logo_data/logo_mime (Basis für add_firmenprofile.sql)
    'add_nummernkreis_format.sql',             // nummernkreise.format (Basis für add_kunden_nummernkreis.sql)
    'add_kunden_nummernkreis.sql',             // braucht .format-Spalte
    'add_paperless_columns.sql',               // braucht verkaufsdokumente
    'add_wiederkehrende_rechnungen.sql',       // braucht kunden + verkaufsdokumente
    'add_gesamtrabatt.sql',                    // braucht verkaufsdokumente
    'add_email_versand.sql',                   // braucht verkaufsdokumente
    'add_email_vorlagen.sql',
    'add_pdf_vorlagen.sql',                    // Basis für add_lieferschein_vorlage.sql
    'add_lieferschein_vorlage.sql',            // braucht pdf_vorlagen
    'add_artikel_kategorie_nummernkreis.sql',  // fügt kategorien.kurzbezeichnung hinzu (Basis für add_artikelgruppen.sql)
    'add_artikelgruppen.sql',                  // entfernt kategorien.kurzbezeichnung wieder, braucht artikel-Tabelle
    'add_artikeluntergruppen.sql',             // braucht artikelgruppen
    'add_artikelnummer_pro_untergruppe.sql',   // braucht artikelgruppen + artikeluntergruppen
    'add_angebot_status_erweiterung.sql',      // muss vor auftrag laufen (ENUM-Redefinition würde sonst Daten kappen)
    'add_auftrag_status_erweiterung.sql',
    'add_firmenprofile.sql',                   // braucht verkaufsdokumente + firma.logo_data/logo_mime
    'add_firmenprofil_farben.sql',             // braucht firmenprofile
    'add_automatisierung_einstellungen.sql',   // unabhängig, eigene neue Tabelle
    'add_bondrucker_einstellungen.sql',        // unabhängig, eigene neue Tabelle
    'add_zahlungsbedingungen.sql',              // braucht verkaufsdokumente
    'add_zahlungsbedingung_skonto.sql',         // braucht zahlungsbedingungen
    'add_paperless_tag_einstellungen.sql',      // unabhängig, eigene neue Tabelle
    'add_paperless_einstellungen.sql',          // unabhängig, eigene neue Tabelle
    'add_nachrichten.sql',                      // unabhängig, eigene neue Tabelle (nur FK auf benutzer)
    'add_aufgaben.sql',                         // braucht kunden + verkaufsdokumente (FK)
    'add_nummernkreis_einmalig.sql',            // braucht nummernkreise (add_verkauf_module.sql)
];

function fuehreAusstehendeMigrationenAus() {
    $db = db();

    $db->exec("CREATE TABLE IF NOT EXISTS migrationen (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dateiname VARCHAR(255) NOT NULL UNIQUE,
        angewendet_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $dateien = glob(__DIR__ . '/../database/add_*.sql');
    if (!$dateien) {
        return;
    }

    // Reihenfolge NICHT über filemtime() bestimmen: ein reiner Datei-Push (FTP/SFTP/Zip)
    // setzt Änderungsdaten oft auf den Upload-Zeitpunkt und nicht die ursprüngliche
    // Erstellungsreihenfolge - dann kippt die Sortierung faktisch auf alphabetisch (glob()
    // liefert ohne GLOB_NOSORT bereits alphabetisch sortierte Treffer, PHPs usort() ist seit
    // 8.0 stabil, gleiche mtimes fallen also auf diese Reihenfolge zurück). Rein alphabetisch
    // verletzt mehrere echte Abhängigkeiten, z.B. add_kunden_nummernkreis.sql (braucht die
    // `format`-Spalte) vs. add_nummernkreis_format.sql (legt sie an), oder
    // add_firmenprofil_farben.sql vs. add_firmenprofile.sql. Deshalb eine feste, anhand der
    // tatsächlichen FREMDSCHLÜSSEL-/Spalten-Abhängigkeiten geprüfte Reihenfolge: siehe
    // MIGRATIONS_REIHENFOLGE unten. Neue Dateien MÜSSEN dort ergänzt werden (ans Ende, oder an
    // die durch ihre Abhängigkeiten vorgegebene Stelle) - unbekannte Dateien landen sonst nach
    // allen bekannten (alphabetisch untereinander sortiert) und werden nur per error_log()
    // markiert, damit ein vergessener Eintrag aus früherer Situation nicht sofort staut.
    $reihenfolge = array_flip(MIGRATIONS_REIHENFOLGE);
    usort($dateien, function ($a, $b) use ($reihenfolge) {
        $na = basename($a);
        $nb = basename($b);
        $pa = $reihenfolge[$na] ?? PHP_INT_MAX;
        $pb = $reihenfolge[$nb] ?? PHP_INT_MAX;
        if ($pa === PHP_INT_MAX && $pb === PHP_INT_MAX) {
            return $na <=> $nb;
        }
        return $pa <=> $pb;
    });
    foreach ($dateien as $pfad) {
        if (!isset($reihenfolge[basename($pfad)])) {
            error_log("Migration '" . basename($pfad) . "' fehlt in MIGRATIONS_REIHENFOLGE (includes/migrations.php) - wird zuletzt einsortiert.");
        }
    }

    $bereitsAngewendet = array_flip(migrationsListeLaden($db));

    $ausstehend = array_filter($dateien, fn($pfad) => !isset($bereitsAngewendet[basename($pfad)]));
    if (empty($ausstehend)) {
        return;
    }

    // GET_LOCK verhindert, dass zwei gleichzeitige Requests direkt nach einem Deploy
    // dieselbe Migration parallel ausführen. Kein Lock erhalten -> ein anderer Request
    // wendet die Migrationen gerade an, einfach normal weiterladen.
    $lockStmt = $db->query("SELECT GET_LOCK('ekassa360_migrationen', 10)");
    $lockErhalten = (bool)$lockStmt->fetchColumn();
    $lockStmt->closeCursor();
    if (!$lockErhalten) {
        return;
    }

    try {
        // Nach Lock-Erhalt erneut prüfen - ein paralleler Request könnte inzwischen
        // fertig geworden sein.
        $bereitsAngewendet = array_flip(migrationsListeLaden($db));

        foreach ($ausstehend as $pfad) {
            $name = basename($pfad);
            if (isset($bereitsAngewendet[$name])) {
                continue;
            }

            try {
                fuehreSqlDateiAus($db, $pfad);
                $stmt = $db->prepare("INSERT INTO migrationen (dateiname) VALUES (?)");
                $stmt->execute([$name]);
                // Mit PDO::ATTR_EMULATE_PREPARES=false bleibt eine (auch bereits vollständig
                // ausgeführte) native Prepared-Statement-Anweisung ohne closeCursor() als
                // "aktiv" markiert und blockiert die nächste Abfrage auf derselben Verbindung
                // mit SQLSTATE HY000/2014.
                $stmt->closeCursor();
            } catch (Exception $e) {
                error_log("Migration fehlgeschlagen: $name - " . $e->getMessage());
                migrationLockFreigeben($db);
                // migrationFehlerAnzeigen() beendet den Request (exit) - Lock ist zu diesem
                // Zeitpunkt bereits freigegeben, das finally unten läuft für diesen Pfad nicht mehr.
                migrationFehlerAnzeigen($name, $e->getMessage());
            }
        }
    } finally {
        migrationLockFreigeben($db);
    }
}

function migrationsListeLaden($db) {
    $stmt = $db->query("SELECT dateiname FROM migrationen");
    $liste = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt->closeCursor();
    return $liste;
}

/**
 * RELEASE_LOCK() ist wie GET_LOCK() ein SELECT und liefert eine Ergebniszeile zurück - über
 * PDO::exec() (für Anweisungen OHNE Ergebnismenge gedacht) bleibt diese Zeile ungelesen und
 * hinterlässt exakt denselben "unbuffered queries active"-Zustand (SQLSTATE HY000/2014) wie
 * eine nicht geschlossene Prepared-Statement-Anweisung. Deshalb hier bewusst über query()
 * mit anschließendem closeCursor(), nicht über exec().
 */
function migrationLockFreigeben($db) {
    $stmt = $db->query("SELECT RELEASE_LOCK('ekassa360_migrationen')");
    $stmt->closeCursor();
}

/**
 * Führt eine Migrations-SQL-Datei Anweisung für Anweisung aus (statt als ein einziges
 * Multi-Statement PDO::exec()) - PDO_MYSQL hinterlässt nach einem Multi-Statement-exec()
 * mit den hier verwendeten PDO-Einstellungen (PDO::ATTR_EMULATE_PREPARES => false) auf der
 * Verbindung einen "unbuffered queries active"-Zustand, der die nächste Abfrage auf
 * derselben Verbindung mit SQLSTATE HY000/2014 scheitern lässt. Da alle Migrationsdateien
 * selbst geschrieben und einfach gehalten sind (keine Strings mit Semikolon), reicht ein
 * simples Aufsplitten am Semikolon.
 */
function fuehreSqlDateiAus($db, $pfad) {
    $inhalt = file_get_contents($pfad);
    $anweisungen = array_filter(array_map('trim', explode(';', $inhalt)));
    foreach ($anweisungen as $anweisung) {
        $db->exec($anweisung);
    }
}

/**
 * Bricht den Request kontrolliert ab, statt die Seite mit einer möglicherweise
 * unvollständigen Datenbank weiterladen zu lassen (Folgefehler wären für den Besucher
 * verwirrender als eine klare Wartungsmeldung). Bei CLI-Aufrufen (z.B. Cron) stattdessen
 * eine Fehlermeldung auf STDERR.
 *
 * Normale Besucher sehen nur die generische Meldung (der Fehlertext könnte Tabellen-/
 * Spaltennamen oder in seltenen Fällen sogar Datenwerte enthalten). Mit dem korrekten
 * MIGRATION_DEBUG_TOKEN als ?migration_debug=... Parameter wird zusätzlich der echte
 * Dateiname + die Exception-Meldung angezeigt - gedacht als kurzfristige Diagnosehilfe ohne
 * Zugriff auf das Server-Error-Log.
 */
function migrationFehlerAnzeigen($dateiname, $meldung) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Migration fehlgeschlagen: $dateiname - $meldung\n");
        exit(1);
    }

    http_response_code(503);
    header('Retry-After: 60');

    $debugErlaubt = isset($_GET['migration_debug'])
        && hash_equals(MIGRATION_DEBUG_TOKEN, (string)$_GET['migration_debug']);

    $detail = '';
    if ($debugErlaubt) {
        $detail = '<pre style="text-align: left; background: #f4f4f4; border: 1px solid #ccc; padding: 1rem; overflow-x: auto; white-space: pre-wrap;">'
            . htmlspecialchars($dateiname) . "\n\n" . htmlspecialchars($meldung)
            . '</pre>';
    }

    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Wartungsarbeiten</title></head><body style="font-family: sans-serif; max-width: 40rem; margin: 4rem auto; text-align: center;">'
        . '<h1>Wartungsarbeiten</h1>'
        . '<p>Ein automatisches Datenbank-Update ist fehlgeschlagen. Bitte in Kürze erneut versuchen.</p>'
        . $detail
        . '</body></html>';
    exit;
}
