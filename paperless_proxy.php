<?php
/**
 * Streamt ein paperless-Dokument serverseitig durch - der API-Token erreicht nie
 * den Browser. IDOR-Schutz: das Dokument muss von einer rechnungen- oder
 * verkaufsdokumente-Zeile referenziert sein, sonst 403.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paperless.php';

requireLogin();

$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(400);
    die('Ungültige Dokument-ID.');
}

$db = db();
$stmt = $db->prepare("SELECT 1 FROM rechnungen WHERE paperless_document_id = ?
                       UNION SELECT 1 FROM verkaufsdokumente WHERE paperless_document_id = ?");
$stmt->execute([$documentId, $documentId]);

if (!$stmt->fetch()) {
    http_response_code(403);
    die('Kein Zugriff auf dieses Dokument.');
}

if (!streamPaperlessDocument($documentId)) {
    http_response_code(502);
    die('Dokument konnte nicht von paperless-ngx geladen werden.');
}
