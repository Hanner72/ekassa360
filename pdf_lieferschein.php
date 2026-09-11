<?php
/**
 * Lieferschein zu einer finalisierten Rechnung (Verkauf-Modul).
 * Kein eigenes Verkaufsdokument/keine eigene Nummer - referenziert die Rechnungsnummer.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);

try {
    $pdfBytes = buildLieferscheinPdf($id);
} catch (Exception $e) {
    die('Fehler bei der PDF-Erstellung: ' . htmlspecialchars($e->getMessage()));
}

$doc = getVerkaufsdokument($id);
$basis = preg_replace('/[^A-Za-z0-9_-]/', '_', $doc['nummer'] ?: $id);
$filename = 'Lieferschein_' . $basis . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfBytes));
echo $pdfBytes;
