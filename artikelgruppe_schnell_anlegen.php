<?php
/**
 * Schnell-Anlage einer Artikelgruppe direkt aus dem "Neuer Artikel"-Modal (artikel.php)
 * heraus. Artikelgruppen sind rein organisatorisch (z.B. "Punchen", "T-Shirts", "Hoodies",
 * "Besticken") und unabhängig von den Buchungs-Kategorien (kategorien-Tabelle, steuert
 * E1a/Ledger) - siehe includes/verkauf_functions.php.
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

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Bitte einen Namen angeben.']);
    exit;
}

$id = saveArtikelgruppe(['name' => $name, 'kurzbezeichnung' => $kurzbezeichnung]);

echo json_encode(['success' => true, 'id' => $id, 'name' => $name, 'kurzbezeichnung' => $kurzbezeichnung]);
