<?php
/**
 * EKassa360 - Einstellungen
 * Firma, USt-Sätze, Kategorien und Kennzahlen
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';
require_once 'includes/mail.php';
require_once 'includes/wiederkehrend_functions.php';
require_once 'includes/bondrucker.php';
require_once 'includes/paperless.php';

requireLogin();

$tab = $_GET['tab'] ?? 'firma';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$db = db();

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Firmendaten speichern
    if (isset($_POST['save_firma'])) {
        $stmt = $db->query("SELECT id, logo_data, logo_mime FROM firma LIMIT 1");
        $existing = $stmt->fetch();

        // Logo: bestehendes beibehalten, außer neuer Upload oder explizites Entfernen
        $logoData = $existing['logo_data'] ?? null;
        $logoMime = $existing['logo_mime'] ?? null;

        if (!empty($_POST['logo_entfernen'])) {
            $logoData = null;
            $logoMime = null;
        } elseif (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $erlaubteMimes = ['image/png', 'image/jpeg', 'image/gif'];
            $maxBytes = 2 * 1024 * 1024;
            $bildinfo = @getimagesize($_FILES['logo']['tmp_name']);

            if ($_FILES['logo']['size'] > $maxBytes) {
                setFlashMessage('danger', 'Logo ist zu groß (max. 2 MB).');
                header('Location: einstellungen.php?tab=firma');
                exit;
            }
            if (!$bildinfo || !in_array($bildinfo['mime'], $erlaubteMimes, true)) {
                setFlashMessage('danger', 'Logo muss ein PNG-, JPEG- oder GIF-Bild sein.');
                header('Location: einstellungen.php?tab=firma');
                exit;
            }

            $logoData = base64_encode(file_get_contents($_FILES['logo']['tmp_name']));
            $logoMime = $bildinfo['mime'];
        }

        if ($existing) {
            $stmt = $db->prepare("UPDATE firma SET
                name = ?, strasse = ?, plz = ?, ort = ?, telefon = ?, email = ?, website = ?,
                logo_data = ?, logo_mime = ?,
                uid_nummer = ?, steuernummer = ?, finanzamt = ?, iban = ?, bic = ?, bank = ?,
                geschaeftsjahr_beginn = ?, ust_periode = ?, kleinunternehmer = ?
                WHERE id = ?");
            $stmt->execute([
                $_POST['name'], $_POST['strasse'], $_POST['plz'], $_POST['ort'],
                $_POST['telefon'], $_POST['email'], $_POST['website'],
                $logoData, $logoMime,
                $_POST['uid_nummer'], $_POST['steuernummer'], $_POST['finanzamt'],
                $_POST['iban'], $_POST['bic'], $_POST['bank'],
                $_POST['geschaeftsjahr_beginn'], $_POST['ust_periode'],
                isset($_POST['kleinunternehmer']) ? 1 : 0,
                $existing['id']
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO firma
                (name, strasse, plz, ort, telefon, email, website, logo_data, logo_mime, uid_nummer, steuernummer,
                 finanzamt, iban, bic, bank, geschaeftsjahr_beginn, ust_periode, kleinunternehmer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'], $_POST['strasse'], $_POST['plz'], $_POST['ort'],
                $_POST['telefon'], $_POST['email'], $_POST['website'],
                $logoData, $logoMime,
                $_POST['uid_nummer'], $_POST['steuernummer'], $_POST['finanzamt'],
                $_POST['iban'], $_POST['bic'], $_POST['bank'],
                $_POST['geschaeftsjahr_beginn'], $_POST['ust_periode'],
                isset($_POST['kleinunternehmer']) ? 1 : 0
            ]);
        }

        setFlashMessage('success', 'Firmendaten gespeichert.');
        header('Location: einstellungen.php?tab=firma');
        exit;
    }

    // Firmenprofil speichern (Marken-Name+Logo, z.B. für einen zweiten Firmenzweig)
    if (isset($_POST['save_firmenprofil'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            setFlashMessage('danger', 'Bitte einen Namen für das Firmenprofil angeben.');
            header('Location: einstellungen.php?tab=firmenprofile');
            exit;
        }

        $logoUpload = null;
        $logoMime = null;
        if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $erlaubteMimes = ['image/png', 'image/jpeg', 'image/gif'];
            $maxBytes = 2 * 1024 * 1024;
            $bildinfo = @getimagesize($_FILES['logo']['tmp_name']);

            if ($_FILES['logo']['size'] > $maxBytes) {
                setFlashMessage('danger', 'Logo ist zu groß (max. 2 MB).');
                header('Location: einstellungen.php?tab=firmenprofile' . (!empty($_POST['id']) ? '&action=edit&id=' . (int)$_POST['id'] : '&action=new'));
                exit;
            }
            if (!$bildinfo || !in_array($bildinfo['mime'], $erlaubteMimes, true)) {
                setFlashMessage('danger', 'Logo muss ein PNG-, JPEG- oder GIF-Bild sein.');
                header('Location: einstellungen.php?tab=firmenprofile' . (!empty($_POST['id']) ? '&action=edit&id=' . (int)$_POST['id'] : '&action=new'));
                exit;
            }

            $logoUpload = file_get_contents($_FILES['logo']['tmp_name']);
            $logoMime = $bildinfo['mime'];
        }

        saveFirmenprofil([
            'id' => $_POST['id'] ?: null,
            'name' => $name,
            'logo_upload' => $logoUpload,
            'logo_mime' => $logoMime,
            'logo_entfernen' => !empty($_POST['logo_entfernen']),
            'farbe1' => $_POST['farbe1'] ?? null,
            'farbe2' => $_POST['farbe2'] ?? null,
            'ist_standard' => isset($_POST['ist_standard']) ? 1 : 0,
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
        ]);
        setFlashMessage('success', 'Firmenprofil gespeichert.');
        header('Location: einstellungen.php?tab=firmenprofile');
        exit;
    }

    // Firmenprofil löschen
    if (isset($_POST['delete_firmenprofil'])) {
        $result = deleteFirmenprofil((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Firmenprofil gelöscht.' : $result['message']);
        header('Location: einstellungen.php?tab=firmenprofile');
        exit;
    }

    // USt-Satz speichern
    if (isset($_POST['save_ust'])) {
        $id = $_POST['id'] ?? null;
        $satz = floatval(str_replace(',', '.', $_POST['satz']));
        
        if (!empty($id)) {
            $stmt = $db->prepare("UPDATE ust_saetze SET bezeichnung=?, satz=?, u30_kennzahl_bemessung=?, u30_kennzahl_steuer=?, aktiv=? WHERE id=?");
            $stmt->execute([$_POST['bezeichnung'], $satz, $_POST['u30_kennzahl_bemessung'] ?: null, $_POST['u30_kennzahl_steuer'] ?: null, isset($_POST['aktiv']) ? 1 : 0, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO ust_saetze (bezeichnung, satz, u30_kennzahl_bemessung, u30_kennzahl_steuer, aktiv) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['bezeichnung'], $satz, $_POST['u30_kennzahl_bemessung'] ?: null, $_POST['u30_kennzahl_steuer'] ?: null, isset($_POST['aktiv']) ? 1 : 0]);
        }
        setFlashMessage('success', 'USt-Satz gespeichert.');
        header('Location: einstellungen.php?tab=ust');
        exit;
    }
    
    // USt-Satz löschen
    if (isset($_POST['delete_ust'])) {
        $db->prepare("DELETE FROM ust_saetze WHERE id = ?")->execute([$_POST['id']]);
        setFlashMessage('success', 'USt-Satz gelöscht.');
        header('Location: einstellungen.php?tab=ust');
        exit;
    }

    // Zahlungsbedingung speichern
    if (isset($_POST['save_zahlungsbedingung'])) {
        $name = trim($_POST['bezeichnung'] ?? '');
        if ($name === '') {
            setFlashMessage('danger', 'Bitte eine Bezeichnung angeben.');
            header('Location: einstellungen.php?tab=zahlungsbedingungen');
            exit;
        }
        saveZahlungsbedingung([
            'id' => $_POST['id'] ?: null,
            'bezeichnung' => $name,
            'tage_bis_faellig' => $_POST['tage_bis_faellig'] ?? '',
            'skonto_prozent' => $_POST['skonto_prozent'] ?? '',
            'skonto_tage' => $_POST['skonto_tage'] ?? '',
            'zahlungshinweis_text' => $_POST['zahlungshinweis_text'] ?? '',
            'ist_standard' => isset($_POST['ist_standard']) ? 1 : 0,
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
        ]);
        setFlashMessage('success', 'Zahlungsbedingung gespeichert.');
        header('Location: einstellungen.php?tab=zahlungsbedingungen');
        exit;
    }

    if (isset($_POST['delete_zahlungsbedingung'])) {
        $result = deleteZahlungsbedingung((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Zahlungsbedingung gelöscht.' : $result['message']);
        header('Location: einstellungen.php?tab=zahlungsbedingungen');
        exit;
    }

    // Kategorie speichern
    if (isset($_POST['save_kategorie'])) {
        $id = $_POST['id'] ?? null;
        
        if (!empty($id)) {
            $stmt = $db->prepare("UPDATE kategorien SET name=?, typ=?, e1a_kennzahl=?, beschreibung=?, farbe=?, aktiv=? WHERE id=?");
            $stmt->execute([$_POST['name'], $_POST['typ'], $_POST['e1a_kennzahl'] ?: null, $_POST['beschreibung'], $_POST['farbe'] ?? '#6c757d', isset($_POST['aktiv']) ? 1 : 0, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO kategorien (name, typ, e1a_kennzahl, beschreibung, farbe, aktiv) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['typ'], $_POST['e1a_kennzahl'] ?: null, $_POST['beschreibung'], $_POST['farbe'] ?? '#6c757d', isset($_POST['aktiv']) ? 1 : 0]);
        }
        setFlashMessage('success', 'Kategorie gespeichert.');
        header('Location: einstellungen.php?tab=kategorien');
        exit;
    }
    
    // Kategorie löschen
    if (isset($_POST['delete_kategorie'])) {
        $db->prepare("DELETE FROM kategorien WHERE id = ?")->execute([$_POST['id']]);
        setFlashMessage('success', 'Kategorie gelöscht.');
        header('Location: einstellungen.php?tab=kategorien');
        exit;
    }

    // Nummernkreis speichern (Verkauf-Modul: Angebote/Aufträge/Rechnungen)
    if (isset($_POST['save_nummernkreis'])) {
        $id = $_POST['id'] ?? null;
        $format = trim($_POST['format'] ?? '');
        $naechsteNummer = max(1, (int)($_POST['naechste_nummer'] ?? 1));

        if ($format === '' || !preg_match('/\{N+\}/', $format)) {
            setFlashMessage('danger', 'Das Format muss mindestens einen Nummern-Platzhalter enthalten, z.B. {NNNN}.');
            header('Location: einstellungen.php?tab=nummernkreise' . (!empty($id) ? "&action=edit&id=$id" : '&action=new'));
            exit;
        }

        if (!empty($id)) {
            // Schlüssel/Jahr bleiben fix - nur Format und nächste Nummer sind änderbar
            $stmt = $db->prepare("UPDATE nummernkreise SET format = ?, naechste_nummer = ? WHERE id = ?");
            $stmt->execute([$format, $naechsteNummer, $id]);
            if (function_exists('logAction')) {
                logAction('nummernkreise', $id, 'geaendert', "Nummernkreis angepasst: Format '$format', nächste Nummer $naechsteNummer");
            }
        } else {
            $schluessel = $_POST['schluessel'] ?? 'rechnung';
            $jahr = (int)($_POST['jahr'] ?? date('Y'));
            try {
                $stmt = $db->prepare("INSERT INTO nummernkreise (schluessel, jahr, format, naechste_nummer) VALUES (?, ?, ?, ?)");
                $stmt->execute([$schluessel, $jahr, $format, $naechsteNummer]);
                if (function_exists('logAction')) {
                    logAction('nummernkreise', $db->lastInsertId(), 'erstellt', "Nummernkreis angelegt: $schluessel $jahr");
                }
            } catch (PDOException $e) {
                setFlashMessage('danger', "Für $schluessel/$jahr existiert bereits ein Nummernkreis.");
                header('Location: einstellungen.php?tab=nummernkreise');
                exit;
            }
        }
        setFlashMessage('success', 'Nummernkreis gespeichert.');
        header('Location: einstellungen.php?tab=nummernkreise');
        exit;
    }

    // PDF-Vorlage speichern (Angebot/Auftrag/Rechnung)
    if (isset($_POST['save_pdf_vorlage'])) {
        $pdfTyp = $_POST['typ'] ?? 'rechnung';
        savePdfVorlage($pdfTyp, $_POST['vorlage'] ?? '', $_SESSION['benutzer_id'] ?? null);
        setFlashMessage('success', 'PDF-Vorlage gespeichert.');
        header('Location: einstellungen.php?tab=pdf_design&typ=' . urlencode($pdfTyp));
        exit;
    }

    // PDF-Vorlage auf Standard zurücksetzen
    if (isset($_POST['reset_pdf_vorlage'])) {
        $pdfTyp = $_POST['typ'] ?? 'rechnung';
        savePdfVorlage($pdfTyp, standardPdfVorlage($pdfTyp), $_SESSION['benutzer_id'] ?? null);
        setFlashMessage('success', 'PDF-Vorlage auf Standard zurückgesetzt.');
        header('Location: einstellungen.php?tab=pdf_design&typ=' . urlencode($pdfTyp));
        exit;
    }

    // E-Mail-Vorlage speichern (Angebot/Auftrag/Rechnung)
    if (isset($_POST['save_email_vorlage'])) {
        $emailTyp = $_POST['typ'] ?? 'rechnung';
        saveEmailVorlage($emailTyp, trim($_POST['betreff'] ?? ''), $_POST['nachricht'] ?? '', $_POST['standard_signatur_id'] ?? null);
        setFlashMessage('success', 'E-Mail-Vorlage gespeichert.');
        header('Location: einstellungen.php?tab=email_vorlagen&typ=' . urlencode($emailTyp));
        exit;
    }

    // E-Mail-Vorlage auf Standard zurücksetzen
    if (isset($_POST['reset_email_vorlage'])) {
        $emailTyp = $_POST['typ'] ?? 'rechnung';
        $standard = standardEmailVorlage($emailTyp);
        saveEmailVorlage($emailTyp, $standard['betreff'], $standard['nachricht'], null);
        setFlashMessage('success', 'E-Mail-Vorlage auf Standard zurückgesetzt.');
        header('Location: einstellungen.php?tab=email_vorlagen&typ=' . urlencode($emailTyp));
        exit;
    }

    // Signatur speichern (neu oder bearbeiten)
    if (isset($_POST['save_email_signatur'])) {
        saveEmailSignatur([
            'id' => $_POST['signatur_id'] ?: null,
            'name' => $_POST['signatur_name'] ?? '',
            'inhalt' => $_POST['signatur_inhalt'] ?? '',
            'ist_standard' => isset($_POST['signatur_ist_standard']) ? 1 : 0,
        ]);
        setFlashMessage('success', 'Signatur gespeichert.');
        header('Location: einstellungen.php?tab=email_vorlagen&typ=' . urlencode($_POST['typ'] ?? 'rechnung'));
        exit;
    }

    // Signatur löschen
    if (isset($_POST['delete_email_signatur'])) {
        deleteEmailSignatur((int)$_POST['signatur_id']);
        setFlashMessage('success', 'Signatur gelöscht.');
        header('Location: einstellungen.php?tab=email_vorlagen&typ=' . urlencode($_POST['typ'] ?? 'rechnung'));
        exit;
    }

    // Beispieldaten erstellen
    if (isset($_POST['create_beispieldaten'])) {
        // Kategorien und USt-Sätze laden
        $kategorien = $db->query("SELECT id, name, typ FROM kategorien WHERE aktiv = 1")->fetchAll();
        $ustSaetze = $db->query("SELECT id, satz, bezeichnung FROM ust_saetze WHERE aktiv = 1")->fetchAll();
        
        // Nach Typ gruppieren
        $einnahmeKategorien = array_filter($kategorien, fn($k) => $k['typ'] === 'einnahme');
        $ausgabeKategorien = array_filter($kategorien, fn($k) => $k['typ'] === 'ausgabe');
        $ust20 = array_filter($ustSaetze, fn($u) => $u['satz'] == 20);
        $ust10 = array_filter($ustSaetze, fn($u) => $u['satz'] == 10);
        $ust0 = array_filter($ustSaetze, fn($u) => $u['satz'] == 0 && strpos($u['bezeichnung'], 'innergemeinschaft') === false && strpos($u['bezeichnung'], 'Reverse') === false);
        
        $ust20Id = !empty($ust20) ? reset($ust20)['id'] : null;
        $ust10Id = !empty($ust10) ? reset($ust10)['id'] : null;
        $ust0Id = !empty($ust0) ? reset($ust0)['id'] : null;
        
        $einnahmeKatIds = array_column(array_values($einnahmeKategorien), 'id');
        $ausgabeKatIds = array_column(array_values($ausgabeKategorien), 'id');
        
        // Beispiel-Einnahmen 2025
        $einnahmen2025 = [
            ['2025-01-15', 'Website-Entwicklung Müller GmbH', 2500.00, $ust20Id],
            ['2025-02-20', 'IT-Beratung Weber AG', 1800.00, $ust20Id],
            ['2025-03-10', 'App-Entwicklung Firma Huber', 3200.00, $ust20Id],
            ['2025-04-05', 'Hosting-Service Q1', 450.00, $ust20Id],
            ['2025-05-18', 'WordPress Wartung', 350.00, $ust20Id],
            ['2025-06-22', 'SEO Optimierung', 1200.00, $ust20Id],
            ['2025-07-30', 'Shop-System Erweiterung', 4500.00, $ust20Id],
            ['2025-08-14', 'Newsletter-System Setup', 800.00, $ust20Id],
            ['2025-09-08', 'Datenbank-Migration', 1650.00, $ust20Id],
            ['2025-10-25', 'Logo-Design Startup', 950.00, $ust20Id],
            ['2025-11-12', 'Webinar-Schulung', 600.00, $ust10Id],
            ['2025-12-19', 'Jahres-Wartungsvertrag', 2800.00, $ust20Id],
        ];
        
        // Beispiel-Ausgaben 2025
        $ausgaben2025 = [
            ['2025-01-05', 'Hosting Server', 89.00, $ust20Id],
            ['2025-01-20', 'Adobe Creative Cloud', 59.99, $ust20Id],
            ['2025-02-10', 'Büromaterial', 45.50, $ust20Id],
            ['2025-03-15', 'Domain-Verlängerungen', 120.00, $ust20Id],
            ['2025-04-22', 'Fachliteratur', 89.00, $ust10Id],
            ['2025-05-08', 'Telefonkosten', 35.00, $ust20Id],
            ['2025-06-30', 'SSL Zertifikate', 75.00, $ust20Id],
            ['2025-07-15', 'Steuerberater Q2', 350.00, $ust20Id],
            ['2025-08-20', 'Online-Werbung', 200.00, $ust20Id],
            ['2025-09-25', 'Cloud-Speicher', 99.00, $ust20Id],
            ['2025-10-10', 'WKO Mitgliedschaft', 120.00, $ust0Id],
            ['2025-11-30', 'Software-Lizenzen', 299.00, $ust20Id],
        ];
        
        // 2025 Buchungsnummer ermitteln
        $stmt = $db->prepare("SELECT COALESCE(MAX(buchungsnummer), 0) + 1 as next FROM rechnungen WHERE YEAR(datum) = 2025");
        $stmt->execute();
        $buchNr2025 = $stmt->fetch()['next'];
        
        // Einnahmen 2025 einfügen
        $stmtInsert = $db->prepare("INSERT INTO rechnungen (buchungsnummer, datum, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, typ, kategorie_id, erstellt_von, bezahlt) VALUES (?, ?, ?, ?, ?, ?, ?, 'einnahme', ?, ?, 1)");
        foreach ($einnahmen2025 as $e) {
            $ustId = $e[3] ?? $ust20Id;
            $ustSatz = 20;
            foreach ($ustSaetze as $u) { if ($u['id'] == $ustId) { $ustSatz = $u['satz']; break; } }
            $ustBetrag = $e[2] * ($ustSatz / 100);
            $katId = !empty($einnahmeKatIds) ? $einnahmeKatIds[array_rand($einnahmeKatIds)] : null;
            $stmtInsert->execute([$buchNr2025++, $e[0], $e[1], $e[2], $ustId, $ustBetrag, $e[2] + $ustBetrag, $katId, $_SESSION['user_id'] ?? 1]);
        }
        
        // Ausgaben 2025 einfügen
        $stmtInsert = $db->prepare("INSERT INTO rechnungen (buchungsnummer, datum, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, typ, kategorie_id, erstellt_von, bezahlt) VALUES (?, ?, ?, ?, ?, ?, ?, 'ausgabe', ?, ?, 1)");
        foreach ($ausgaben2025 as $a) {
            $ustId = $a[3] ?? $ust20Id;
            $ustSatz = 20;
            foreach ($ustSaetze as $u) { if ($u['id'] == $ustId) { $ustSatz = $u['satz']; break; } }
            $ustBetrag = $a[2] * ($ustSatz / 100);
            $katId = !empty($ausgabeKatIds) ? $ausgabeKatIds[array_rand($ausgabeKatIds)] : null;
            $stmtInsert->execute([$buchNr2025++, $a[0], $a[1], $a[2], $ustId, $ustBetrag, $a[2] + $ustBetrag, $katId, $_SESSION['user_id'] ?? 1]);
        }
        
        // 2026 Beispieldaten
        $stmt = $db->prepare("SELECT COALESCE(MAX(buchungsnummer), 0) + 1 as next FROM rechnungen WHERE YEAR(datum) = 2026");
        $stmt->execute();
        $buchNr2026 = $stmt->fetch()['next'];
        
        $einnahmen2026 = [
            ['2026-01-10', 'Webshop Relaunch', 5500.00, $ust20Id],
            ['2026-01-25', 'API Integration', 1900.00, $ust20Id],
        ];
        $ausgaben2026 = [
            ['2026-01-08', 'Server Upgrade', 149.00, $ust20Id],
            ['2026-01-20', 'Business-Software', 199.00, $ust20Id],
        ];
        
        $stmtInsert = $db->prepare("INSERT INTO rechnungen (buchungsnummer, datum, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, typ, kategorie_id, erstellt_von, bezahlt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        foreach ($einnahmen2026 as $e) {
            $ustSatz = 20;
            foreach ($ustSaetze as $u) { if ($u['id'] == $e[3]) { $ustSatz = $u['satz']; break; } }
            $ustBetrag = $e[2] * ($ustSatz / 100);
            $katId = !empty($einnahmeKatIds) ? $einnahmeKatIds[array_rand($einnahmeKatIds)] : null;
            $stmtInsert->execute([$buchNr2026++, $e[0], $e[1], $e[2], $e[3], $ustBetrag, $e[2] + $ustBetrag, 'einnahme', $katId, $_SESSION['user_id'] ?? 1]);
        }
        foreach ($ausgaben2026 as $a) {
            $ustSatz = 20;
            foreach ($ustSaetze as $u) { if ($u['id'] == $a[3]) { $ustSatz = $u['satz']; break; } }
            $ustBetrag = $a[2] * ($ustSatz / 100);
            $katId = !empty($ausgabeKatIds) ? $ausgabeKatIds[array_rand($ausgabeKatIds)] : null;
            $stmtInsert->execute([$buchNr2026++, $a[0], $a[1], $a[2], $a[3], $ustBetrag, $a[2] + $ustBetrag, 'ausgabe', $katId, $_SESSION['user_id'] ?? 1]);
        }
        
        // Beispiel-Anlagegut
        $stmtAnlage = $db->prepare("INSERT INTO anlagegueter (buchungsnummer, bezeichnung, kategorie, anschaffungsdatum, anschaffungswert, netto_betrag, ust_satz_id, ust_betrag, nutzungsdauer, afa_methode, e1a_kennzahl, status, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktiv', ?)");
        $anschaffungswert = 1899.00;
        $ustBetragAnlage = $anschaffungswert * 0.20;
        $stmtAnlage->execute([1, 'MacBook Pro 14"', 'Sonstige', '2025-02-01', $anschaffungswert + $ustBetragAnlage, $anschaffungswert, $ust20Id, $ustBetragAnlage, 3, 'linear', '9130', $_SESSION['user_id'] ?? 1]);

        $afabeispiele = [
            [1, 2025, 759.27, 2278.80, 1519.53, '2026-01-14 15:35:15'],
            [1, 2026, 759.27, 1519.53, 760.27, '2026-01-14 15:35:15'],
            [1, 2027, 759.27, 760.27, 1.00, '2026-01-14 15:35:15'],
        ];
        $stmtafa = $db->prepare("INSERT INTO afa_buchungen (anlagegut_id, jahr, afa_betrag, restwert_vor, restwert_nach, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($afabeispiele as $af) {
            $stmtafa->execute([$af[0], $af[1], $af[2], $af[3], $af[4], $af[5]]);
        }
        
        setFlashMessage('success', 'Beispieldaten wurden erstellt: 24 Rechnungen für 2025, 4 für 2026, 1 Anlagegut.');
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }
    
    // Alle Daten löschen
    if (isset($_POST['reset_daten']) && $_POST['confirm_text'] === 'LÖSCHEN') {
        $db->exec("DELETE FROM afa_buchungen");
        $db->exec("DELETE FROM anlagegueter");
        $db->exec("DELETE FROM rechnungen");
        $db->exec("DELETE FROM einkommensteuer");
        $db->exec("DELETE FROM ust_voranmeldungen");
        $db->exec("DELETE FROM aenderungsprotokoll WHERE tabelle != 'benutzer'");
        
        // Auto-Increment zurücksetzen
        $db->exec("ALTER TABLE rechnungen AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE anlagegueter AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE afa_buchungen AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE einkommensteuer AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE ust_voranmeldungen AUTO_INCREMENT = 1");
        
        setFlashMessage('success', 'Alle Buchungsdaten wurden gelöscht. Kategorien, USt-Sätze und Benutzer bleiben erhalten.');
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    // Automatische wiederkehrende Rechnungen (Zeitsteuerung für cron/generate_wiederkehrende_rechnungen.php)
    if (isset($_POST['save_automatisierung_einstellungen'])) {
        saveAutomatisierungEinstellungen(
            isset($_POST['wiederkehrend_aktiv']),
            $_POST['wiederkehrend_uhrzeit'] ?: '03:00'
        );
        setFlashMessage('success', 'Automatisierungs-Einstellungen gespeichert.');
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    // Netzwerk-Bondrucker (IP/Port/Papierbreite)
    if (isset($_POST['save_bondrucker_einstellungen'])) {
        saveBondruckerEinstellungen(
            trim($_POST['bondrucker_ip'] ?? ''),
            $_POST['bondrucker_port'] ?? 9100,
            $_POST['bondrucker_papierbreite'] ?? '80mm'
        );
        setFlashMessage('success', 'Bondrucker-Einstellungen gespeichert.');
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    if (isset($_POST['bondrucker_testdruck'])) {
        $result = druckeBondruckerTestseite(
            trim($_POST['bondrucker_ip'] ?? ''),
            $_POST['bondrucker_port'] ?? 9100,
            $_POST['bondrucker_papierbreite'] ?? '80mm'
        );
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['message']);
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    if (isset($_POST['save_paperless_einstellungen'])) {
        $bestehende = getPaperlessEinstellungen();
        $neuesToken = trim($_POST['paperless_api_token'] ?? '');
        savePaperlessEinstellungen(
            isset($_POST['paperless_aktiv']) ? 1 : 0,
            trim($_POST['paperless_base_url'] ?? ''),
            $neuesToken !== '' ? $neuesToken : $bestehende['api_token'],
            isset($_POST['paperless_verify_ssl']) ? 1 : 0
        );
        setFlashMessage('success', 'paperless-Einstellungen gespeichert.');
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    if (isset($_POST['paperless_verbindung_testen'])) {
        $bestehende = getPaperlessEinstellungen();
        $neuesToken = trim($_POST['paperless_api_token'] ?? '');
        $result = testePaperlessVerbindung(
            trim($_POST['paperless_base_url'] ?? ''),
            $neuesToken !== '' ? $neuesToken : $bestehende['api_token'],
            isset($_POST['paperless_verify_ssl'])
        );
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['message']);
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    if (isset($_POST['save_paperless_tags'])) {
        foreach (['angebot', 'auftrag', 'rechnung'] as $ptyp) {
            savePaperlessTagsFuerTyp($ptyp, $_POST['paperless_tags_' . $ptyp] ?? '');
        }
        setFlashMessage('success', 'Paperless-Tags gespeichert.');
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }

    // Update per Git-Pull (Deployment) - nur Admins, führt den fest hinterlegten Befehl aus,
    // nimmt keinerlei Benutzereingabe in den Shell-Befehl auf
    if (isset($_POST['git_pull_deploy'])) {
        requireAdmin();
        $projectDir = __DIR__;
        if (!is_dir($projectDir . '/.git')) {
            setFlashMessage('danger', 'Kein Git-Repository gefunden - einmalige Einrichtung auf dem Server nötig.');
        } elseif (!function_exists('shell_exec')) {
            setFlashMessage('danger', 'shell_exec ist auf diesem Server deaktiviert - Update per Button nicht möglich.');
        } else {
            $ausgabe = shell_exec('git -C ' . escapeshellarg($projectDir) . ' pull 2>&1');
            $ausgabe = $ausgabe !== null ? trim($ausgabe) : '(keine Ausgabe erhalten)';
            logAction('system', 0, 'geaendert', "Deployment: git pull ausgeführt.\n" . $ausgabe);
            $_SESSION['deploy_ausgabe'] = $ausgabe;
            setFlashMessage('success', 'Update ausgeführt - Ausgabe siehe unten. Etwaige Datenbank-Migrationen laufen automatisch beim nächsten Seitenaufruf.');
        }
        header('Location: einstellungen.php?tab=wartung');
        exit;
    }
}

$deployAusgabe = $_SESSION['deploy_ausgabe'] ?? null;
unset($_SESSION['deploy_ausgabe']);

// Daten laden
$firma = $db->query("SELECT * FROM firma LIMIT 1")->fetch();
$ustSaetze = $db->query("SELECT * FROM ust_saetze ORDER BY satz DESC")->fetchAll();
$kategorien = $db->query("SELECT * FROM kategorien ORDER BY typ, name")->fetchAll();
$firmenprofile = $tab === 'firmenprofile' ? getAlleFirmenprofile(false) : [];
$automatisierung = $tab === 'wartung' ? getAutomatisierungEinstellungen() : [];
$bondrucker = $tab === 'wartung' ? getBondruckerEinstellungen() : [];
$paperlessEinstellungen = $tab === 'wartung' ? getPaperlessEinstellungen() : [];
$paperlessTags = $tab === 'wartung' ? getAllePaperlessTagEinstellungen() : [];
$gitInfo = null;
if ($tab === 'wartung' && is_dir(__DIR__ . '/.git') && function_exists('shell_exec')) {
    $gitInfo = [
        'branch' => trim((string) shell_exec('git -C ' . escapeshellarg(__DIR__) . ' rev-parse --abbrev-ref HEAD 2>&1')),
        'commit' => trim((string) shell_exec('git -C ' . escapeshellarg(__DIR__) . ' log -1 --format=%h\ %cd --date=format:%d.%m.%Y\ %H:%M 2>&1')),
    ];
}
$zahlungsbedingungen = $tab === 'zahlungsbedingungen' ? getAlleZahlungsbedingungen(false) : [];
$zahlungsbedingung = ($id && $tab === 'zahlungsbedingungen') ? getZahlungsbedingung((int)$id) : null;

// Einzeldaten für Edit
$ustSatz = null;
$kategorie = null;
$firmenprofil = ($id && $tab === 'firmenprofile') ? getFirmenprofil((int)$id) : null;
if ($id && $tab === 'ust') {
    $stmt = $db->prepare("SELECT * FROM ust_saetze WHERE id = ?");
    $stmt->execute([$id]);
    $ustSatz = $stmt->fetch();
}
if ($id && $tab === 'kategorien') {
    $stmt = $db->prepare("SELECT * FROM kategorien WHERE id = ?");
    $stmt->execute([$id]);
    $kategorie = $stmt->fetch();
}

$nummernkreise = [];
$nummernkreis = null;
if ($tab === 'nummernkreise') {
    $nummernkreise = $db->query("SELECT * FROM nummernkreise ORDER BY jahr DESC, schluessel")->fetchAll();
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM nummernkreise WHERE id = ?");
        $stmt->execute([$id]);
        $nummernkreis = $stmt->fetch();
    }
}
$nummernkreisLabels = ['rechnung' => 'Rechnung', 'angebot' => 'Angebot', 'auftrag' => 'Auftrag'];
// Nur für die Nummernkreise-Verwaltung: zusätzlich "Kunde" (jahresunabhängig, siehe
// zieheKundennummer()) - bewusst getrennt von $nummernkreisLabels, da dieses auch die
// PDF-Design- und E-Mail-Vorlagen-Tabs steuert, für die es keinen "Kunde"-Typ gibt.
$nummernkreisSchluesselLabels = $nummernkreisLabels + ['kunde' => 'Kunde'];
$pdfDesignTypen = $nummernkreisLabels + ['lieferschein' => 'Lieferschein'];

$pdfDesignTyp = $_GET['typ'] ?? 'rechnung';
if (!array_key_exists($pdfDesignTyp, $pdfDesignTypen)) {
    $pdfDesignTyp = 'rechnung';
}
$pdfVorlageInhalt = $tab === 'pdf_design' ? getPdfVorlage($pdfDesignTyp) : '';

$emailDesignTyp = $_GET['typ'] ?? 'rechnung';
if (!array_key_exists($emailDesignTyp, $nummernkreisLabels)) {
    $emailDesignTyp = 'rechnung';
}
$emailVorlageAktuell = $tab === 'email_vorlagen' ? getEmailVorlage($emailDesignTyp) : null;
$emailSignaturenListe = $tab === 'email_vorlagen' ? getAlleEmailSignaturen() : [];

// E1a Kennzahlen
$e1aKennzahlen = [
    'Einnahmen' => ['9040' => 'Erlöse Waren', '9050' => 'Erlöse Dienstleistungen', '9060' => 'Anlagenerträge', '9090' => 'Übrige Erträge'],
    'Ausgaben' => ['9100' => 'Wareneinkauf', '9110' => 'Fremdleistungen', '9120' => 'Personalaufwand', '9130' => 'AfA normal', '9134' => 'AfA degressiv', '9135' => 'AfA Gebäude', '9140' => 'Betriebsräume', '9150' => 'Instandhaltung', '9160' => 'Reisekosten', '9170' => 'Kfz-Kosten', '9180' => 'Miete/Leasing', '9190' => 'Provisionen', '9200' => 'Werbung', '9220' => 'Zinsen', '9225' => 'SVS Beiträge', '9230' => 'Übrige']
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - EKassa360</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php displayFlashMessage(); ?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-gear me-2"></i>Einstellungen</h1>
                </div>

                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'firma' ? 'active' : '' ?>" href="?tab=firma">
                            <i class="bi bi-building me-1"></i>Firma
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'firmenprofile' ? 'active' : '' ?>" href="?tab=firmenprofile">
                            <i class="bi bi-buildings me-1"></i>Firmenprofile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'ust' ? 'active' : '' ?>" href="?tab=ust">
                            <i class="bi bi-percent me-1"></i>USt-Sätze
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'kategorien' ? 'active' : '' ?>" href="?tab=kategorien">
                            <i class="bi bi-tags me-1"></i>Kategorien
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'zahlungsbedingungen' ? 'active' : '' ?>" href="?tab=zahlungsbedingungen">
                            <i class="bi bi-cash-coin me-1"></i>Zahlungsbedingungen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'nummernkreise' ? 'active' : '' ?>" href="?tab=nummernkreise">
                            <i class="bi bi-123 me-1"></i>Nummernkreise
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'pdf_design' ? 'active' : '' ?>" href="?tab=pdf_design&typ=<?= $pdfDesignTyp ?>">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF-Design
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'email_vorlagen' ? 'active' : '' ?>" href="?tab=email_vorlagen&typ=<?= $emailDesignTyp ?>">
                            <i class="bi bi-envelope-paper me-1"></i>E-Mail-Vorlagen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'hilfe' ? 'active' : '' ?>" href="?tab=hilfe">
                            <i class="bi bi-question-circle me-1"></i>Kennzahlen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'wartung' ? 'active' : '' ?>" href="?tab=wartung">
                            <i class="bi bi-gear me-1"></i>Wartung
                        </a>
                    </li>
                </ul>

                <?php if ($tab === 'firma'): ?>
                <!-- FIRMA -->
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="bi bi-building me-2"></i>Firmendaten
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Firmenname *</label>
                                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($firma['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-8">
                                            <label class="form-label">Straße</label>
                                            <input type="text" class="form-control" name="strasse" value="<?= htmlspecialchars($firma['strasse'] ?? '') ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">PLZ</label>
                                            <input type="text" class="form-control" name="plz" value="<?= htmlspecialchars($firma['plz'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ort</label>
                                        <input type="text" class="form-control" name="ort" value="<?= htmlspecialchars($firma['ort'] ?? '') ?>">
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Telefon</label>
                                            <input type="text" class="form-control" name="telefon" value="<?= htmlspecialchars($firma['telefon'] ?? '') ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">E-Mail</label>
                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($firma['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control" name="website" value="<?= htmlspecialchars($firma['website'] ?? '') ?>" placeholder="https://">
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="bi bi-image me-2"></i>Logo
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($firma['logo_data'])): ?>
                                    <div class="mb-3">
                                        <img src="data:<?= htmlspecialchars($firma['logo_mime']) ?>;base64,<?= $firma['logo_data'] ?>" style="max-height: 80px; max-width: 100%;" alt="Firmenlogo">
                                    </div>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="logo_entfernen" id="logo_entfernen" value="1">
                                        <label class="form-check-label" for="logo_entfernen">Logo entfernen</label>
                                    </div>
                                    <div class="form-text mb-2">Neue Datei wählen, um das Logo zu ersetzen:</div>
                                    <?php else: ?>
                                    <p class="text-muted small">Noch kein Logo hinterlegt.</p>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/gif">
                                    <div class="form-text">PNG, JPEG oder GIF, max. 2 MB. Wird in den PDF-Vorlagen über den Platzhalter <code>{{firma_logo}}</code> eingebunden.</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="bi bi-bank me-2"></i>Steuer & Bank
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label">UID-Nummer</label>
                                            <input type="text" class="form-control" name="uid_nummer" value="<?= htmlspecialchars($firma['uid_nummer'] ?? '') ?>" placeholder="ATU12345678">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">Steuernummer</label>
                                            <input type="text" class="form-control" name="steuernummer" value="<?= htmlspecialchars($firma['steuernummer'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Finanzamt</label>
                                        <input type="text" class="form-control" name="finanzamt" value="<?= htmlspecialchars($firma['finanzamt'] ?? '') ?>">
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <label class="form-label">Bank</label>
                                        <input type="text" class="form-control" name="bank" value="<?= htmlspecialchars($firma['bank'] ?? '') ?>">
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-8">
                                            <label class="form-label">IBAN</label>
                                            <input type="text" class="form-control" name="iban" value="<?= htmlspecialchars($firma['iban'] ?? '') ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">BIC</label>
                                            <input type="text" class="form-control" name="bic" value="<?= htmlspecialchars($firma['bic'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-header">
                                    <i class="bi bi-calendar me-2"></i>Buchhaltung
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="form-label">Geschäftsjahr</label>
                                            <select class="form-select" name="geschaeftsjahr_beginn">
                                                <option value="01-01" <?= ($firma['geschaeftsjahr_beginn'] ?? '01-01') === '01-01' ? 'selected' : '' ?>>1. Jänner</option>
                                                <option value="04-01" <?= ($firma['geschaeftsjahr_beginn'] ?? '') === '04-01' ? 'selected' : '' ?>>1. April</option>
                                                <option value="07-01" <?= ($firma['geschaeftsjahr_beginn'] ?? '') === '07-01' ? 'selected' : '' ?>>1. Juli</option>
                                                <option value="10-01" <?= ($firma['geschaeftsjahr_beginn'] ?? '') === '10-01' ? 'selected' : '' ?>>1. Oktober</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">USt-Periode</label>
                                            <select class="form-select" name="ust_periode">
                                                <option value="monatlich" <?= ($firma['ust_periode'] ?? '') === 'monatlich' ? 'selected' : '' ?>>Monatlich</option>
                                                <option value="quartalsweise" <?= ($firma['ust_periode'] ?? '') === 'quartalsweise' ? 'selected' : '' ?>>Quartalsweise</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="kleinunternehmer" id="kleinunternehmer" <?= ($firma['kleinunternehmer'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="kleinunternehmer">Kleinunternehmerregelung (§ 6 Abs. 1 Z 27 UStG)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="save_firma" class="btn btn-success btn-lg">
                        <i class="bi bi-check-lg me-2"></i>Firmendaten speichern
                    </button>
                </form>

                <?php elseif ($tab === 'firmenprofile'): ?>
                <!-- FIRMENPROFILE (Marken-Name+Logo pro Firmenzweig, auswählbar bei Angebot/Auftrag/Rechnung) -->
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Name, Logo und zwei Hauptfarben unterscheiden sich pro Profil - Adresse, UID, IBAN und Bankverbindung
                    kommen weiterhin einheitlich aus dem Tab "Firma", da es sich steuerlich um dieselbe Firma handelt.
                    Die Farben stehen in den PDF-Vorlagen (Tab "PDF-Design") als <code>{{firma_farbe1}}</code> und
                    <code>{{firma_farbe2}}</code> zur Verfügung.
                </div>
                <?php if ($action === 'edit' || $action === 'new'): ?>
                <div class="card">
                    <div class="card-header"><?= $action === 'new' ? 'Neues Firmenprofil' : 'Firmenprofil bearbeiten' ?></div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= $firmenprofil['id'] ?? '' ?>">
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <label class="form-label required">Name</label>
                                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($firmenprofil['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" class="form-check-input" name="aktiv" id="fp_aktiv" <?= ($firmenprofil['aktiv'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="fp_aktiv">Aktiv</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Logo</label>
                                <?php if (!empty($firmenprofil['logo_data'])): ?>
                                <div class="mb-2">
                                    <img src="data:<?= htmlspecialchars($firmenprofil['logo_mime']) ?>;base64,<?= $firmenprofil['logo_data'] ?>" style="max-height: 60px;" alt="Aktuelles Logo">
                                    <div class="form-check mt-1">
                                        <input type="checkbox" class="form-check-input" name="logo_entfernen" id="fp_logo_entfernen">
                                        <label class="form-check-label" for="fp_logo_entfernen">Logo entfernen</label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="logo" accept="image/png,image/jpeg,image/gif">
                                <div class="form-text">PNG, JPEG oder GIF, max. 2 MB. Wird als Base64-Data-URI in den PDF-Vorlagen eingebettet.</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Hauptfarbe 1</label>
                                    <input type="color" class="form-control form-control-color" name="farbe1" value="<?= htmlspecialchars($firmenprofil['farbe1'] ?? '#0d6efd') ?>" title="Hauptfarbe 1">
                                    <div class="form-text">Platzhalter <code>{{firma_farbe1}}</code></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Hauptfarbe 2</label>
                                    <input type="color" class="form-control form-control-color" name="farbe2" value="<?= htmlspecialchars($firmenprofil['farbe2'] ?? '#6c757d') ?>" title="Hauptfarbe 2">
                                    <div class="form-text">Platzhalter <code>{{firma_farbe2}}</code></div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" name="ist_standard" id="fp_ist_standard" <?= ($firmenprofil['ist_standard'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="fp_ist_standard">Als Standard verwenden (Vorauswahl bei neuen Dokumenten)</label>
                            </div>
                            <button type="submit" name="save_firmenprofil" class="btn btn-success">Speichern</button>
                            <a href="?tab=firmenprofile" class="btn btn-secondary">Abbrechen</a>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-buildings me-2"></i>Firmenprofile</span>
                        <a href="?tab=firmenprofile&action=new" class="btn btn-light btn-sm">+ Neu</a>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Logo</th><th>Name</th><th>Farben</th><th class="text-center">Standard</th><th class="text-center">Status</th><th></th></tr></thead>
                        <tbody>
                        <?php if (empty($firmenprofile)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Keine Firmenprofile vorhanden</td></tr>
                        <?php else: foreach ($firmenprofile as $profil): ?>
                        <tr class="<?= !$profil['aktiv'] ? 'table-secondary' : '' ?>">
                            <td><?php if (!empty($profil['logo_data'])): ?><img src="data:<?= htmlspecialchars($profil['logo_mime']) ?>;base64,<?= $profil['logo_data'] ?>" style="max-height: 32px;" alt=""><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td><?= htmlspecialchars($profil['name']) ?></td>
                            <td>
                                <span class="d-inline-block rounded-circle border" style="width:18px;height:18px;background:<?= htmlspecialchars($profil['farbe1'] ?? '#0d6efd') ?>;" title="Hauptfarbe 1"></span>
                                <span class="d-inline-block rounded-circle border" style="width:18px;height:18px;background:<?= htmlspecialchars($profil['farbe2'] ?? '#6c757d') ?>;" title="Hauptfarbe 2"></span>
                            </td>
                            <td class="text-center"><?php if ($profil['ist_standard']): ?><span class="badge bg-primary">Standard</span><?php endif; ?></td>
                            <td class="text-center"><span class="badge bg-<?= $profil['aktiv'] ? 'success' : 'secondary' ?>"><?= $profil['aktiv'] ? 'Aktiv' : 'Inaktiv' ?></span></td>
                            <td class="text-end">
                                <a href="?tab=firmenprofile&action=edit&id=<?= $profil['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Firmenprofil wirklich löschen?')">
                                    <input type="hidden" name="id" value="<?= $profil['id'] ?>">
                                    <button name="delete_firmenprofil" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php elseif ($tab === 'ust'): ?>
                <!-- UST-SÄTZE -->
                <?php if ($action === 'edit' || $action === 'new'): ?>
                <div class="card">
                    <div class="card-header"><?= $action === 'new' ? 'Neuer USt-Satz' : 'USt-Satz bearbeiten' ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $ustSatz['id'] ?? '' ?>">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Bezeichnung *</label>
                                    <input type="text" class="form-control" name="bezeichnung" value="<?= htmlspecialchars($ustSatz['bezeichnung'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Satz (%) *</label>
                                    <input type="text" class="form-control" name="satz" value="<?= isset($ustSatz['satz']) ? number_format($ustSatz['satz'], 2, ',', '') : '' ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" name="aktiv" <?= ($ustSatz['aktiv'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Aktiv</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">U30 Kennzahl Bemessung</label>
                                    <input type="text" class="form-control" name="u30_kennzahl_bemessung" value="<?= htmlspecialchars($ustSatz['u30_kennzahl_bemessung'] ?? '') ?>" placeholder="z.B. 022">
                                    <small class="text-muted">20%=022, 10%=029, 13%=006</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">U30 Kennzahl Steuer (optional)</label>
                                    <input type="text" class="form-control" name="u30_kennzahl_steuer" value="<?= htmlspecialchars($ustSatz['u30_kennzahl_steuer'] ?? '') ?>">
                                </div>
                            </div>
                            <button type="submit" name="save_ust" class="btn btn-success">Speichern</button>
                            <a href="?tab=ust" class="btn btn-secondary">Abbrechen</a>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-percent me-2"></i>USt-Sätze</span>
                        <a href="?tab=ust&action=new" class="btn btn-light btn-sm">+ Neu</a>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Bezeichnung</th><th class="text-center">Satz</th><th class="text-center">U30 KZ</th><th class="text-center">Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($ustSaetze as $us): ?>
                        <tr class="<?= !$us['aktiv'] ? 'table-secondary' : '' ?>">
                            <td><?= htmlspecialchars($us['bezeichnung']) ?></td>
                            <td class="text-center"><span class="badge bg-primary"><?= number_format($us['satz'], 0) ?>%</span></td>
                            <td class="text-center"><?= $us['u30_kennzahl_bemessung'] ? '<code>'.$us['u30_kennzahl_bemessung'].'</code>' : '-' ?></td>
                            <td class="text-center"><span class="badge bg-<?= $us['aktiv'] ? 'success' : 'secondary' ?>"><?= $us['aktiv'] ? 'Aktiv' : 'Inaktiv' ?></span></td>
                            <td class="text-end">
                                <a href="?tab=ust&action=edit&id=<?= $us['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Löschen?')">
                                    <input type="hidden" name="id" value="<?= $us['id'] ?>">
                                    <button name="delete_ust" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-warning mt-3">
                    <strong>U30 2025 korrekte Kennzahlen:</strong> <code>20%→022</code> | <code>10%→029</code> | <code>13%→006</code> | <code>ig→017</code>
                </div>
                <?php endif; ?>

                <?php elseif ($tab === 'kategorien'): ?>
                <!-- KATEGORIEN -->
                <?php if ($action === 'edit' || $action === 'new'): ?>
                <div class="card">
                    <div class="card-header"><?= $action === 'new' ? 'Neue Kategorie' : 'Kategorie bearbeiten' ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $kategorie['id'] ?? '' ?>">
                            <div class="row mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Name *</label>
                                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($kategorie['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Typ *</label>
                                    <select class="form-select" name="typ" required>
                                        <option value="einnahme" <?= ($kategorie['typ'] ?? '') === 'einnahme' ? 'selected' : '' ?>>Einnahme</option>
                                        <option value="ausgabe" <?= ($kategorie['typ'] ?? '') === 'ausgabe' ? 'selected' : '' ?>>Ausgabe</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Farbe</label>
                                    <input type="color" class="form-control" name="farbe" value="<?= $kategorie['farbe'] ?? '#6c757d' ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" name="aktiv" <?= ($kategorie['aktiv'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Aktiv</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">E1a Kennzahl</label>
                                    <select class="form-select" name="e1a_kennzahl">
                                        <option value="">-- Keine --</option>
                                        <?php foreach ($e1aKennzahlen as $group => $kzs): ?>
                                        <optgroup label="<?= $group ?>">
                                            <?php foreach ($kzs as $kz => $bez): ?>
                                            <option value="<?= $kz ?>" <?= ($kategorie['e1a_kennzahl'] ?? '') === $kz ? 'selected' : '' ?>><?= $kz ?> - <?= $bez ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Beschreibung</label>
                                    <input type="text" class="form-control" name="beschreibung" value="<?= htmlspecialchars($kategorie['beschreibung'] ?? '') ?>">
                                </div>
                            </div>
                            <button type="submit" name="save_kategorie" class="btn btn-success">Speichern</button>
                            <a href="?tab=kategorien" class="btn btn-secondary">Abbrechen</a>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-tags me-2"></i>Kategorien</span>
                        <a href="?tab=kategorien&action=new" class="btn btn-light btn-sm">+ Neu</a>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th class="text-center">Typ</th><th class="text-center">E1a KZ</th><th>Beschreibung</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($kategorien as $kat): ?>
                        <tr class="<?= !$kat['aktiv'] ? 'table-secondary' : '' ?>">
                            <td><span class="badge me-1" style="background:<?= $kat['farbe'] ?>">&nbsp;</span><?= htmlspecialchars($kat['name']) ?></td>
                            <td class="text-center"><span class="badge bg-<?= $kat['typ'] === 'einnahme' ? 'success' : 'danger' ?>"><?= ucfirst($kat['typ']) ?></span></td>
                            <td class="text-center"><?= $kat['e1a_kennzahl'] ? '<code>'.$kat['e1a_kennzahl'].'</code>' : '-' ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($kat['beschreibung'] ?? '') ?></td>
                            <td class="text-end">
                                <a href="?tab=kategorien&action=edit&id=<?= $kat['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Löschen?')">
                                    <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                                    <button name="delete_kategorie" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php elseif ($tab === 'zahlungsbedingungen'): ?>
                <!-- ZAHLUNGSBEDINGUNGEN (nur Verkaufsrechnungen) -->
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Auswählbar beim Erstellen einer Verkaufsrechnung. Der Zahlungshinweis-Text erscheint so
                    (mit Platzhaltern ersetzt) auf dem PDF-Ausdruck der Rechnung.
                </div>
                <?php if ($action === 'edit' || $action === 'new'): ?>
                <div class="card">
                    <div class="card-header"><?= $action === 'new' ? 'Neue Zahlungsbedingung' : 'Zahlungsbedingung bearbeiten' ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $zahlungsbedingung['id'] ?? '' ?>">
                            <div class="row mb-3">
                                <div class="col-md-7">
                                    <label class="form-label required">Bezeichnung</label>
                                    <input type="text" class="form-control" name="bezeichnung" value="<?= htmlspecialchars($zahlungsbedingung['bezeichnung'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tage bis fällig</label>
                                    <input type="number" class="form-control" name="tage_bis_faellig" min="0" value="<?= htmlspecialchars($zahlungsbedingung['tage_bis_faellig'] ?? '') ?>" placeholder="z.B. 0 oder 14">
                                    <div class="form-text">Schlägt im Rechnungsformular automatisch "Fällig am" vor (bleibt änderbar). Leer lassen, wenn nicht zutreffend.</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" class="form-check-input" name="aktiv" id="zb_aktiv" <?= ($zahlungsbedingung['aktiv'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="zb_aktiv">Aktiv</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Skonto (%)</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="skonto_prozent" value="<?= isset($zahlungsbedingung['skonto_prozent']) ? htmlspecialchars(rtrim(rtrim(number_format($zahlungsbedingung['skonto_prozent'], 2, ',', ''), '0'), ',')) : '' ?>" placeholder="z.B. 2">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Skonto-Frist (Tage)</label>
                                    <input type="number" class="form-control" name="skonto_tage" min="0" value="<?= htmlspecialchars($zahlungsbedingung['skonto_tage'] ?? '') ?>" placeholder="z.B. 7">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-text mt-4">
                                        Beide Felder leer lassen, wenn diese Zahlungsbedingung keinen Skonto vorsieht. Frist zählt ab dem
                                        Rechnungsdatum. Ergebnis über <code>{{skonto_*}}</code>-Platzhalter im Zahlungshinweis-Text unten nutzbar.
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Zahlungshinweis-Text (für den PDF-Ausdruck)</label>
                                <textarea class="form-control" name="zahlungshinweis_text" rows="3"><?= htmlspecialchars($zahlungsbedingung['zahlungshinweis_text'] ?? '') ?></textarea>
                                <div class="form-text">
                                    Platzhalter: <code>{{faellig_am}}</code>, <code>{{iban}}</code>, <code>{{bic}}</code>,
                                    <code>{{bic_hinweis}}</code> (fertiger " (BIC ...)"-Zusatz, leer wenn keine BIC hinterlegt), <code>{{bank}}</code>.<br>
                                    Skonto (nur falls oben ausgefüllt): <code>{{skonto_prozent}}</code>, <code>{{skonto_tage}}</code>,
                                    <code>{{skonto_datum}}</code> (Rechnungsdatum + Skonto-Frist), <code>{{skonto_betrag}}</code> (Rechnungsbetrag abzüglich Skonto),
                                    <code>{{skonto_hinweis}}</code> (fertiger Satz, z.B. "Bei Zahlung bis ... gewähren wir ...% Skonto (Betrag: ...).").<br>
                                    Leer lassen, wenn auf der Rechnung kein Zahlungshinweis erscheinen soll.
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" name="ist_standard" id="zb_ist_standard" <?= ($zahlungsbedingung['ist_standard'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="zb_ist_standard">Als Standard verwenden (Vorauswahl bei neuen Rechnungen)</label>
                            </div>
                            <button type="submit" name="save_zahlungsbedingung" class="btn btn-success">Speichern</button>
                            <a href="?tab=zahlungsbedingungen" class="btn btn-secondary">Abbrechen</a>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-cash-coin me-2"></i>Zahlungsbedingungen</span>
                        <a href="?tab=zahlungsbedingungen&action=new" class="btn btn-light btn-sm">+ Neu</a>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Bezeichnung</th><th class="text-center">Tage bis fällig</th><th class="text-center">Skonto</th><th>Zahlungshinweis</th><th class="text-center">Standard</th><th class="text-center">Status</th><th></th></tr></thead>
                        <tbody>
                        <?php if (empty($zahlungsbedingungen)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">Keine Zahlungsbedingungen vorhanden</td></tr>
                        <?php else: foreach ($zahlungsbedingungen as $zb): ?>
                        <tr class="<?= !$zb['aktiv'] ? 'table-secondary' : '' ?>">
                            <td><?= htmlspecialchars($zb['bezeichnung']) ?></td>
                            <td class="text-center"><?= $zb['tage_bis_faellig'] !== null ? (int)$zb['tage_bis_faellig'] : '-' ?></td>
                            <td class="text-center">
                                <?php if ($zb['skonto_prozent'] !== null && $zb['skonto_tage'] !== null): ?>
                                <?= htmlspecialchars(rtrim(rtrim(number_format($zb['skonto_prozent'], 2, ',', ''), '0'), ',')) ?>% / <?= (int)$zb['skonto_tage'] ?> Tage
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars(mb_strimwidth($zb['zahlungshinweis_text'] ?? '', 0, 60, '…')) ?></td>
                            <td class="text-center"><?php if ($zb['ist_standard']): ?><span class="badge bg-primary">Standard</span><?php endif; ?></td>
                            <td class="text-center"><span class="badge bg-<?= $zb['aktiv'] ? 'success' : 'secondary' ?>"><?= $zb['aktiv'] ? 'Aktiv' : 'Inaktiv' ?></span></td>
                            <td class="text-end">
                                <a href="?tab=zahlungsbedingungen&action=edit&id=<?= $zb['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Zahlungsbedingung wirklich löschen?')">
                                    <input type="hidden" name="id" value="<?= $zb['id'] ?>">
                                    <button name="delete_zahlungsbedingung" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php elseif ($tab === 'nummernkreise'): ?>
                <!-- NUMMERNKREISE (Verkauf-Modul: Angebote/Aufträge/Rechnungen) -->
                <?php if ($action === 'edit' || $action === 'new'): ?>
                <div class="card">
                    <div class="card-header"><?= $action === 'new' ? 'Neuer Nummernkreis' : 'Nummernkreis bearbeiten' ?></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= $nummernkreis['id'] ?? '' ?>">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Dokumenttyp *</label>
                                    <?php if ($action === 'edit'): ?>
                                    <input type="text" class="form-control" value="<?= $nummernkreisSchluesselLabels[$nummernkreis['schluessel']] ?? $nummernkreis['schluessel'] ?>" disabled>
                                    <?php else: ?>
                                    <select class="form-select" name="schluessel" id="nk_schluessel" required>
                                        <?php foreach ($nummernkreisSchluesselLabels as $val => $label): ?>
                                        <option value="<?= $val ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jahr *</label>
                                    <?php if ($action === 'edit'): ?>
                                    <input type="text" class="form-control" value="<?= $nummernkreis['schluessel'] === 'kunde' ? 'Kein Jahresbezug' : $nummernkreis['jahr'] ?>" disabled>
                                    <?php else: ?>
                                    <input type="number" class="form-control" name="jahr" id="nk_jahr" value="<?= date('Y') ?>" required>
                                    <div class="form-text" id="nk_jahr_hinweis" style="display:none;">Kundennummern sind nicht jahresgebunden - Jahr wird ignoriert (auf 0 gesetzt).</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nächste Nummer *</label>
                                    <input type="number" class="form-control" id="nk_naechste_nummer" name="naechste_nummer" min="1" value="<?= $nummernkreis['naechste_nummer'] ?? 1 ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Format *</label>
                                <input type="text" class="form-control font-monospace" id="nk_format" name="format"
                                       value="<?= htmlspecialchars($nummernkreis['format'] ?? 'RE-{JJJJ}-{NNNN}') ?>" required>
                                <div class="form-text">
                                    Platzhalter: <code>{JJJJ}</code> Jahr 4-stellig, <code>{JJ}</code> Jahr 2-stellig,
                                    <code>{MM}</code> Monat, <code>{TT}</code> Tag (jeweils vom Belegdatum) ·
                                    <code>{NNNN}</code> laufende Nummer (Anzahl der <code>N</code> = Anzahl Stellen, z.B. <code>{NNN}</code> = 3-stellig)
                                </div>
                            </div>
                            <div class="alert alert-secondary">
                                Vorschau der nächsten Nummer: <code id="nk_vorschau" class="fs-6"></code>
                            </div>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Vorsicht beim nachträglichen Ändern der "Nächsten Nummer": Wird sie auf einen bereits vergebenen Wert zurückgesetzt,
                                entsteht bei der nächsten Finalisierung eine doppelt vergebene Rechnungsnummer.
                            </div>
                            <button type="submit" name="save_nummernkreis" class="btn btn-success">Speichern</button>
                            <a href="?tab=nummernkreise" class="btn btn-secondary">Abbrechen</a>
                        </form>
                    </div>
                </div>
                <script>
                    function nkAktualisiereVorschau() {
                        const heute = new Date();
                        const jjjj = String(heute.getFullYear());
                        const jj = jjjj.slice(-2);
                        const mm = String(heute.getMonth() + 1).padStart(2, '0');
                        const tt = String(heute.getDate()).padStart(2, '0');
                        let format = document.getElementById('nk_format').value;
                        const nummer = parseInt(document.getElementById('nk_naechste_nummer').value || '1', 10);

                        let vorschau = format
                            .replaceAll('{JJJJ}', jjjj)
                            .replaceAll('{JJ}', jj)
                            .replaceAll('{MM}', mm)
                            .replaceAll('{TT}', tt);
                        vorschau = vorschau.replace(/\{(N+)\}/g, (m, ns) => String(nummer).padStart(ns.length, '0'));
                        document.getElementById('nk_vorschau').textContent = vorschau || '-';
                    }
                    document.getElementById('nk_format').addEventListener('input', nkAktualisiereVorschau);
                    document.getElementById('nk_naechste_nummer').addEventListener('input', nkAktualisiereVorschau);
                    nkAktualisiereVorschau();

                    // "Kunde" ist jahresunabhängig (siehe zieheKundennummer()) - Jahr-Feld beim
                    // Anlegen auf 0 fixieren und Standard-Format vorschlagen.
                    const nkStandardFormate = { rechnung: 'RE-{JJJJ}-{NNNN}', angebot: 'AN-{JJJJ}-{NNNN}', auftrag: 'AU-{JJJJ}-{NNNN}', kunde: 'K-{NNNN}' };
                    const nkSchluesselSelect = document.getElementById('nk_schluessel');
                    const nkJahrFeld = document.getElementById('nk_jahr');
                    const nkJahrHinweis = document.getElementById('nk_jahr_hinweis');
                    const nkFormatFeld = document.getElementById('nk_format');
                    function nkSchluesselGeaendert() {
                        if (!nkSchluesselSelect || !nkJahrFeld) return;
                        const istKunde = nkSchluesselSelect.value === 'kunde';
                        if (Object.values(nkStandardFormate).includes(nkFormatFeld.value)) {
                            nkFormatFeld.value = nkStandardFormate[nkSchluesselSelect.value] || nkFormatFeld.value;
                        }
                        if (istKunde) {
                            nkJahrFeld.dataset.vorherigerWert = nkJahrFeld.dataset.vorherigerWert || nkJahrFeld.value;
                            nkJahrFeld.value = '0';
                            nkJahrFeld.readOnly = true;
                        } else {
                            nkJahrFeld.readOnly = false;
                            if (nkJahrFeld.dataset.vorherigerWert) nkJahrFeld.value = nkJahrFeld.dataset.vorherigerWert;
                        }
                        if (nkJahrHinweis) nkJahrHinweis.style.display = istKunde ? '' : 'none';
                        nkAktualisiereVorschau();
                    }
                    nkSchluesselSelect?.addEventListener('change', nkSchluesselGeaendert);
                </script>
                <?php else: ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-123 me-2"></i>Nummernkreise (Verkauf-Modul)</span>
                        <a href="?tab=nummernkreise&action=new" class="btn btn-light btn-sm">+ Neu</a>
                    </div>
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Dokumenttyp</th><th class="text-center">Jahr</th><th>Format</th><th class="text-center">Nächste Nummer</th><th>Vorschau</th><th></th></tr></thead>
                        <tbody>
                        <?php if (empty($nummernkreise)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Noch keine Nummernkreise vorhanden</td></tr>
                        <?php else: foreach ($nummernkreise as $nk): ?>
                        <tr>
                            <td><?= $nummernkreisSchluesselLabels[$nk['schluessel']] ?? htmlspecialchars($nk['schluessel']) ?></td>
                            <td class="text-center"><?= $nk['schluessel'] === 'kunde' ? '<span class="text-muted">kein Jahresbezug</span>' : $nk['jahr'] ?></td>
                            <td><code><?= htmlspecialchars($nk['format']) ?></code></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $nk['naechste_nummer'] ?></span></td>
                            <td><code><?= htmlspecialchars(formatiereNummernkreisNummer($nk['format'], date('Y-m-d'), $nk['naechste_nummer'])) ?></code></td>
                            <td class="text-end">
                                <a href="?tab=nummernkreise&action=edit&id=<?= $nk['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Für jedes Jahr wird beim ersten Finalisieren eines Dokumenttyps automatisch ein neuer Nummernkreis
                    angelegt (Standard-Format <code>RE-{JJJJ}-{NNNN}</code>, Start bei 1), falls noch keiner existiert.
                    Hier kannst du Format und Startwert pro Jahr im Voraus selbst festlegen oder nachträglich korrigieren.
                </div>
                <?php endif; ?>

                <?php elseif ($tab === 'pdf_design'): ?>
                <!-- PDF-DESIGN (Angebot/Auftrag/Rechnung/Lieferschein: HTML/CSS-Vorlagen-Editor) -->
                <ul class="nav nav-pills mb-3">
                    <?php foreach ($pdfDesignTypen as $val => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $pdfDesignTyp === $val ? 'active' : '' ?>" href="?tab=pdf_design&typ=<?= $val ?>"><?= $label ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="row">
                    <div class="col-lg-8">
                        <form method="POST">
                            <input type="hidden" name="typ" value="<?= $pdfDesignTyp ?>">
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0">HTML/CSS-Vorlage</label>
                                <div>
                                    <button type="submit" name="save_pdf_vorlage" class="btn btn-success btn-sm">
                                        <i class="bi bi-check-lg me-1"></i>Speichern
                                    </button>
                                </div>
                            </div>
                            <textarea class="form-control font-monospace" id="vorlage_text" name="vorlage" rows="24" style="font-size: 0.85rem;" spellcheck="false"><?= htmlspecialchars($pdfVorlageInhalt) ?></textarea>
                        </form>

                        <div class="d-flex justify-content-between mt-2">
                            <form method="POST" onsubmit="return confirm('Vorlage wirklich auf den Standard zurücksetzen? Eigene Änderungen gehen verloren.')">
                                <input type="hidden" name="typ" value="<?= $pdfDesignTyp ?>">
                                <button type="submit" name="reset_pdf_vorlage" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Auf Standard zurücksetzen
                                </button>
                            </form>
                            <form method="POST" action="pdf_vorlage_preview.php?typ=<?= $pdfDesignTyp ?>" target="_blank" id="previewForm">
                                <input type="hidden" name="vorlage" id="preview_vorlage_hidden">
                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>Vorschau (aktueller Textinhalt, auch ungespeichert)
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header"><i class="bi bi-code-slash me-2"></i>Platzhalter</div>
                            <div class="card-body">
                                <p class="small text-muted">Einfache <code>{{platzhalter}}</code>-Ersetzung, keine Schleifen. Bilder nur als Base64-Data-URI einbettbar (keine externen URLs).</p>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr><td><code>{{firma_logo}}</code></td><td class="small">Firmenlogo (falls in Firma-Einstellungen hochgeladen)</td></tr>
                                        <tr><td><code>{{firma_name}}</code></td><td class="small">Firmenname</td></tr>
                                        <tr><td><code>{{firma_adresse}}</code></td><td class="small">Straße, PLZ Ort</td></tr>
                                        <tr><td><code>{{firma_uid}}</code></td><td class="small">UID-Nummer</td></tr>
                                        <tr><td><code>{{firma_iban}}</code>, <code>{{firma_bic}}</code>, <code>{{firma_bank}}</code></td><td class="small">Bankdaten</td></tr>
                                        <tr><td><code>{{firma_farbe1}}</code>, <code>{{firma_farbe2}}</code></td><td class="small">Hauptfarben des gewählten Firmenprofils (Hex, z.B. für <code>style="color: {{firma_farbe1}}"</code>)</td></tr>
                                        <tr><td><code>{{dokument_typ_label}}</code></td><td class="small">"Angebot" / "Auftragsbestätigung" / "Rechnung"</td></tr>
                                        <tr><td><code>{{nummer}}</code>, <code>{{status}}</code></td><td class="small">z.B. "RE-2026-0003", "entwurf"</td></tr>
                                        <tr><td><code>{{datum}}</code>, <code>{{leistungsdatum}}</code>, <code>{{gueltig_bis}}</code>, <code>{{faellig_am}}</code></td><td class="small">Formatierte Daten</td></tr>
                                        <tr><td><code>{{kunde_name}}</code>, <code>{{kunde_adresse}}</code>, <code>{{kunde_uid}}</code></td><td class="small">Kundendaten</td></tr>
                                        <tr><td><code>{{betreff}}</code>, <code>{{einleitungstext}}</code>, <code>{{schlusstext}}</code></td><td class="small">Freitexte des Dokuments</td></tr>
                                        <tr><td><code>{{positionen_tabelle}}</code></td><td class="small">
                                            Fertige Positions-Tabelle (Klasse <code>.positionen-tabelle</code>). Jede Spalte trägt zusätzlich
                                            eine eigene Klasse zum gezielten Einstellen der Breite: <code>.spalte-pos</code>, <code>.spalte-bezeichnung</code>,
                                            <code>.spalte-menge</code>, <code>.spalte-einzelpreis</code>, <code>.spalte-rabatt</code>, <code>.spalte-ust</code>,
                                            <code>.spalte-netto</code>, <code>.spalte-brutto</code>. Beispiel:
                                            <pre class="mt-1 mb-0 small">.positionen-tabelle { table-layout: fixed; }
.positionen-tabelle .spalte-bezeichnung { width: 45%; }
.positionen-tabelle .spalte-menge { width: 10%; }</pre>
                                            <code>table-layout: fixed</code> wird empfohlen, sonst richten sich die Breiten weiter nach dem Inhalt.
                                            <strong>Wichtig:</strong> <code>.positionen-tabelle</code> darf dabei KEIN eigenes <code>width: 100%</code>
                                            haben (steht evtl. schon in der Basis-Vorlage) - das überschreibt sonst die einzelnen Spaltenbreiten.
                                            Die Summe aller Spaltenbreiten sollte außerdem ca. 700px (bzw. 500pt) bei Standard-Seitenrändern nicht
                                            überschreiten, sonst läuft die Tabelle rechts über den Rand hinaus.
                                        </td></tr>
                                        <tr><td><code>{{netto_gesamt}}</code>, <code>{{ust_gesamt}}</code>, <code>{{brutto_gesamt}}</code></td><td class="small">Rohe Summenwerte</td></tr>
                                        <tr><td><code>{{summenblock}}</code></td><td class="small">Fertiger Summenblock (Kleinunternehmer-bewusst)</td></tr>
                                        <tr><td><code>{{zahlungshinweis}}</code></td><td class="small">Nur Rechnung, nur wenn IBAN gesetzt</td></tr>
                                        <tr><td><code>{{wasserzeichen}}</code></td><td class="small">Nur bei Status "entwurf"</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    document.getElementById('previewForm').addEventListener('submit', function() {
                        document.getElementById('preview_vorlage_hidden').value = document.getElementById('vorlage_text').value;
                    });
                </script>

                <?php elseif ($tab === 'email_vorlagen'): ?>
                <!-- E-MAIL-VORLAGEN (Betreff/Text pro Dokumenttyp) + Signaturen -->
                <ul class="nav nav-pills mb-3">
                    <?php foreach ($nummernkreisLabels as $val => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $emailDesignTyp === $val ? 'active' : '' ?>" href="?tab=email_vorlagen&typ=<?= $val ?>"><?= $label ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="row">
                    <div class="col-lg-7">
                        <form method="POST">
                            <input type="hidden" name="typ" value="<?= $emailDesignTyp ?>">
                            <div class="mb-3">
                                <label class="form-label">Betreff</label>
                                <input type="text" class="form-control" name="betreff" value="<?= htmlspecialchars($emailVorlageAktuell['betreff']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nachricht</label>
                                <textarea class="form-control" name="nachricht" rows="10"><?= htmlspecialchars($emailVorlageAktuell['nachricht']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Standard-Signatur für diesen Dokumenttyp</label>
                                <select class="form-select" name="standard_signatur_id">
                                    <option value="">-- Global-Standard verwenden --</option>
                                    <?php foreach ($emailSignaturenListe as $sig): ?>
                                    <option value="<?= $sig['id'] ?>" <?= ($emailVorlageAktuell['standard_signatur_id'] ?? '') == $sig['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sig['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Beim Versand kann die Signatur pro E-Mail trotzdem noch geändert werden.</div>
                            </div>
                            <button type="submit" name="save_email_vorlage" class="btn btn-success btn-sm">
                                <i class="bi bi-check-lg me-1"></i>Speichern
                            </button>
                        </form>
                        <form method="POST" class="mt-2" onsubmit="return confirm('Vorlage wirklich auf den Standard zurücksetzen? Eigene Änderungen gehen verloren.')">
                            <input type="hidden" name="typ" value="<?= $emailDesignTyp ?>">
                            <button type="submit" name="reset_email_vorlage" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Auf Standard zurücksetzen
                            </button>
                        </form>
                    </div>

                    <div class="col-lg-5">
                        <div class="card mb-3">
                            <div class="card-header"><i class="bi bi-code-slash me-2"></i>Platzhalter</div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td><code>{{typ_label}}</code></td><td class="small">"Angebot" / "Auftragsbestätigung" / "Rechnung"</td></tr>
                                        <tr><td><code>{{nummer}}</code></td><td class="small">Dokumentnummer</td></tr>
                                        <tr><td><code>{{datum}}</code>, <code>{{faellig_am}}</code>, <code>{{gueltig_bis}}</code></td><td class="small">Formatierte Daten</td></tr>
                                        <tr><td><code>{{betreff}}</code></td><td class="small">Betreff-Feld des Dokuments</td></tr>
                                        <tr><td><code>{{firma_name}}</code></td><td class="small">Firmenname</td></tr>
                                        <tr><td><code>{{kunde_name}}</code></td><td class="small">Anzeigename des Kunden</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-pen me-2"></i>Signaturen</span>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#signaturModal" onclick="neueSignatur()">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <?php if (empty($emailSignaturenListe)): ?>
                                        <tr><td class="text-center text-muted py-3">Keine Signaturen angelegt</td></tr>
                                        <?php else: foreach ($emailSignaturenListe as $sig): ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($sig['name']) ?>
                                                <?php if ($sig['ist_standard']): ?><span class="badge bg-secondary">Standard</span><?php endif; ?>
                                            </td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#signaturModal"
                                                        onclick='bearbeiteSignatur(<?= json_encode($sig, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Signatur wirklich löschen?');">
                                                    <input type="hidden" name="signatur_id" value="<?= $sig['id'] ?>">
                                                    <input type="hidden" name="typ" value="<?= $emailDesignTyp ?>">
                                                    <button type="submit" name="delete_email_signatur" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signatur Modal -->
                <div class="modal fade" id="signaturModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="signatur_id" id="sig_id" value="">
                                <input type="hidden" name="typ" value="<?= $emailDesignTyp ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="sig_modalTitle"><i class="bi bi-pen me-2"></i>Neue Signatur</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="signatur_name" id="sig_name" required placeholder="z.B. Standard, Verkauf, Buchhaltung">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Inhalt</label>
                                        <textarea class="form-control" name="signatur_inhalt" id="sig_inhalt" rows="4"></textarea>
                                        <div class="form-text">Platzhalter wie <code>{{firma_name}}</code> werden beim Versand ersetzt.</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="signatur_ist_standard" id="sig_ist_standard">
                                        <label class="form-check-label" for="sig_ist_standard">Als globalen Standard verwenden</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                    <button type="submit" name="save_email_signatur" class="btn btn-primary">Speichern</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                    function neueSignatur() {
                        document.getElementById('sig_modalTitle').innerHTML = '<i class="bi bi-pen me-2"></i>Neue Signatur';
                        document.getElementById('sig_id').value = '';
                        document.getElementById('sig_name').value = '';
                        document.getElementById('sig_inhalt').value = '';
                        document.getElementById('sig_ist_standard').checked = false;
                    }
                    function bearbeiteSignatur(sig) {
                        document.getElementById('sig_modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Signatur bearbeiten';
                        document.getElementById('sig_id').value = sig.id;
                        document.getElementById('sig_name').value = sig.name || '';
                        document.getElementById('sig_inhalt').value = sig.inhalt || '';
                        document.getElementById('sig_ist_standard').checked = sig.ist_standard == 1;
                    }
                </script>

                <?php elseif ($tab === 'hilfe'): ?>
                <!-- KENNZAHLEN-REFERENZ -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header"><i class="bi bi-file-text me-2"></i>U30 Kennzahlen 2025</div>
                            <div class="card-body">
                                <h6>Umsatzsteuer</h6>
                                <table class="table table-sm">
                                    <tr><td>Gesamtbetrag</td><td><code>000</code></td></tr>
                                    <tr><td>20% Bemessung</td><td><code>022</code></td></tr>
                                    <tr><td>10% Bemessung</td><td><code>029</code></td></tr>
                                    <tr><td>13% Bemessung</td><td><code>006</code></td></tr>
                                    <tr><td>ig Lieferungen</td><td><code>017</code></td></tr>
                                    <tr><td>ig Erwerbe</td><td><code>070</code></td></tr>
                                </table>
                                <h6 class="mt-3">Vorsteuer</h6>
                                <table class="table table-sm">
                                    <tr><td>Vorsteuer gesamt</td><td><code>060</code></td></tr>
                                    <tr><td>VSt ig Erwerb</td><td><code>065</code></td></tr>
                                    <tr><td>Reverse Charge</td><td><code>066</code></td></tr>
                                    <tr><td><strong>Zahllast/Gutschrift</strong></td><td><code>095</code></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header"><i class="bi bi-file-text me-2"></i>E1a Kennzahlen 2024</div>
                            <div class="card-body">
                                <h6>Einnahmen</h6>
                                <table class="table table-sm">
                                    <tr><td>Erlöse Waren</td><td><code>9040</code></td></tr>
                                    <tr><td>Erlöse Dienstleist.</td><td><code>9050</code></td></tr>
                                </table>
                                <h6 class="mt-3">Ausgaben</h6>
                                <table class="table table-sm">
                                    <tr><td>Wareneinkauf</td><td><code>9100</code></td></tr>
                                    <tr><td>Fremdleistungen</td><td><code>9110</code></td></tr>
                                    <tr><td>Personalaufwand</td><td><code>9120</code></td></tr>
                                    <tr class="table-primary"><td><strong>AfA normal</strong></td><td><code>9130</code></td></tr>
                                    <tr class="table-primary"><td><strong>AfA degressiv</strong></td><td><code>9134</code></td></tr>
                                    <tr class="table-primary"><td><strong>AfA Gebäude</strong></td><td><code>9135</code></td></tr>
                                    <tr><td>Betriebsräume</td><td><code>9140</code></td></tr>
                                    <tr><td>Instandhaltung</td><td><code>9150</code></td></tr>
                                    <tr><td>SVS Beiträge</td><td><code>9225</code></td></tr>
                                    <tr><td>Übrige</td><td><code>9230</code></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif ($tab === 'wartung'): ?>
                <!-- WARTUNG -->
                <div class="card mb-4 border-primary">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-cloud-download me-2"></i>Update
                    </div>
                    <div class="card-body">
                        <?php if ($gitInfo): ?>
                            <p class="mb-2">
                                Aktueller Branch: <code><?= htmlspecialchars($gitInfo['branch']) ?></code>
                                &middot; Letzter Commit: <code><?= htmlspecialchars($gitInfo['commit']) ?></code>
                            </p>
                            <p class="text-muted">
                                Holt den neuesten Stand von GitHub (<code>git pull</code>) für den aktuell ausgecheckten Branch.
                                Datenbank-Migrationen laufen danach automatisch beim nächsten Seitenaufruf - kein weiterer Schritt nötig.
                            </p>
                            <?php if (!isAdmin()): ?>
                                <div class="alert alert-secondary mb-0">Nur Administratoren können ein Update auslösen.</div>
                            <?php else: ?>
                                <form method="POST" onsubmit="return confirm('Jetzt den neuesten Stand von GitHub laden?')">
                                    <button type="submit" name="git_pull_deploy" class="btn btn-primary">
                                        <i class="bi bi-arrow-repeat me-1"></i>Jetzt aktualisieren
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if ($deployAusgabe !== null): ?>
                                <hr>
                                <p class="mb-1"><strong>Ausgabe:</strong></p>
                                <pre class="bg-light p-2 small" style="white-space: pre-wrap;"><?= htmlspecialchars($deployAusgabe) ?></pre>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                Kein Git-Repository unter <code><?= htmlspecialchars(__DIR__) ?></code> gefunden (oder <code>shell_exec</code> ist deaktiviert) -
                                einmalige Einrichtung auf diesem Server nötig, bevor der Update-Button funktioniert.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <i class="bi bi-plus-circle me-2"></i>Beispieldaten erstellen
                            </div>
                            <div class="card-body">
                                <p>Erstellt realistische Testdaten für die Demonstration:</p>
                                <ul>
                                    <li>12 Einnahmen für 2025</li>
                                    <li>12 Ausgaben für 2025</li>
                                    <li>2 Einnahmen für 2026</li>
                                    <li>2 Ausgaben für 2026</li>
                                    <li>1 Anlagegut (Laptop)</li>
                                </ul>
                                <form method="POST" onsubmit="return confirm('Beispieldaten wirklich erstellen?')">
                                    <button type="submit" name="create_beispieldaten" class="btn btn-success">
                                        <i class="bi bi-plus-lg me-1"></i>Beispieldaten erstellen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4 border-danger">
                            <div class="card-header bg-danger text-white">
                                <i class="bi bi-exclamation-triangle me-2"></i>Alle Daten löschen
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <strong>Achtung!</strong> Diese Aktion löscht unwiderruflich:
                                    <ul class="mb-0 mt-2">
                                        <li>Alle Rechnungen (Einnahmen/Ausgaben)</li>
                                        <li>Alle Anlagegüter und AfA-Buchungen</li>
                                        <li>Alle E1a und U30 Berechnungen</li>
                                        <li>Das Änderungsprotokoll</li>
                                    </ul>
                                </div>
                                <p><strong>Nicht gelöscht:</strong> Kategorien, USt-Sätze, Firmendaten, Benutzer</p>
                                <form method="POST" onsubmit="return confirm('ACHTUNG: Alle Buchungsdaten werden unwiderruflich gelöscht! Fortfahren?')">
                                    <div class="mb-3">
                                        <label class="form-label">Zur Bestätigung "LÖSCHEN" eingeben:</label>
                                        <input type="text" class="form-control" name="confirm_text" placeholder="LÖSCHEN" required autocomplete="off">
                                    </div>
                                    <button type="submit" name="reset_daten" class="btn btn-danger">
                                        <i class="bi bi-trash me-1"></i>Alle Daten löschen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history me-2"></i>Automatische wiederkehrende Rechnungen
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Der Server prüft im Hintergrund alle 15 Minuten, ob wiederkehrende Rechnungen fällig sind.
                            Hier stellst du ein, <strong>ob</strong> und zu welcher <strong>Uhrzeit</strong> das tatsächlich passieren soll -
                            eine Änderung wird sofort wirksam, ohne dass am Server etwas eingerichtet werden muss.
                        </p>
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-auto">
                                <div class="form-check form-switch mt-4">
                                    <input type="checkbox" class="form-check-input" role="switch" name="wiederkehrend_aktiv" id="wk_aktiv" <?= !empty($automatisierung['wiederkehrend_aktiv']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="wk_aktiv">Aktiv</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <label class="form-label" for="wk_uhrzeit">Uhrzeit</label>
                                <input type="time" class="form-control" name="wiederkehrend_uhrzeit" id="wk_uhrzeit" value="<?= htmlspecialchars(substr($automatisierung['wiederkehrend_uhrzeit'] ?? '03:00:00', 0, 5)) ?>">
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="save_automatisierung_einstellungen" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Speichern
                                </button>
                            </div>
                            <div class="col-auto">
                                <span class="text-muted small">
                                    Zuletzt ausgeführt:
                                    <?= !empty($automatisierung['wiederkehrend_zuletzt_ausgefuehrt']) ? formatDatum($automatisierung['wiederkehrend_zuletzt_ausgefuehrt']) : 'noch nie' ?>
                                </span>
                            </div>
                        </form>
                        <details class="mt-3">
                            <summary class="text-muted" style="cursor: pointer;">Einmalige Server-Einrichtung (nur beim ersten Mal nötig)</summary>
                            <p class="mt-2 mb-1">Per SSH auf dem Server, einmalig <code>crontab -e</code> und diese Zeile einfügen:</p>
                            <pre class="bg-light p-2 small">*/15 * * * * php /pfad/zu/ekassa360/cron/generate_wiederkehrende_rechnungen.php >> /var/log/ekassa360_cron.log 2>&amp;1</pre>
                            <p class="mb-0 text-muted small">Danach nie wieder anfassen - Aktiv/Uhrzeit oben steuern von da an alles.</p>
                        </details>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-printer me-2"></i>Bondrucker (Netzwerk)
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Für den Bon-Druckbutton bei Verkaufsrechnungen. Der Drucker muss per Netzwerk (IP-Adresse)
                            erreichbar sein und ESC/POS unterstützen (Standard bei praktisch allen Netzwerk-Bondruckern, Port meist 9100).
                        </p>
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label" for="bd_ip">IP-Adresse</label>
                                <input type="text" class="form-control" name="bondrucker_ip" id="bd_ip" placeholder="z.B. 192.168.1.50" value="<?= htmlspecialchars($bondrucker['ip_adresse'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="bd_port">Port</label>
                                <input type="number" class="form-control" name="bondrucker_port" id="bd_port" value="<?= (int)($bondrucker['port'] ?? 9100) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="bd_breite">Papierbreite</label>
                                <select class="form-select" name="bondrucker_papierbreite" id="bd_breite">
                                    <option value="80mm" <?= ($bondrucker['papierbreite'] ?? '80mm') === '80mm' ? 'selected' : '' ?>>80mm (48 Zeichen)</option>
                                    <option value="58mm" <?= ($bondrucker['papierbreite'] ?? '') === '58mm' ? 'selected' : '' ?>>58mm (32 Zeichen)</option>
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="save_bondrucker_einstellungen" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Speichern
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="bondrucker_testdruck" class="btn btn-outline-secondary">
                                    <i class="bi bi-printer me-1"></i>Testdruck
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-archive me-2"></i>paperless-ngx
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Automatisches Archivieren von Angeboten/Aufträgen/Rechnungen in einer paperless-ngx-Instanz beim Finalisieren.
                            Wird nicht jeder brauchen - über "Aktiv" komplett abschaltbar, ohne die übrigen Werte zu verlieren.
                        </p>
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <div class="form-check form-switch mt-4">
                                    <input type="checkbox" class="form-check-input" role="switch" name="paperless_aktiv" id="pl_aktiv" <?= !empty($paperlessEinstellungen['aktiv']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pl_aktiv">Aktiv</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pl_base_url">Basis-URL</label>
                                <input type="text" class="form-control" name="paperless_base_url" id="pl_base_url" placeholder="https://paperless.example.internal" value="<?= htmlspecialchars($paperlessEinstellungen['base_url'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pl_api_token">API-Token</label>
                                <input type="password" class="form-control" name="paperless_api_token" id="pl_api_token" autocomplete="off" placeholder="<?= !empty($paperlessEinstellungen['api_token']) ? 'Unverändert lassen, um das bestehende Token zu behalten' : 'z.B. aus paperless-ngx -> Mein Profil -> API-Token' ?>">
                            </div>
                            <div class="col-md-2">
                                <div class="form-check form-switch mt-4">
                                    <input type="checkbox" class="form-check-input" role="switch" name="paperless_verify_ssl" id="pl_verify_ssl" <?= !empty($paperlessEinstellungen['verify_ssl']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pl_verify_ssl">SSL prüfen</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="save_paperless_einstellungen" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Speichern
                                </button>
                            </div>
                            <div class="col-auto">
                                <button type="submit" name="paperless_verbindung_testen" class="btn btn-outline-secondary">
                                    <i class="bi bi-plug me-1"></i>Verbindung testen
                                </button>
                            </div>
                        </form>
                        <div class="form-text mt-2">
                            "API-Token" leer lassen, um beim Speichern/Testen das bereits hinterlegte Token weiter zu verwenden -
                            wird aus Sicherheitsgründen nie im Feld angezeigt. "SSL prüfen" nur bei selbstsigniertem Zertifikat deaktivieren.
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-tags me-2"></i>Paperless-Tags
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Tags, die beim automatischen Hochladen nach paperless-ngx pro Dokumenttyp vergeben werden.
                            Mehrere Tags durch Komma trennen. Leer lassen für keine Tags. Wirkt erst bei künftigen Uploads
                            (bereits archivierte Dokumente werden nicht nachträglich geändert).
                        </p>
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label" for="pt_angebot">Angebot</label>
                                <input type="text" class="form-control" name="paperless_tags_angebot" id="pt_angebot" placeholder="z.B. Angebot, MeineFirma" value="<?= htmlspecialchars($paperlessTags['angebot']['tags'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pt_auftrag">Auftrag</label>
                                <input type="text" class="form-control" name="paperless_tags_auftrag" id="pt_auftrag" placeholder="z.B. Auftrag, MeineFirma" value="<?= htmlspecialchars($paperlessTags['auftrag']['tags'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pt_rechnung">Rechnung</label>
                                <input type="text" class="form-control" name="paperless_tags_rechnung" id="pt_rechnung" placeholder="z.B. Rechnung, MeineFirma" value="<?= htmlspecialchars($paperlessTags['rechnung']['tags'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" name="save_paperless_tags" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>System-Informationen
                    </div>
                    <div class="card-body">
                        <?php
                        $rechnungenCount = $db->query("SELECT COUNT(*) FROM rechnungen")->fetchColumn();
                        $anlagenCount = $db->query("SELECT COUNT(*) FROM anlagegueter")->fetchColumn();
                        $e1aCount = $db->query("SELECT COUNT(*) FROM einkommensteuer")->fetchColumn();
                        $u30Count = $db->query("SELECT COUNT(*) FROM ust_voranmeldungen")->fetchColumn();
                        ?>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <!-- <tr><td>PHP Version</td><td><code><?= phpversion() ?></code></td></tr> -->
                                    <tr><td>PHP Version</td><td><img src="https://img.shields.io/badge/PHP-<?= phpversion() ?>-blue" alt=""></td></tr>
                                    <!-- <tr><td>MySQL Version</td><td><code><?= $db->query("SELECT VERSION()")->fetchColumn() ?></code></td></tr> -->
                                    <tr><td>MySQL Version</td><td><img src="https://img.shields.io/badge/MySQL-<?= $db->query("SELECT VERSION()")->fetchColumn() ?>-777BB4" alt=""></td></tr>
                                    <tr><td>EKassa360 Version</td><td><img src="https://img.shields.io/badge/Version-v0.1.7-lightgreen" alt=""></td></tr>
                                    <tr><td>EKassa360 auf Github</td><td><img src="https://img.shields.io/github/v/release/Hanner72/ekassa360?include_prereleases" alt=""></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><td>Rechnungen</td><td><span class="badge bg-primary"><?= $rechnungenCount ?></span></td></tr>
                                    <tr><td>Anlagegüter</td><td><span class="badge bg-primary"><?= $anlagenCount ?></span></td></tr>
                                    <tr><td>E1a Berechnungen</td><td><span class="badge bg-info"><?= $e1aCount ?></span></td></tr>
                                    <tr><td>U30 Meldungen</td><td><span class="badge bg-info"><?= $u30Count ?></span></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
