<?php
/**
 * EKassa360 - Einstellungen
 * Firma, USt-Sätze, Kategorien und Kennzahlen
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireLogin();

$tab = $_GET['tab'] ?? 'firma';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

$db = db();

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Firmendaten speichern
    if (isset($_POST['save_firma'])) {
        $stmt = $db->query("SELECT id FROM firma LIMIT 1");
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $db->prepare("UPDATE firma SET 
                name = ?, strasse = ?, plz = ?, ort = ?, telefon = ?, email = ?, website = ?,
                uid_nummer = ?, steuernummer = ?, finanzamt = ?, iban = ?, bic = ?, bank = ?,
                geschaeftsjahr_beginn = ?, ust_periode = ?, kleinunternehmer = ?
                WHERE id = ?");
            $stmt->execute([
                $_POST['name'], $_POST['strasse'], $_POST['plz'], $_POST['ort'],
                $_POST['telefon'], $_POST['email'], $_POST['website'],
                $_POST['uid_nummer'], $_POST['steuernummer'], $_POST['finanzamt'],
                $_POST['iban'], $_POST['bic'], $_POST['bank'],
                $_POST['geschaeftsjahr_beginn'], $_POST['ust_periode'], 
                isset($_POST['kleinunternehmer']) ? 1 : 0,
                $existing['id']
            ]);
        } else {
            $stmt = $db->prepare("INSERT INTO firma 
                (name, strasse, plz, ort, telefon, email, website, uid_nummer, steuernummer, 
                 finanzamt, iban, bic, bank, geschaeftsjahr_beginn, ust_periode, kleinunternehmer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'], $_POST['strasse'], $_POST['plz'], $_POST['ort'],
                $_POST['telefon'], $_POST['email'], $_POST['website'],
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
        $stmtInsert = $db->prepare("INSERT INTO rechnungen (buchungsnummer, datum, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, typ, kategorie_id, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?, 'einnahme', ?, ?)");
        foreach ($einnahmen2025 as $e) {
            $ustId = $e[3] ?? $ust20Id;
            $ustSatz = 20;
            foreach ($ustSaetze as $u) { if ($u['id'] == $ustId) { $ustSatz = $u['satz']; break; } }
            $ustBetrag = $e[2] * ($ustSatz / 100);
            $katId = !empty($einnahmeKatIds) ? $einnahmeKatIds[array_rand($einnahmeKatIds)] : null;
            $stmtInsert->execute([$buchNr2025++, $e[0], $e[1], $e[2], $ustId, $ustBetrag, $e[2] + $ustBetrag, $katId, $_SESSION['user_id'] ?? 1]);
        }
        
        // Ausgaben 2025 einfügen
        $stmtInsert = $db->prepare("INSERT INTO rechnungen (buchungsnummer, datum, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, typ, kategorie_id, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?, 'ausgabe', ?, ?)");
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
        
        $stmtInsert = $db->prepare("INSERT INTO rechnungen (buchungsnummer, datum, beschreibung, netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, typ, kategorie_id, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                <form method="POST">
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
