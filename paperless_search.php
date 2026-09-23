<?php
/**
 * JSON-Endpoint für Volltextsuche in paperless-ngx (für Beleg-Verknüpfung in rechnungen.php).
 * Der API-Token bleibt serverseitig - der Browser bekommt nur id/title/created/correspondent.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paperless.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');
if ($query === '' || !paperlessConfigured()) {
    echo json_encode(['results' => []]);
    exit;
}

echo json_encode(['results' => searchPaperlessDocuments($query)]);
