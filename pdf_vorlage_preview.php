<?php
/**
 * Live-Vorschau einer (noch ungespeicherten) PDF-Vorlage aus den Einstellungen.
 * Rendert mit Beispieldaten (neuestes Dokument des Typs, sonst Dummy-Daten).
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';

requireLogin();

$typ = $_GET['typ'] ?? 'rechnung';
if (!in_array($typ, ['angebot', 'auftrag', 'rechnung'], true)) {
    die('Ungültiger Dokumenttyp.');
}

$vorlage = $_POST['vorlage'] ?? '';
if ($vorlage === '') {
    die('Keine Vorlage übermittelt.');
}

try {
    $html = renderVorlageVorschau($typ, $vorlage);
    $pdfBytes = renderHtmlZuPdfBytes($html);
} catch (Exception $e) {
    die('Fehler bei der Vorschau: ' . htmlspecialchars($e->getMessage()));
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="vorschau_' . $typ . '.pdf"');
header('Content-Length: ' . strlen($pdfBytes));
echo $pdfBytes;
