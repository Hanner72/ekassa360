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

    // Nach Änderungsdatum sortieren - entspricht der tatsächlichen Erstellungsreihenfolge
    // und damit der fachlich korrekten Abhängigkeits-Reihenfolge der Migrationen
    // (z.B. muss die Tabelle einer Fremdschlüssel-Referenz zuerst angelegt werden).
    usort($dateien, fn($a, $b) => filemtime($a) <=> filemtime($b));

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
 */
function migrationFehlerAnzeigen($dateiname, $meldung) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Migration fehlgeschlagen: $dateiname - $meldung\n");
        exit(1);
    }

    http_response_code(503);
    header('Retry-After: 60');
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Wartungsarbeiten</title></head><body style="font-family: sans-serif; max-width: 40rem; margin: 4rem auto; text-align: center;">'
        . '<h1>Wartungsarbeiten</h1>'
        . '<p>Ein automatisches Datenbank-Update ist fehlgeschlagen. Bitte in Kürze erneut versuchen.</p>'
        . '</body></html>';
    exit;
}
