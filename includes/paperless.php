<?php
/**
 * paperless-ngx Client. Nutzt cURL wenn verfügbar, sonst automatisch einen
 * PHP-Stream-Fallback (file_get_contents + SSL-Context) - manche lokale
 * Laragon-PHP-Builds haben eine kaputte curl-Extension (bekanntes Windows-DLL-Problem),
 * während der Stream-Wrapper über openssl zuverlässig funktioniert. Dadurch läuft die
 * Integration überall gleich, ohne dass Aufrufer den Unterschied merken.
 *
 * Alle Funktionen liefern bei Fehlern strukturierte Ergebnis-Arrays statt Exceptions
 * zu werfen - ein Archivierungs-Fehlschlag darf das Finalisieren einer Rechnung
 * nicht blockieren (Aufrufer entscheidet über Badge "nicht archiviert" / Retry).
 */

if (file_exists(__DIR__ . '/../config/paperless.php')) {
    require_once __DIR__ . '/../config/paperless.php';
}

function paperlessConfigured() {
    // Mindestens ein Transportweg muss nutzbar sein, sonst würde jeder Aufruf mit
    // einem nicht abfangbaren PHP-Error abstürzen statt sauber "nicht verfügbar" zu melden.
    $transportVerfuegbar = function_exists('curl_init') || ini_get('allow_url_fopen');

    return defined('PAPERLESS_ENABLED') && PAPERLESS_ENABLED
        && defined('PAPERLESS_BASE_URL') && PAPERLESS_BASE_URL
        && defined('PAPERLESS_API_TOKEN') && PAPERLESS_API_TOKEN
        && $transportVerfuegbar;
}

/**
 * Low-Level-HTTP-Transport: cURL falls verfügbar, sonst PHP-Streams.
 * Gibt einheitlich ['status' => int, 'body' => string|false, 'content_type' => string|null, 'error' => string|null] zurück.
 */
function paperlessHttpTransport($method, $url, array $headers, $body = null, $timeoutSeconds = 20) {
    if (function_exists('curl_init')) {
        return paperlessHttpViaCurl($method, $url, $headers, $body, $timeoutSeconds);
    }
    return paperlessHttpViaStream($method, $url, $headers, $body, $timeoutSeconds);
}

function paperlessHttpViaCurl($method, $url, array $headers, $body, $timeoutSeconds) {
    $verifySsl = !defined('PAPERLESS_VERIFY_SSL') || PAPERLESS_VERIFY_SSL;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false) {
        return ['status' => 0, 'body' => false, 'content_type' => null, 'error' => $curlError ?: 'Verbindung zu paperless fehlgeschlagen.'];
    }
    return ['status' => $status, 'body' => $responseBody, 'content_type' => $contentType, 'error' => null];
}

function paperlessHttpViaStream($method, $url, array $headers, $body, $timeoutSeconds) {
    $verifySsl = !defined('PAPERLESS_VERIFY_SSL') || PAPERLESS_VERIFY_SSL;

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true, // damit auch 4xx/5xx-Antworten als Body zurückkommen statt eine PHP-Warnung zu werfen
            'timeout' => $timeoutSeconds,
        ],
        'ssl' => [
            'verify_peer' => $verifySsl,
            'verify_peer_name' => $verifySsl,
        ],
    ];
    if ($body !== null) {
        $options['http']['content'] = $body;
    }

    $context = stream_context_create($options);
    $responseBody = @file_get_contents($url, false, $context);

    if ($responseBody === false) {
        $error = error_get_last();
        return ['status' => 0, 'body' => false, 'content_type' => null, 'error' => $error['message'] ?? 'Verbindung zu paperless fehlgeschlagen.'];
    }

    $status = 0;
    $contentType = null;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $headerLine, $m)) {
                $status = (int)$m[1]; // bei Redirects gewinnt die letzte Statuszeile
            } elseif (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, strlen('Content-Type:')));
            }
        }
    }

    return ['status' => $status, 'body' => $responseBody, 'content_type' => $contentType, 'error' => null];
}

/**
 * Formt ein rohes Transport-Ergebnis in das einheitliche API-Antwortformat um (JSON-Decode + Erfolgsstatus).
 */
function paperlessFormatResult($result) {
    if ($result['body'] === false) {
        return ['success' => false, 'status' => 0, 'body' => null, 'error' => $result['error'] ?: 'Verbindung zu paperless fehlgeschlagen.'];
    }

    $decoded = json_decode($result['body'], true);
    $body = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $result['body'];

    if ($result['status'] < 200 || $result['status'] >= 300) {
        return ['success' => false, 'status' => $result['status'], 'body' => $body, 'error' => "HTTP {$result['status']}"];
    }

    return ['success' => true, 'status' => $result['status'], 'body' => $body, 'error' => null];
}

/**
 * Low-Level-Request gegen die paperless-ngx-API.
 * $data: bei GET als Query-Parameter, sonst als JSON-Body.
 */
function paperlessRequest($method, $path, $data = null) {
    if (!paperlessConfigured()) {
        return ['success' => false, 'status' => 0, 'body' => null, 'error' => 'paperless-Integration nicht konfiguriert.'];
    }

    $url = rtrim(PAPERLESS_BASE_URL, '/') . $path;
    if ($method === 'GET' && !empty($data)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
    }

    $headers = ['Authorization: Token ' . PAPERLESS_API_TOKEN];
    $body = null;
    if ($method !== 'GET' && $data !== null) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($data);
    }

    $result = paperlessHttpTransport($method, $url, $headers, $body);
    return paperlessFormatResult($result);
}

/**
 * POST eines multipart/form-data-Requests (nur für den Dokument-Upload benötigt).
 * Baut den Multipart-Body selbst (kein CURLFile) - funktioniert dadurch identisch
 * über cURL und den Stream-Fallback. Ein Feldwert darf ein Array sein (z.B. mehrere
 * Tags) - wird dann als mehrere form-data-Teile mit demselben Feldnamen gesendet
 * (Standard-HTML-Verhalten für Mehrfachwerte, das DRF vom `tags`-Feld erwartet).
 */
function paperlessMultipartRequest($path, $fields, $fileField, $filePath, $fileName, $fileMime) {
    if (!paperlessConfigured()) {
        return ['success' => false, 'status' => 0, 'body' => null, 'error' => 'paperless-Integration nicht konfiguriert.'];
    }
    if (!is_file($filePath)) {
        return ['success' => false, 'status' => 0, 'body' => null, 'error' => 'Datei nicht gefunden: ' . $filePath];
    }

    $boundary = '----EKassa360' . bin2hex(random_bytes(16));
    $body = '';
    foreach ($fields as $name => $value) {
        foreach (is_array($value) ? $value : [$value] as $einzelwert) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$einzelwert\r\n";
        }
    }
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$fileField\"; filename=\"" . basename($fileName) . "\"\r\n";
    $body .= "Content-Type: $fileMime\r\n\r\n" . file_get_contents($filePath) . "\r\n";
    $body .= "--$boundary--\r\n";

    $url = rtrim(PAPERLESS_BASE_URL, '/') . $path;
    $headers = [
        'Authorization: Token ' . PAPERLESS_API_TOKEN,
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ];

    $result = paperlessHttpTransport('POST', $url, $headers, $body);
    return paperlessFormatResult($result);
}

/**
 * Correspondent per Name holen oder anlegen (Cache in kunden.paperless_correspondent_id
 * übernimmt der Aufrufer). Gibt die ID zurück oder null bei Fehler.
 */
function getOrCreateCorrespondent($name) {
    if (empty($name)) return null;

    $result = paperlessRequest('GET', '/api/correspondents/', ['name__iexact' => $name]);
    if ($result['success'] && !empty($result['body']['results'])) {
        return $result['body']['results'][0]['id'];
    }

    $result = paperlessRequest('POST', '/api/correspondents/', ['name' => $name]);
    if ($result['success'] && !empty($result['body']['id'])) {
        return $result['body']['id'];
    }

    return null;
}

/**
 * Document-Type per Name holen oder anlegen. Gibt die ID zurück oder null bei Fehler.
 */
function getOrCreateDocumentType($name) {
    if (empty($name)) return null;

    $result = paperlessRequest('GET', '/api/document_types/', ['name__iexact' => $name]);
    if ($result['success'] && !empty($result['body']['results'])) {
        return $result['body']['results'][0]['id'];
    }

    $result = paperlessRequest('POST', '/api/document_types/', ['name' => $name]);
    if ($result['success'] && !empty($result['body']['id'])) {
        return $result['body']['id'];
    }

    return null;
}

/**
 * Tag per Name holen oder anlegen. Gibt die ID zurück oder null bei Fehler.
 */
function getOrCreateTag($name) {
    if (empty($name)) return null;

    $result = paperlessRequest('GET', '/api/tags/', ['name__iexact' => $name]);
    if ($result['success'] && !empty($result['body']['results'])) {
        return $result['body']['results'][0]['id'];
    }

    $result = paperlessRequest('POST', '/api/tags/', ['name' => $name]);
    if ($result['success'] && !empty($result['body']['id'])) {
        return $result['body']['id'];
    }

    return null;
}

/**
 * Mehrere Tag-Namen zu IDs auflösen (get-or-create je Name), leere/doppelte Namen ignoriert.
 */
function getOrCreateTags($namen) {
    $ids = [];
    foreach (array_unique(array_filter($namen)) as $name) {
        $id = getOrCreateTag($name);
        if ($id) $ids[] = $id;
    }
    return $ids;
}

/**
 * PDF asynchron nach paperless-ngx hochladen und auf das fertige Dokument warten.
 * $meta: ['title' => ..., 'correspondent_id' => ..., 'document_type_id' => ..., 'tag_ids' => [...]]
 */
function uploadDocumentToPaperless($pdfPath, $meta = []) {
    if (!paperlessConfigured()) {
        return ['success' => false, 'document_id' => null, 'error' => 'paperless-Integration nicht konfiguriert.'];
    }
    if (!is_file($pdfPath)) {
        return ['success' => false, 'document_id' => null, 'error' => 'PDF-Datei nicht gefunden: ' . $pdfPath];
    }

    $fields = ['title' => $meta['title'] ?? basename($pdfPath)];
    if (!empty($meta['correspondent_id'])) $fields['correspondent'] = $meta['correspondent_id'];
    if (!empty($meta['document_type_id'])) $fields['document_type'] = $meta['document_type_id'];
    if (!empty($meta['tag_ids'])) $fields['tags'] = $meta['tag_ids'];

    $result = paperlessMultipartRequest('/api/documents/post_document/', $fields, 'document', $pdfPath, basename($pdfPath), 'application/pdf');
    if (!$result['success']) {
        return ['success' => false, 'document_id' => null, 'error' => $result['error']];
    }

    $taskId = is_string($result['body']) ? trim($result['body'], "\" \n") : $result['body'];
    if (empty($taskId)) {
        return ['success' => false, 'document_id' => null, 'error' => 'Keine Task-ID von paperless erhalten.'];
    }

    return pollPaperlessTask($taskId);
}

/**
 * Task-Status abfragen bis SUCCESS/FAILURE oder Timeout (Default max. ~10s).
 */
function pollPaperlessTask($taskId, $maxAttempts = 10, $delaySeconds = 1) {
    for ($i = 0; $i < $maxAttempts; $i++) {
        $result = paperlessRequest('GET', '/api/tasks/', ['task_id' => $taskId]);
        if ($result['success'] && !empty($result['body'][0])) {
            $task = $result['body'][0];
            $status = $task['status'] ?? '';
            if ($status === 'SUCCESS') {
                return ['success' => true, 'document_id' => $task['related_document'] ?? null, 'error' => null];
            }
            if ($status === 'FAILURE') {
                return ['success' => false, 'document_id' => null, 'error' => is_string($task['result'] ?? null) ? $task['result'] : 'paperless-Task fehlgeschlagen.'];
            }
        }
        sleep($delaySeconds);
    }
    return ['success' => false, 'document_id' => null, 'error' => 'Zeitüberschreitung beim Warten auf paperless-Verarbeitung.'];
}

/**
 * Volltextsuche in paperless-ngx. Gibt nur unkritische Felder zurück (kein Token-Leak).
 */
function searchPaperlessDocuments($query) {
    $result = paperlessRequest('GET', '/api/documents/', ['query' => $query, 'page_size' => 20]);
    if (!$result['success'] || empty($result['body']['results'])) {
        return [];
    }

    // Die Dokumente liefern `correspondent` nur als ID (keine `correspondent_name`-Property in
    // dieser paperless-ngx-API-Version) - Namen einmalig auflösen statt pro Dokument nachzufragen.
    $correspondentNamen = getPaperlessCorrespondentNamen();

    $out = [];
    foreach ($result['body']['results'] as $doc) {
        $correspondentId = $doc['correspondent'] ?? null;
        $out[] = [
            'id' => $doc['id'],
            'title' => $doc['title'] ?? ('Dokument #' . $doc['id']),
            'created' => $doc['created'] ?? null,
            'correspondent' => $correspondentId ? ($correspondentNamen[$correspondentId] ?? null) : null
        ];
    }
    return $out;
}

/**
 * ID -> Name-Map aller Correspondents (für die Anzeige in der Beleg-Suche).
 */
function getPaperlessCorrespondentNamen() {
    $result = paperlessRequest('GET', '/api/correspondents/', ['page_size' => 1000]);
    if (!$result['success'] || empty($result['body']['results'])) {
        return [];
    }
    $namen = [];
    foreach ($result['body']['results'] as $c) {
        $namen[$c['id']] = $c['name'];
    }
    return $namen;
}

/**
 * Streamt ein paperless-Dokument serverseitig durch - der API-Token erreicht nie
 * den Browser. Gibt bei Fehler false zurück, sonst wird direkt ausgegeben.
 */
function streamPaperlessDocument($documentId) {
    if (!paperlessConfigured()) {
        return false;
    }

    $url = rtrim(PAPERLESS_BASE_URL, '/') . '/api/documents/' . intval($documentId) . '/download/';
    $headers = ['Authorization: Token ' . PAPERLESS_API_TOKEN];

    $result = paperlessHttpTransport('GET', $url, $headers, null, 30);

    if ($result['body'] === false || $result['status'] < 200 || $result['status'] >= 300) {
        return false;
    }

    header('Content-Type: ' . ($result['content_type'] ?: 'application/octet-stream'));
    header('Content-Length: ' . strlen($result['body']));
    header('Content-Disposition: inline; filename="beleg_' . intval($documentId) . '.pdf"');
    echo $result['body'];
    return true;
}

/**
 * Ausgehende Archivierung: rendert das Verkaufsdokument-PDF, speichert es lokal
 * unter storage/verkauf_pdfs/, lädt es nach paperless-ngx hoch und schreibt
 * pdf_pfad/paperless_document_id zurück. Ein Fehlschlag wird zurückgegeben,
 * blockiert aber nichts - der Aufrufer (Finalisieren-Button, Cron) entscheidet
 * selbst, dass das Dokument trotzdem als finalisiert gilt (Badge "nicht archiviert").
 *
 * Benötigt includes/verkauf_functions.php und includes/verkauf_pdf.php (require_once
 * vor dem Aufruf durch die aufrufende Seite).
 */
function archiviereVerkaufsdokumentInPaperless($verkaufsdokumentId) {
    if (!paperlessConfigured()) {
        return ['success' => false, 'error' => 'paperless-Integration nicht konfiguriert.'];
    }

    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc) {
        return ['success' => false, 'error' => 'Verkaufsdokument nicht gefunden.'];
    }

    try {
        $pdfBytes = buildVerkaufsdokumentPdf($verkaufsdokumentId);
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'PDF-Erstellung fehlgeschlagen: ' . $e->getMessage()];
    }

    $verzeichnis = __DIR__ . '/../storage/verkauf_pdfs';
    if (!is_dir($verzeichnis)) {
        mkdir($verzeichnis, 0775, true);
    }
    $dateiname = verkaufsdokumentDateiname($doc);
    $lokalerPfad = $verzeichnis . '/' . $dateiname;
    file_put_contents($lokalerPfad, $pdfBytes);

    $kundeName = kundenAnzeigename($doc);
    $correspondentId = null;
    if ($kundeName) {
        $correspondentId = getOrCreateCorrespondent($kundeName);
        if ($correspondentId && !empty($doc['kunde_id'])) {
            $db = db();
            $db->prepare("UPDATE kunden SET paperless_correspondent_id = ? WHERE id = ? AND (paperless_correspondent_id IS NULL OR paperless_correspondent_id != ?)")
               ->execute([$correspondentId, $doc['kunde_id'], $correspondentId]);
        }
    }

    $typLabels = ['angebot' => 'Angebot', 'auftrag' => 'Auftrag', 'rechnung' => 'Rechnung'];
    $typLabel = $typLabels[$doc['typ']] ?? ucfirst($doc['typ']);
    $docTypeId = getOrCreateDocumentType($typLabel);
    $titel = $typLabel . ' ' . ($doc['nummer'] ?: ('Entwurf #' . $doc['id']));

    $tagNamen = array_merge([$typLabel], defined('PAPERLESS_EXTRA_TAGS') ? PAPERLESS_EXTRA_TAGS : []);
    $tagIds = getOrCreateTags($tagNamen);

    $result = uploadDocumentToPaperless($lokalerPfad, [
        'title' => $titel,
        'correspondent_id' => $correspondentId,
        'document_type_id' => $docTypeId,
        'tag_ids' => $tagIds
    ]);

    $relativerPfad = 'storage/verkauf_pdfs/' . $dateiname;
    $db = db();
    if ($result['success']) {
        $db->prepare("UPDATE verkaufsdokumente SET pdf_pfad = ?, paperless_document_id = ? WHERE id = ?")
           ->execute([$relativerPfad, $result['document_id'], $verkaufsdokumentId]);
    } else {
        $db->prepare("UPDATE verkaufsdokumente SET pdf_pfad = ? WHERE id = ?")
           ->execute([$relativerPfad, $verkaufsdokumentId]);
    }

    return $result;
}
