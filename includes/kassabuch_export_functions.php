<?php
/**
 * Kassabuch-Journal-Export: Buchungen eines Zeitraums (Jahr/Quartal/Datumsbereich) als
 * ZIP-Datei mit Journal-Tabelle (Excel, via PHPSpreadsheet) + einem PDF-Beleg pro Buchung,
 * benannt nach Buchungsnummer-Rechnungsnummer-Kunde/Lieferant
 * (z.B. "001-2026058-Cotton.pdf").
 *
 * Nur Buchungen MIT Buchungsnummer werden berücksichtigt (siehe getRechnungenFuerJournalExport()
 * in includes/functions.php) - ein Journal für die Betriebsprüfung enthält nur die
 * tatsächlich nummerierte, fortlaufende Buchungsfolge.
 *
 * Benötigt vor dem Aufruf: includes/functions.php, includes/verkauf_functions.php,
 * includes/verkauf_pdf.php, includes/paperless.php (require_once durch die aufrufende Seite).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Entfernt Dateisystem-unsichere Zeichen aus einem Namen für die Verwendung in einem
 * Beleg-Dateinamen. Erhält Umlaute/Leerzeichen, ersetzt nur das, was auf gängigen
 * Dateisystemen (Windows/Linux) bzw. in ZIP-Einträgen tatsächlich Probleme macht.
 */
function sanitiereBelegDateiname($text) {
    $text = trim((string)$text);
    $text = preg_replace('/[\/\\\\:*?"<>|]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text, " .-");
    return $text !== '' ? $text : 'Unbenannt';
}

/**
 * Dateiname für den Beleg einer Buchung: "{Buchungsnummer, min. 3-stellig}-{Rechnungsnummer}-
 * {Kunde/Lieferant}.pdf". Buchungsnummern über 999 werden NICHT gekürzt, nur mindestens
 * auf 3 Stellen aufgefüllt.
 */
function belegDateinameFuerBuchung($rechnung) {
    $buchungsnummer = str_pad((string)(int)$rechnung['buchungsnummer'], 3, '0', STR_PAD_LEFT);
    $rechnungsnummer = sanitiereBelegDateiname($rechnung['rechnungsnummer'] ?: ('Buchung' . $rechnung['id']));
    $firma = sanitiereBelegDateiname($rechnung['kunde_lieferant'] ?: 'Unbekannt');
    return "{$buchungsnummer}-{$rechnungsnummer}-{$firma}.pdf";
}

/**
 * Beleg-PDF-Bytes für eine Buchung besorgen: bevorzugt das verknüpfte Verkaufsdokument
 * (wird frisch gerendert, immer aktuell), sonst ein in paperless-ngx archiviertes Dokument,
 * sonst "kein Beleg verfügbar" (z.B. eine manuelle Buchung ohne hochgeladenen Beleg).
 */
function holeBelegBytesFuerBuchung($rechnung) {
    if (!empty($rechnung['verkaufsdokument_id'])) {
        try {
            $bytes = buildVerkaufsdokumentPdf($rechnung['verkaufsdokument_id']);
            return ['success' => true, 'bytes' => $bytes, 'error' => null];
        } catch (Exception $e) {
            return ['success' => false, 'bytes' => null, 'error' => 'PDF-Erstellung fehlgeschlagen: ' . $e->getMessage()];
        }
    }
    if (!empty($rechnung['paperless_document_id'])) {
        $result = holePaperlessDocumentBytes($rechnung['paperless_document_id']);
        return ['success' => $result['success'], 'bytes' => $result['bytes'], 'error' => $result['error']];
    }
    return ['success' => false, 'bytes' => null, 'error' => 'Kein Beleg verknüpft (weder Verkaufsdokument noch paperless-Dokument).'];
}

/**
 * Baut das Journal als XLSX-Datei (PHPSpreadsheet) und gibt den rohen Dateiinhalt zurück.
 */
function baueJournalXlsx($buchungen) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Journal');

    $kopf = ['Buchungsnr.', 'Datum', 'Typ', 'Rechnungsnr.', 'Kunde/Lieferant', 'Beschreibung', 'Kategorie',
             'Netto', 'USt-Satz %', 'USt-Betrag', 'Brutto', 'Bezahlt', 'Bezahlt am', 'Zahlungsart',
             'Buchungsart', 'Beleg-Datei'];
    $sheet->fromArray($kopf, null, 'A1');
    $sheet->getStyle('A1:P1')->getFont()->setBold(true);

    $zeile = 2;
    foreach ($buchungen as $b) {
        $sheet->fromArray([
            (int)$b['buchungsnummer'],
            $b['datum'],
            $b['typ'] === 'einnahme' ? 'Einnahme' : 'Ausgabe',
            $b['rechnungsnummer'],
            $b['kunde_lieferant'],
            $b['beschreibung'],
            $b['kategorie_name'],
            (float)$b['netto_betrag'],
            $b['ust_prozent'] !== null ? (float)$b['ust_prozent'] : null,
            (float)$b['ust_betrag'],
            (float)$b['brutto_betrag'],
            $b['bezahlt'] ? 'Ja' : 'Nein',
            $b['bezahlt_am'],
            $b['zahlungsart'],
            $b['buchungsart'],
            belegDateinameFuerBuchung($b),
        ], null, "A{$zeile}");
        $zeile++;
    }

    foreach (range('A', 'P') as $spalte) {
        $sheet->getColumnDimension($spalte)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    $tempPfad = tempnam(sys_get_temp_dir(), 'journal_');
    $writer->save($tempPfad);
    $inhalt = file_get_contents($tempPfad);
    unlink($tempPfad);
    return $inhalt;
}

/**
 * Baut den kompletten Export (Journal_<Zeitraum>.xlsx + ein PDF pro Buchung unter Belege/)
 * als ZIP-Datei. Gibt den Pfad zur temporären ZIP-Datei zurück - der Aufrufer ist fürs
 * Löschen nach dem Ausliefern verantwortlich (readfile() + unlink()).
 */
function erzeugeJournalExportZip($datumVon, $datumBis, $zeitraumBezeichnung) {
    $buchungen = getRechnungenFuerJournalExport($datumVon, $datumBis);

    $zipPfad = tempnam(sys_get_temp_dir(), 'kassabuch_export_');
    $zip = new ZipArchive();
    $zip->open($zipPfad, ZipArchive::OVERWRITE);

    $zip->addFromString('Journal_' . $zeitraumBezeichnung . '.xlsx', baueJournalXlsx($buchungen));

    $fehlendeBelege = [];
    foreach ($buchungen as $b) {
        $beleg = holeBelegBytesFuerBuchung($b);
        if ($beleg['success']) {
            $zip->addFromString('Belege/' . belegDateinameFuerBuchung($b), $beleg['bytes']);
        } else {
            $fehlendeBelege[] = [
                'buchungsnummer' => (int)$b['buchungsnummer'],
                'rechnungsnummer' => $b['rechnungsnummer'],
                'grund' => $beleg['error'] ?? 'Unbekannter Fehler',
            ];
        }
    }

    if (!empty($fehlendeBelege)) {
        $hinweisText = "Folgende Buchungen hatten im Export keinen abrufbaren Beleg:\r\n\r\n";
        foreach ($fehlendeBelege as $f) {
            $hinweisText .= "Buchung {$f['buchungsnummer']} ({$f['rechnungsnummer']}): {$f['grund']}\r\n";
        }
        $zip->addFromString('Fehlende_Belege.txt', $hinweisText);
    }

    $zip->close();

    return [
        'zip_pfad' => $zipPfad,
        'anzahl_buchungen' => count($buchungen),
        'anzahl_fehlende_belege' => count($fehlendeBelege),
    ];
}
