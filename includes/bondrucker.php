<?php
/**
 * Netzwerk-Bondrucker (ESC/POS über TCP, Standardport 9100) für Verkaufsrechnungen.
 * Nutzt die Composer-Bibliothek mike42/escpos-php. IP-Adresse, Port und Papierbreite sind
 * in den Einstellungen (Tab "Wartung") konfigurierbar (Tabelle bondrucker_einstellungen),
 * damit ein Druckerwechsel keine Code-Änderung braucht.
 *
 * Alle öffentlichen Funktionen liefern strukturierte Ergebnis-Arrays statt Exceptions zu
 * werfen (analog zu includes/paperless.php) - ein Druckfehler (Drucker aus, falsche IP,
 * Papier leer) darf die aufrufende Seite nicht mit einem Fatal Error abbrechen.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

function getBondruckerEinstellungen() {
    $db = db();
    $row = $db->query("SELECT * FROM bondrucker_einstellungen WHERE id = 1")->fetch();
    return $row ?: ['id' => 1, 'ip_adresse' => null, 'port' => 9100, 'papierbreite' => '80mm'];
}

function saveBondruckerEinstellungen($ipAdresse, $port, $papierbreite) {
    $db = db();
    $papierbreite = in_array($papierbreite, ['58mm', '80mm'], true) ? $papierbreite : '80mm';
    $port = (int)$port ?: 9100;
    $stmt = $db->prepare("UPDATE bondrucker_einstellungen SET ip_adresse = ?, port = ?, papierbreite = ? WHERE id = 1");
    $stmt->execute([trim($ipAdresse) ?: null, $port, $papierbreite]);
}

function bondruckerKonfiguriert() {
    $e = getBondruckerEinstellungen();
    return !empty($e['ip_adresse']);
}

/** Zeichen pro Zeile im Standard-Zeichensatz (Font A), abhängig von der Papierbreite. */
function bondruckerZeichenbreite($papierbreite) {
    return $papierbreite === '58mm' ? 32 : 48;
}

/**
 * $links und $rechts auf eine Zeile mit $breite Zeichen, $links linksbündig und $rechts
 * rechtsbündig, dazwischen mit Leerzeichen aufgefüllt. $links wird gekürzt, falls für beide
 * zusammen kein Platz ist (z.B. sehr lange Artikelbezeichnung + Betrag).
 */
function bondruckerZeile($links, $rechts, $breite) {
    $links = (string)$links;
    $rechts = (string)$rechts;
    $platzFuerLinks = $breite - strlen($rechts) - 1;
    if ($platzFuerLinks < 1) {
        $platzFuerLinks = max(1, $breite - 1);
    }
    if (strlen($links) > $platzFuerLinks) {
        $links = substr($links, 0, $platzFuerLinks);
    }
    $luecke = max(1, $breite - strlen($links) - strlen($rechts));
    return $links . str_repeat(' ', $luecke) . $rechts;
}

/** Wortumbruch auf die gegebene Zeilenbreite, gibt ein Array einzelner Zeilen zurück. */
function bondruckerUmbruch($text, $breite) {
    return explode("\n", wordwrap((string)$text, $breite, "\n", true));
}

/**
 * Betrag fürs Bon-Layout formatieren - bewusst "EUR" statt "€" (anders als formatBetrag() in
 * includes/functions.php, das für PDF/Web gedacht ist). Das Euro-Zeichen liegt in ESC/POS in
 * einer druckerspezifischen Zeichentabelle, deren Tabellennummer zwischen Herstellern nicht
 * einheitlich ist - viele (v.a. günstige/generische) Bondrucker zeigen dafür ein falsches
 * Zeichen an. "EUR" besteht nur aus druckerunabhängigen ASCII-Zeichen und funktioniert daher
 * garantiert auf jedem ESC/POS-Drucker.
 */
function bondruckerBetrag($betrag) {
    return 'EUR ' . number_format((float)$betrag, 2, ',', '.');
}

/**
 * Baut eine PrintConnector+Printer-Instanz für die aktuell konfigurierten Bondrucker-
 * Einstellungen. Wirft eine Exception, wenn keine IP konfiguriert ist oder die Verbindung
 * fehlschlägt (Timeout bewusst kurz, damit eine falsche/nicht erreichbare IP die Seite nicht
 * lange blockiert).
 */
function bondruckerVerbinden() {
    $einstellungen = getBondruckerEinstellungen();
    if (empty($einstellungen['ip_adresse'])) {
        throw new Exception('Kein Bondrucker konfiguriert (Einstellungen -> Wartung).');
    }
    $connector = new NetworkPrintConnector($einstellungen['ip_adresse'], (int)$einstellungen['port'], 5);
    return [new Printer($connector), bondruckerZeichenbreite($einstellungen['papierbreite'])];
}

/**
 * Verkaufsrechnung als Bon drucken. Erwartet ein finalisiertes oder Entwurfs-Dokument vom
 * Typ 'rechnung' (Positionen + Summen sind bei beiden bereits vorhanden).
 */
function druckeVerkaufsrechnungAufBondrucker($verkaufsdokumentId) {
    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc || $doc['typ'] !== 'rechnung') {
        return ['success' => false, 'message' => 'Rechnung nicht gefunden.'];
    }
    $positionen = getVerkaufsdokumentPositionen($verkaufsdokumentId);
    $firma = getFirmendaten();

    // Firmenprofil (nur Name) des Dokuments, sonst das Standard-Profil - analog zur PDF-Erzeugung
    // in baueDokumentPlatzhalter() (includes/verkauf_pdf.php). Der Profilname (z.B. der
    // Marken-/Zweigname) kommt groß oben, der eigentliche (steuerlich relevante) Firmenname
    // darunter - nur wenn die beiden sich unterscheiden, sonst wäre die Zeile doppelt.
    $profil = !empty($doc['firmenprofil_id']) ? getFirmenprofil($doc['firmenprofil_id']) : null;
    if (!$profil || !$profil['aktiv']) {
        $profil = getStandardFirmenprofil();
    }
    $profilName = ($profil['name'] ?? '') ?: ($firma['name'] ?? '');

    // Auftragsnummer des Vorgänger-Dokuments, falls diese Rechnung aus einem Auftrag umgewandelt
    // wurde (umwandelnVerkaufsdokument() erlaubt nur auftrag -> rechnung, ein vorgaenger_id bei
    // einer Rechnung ist also immer ein Auftrag).
    $auftragsnummer = '';
    if (!empty($doc['vorgaenger_id'])) {
        $stmt = db()->prepare("SELECT nummer FROM verkaufsdokumente WHERE id = ?");
        $stmt->execute([$doc['vorgaenger_id']]);
        $auftragsnummer = (string)$stmt->fetchColumn();
    }

    try {
        [$printer, $breite] = bondruckerVerbinden();

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 2);
        $printer->text($profilName . "\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        if (!empty($firma['name']) && $firma['name'] !== $profilName) {
            $printer->text($firma['name'] . "\n");
        }
        $firmaAdresse = trim(($firma['strasse'] ?? '') . ', ' . ($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? ''), ' ,');
        if ($firmaAdresse !== '') {
            $printer->text($firmaAdresse . "\n");
        }
        if (!empty($firma['uid_nummer'])) {
            $printer->text('UID: ' . $firma['uid_nummer'] . "\n");
        }
        $printer->feed(1);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->setEmphasis(true);
        $printer->text('RECHNUNG ' . ($doc['nummer'] ?: '(Entwurf)') . "\n");
        $printer->setEmphasis(false);
        if ($auftragsnummer !== '') {
            $printer->text(bondruckerZeile('Auftrag:', $auftragsnummer, $breite) . "\n");
        }
        $printer->text(bondruckerZeile('Datum:', formatDatum($doc['datum']), $breite) . "\n");
        $kundeName = kundenAnzeigename($doc);
        if ($kundeName !== '') {
            $printer->text(bondruckerZeile('Kunde:', $kundeName, $breite) . "\n");
        }
        if (!empty($doc['kundennummer'])) {
            $printer->text(bondruckerZeile('Kundennr.:', $doc['kundennummer'], $breite) . "\n");
        }
        if (!empty($doc['betreff'])) {
            foreach (bondruckerUmbruch('Betreff: ' . $doc['betreff'], $breite) as $zeile) {
                $printer->text($zeile . "\n");
            }
        }
        $printer->text(str_repeat('-', $breite) . "\n");

        foreach ($positionen as $pos) {
            foreach (bondruckerUmbruch($pos['bezeichnung'], $breite) as $zeile) {
                $printer->text($zeile . "\n");
            }
            $mengeText = number_format($pos['menge'], 2, ',', '.') . ' ' . $pos['einheit'] . ' x ' . bondruckerBetrag($pos['einzelpreis_netto']);
            $printer->text(bondruckerZeile($mengeText, bondruckerBetrag($pos['brutto_summe']), $breite) . "\n");
        }
        $printer->text(str_repeat('-', $breite) . "\n");

        $printer->text(bondruckerZeile('Netto gesamt:', bondruckerBetrag($doc['netto_gesamt']), $breite) . "\n");
        if (empty($firma['kleinunternehmer'])) {
            $printer->text(bondruckerZeile('USt gesamt:', bondruckerBetrag($doc['ust_gesamt']), $breite) . "\n");
        } else {
            $printer->text("Kleinunternehmer gem. Art. 6 Abs. 1 Z 27 UStG\n");
        }
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 1);
        // Bei doppelter Zeichenbreite passt nur die halbe Zeichenanzahl in dieselbe Papierbreite.
        $printer->text(bondruckerZeile('BRUTTO:', bondruckerBetrag($doc['brutto_gesamt']), (int)($breite / 2)) . "\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);

        $printer->feed(2);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Vielen Dank!\n");
        $printer->feed(3);
        $printer->cut();
        $printer->close();

        return ['success' => true, 'message' => 'Bon gedruckt.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Druck fehlgeschlagen: ' . $e->getMessage()];
    }
}

/** Testdruck für die Einstellungen-Seite - prüft Verbindung + Druckausgabe ohne echtes Dokument. */
function druckeBondruckerTestseite($ipAdresse, $port, $papierbreite) {
    $breite = bondruckerZeichenbreite($papierbreite);
    try {
        $connector = new NetworkPrintConnector($ipAdresse, (int)$port, 5);
        $printer = new Printer($connector);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("TESTDRUCK\n");
        $printer->setEmphasis(false);
        $printer->text("EKassa360 Bondrucker-Test\n");
        $printer->text(date('d.m.Y H:i:s') . "\n");
        $printer->text(str_repeat('-', $breite) . "\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Wenn du das lesen kannst,\nfunktioniert die Verbindung und\ndie Papierbreite ist korrekt (" . $breite . " Zeichen/Zeile).\n");
        $printer->feed(3);
        $printer->cut();
        $printer->close();
        return ['success' => true, 'message' => 'Testdruck gesendet.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Testdruck fehlgeschlagen: ' . $e->getMessage()];
    }
}
