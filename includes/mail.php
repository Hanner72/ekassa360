<?php
/**
 * E-Mail-Versand für Angebot/Auftrag/Rechnung (manueller Button + automatisch bei
 * wiederkehrenden Rechnungen). Nutzt PHPMailer/SMTP.
 *
 * Betreff/Text sind pro Dokumenttyp in `email_vorlagen` frei editierbar (Einstellungen ->
 * E-Mail-Vorlagen), Signaturen liegen unabhängig davon in `email_signaturen` und werden
 * einer Vorlage nur als Vorauswahl (`standard_signatur_id`) zugeordnet. Einfache
 * {{platzhalter}}-Ersetzung wie beim PDF-Vorlagen-Editor (includes/verkauf_pdf.php),
 * aber auf Klartext statt HTML.
 *
 * Wie bei paperless.php: Fehler werden als strukturierte Ergebnis-Arrays zurückgegeben,
 * nie als Exception geworfen - ein Versandfehler darf das Finalisieren/die automatische
 * Rechnungserstellung nicht blockieren.
 *
 * Achtung Begriffskollision: verkaufsdokumente.status kennt bereits den Wert 'versendet'
 * (= finalisiert/nummeriert, siehe finalizeAngebotOderAuftrag()) - das hat NICHTS mit
 * einem tatsächlichen E-Mail-Versand zu tun. Für den E-Mail-Status werden bewusst eigene
 * Spalten (versendet_am/versendet_an) und eigener UI-Text ("Per E-Mail gesendet") verwendet,
 * um beide Konzepte klar zu trennen.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (file_exists(__DIR__ . '/../config/mail.php')) {
    require_once __DIR__ . '/../config/mail.php';
}

function mailConfigured() {
    return defined('MAIL_ENABLED') && MAIL_ENABLED
        && defined('MAIL_SMTP_HOST') && MAIL_SMTP_HOST
        && defined('MAIL_FROM_ADDRESS') && MAIL_FROM_ADDRESS;
}

// ============================================
// E-MAIL-VORLAGEN (Betreff/Text pro Dokumenttyp)
// ============================================

function standardEmailVorlage($typ) {
    $typLabels = ['angebot' => 'Angebot', 'auftrag' => 'Auftragsbestätigung', 'rechnung' => 'Rechnung'];
    $einleitung = [
        'angebot' => 'anbei erhalten Sie unser Angebot {{nummer}} vom {{datum}}.',
        'auftrag' => 'anbei erhalten Sie die Auftragsbestätigung {{nummer}} vom {{datum}}.',
        'rechnung' => 'anbei erhalten Sie die Rechnung {{nummer}} vom {{datum}}.',
    ];
    return [
        'typ' => $typ,
        'betreff' => '{{typ_label}} {{nummer}} - {{firma_name}}',
        'nachricht' => "Sehr geehrte Damen und Herren,\n\n" . ($einleitung[$typ] ?? 'anbei erhalten Sie das Dokument {{nummer}}.'),
        'standard_signatur_id' => null,
    ];
}

function getEmailVorlage($typ) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM email_vorlagen WHERE typ = ?");
    $stmt->execute([$typ]);
    $vorlage = $stmt->fetch();
    return $vorlage ?: standardEmailVorlage($typ);
}

function saveEmailVorlage($typ, $betreff, $nachricht, $standardSignaturId) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO email_vorlagen (typ, betreff, nachricht, standard_signatur_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE betreff = VALUES(betreff), nachricht = VALUES(nachricht), standard_signatur_id = VALUES(standard_signatur_id)");
    return $stmt->execute([$typ, $betreff, $nachricht, $standardSignaturId ?: null]);
}

// ============================================
// SIGNATUREN
// ============================================

function getAlleEmailSignaturen() {
    $db = db();
    return $db->query("SELECT * FROM email_signaturen ORDER BY ist_standard DESC, name")->fetchAll();
}

function getEmailSignatur($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM email_signaturen WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getStandardEmailSignatur() {
    $db = db();
    return $db->query("SELECT * FROM email_signaturen WHERE ist_standard = 1 LIMIT 1")->fetch();
}

function saveEmailSignatur($data) {
    $db = db();
    $name = trim($data['name'] ?? '');
    $inhalt = $data['inhalt'] ?? '';
    $istStandard = !empty($data['ist_standard']) ? 1 : 0;

    if ($istStandard) {
        $db->exec("UPDATE email_signaturen SET ist_standard = 0");
    }

    if (!empty($data['id'])) {
        $stmt = $db->prepare("UPDATE email_signaturen SET name = ?, inhalt = ?, ist_standard = ? WHERE id = ?");
        return $stmt->execute([$name, $inhalt, $istStandard, $data['id']]);
    }

    $stmt = $db->prepare("INSERT INTO email_signaturen (name, inhalt, ist_standard) VALUES (?, ?, ?)");
    $stmt->execute([$name, $inhalt, $istStandard]);
    return $db->lastInsertId();
}

function deleteEmailSignatur($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM email_signaturen WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// RENDERING (Platzhalter-Ersetzung)
// ============================================

/**
 * Platzhalter für Betreff/Text/Signatur einer Versand-Mail. Bewusst ein kleineres Set als
 * beim PDF-Vorlagen-Editor (baueDokumentPlatzhalter()) - hier geht es nur um die Begleit-Mail,
 * nicht um das Dokument selbst.
 */
function baueEmailPlatzhalter($doc, $firma) {
    $typLabels = ['angebot' => 'Angebot', 'auftrag' => 'Auftragsbestätigung', 'rechnung' => 'Rechnung'];
    return [
        '{{typ_label}}' => $typLabels[$doc['typ']] ?? ucfirst($doc['typ']),
        '{{nummer}}' => $doc['nummer'] ?: '(Entwurf)',
        '{{datum}}' => formatDatum($doc['datum']),
        '{{faellig_am}}' => formatDatum($doc['faellig_am'] ?? null),
        '{{gueltig_bis}}' => formatDatum($doc['gueltig_bis'] ?? null),
        '{{betreff}}' => $doc['betreff'] ?? '',
        '{{firma_name}}' => $firma['name'] ?? '',
        '{{kunde_name}}' => kundenAnzeigename($doc),
    ];
}

/**
 * Vorlage + Standard-Signatur für ein Dokument fertig gerendert (Platzhalter ersetzt).
 * Liefert Betreff/Text getrennt von der Signatur - der Aufrufer entscheidet, ob/wie er
 * beides zusammenführt (siehe sendeVerkaufsdokumentEmail()).
 */
function renderEmailVorlage($verkaufsdokumentId) {
    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc) {
        return null;
    }
    $firma = getFirmendaten();
    $vorlage = getEmailVorlage($doc['typ']);
    $platzhalter = baueEmailPlatzhalter($doc, $firma);

    $signaturId = $vorlage['standard_signatur_id'] ?? null;
    $signatur = $signaturId ? getEmailSignatur($signaturId) : getStandardEmailSignatur();
    $signaturText = $signatur ? strtr($signatur['inhalt'], $platzhalter) : '';

    return [
        'betreff' => strtr($vorlage['betreff'], $platzhalter),
        'text' => strtr($vorlage['nachricht'], $platzhalter),
        'signatur' => $signaturText,
        'signatur_id' => $signatur['id'] ?? null,
    ];
}

/**
 * Fertige, HTML-attribut-sichere Argumentliste für den onclick="oeffneVersandModal(...)"-Aufruf
 * in angebote.php/auftraege.php/verkaufsrechnungen.php: id, E-Mail, gerenderter Betreff/Text/
 * Signatur-Id/Signatur-Text - jedes Argument einzeln JSON-kodiert und für ein doppelt-quotetes
 * HTML-Attribut escaped (htmlspecialchars), da die Werte Anführungszeichen/Zeilenumbrüche
 * enthalten können.
 */
function versandModalOnclickArgs($doc) {
    $render = renderEmailVorlage($doc['id']) ?: ['betreff' => '', 'text' => '', 'signatur' => '', 'signatur_id' => null];
    $werte = [
        $doc['id'],
        $doc['email'] ?? '',
        $render['betreff'],
        $render['text'],
        $render['signatur_id'],
        $render['signatur'],
    ];
    return implode(', ', array_map(function ($wert) {
        return htmlspecialchars(json_encode($wert), ENT_QUOTES);
    }, $werte));
}

/**
 * PDF eines Verkaufsdokuments per E-Mail versenden. $empfaengerEmail überschreibt die
 * hinterlegte Kunden-E-Mail. $betreff/$text/$signatur überschreiben (falls gegeben) die
 * automatisch aus der Vorlage gerenderten Werte - genutzt vom Versand-Modal, wo der Nutzer
 * Betreff/Text/Signatur vor dem Senden noch anpassen kann. Ohne Overrides (z.B. beim
 * automatischen Versand wiederkehrender Rechnungen) wird die hinterlegte Vorlage 1:1 verwendet.
 * Nur für bereits finalisierte Dokumente (status != 'entwurf') sinnvoll.
 */
function sendeVerkaufsdokumentEmail($verkaufsdokumentId, $empfaengerEmail = null, $betreff = null, $text = null, $signatur = null) {
    if (!mailConfigured()) {
        return ['success' => false, 'error' => 'E-Mail-Versand nicht konfiguriert (config/mail.php).'];
    }

    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc) {
        return ['success' => false, 'error' => 'Verkaufsdokument nicht gefunden.'];
    }
    if ($doc['status'] === 'entwurf') {
        return ['success' => false, 'error' => 'Dokument muss zuerst finalisiert werden, bevor es versendet werden kann.'];
    }

    $empfaenger = trim($empfaengerEmail ?: ($doc['email'] ?? ''));
    if (!$empfaenger || !filter_var($empfaenger, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Keine gültige Empfänger-E-Mail-Adresse vorhanden.'];
    }

    if ($betreff === null || $text === null) {
        $gerendert = renderEmailVorlage($verkaufsdokumentId);
        $betreff = $betreff ?? $gerendert['betreff'];
        $text = $text ?? $gerendert['text'];
        $signatur = $signatur ?? $gerendert['signatur'];
    }

    try {
        $pdfBytes = buildVerkaufsdokumentPdf($verkaufsdokumentId);
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'PDF-Erstellung fehlgeschlagen: ' . $e->getMessage()];
    }

    $firma = getFirmendaten();
    $body = trim($text) . (trim((string)$signatur) !== '' ? "\n\n" . trim($signatur) : '');
    $dateiname = verkaufsdokumentDateiname($doc);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = MAIL_SMTP_HOST;
        $mail->Port = defined('MAIL_SMTP_PORT') ? MAIL_SMTP_PORT : 587;
        $mail->SMTPAuth = !empty(MAIL_SMTP_USERNAME);
        if ($mail->SMTPAuth) {
            $mail->Username = MAIL_SMTP_USERNAME;
            $mail->Password = MAIL_SMTP_PASSWORD;
        }
        $verschluesselung = defined('MAIL_SMTP_ENCRYPTION') ? MAIL_SMTP_ENCRYPTION : 'tls';
        if ($verschluesselung === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($verschluesselung === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM_ADDRESS, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : ($firma['name'] ?? ''));
        $mail->addAddress($empfaenger);
        $mail->Subject = $betreff;
        $mail->Body = $body;
        $mail->addStringAttachment($pdfBytes, $dateiname, 'base64', 'application/pdf');

        $mail->send();
    } catch (PHPMailerException $e) {
        return ['success' => false, 'error' => 'Versand fehlgeschlagen: ' . $mail->ErrorInfo];
    }

    $db = db();
    $db->prepare("UPDATE verkaufsdokumente SET versendet_am = NOW(), versendet_an = ? WHERE id = ?")
       ->execute([$empfaenger, $verkaufsdokumentId]);

    if (function_exists('logAction')) {
        logAction('verkaufsdokumente', $verkaufsdokumentId, 'geaendert', "Per E-Mail versendet an $empfaenger");
    }

    return ['success' => true, 'error' => null, 'empfaenger' => $empfaenger];
}
