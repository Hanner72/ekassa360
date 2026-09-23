<?php
/**
 * PDF-Export für Angebot/Auftrag/Rechnung (Verkauf-Modul)
 * Muster: pdf_u30.php
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);
$doc = getVerkaufsdokument($id);
if (!$doc) {
    die('Dokument nicht gefunden.');
}

try {
    $pdfBytes = buildVerkaufsdokumentPdf($id);
} catch (Exception $e) {
    die('Fehler bei der PDF-Erstellung: ' . htmlspecialchars($e->getMessage()));
}

$filename = verkaufsdokumentDateiname($doc);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfBytes));
echo $pdfBytes;
