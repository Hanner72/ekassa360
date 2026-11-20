<?php
/**
 * Kassabuch-Journal-Export: Streamt eine ZIP-Datei mit Journal (Excel) + Beleg-PDFs für
 * den per Formular gewählten Zeitraum (Jahr/Quartal/Datumsbereich) - siehe rechnungen.php
 * für das auslösende Formular und includes/kassabuch_export_functions.php für die
 * eigentliche Export-Logik.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';
require_once 'includes/paperless.php';
require_once 'includes/kassabuch_export_functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: rechnungen.php');
    exit;
}

$modus = $_POST['export_modus'] ?? 'jahr';

if ($modus === 'quartal') {
    $jahr = (int)($_POST['export_jahr'] ?? date('Y'));
    $quartal = max(1, min(4, (int)($_POST['export_quartal'] ?? 1)));
    $startMonat = ($quartal - 1) * 3 + 1;
    $datumVon = sprintf('%04d-%02d-01', $jahr, $startMonat);
    $datumBis = date('Y-m-t', strtotime($datumVon . ' +2 months'));
    $bezeichnung = "{$jahr}_Q{$quartal}";
} elseif ($modus === 'datum') {
    // strtotime() + date()-Normalisierung statt der rohen $_POST-Strings direkt zu verwenden:
    // $datumVon/$datumBis landen später im Dateinamen (Content-Disposition-Header), eine
    // beliebige Nutzereingabe dort unverändert einzusetzen wäre ein Header-/Dateinamen-
    // Injection-Risiko. Nach der Normalisierung bestehen beide garantiert nur aus Ziffern/-.
    $datumVonEingabe = strtotime($_POST['export_datum_von'] ?? '') ?: strtotime(date('Y') . '-01-01');
    $datumBisEingabe = strtotime($_POST['export_datum_bis'] ?? '') ?: strtotime('today');
    $datumVon = date('Y-m-d', $datumVonEingabe);
    $datumBis = date('Y-m-d', $datumBisEingabe);
    $bezeichnung = str_replace('-', '', $datumVon) . '_' . str_replace('-', '', $datumBis);
} else { // 'jahr' (Standard)
    $jahr = (int)($_POST['export_jahr'] ?? date('Y'));
    $datumVon = "{$jahr}-01-01";
    $datumBis = "{$jahr}-12-31";
    $bezeichnung = (string)$jahr;
}

if (strtotime($datumVon) === false || strtotime($datumBis) === false || strtotime($datumVon) > strtotime($datumBis)) {
    setFlashMessage('danger', 'Ungültiger Zeitraum für den Export (Startdatum liegt nach dem Enddatum?).');
    header('Location: rechnungen.php');
    exit;
}

// Beim Abruf vieler paperless-Belege kann der Export etwas dauern - keine künstliche
// Zeitbegrenzung für diesen einen Request.
set_time_limit(0);

$ergebnis = erzeugeJournalExportZip($datumVon, $datumBis, $bezeichnung);

if ($ergebnis['anzahl_buchungen'] === 0) {
    @unlink($ergebnis['zip_pfad']);
    setFlashMessage('warning', 'Keine nummerierten Buchungen im gewählten Zeitraum (' . $datumVon . ' bis ' . $datumBis . ') gefunden.');
    header('Location: rechnungen.php');
    exit;
}

logAction('rechnungen', 0, 'export', "Kassabuch-Journal-Export {$datumVon} bis {$datumBis}: {$ergebnis['anzahl_buchungen']} Buchung(en), {$ergebnis['anzahl_fehlende_belege']} ohne Beleg.");

$downloadName = 'Kassabuch_Journal_' . $bezeichnung . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($ergebnis['zip_pfad']));
readfile($ergebnis['zip_pfad']);
@unlink($ergebnis['zip_pfad']);
exit;
