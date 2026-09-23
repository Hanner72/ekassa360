<?php
/**
 * Schnell-Anlage einer Artikeluntergruppe direkt aus dem "Neuer Artikel"-Modal (artikel.php)
 * heraus. Eine Artikeluntergruppe gehört immer zu genau einer Artikelgruppe (z.B. Gruppe
 * "Textilien" -> Untergruppe "T-Shirts") - siehe includes/verkauf_functions.php.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$kurzbezeichnung = trim($_POST['kurzbezeichnung'] ?? '');
$artikelgruppeId = (int)($_POST['artikelgruppe_id'] ?? 0);

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Bitte einen Namen angeben.']);
    exit;
}
if (!$artikelgruppeId) {
    echo json_encode(['success' => false, 'message' => 'Bitte zuerst eine Artikelgruppe auswählen.']);
    exit;
}

$id = saveArtikeluntergruppe(['name' => $name, 'kurzbezeichnung' => $kurzbezeichnung, 'artikelgruppe_id' => $artikelgruppeId]);

echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'kurzbezeichnung' => $kurzbezeichnung, 'artikelgruppe_id' => $artikelgruppeId]);
