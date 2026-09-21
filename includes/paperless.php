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

/**
 * paperless-Konfiguration (aktiv-Schalter, Basis-URL, API-Token, SSL-Prüfung) - liegt in der
 * Datenbank (Einstellungen -> Wartung), nicht mehr in config/paperless.php. Innerhalb eines
 * Requests gecacht, da praktisch jede paperless-Funktion hier durchläuft.
 *
 * Einmaliger Auto-Import: existiert noch eine alte config/paperless.php (lokal, nicht in Git)
 * UND ist in der Datenbank noch keine base_url hinterlegt, wird deren Inhalt einmalig in die
 * Datenbank übernommen - damit eine bereits laufende Installation nach diesem Update nichts
 * manuell neu eintragen muss. Danach ist ausschließlich die Datenbank (und damit die
 * Einstellungen-Seite) maßgeblich; die alte Datei wird nicht mehr gelesen.
 */
function getPaperlessEinstellungen() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $db = db();
    $row = $db->query("SELECT * FROM paperless_einstellungen WHERE id = 1")->fetch();
    if (!$row) {
        $row = ['id' => 1, 'aktiv' => 0, 'base_url' => null, 'api_token' => null, 'verify_ssl' => 1];
    }

    if (empty($row['base_url']) && file_exists(__DIR__ . '/../config/paperless.php')) {
        require_once __DIR__ . '/../config/paperless.php';
        if (defined('PAPERLESS_BASE_URL') && PAPERLESS_BASE_URL) {
            savePaperlessEinstellungen(
                defined('PAPERLESS_ENABLED') ? (bool)PAPERLESS_ENABLED : true,
                PAPERLESS_BASE_URL,
                defined('PAPERLESS_API_TOKEN') ? PAPERLESS_API_TOKEN : '',
                defined('PAPERLESS_VERIFY_SSL') ? (bool)PAPERLESS_VERIFY_SSL : true
            );
            $row = $db->query("SELECT * FROM paperless_einstellungen WHERE id = 1")->fetch();
        }
    }

    $cache = $row;
    return $cache;
}

function savePaperlessEinstellungen($aktiv, $baseUrl, $apiToken, $verifySsl) {
    $db = db();
    $stmt = $db->prepare("UPDATE paperless_einstellungen SET aktiv = ?, base_url = ?, api_token = ?, verify_ssl = ? WHERE id = 1");
    $stmt->execute([$aktiv ? 1 : 0, trim((string)$baseUrl) ?: null, trim((string)$apiToken) ?: null, $verifySsl ? 1 : 0]);
}

function paperlessConfigured() {
    // Mindestens ein Transportweg muss nutzbar sein, sonst würde jeder Aufruf mit
    // einem nicht abfangbaren PHP-Error abstürzen statt sauber "nicht verfügbar" zu melden.
    $transportVerfuegbar = function_exists('curl_init') || ini_get('allow_url_fopen');
    $einstellungen = getPaperlessEinstellungen();

    return !empty($einstellungen['aktiv'])
        && !empty($einstellungen['base_url'])
        && !empty($einstellungen['api_token'])
        && $transportVerfuegbar;
}

/**
 * Verbindungstest für die Einstellungen-Seite - nutzt bewusst die im Formular eingegebenen
 * (noch ungespeicherten) Werte statt der gespeicherten Einstellungen, damit vor dem Speichern
 * geprüft werden kann. Ruft einen möglichst günstigen Endpunkt ab (1 Dokument, keine Details).
 */
function testePaperlessVerbindung($baseUrl, $apiToken, $verifySsl) {
    $baseUrl = trim((string)$baseUrl);
    $apiToken = trim((string)$apiToken);
    if ($baseUrl === '' || $apiToken === '') {
        return ['success' => false, 'message' => 'Bitte Basis-URL und API-Token angeben.'];
    }

    $url = rtrim($baseUrl, '/') . '/api/documents/?page_size=1';
    $headers = ['Authorization: Token ' . $apiToken];
    $result = paperlessHttpTransport('GET', $url, $headers, null, 10, (bool)$verifySsl);
    $formatiert = paperlessFormatResult($result);

    if ($formatiert['success']) {
        return ['success' => true, 'message' => 'Verbindung erfolgreich.'];
    }
    return ['success' => false, 'message' => 'Verbindung fehlgeschlagen: ' . ($formatiert['error'] ?: 'unbekannter Fehler')];
}

/**
 * Low-Level-HTTP-Transport: cURL falls verfügbar, sonst PHP-Streams.
 * Gibt einheitlich ['status' => int, 'body' => string|false, 'content_type' => string|null, 'error' => string|null] zurück.
 * $verifySsl: null (Standard) liest die gespeicherten Einstellungen, ein expliziter bool-Wert
 * überschreibt das - gedacht für pruefePaperlessVerbindung() mit noch ungespeicherten Werten.
 */
function paperlessHttpTransport($method, $url, array $headers, $body = null, $timeoutSeconds = 20, $verifySsl = null) {
    if ($verifySsl === null) {
        $verifySsl = !empty(getPaperlessEinstellungen()['verify_ssl']);
    }
    if (function_exists('curl_init')) {
        return paperlessHttpViaCurl($method, $url, $headers, $body, $timeoutSeconds, $verifySsl);
    }
    return paperlessHttpViaStream($method, $url, $headers, $body, $timeoutSeconds, $verifySsl);
}

function paperlessHttpViaCurl($method, $url, array $headers, $body, $timeoutSeconds, $verifySsl) {
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

function paperlessHttpViaStream($method, $url, array $headers, $body, $timeoutSeconds, $verifySsl) {
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

    $einstellungen = getPaperlessEinstellungen();
    $url = rtrim($einstellungen['base_url'], '/') . $path;
    if ($method === 'GET' && !empty($data)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
    }

    $headers = ['Authorization: Token ' . $einstellungen['api_token']];
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

    $einstellungen = getPaperlessEinstellungen();
    $url = rtrim($einstellungen['base_url'], '/') . $path;
    $headers = [
        'Authorization: Token ' . $einstellungen['api_token'],
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

// ============================================
// PAPERLESS-TAGS PRO DOKUMENTTYP (Angebot/Auftrag/Rechnung) - frei einstellbar in den
// Einstellungen (Tab "Wartung"), ersetzt die frühere feste PAPERLESS_EXTRA_TAGS-Konstante.
// ============================================

function getAllePaperlessTagEinstellungen() {
    $db = db();
    return $db->query("SELECT * FROM paperless_tag_einstellungen")->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
}

/** Komma-getrennte Tag-Namen für einen Dokumenttyp als Array (getrimmt, Leerstrings entfernt). */
function getPaperlessTagsFuerTyp($typ) {
    $db = db();
    $stmt = $db->prepare("SELECT tags FROM paperless_tag_einstellungen WHERE typ = ?");
    $stmt->execute([$typ]);
    $roh = $stmt->fetchColumn();
    if (!$roh) return [];
    return array_values(array_filter(array_map('trim', explode(',', $roh)), fn($t) => $t !== ''));
}

function savePaperlessTagsFuerTyp($typ, $tagsKommagetrennt) {
    $db = db();
    // Auf ein sauberes "a, b, c"-Format normalisieren statt die Roheingabe zu übernehmen.
    $normalisiert = implode(', ', array_values(array_filter(array_map('trim', explode(',', $tagsKommagetrennt)), fn($t) => $t !== '')));
    $stmt = $db->prepare("INSERT INTO paperless_tag_einstellungen (typ, tags) VALUES (?, ?) ON DUPLICATE KEY UPDATE tags = VALUES(tags)");
    $stmt->execute([$typ, $normalisiert ?: null]);
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

    $titel = $meta['title'] ?? basename($pdfPath);
    $fields = ['title' => $titel];
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

    $poll = pollPaperlessTask($taskId);
    if ($poll['success']) {
        return $poll;
    }

    // Timeout/unklarer Task-Status: paperless verarbeitet Uploads asynchron (OCR, Texterkennung
    // etc.) und kann dafür länger brauchen, als wir synchron im Request warten wollen - der
    // Upload selbst ist zu diesem Zeitpunkt aber bereits abgeschlossen (sonst gäbe es gar keine
    // Task-ID). Vor einer Fehlermeldung einmalig direkt nach dem fertigen Dokument suchen -
    // ohne diesen Fallback würde ein "Erneut senden" das PDF ein zweites Mal hochladen, da es
    // keinen Duplikat-Schutz gibt.
    $gefundenId = findePaperlessDokumentPerTitel($titel);
    if ($gefundenId) {
        return ['success' => true, 'document_id' => $gefundenId, 'error' => null];
    }

    return $poll;
}

/**
 * Sucht ein Dokument per exaktem Titel (Fallback nach einem Task-Timeout in
 * uploadDocumentToPaperless() - der Titel enthält die Beleg-/Dokumentnummer und ist damit
 * praktisch eindeutig). Bei mehreren Treffern das zuletzt erstellte.
 */
function findePaperlessDokumentPerTitel($titel) {
    $result = paperlessRequest('GET', '/api/documents/', ['title__iexact' => $titel, 'ordering' => '-created']);
    if ($result['success'] && !empty($result['body']['results'][0]['id'])) {
        return $result['body']['results'][0]['id'];
    }
    return null;
}

/**
 * Task-Status abfragen bis success/failure oder Timeout (Default max. ~25s - paperless-ngx
 * verarbeitet Uploads asynchron, u.a. mit OCR, das je nach Serverlast/Dokumentgröße länger als
 * ursprünglich angenommen dauern kann; siehe zusätzlich den Titel-Fallback in
 * uploadDocumentToPaperless() für den Fall, dass selbst das nicht reicht).
 *
 * Zwei API-Formate müssen abgefangen werden (unterscheidet sich je paperless-ngx-Version):
 * - /api/tasks/ liefert manche Versionen als nackte Liste, neuere als paginierte Hülle
 *   ({count, next, previous, results: [...]}) - beide werden unterstützt.
 * - status kommt klein geschrieben ("success"/"failure"), nicht wie ursprünglich angenommen
 *   groß ("SUCCESS"/"FAILURE") - Vergleich deshalb per strtolower().
 * - die Dokument-ID der neu erzeugten Datei steht unter result_data.document_id (mit
 *   related_document_ids[0] und dem älteren related_document als Fallback für andere Versionen).
 */
function pollPaperlessTask($taskId, $maxAttempts = 25, $delaySeconds = 1) {
    for ($i = 0; $i < $maxAttempts; $i++) {
        $result = paperlessRequest('GET', '/api/tasks/', ['task_id' => $taskId]);
        $tasks = $result['body']['results'] ?? ($result['body'] ?? []);
        if ($result['success'] && !empty($tasks[0])) {
            $task = $tasks[0];
            $status = strtolower($task['status'] ?? '');
            if ($status === 'success') {
                $documentId = $task['result_data']['document_id']
                    ?? ($task['related_document_ids'][0] ?? null)
                    ?? ($task['related_document'] ?? null);
                return ['success' => true, 'document_id' => $documentId, 'error' => null];
            }
            if ($status === 'failure') {
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

    $einstellungen = getPaperlessEinstellungen();
    $url = rtrim($einstellungen['base_url'], '/') . '/api/documents/' . intval($documentId) . '/download/';
    $headers = ['Authorization: Token ' . $einstellungen['api_token']];

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

    // Tags pro Dokumenttyp frei einstellbar (Einstellungen -> Wartung), siehe
    // getPaperlessTagsFuerTyp() weiter oben. Ohne eigene Einstellung (z.B. frisch migriert)
    // fällt es auf den reinen Typ-Namen zurück.
    $tagNamen = getPaperlessTagsFuerTyp($doc['typ']) ?: [$typLabel];
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
