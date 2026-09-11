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
}

// Daten laden
$firma = $db->query("SELECT * FROM firma LIMIT 1")->fetch();
$ustSaetze = $db->query("SELECT * FROM ust_saetze ORDER BY satz DESC")->fetchAll();
$kategorien = $db->query("SELECT * FROM kategorien ORDER BY typ, name")->fetchAll();

// Einzeldaten für Edit
$ustSatz = null;
$kategorie = null;
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
$pdfDesignTypen = $nummernkreisLabels + ['lieferschein' => 'Lieferschein'];

$pdfDesignTyp = $_GET['typ'] ?? 'rechnung';
if (!array_key_exists($pdfDesignTyp, $pdfDesignTypen)) {
    $pdfDesignTyp = 'rechnung';
}
$pdfVorlageInhalt = $tab === 'pdf_design' ? getPdfVorlage($pdfDesignTyp) : '';

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
                                    <input type="text" class="form-control" value="<?= $nummernkreisLabels[$nummernkreis['schluessel']] ?? $nummernkreis['schluessel'] ?>" disabled>
                                    <?php else: ?>
                                    <select class="form-select" name="schluessel" required>
                                        <?php foreach ($nummernkreisLabels as $val => $label): ?>
                                        <option value="<?= $val ?>"><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Jahr *</label>
                                    <?php if ($action === 'edit'): ?>
                                    <input type="text" class="form-control" value="<?= $nummernkreis['jahr'] ?>" disabled>
                                    <?php else: ?>
                                    <input type="number" class="form-control" name="jahr" value="<?= date('Y') ?>" required>
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
                            <td><?= $nummernkreisLabels[$nk['schluessel']] ?? htmlspecialchars($nk['schluessel']) ?></td>
                            <td class="text-center"><?= $nk['jahr'] ?></td>
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
                                        <tr><td><code>{{dokument_typ_label}}</code></td><td class="small">"Angebot" / "Auftragsbestätigung" / "Rechnung"</td></tr>
                                        <tr><td><code>{{nummer}}</code>, <code>{{status}}</code></td><td class="small">z.B. "RE-2026-0003", "entwurf"</td></tr>
                                        <tr><td><code>{{datum}}</code>, <code>{{leistungsdatum}}</code>, <code>{{gueltig_bis}}</code>, <code>{{faellig_am}}</code></td><td class="small">Formatierte Daten</td></tr>
                                        <tr><td><code>{{kunde_name}}</code>, <code>{{kunde_adresse}}</code>, <code>{{kunde_uid}}</code></td><td class="small">Kundendaten</td></tr>
                                        <tr><td><code>{{betreff}}</code>, <code>{{einleitungstext}}</code>, <code>{{schlusstext}}</code></td><td class="small">Freitexte des Dokuments</td></tr>
                                        <tr><td><code>{{positionen_tabelle}}</code></td><td class="small">Fertige Positions-Tabelle (Klasse <code>.positionen-tabelle</code>)</td></tr>
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
