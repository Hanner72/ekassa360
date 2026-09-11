<?php
/**
 * E-Mail-Versand für Angebot/Auftrag/Rechnung (manueller Button + automatisch bei
 * wiederkehrenden Rechnungen). Nutzt PHPMailer/SMTP.
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

/**
 * Betreff + Text für die Versand-Mail eines Verkaufsdokuments.
 */
function baueVerkaufsdokumentEmailInhalt($doc, $firma) {
    $typLabels = ['angebot' => 'Angebot', 'auftrag' => 'Auftragsbestätigung', 'rechnung' => 'Rechnung'];
    $typLabel = $typLabels[$doc['typ']] ?? ucfirst($doc['typ']);
    $nummer = $doc['nummer'] ?: '(Entwurf)';
    $firmaName = $firma['name'] ?? '';
    $kundeName = kundenAnzeigename($doc);
    $anrede = $kundeName ? "Sehr geehrte Damen und Herren,\n\n" : "Sehr geehrte Damen und Herren,\n\n";

    $betreff = "$typLabel $nummer" . ($firmaName ? " - $firmaName" : '');

    $einleitung = [
        'angebot' => "anbei erhalten Sie unser Angebot $nummer vom " . formatDatum($doc['datum']) . '.',
        'auftrag' => "anbei erhalten Sie die Auftragsbestätigung $nummer vom " . formatDatum($doc['datum']) . '.',
        'rechnung' => "anbei erhalten Sie die Rechnung $nummer vom " . formatDatum($doc['datum']) . '.',
    ];

    $text = $anrede
        . ($einleitung[$doc['typ']] ?? "anbei erhalten Sie das Dokument $nummer.") . "\n\n"
        . "Mit freundlichen Grüßen\n"
        . $firmaName;

    return ['betreff' => $betreff, 'text' => $text];
}

/**
 * PDF eines Verkaufsdokuments per E-Mail versenden. $empfaengerEmail überschreibt die
 * hinterlegte Kunden-E-Mail; $zusatztext wird der Standard-Nachricht vorangestellt.
 * Nur für bereits finalisierte Dokumente (status != 'entwurf') sinnvoll.
 */
function sendeVerkaufsdokumentEmail($verkaufsdokumentId, $empfaengerEmail = null, $zusatztext = null) {
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

    try {
        $pdfBytes = buildVerkaufsdokumentPdf($verkaufsdokumentId);
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'PDF-Erstellung fehlgeschlagen: ' . $e->getMessage()];
    }

    $firma = getFirmendaten();
    $inhalt = baueVerkaufsdokumentEmailInhalt($doc, $firma);
    $text = $zusatztext ? trim($zusatztext) . "\n\n" . $inhalt['text'] : $inhalt['text'];
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
        $mail->Subject = $inhalt['betreff'];
        $mail->Body = $text;
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
