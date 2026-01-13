<?php
/**
 * Rechnungsverwaltung
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$typ = $_GET['typ'] ?? null;

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save'])) {
        $data = [
            'id' => $_POST['id'] ?? null,
            'typ' => $_POST['typ'],
            'rechnungsnummer' => $_POST['rechnungsnummer'],
            'buchungsnummer' => $_POST['buchungsnummer'] ?: null,
            'datum' => $_POST['datum'],
            'faellig_am' => $_POST['faellig_am'],
            'kunde_lieferant' => $_POST['kunde_lieferant'],
            'beschreibung' => $_POST['beschreibung'],
            'netto_betrag' => floatval(str_replace(',', '.', $_POST['netto_betrag'])),
            'ust_satz_id' => $_POST['ust_satz_id'] ?: null,
            'kategorie_id' => $_POST['kategorie_id'] ?: null,
            'bezahlt' => isset($_POST['bezahlt']) ? 1 : 0,
            'bezahlt_am' => $_POST['bezahlt_am'] ?: null,
            'notizen' => $_POST['notizen'],
            // EU-Buchungsfelder
            'buchungsart' => $_POST['buchungsart'] ?? 'inland',
            'lieferant_land' => $_POST['lieferant_land'] ?: null,
            'lieferant_uid' => $_POST['lieferant_uid'] ?: null,
            'ausland_ust_satz' => $_POST['ausland_ust_satz'] ?: null,
            'ausland_ust_betrag' => $_POST['ausland_ust_betrag'] ?: null
        ];
        
        if (saveRechnung($data)) {
            setFlashMessage('success', 'Rechnung erfolgreich gespeichert.');
            // Zurück zur gefilterten Liste mit korrekten Parameter-Namen
            $redirectParams = [];
            if (!empty($_SESSION['rechnungen_filter'])) {
                foreach ($_SESSION['rechnungen_filter'] as $key => $value) {
                    if ($value !== '' && $value !== null) {
                        $redirectParams[$key] = $value;
                    }
                }
            }
            header('Location: rechnungen.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : ''));
            exit;
        } else {
            setFlashMessage('danger', 'Fehler beim Speichern.');
        }
    }
    
    if (isset($_POST['delete'])) {
        if (deleteRechnung($_POST['id'])) {
            setFlashMessage('success', 'Rechnung gelöscht.');
        } else {
            setFlashMessage('danger', 'Fehler beim Löschen.');
        }
        // Zurück zur gefilterten Liste mit korrekten Parameter-Namen
        $redirectParams = [];
        if (!empty($_SESSION['rechnungen_filter'])) {
            foreach ($_SESSION['rechnungen_filter'] as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $redirectParams[$key] = $value;
                }
            }
        }
        header('Location: rechnungen.php' . (!empty($redirectParams) ? '?' . http_build_query($redirectParams) : ''));
        exit;
    }
}

// Daten laden
$kategorien = getKategorien();
$ustSaetze = getUstSaetze();
$kundenLieferanten = getKundenLieferanten();
$rechnung = $id ? getRechnung($id) : null;

// Filter-Logik: Session-basiert für Persistenz
// Filter zurücksetzen wenn explizit angefordert
if (isset($_GET['reset_filter'])) {
    unset($_SESSION['rechnungen_filter']);
    header('Location: rechnungen.php');
    exit;
}

// Prüfen ob neue Filter gesetzt wurden (Formular abgeschickt oder URL-Parameter)
$hasFilterParams = isset($_GET['typ']) || isset($_GET['jahr']) || isset($_GET['monat']) || 
                   isset($_GET['kategorie_id']) || isset($_GET['bezahlt']) || isset($_GET['suche']);

if ($hasFilterParams) {
    // Neue Filter aus GET übernehmen
    $filters = [
        'typ' => $_GET['typ'] ?? '',
        'jahr' => $_GET['jahr'] ?? '',
        'monat' => $_GET['monat'] ?? '',
        'kategorie_id' => $_GET['kategorie_id'] ?? '',
        'bezahlt' => $_GET['bezahlt'] ?? '',
        'suche' => trim($_GET['suche'] ?? '')
    ];
    // In Session speichern
    $_SESSION['rechnungen_filter'] = $filters;
} elseif (isset($_SESSION['rechnungen_filter']) && $action === 'list') {
    // Filter aus Session laden (nur bei Liste)
    $filters = $_SESSION['rechnungen_filter'];
} else {
    // Standard-Filter
    $filters = [
        'typ' => '',
        'jahr' => date('Y'),
        'monat' => '',
        'kategorie_id' => '',
        'bezahlt' => '',
        'suche' => ''
    ];
    // Standard auch in Session speichern
    if ($action === 'list') {
        $_SESSION['rechnungen_filter'] = $filters;
    }
}

$rechnungen = getRechnungen($filters);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechnungen - Buchhaltung</title>
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
                <?php if ($flash = getFlashMessage()): ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show mt-3" role="alert">
                    <?= $flash['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($action === 'new' || $action === 'edit'): ?>
                <!-- Formular -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-<?= $action === 'new' ? 'plus-circle' : 'pencil' ?> me-2"></i>
                        <?= $action === 'new' ? 'Neue Rechnung' : 'Rechnung bearbeiten' ?>
                    </h1>
                    <a href="rechnungen.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Zurück
                    </a>
                </div>

                <div class="form-container">
                    <form method="POST" class="card">
                        <div class="card-body">
                            <input type="hidden" name="id" value="<?= $rechnung['id'] ?? '' ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label required">Typ</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="typ" id="typ_einnahme" value="einnahme" 
                                               <?= ($rechnung['typ'] ?? $typ) === 'einnahme' ? 'checked' : '' ?> required>
                                        <label class="btn btn-outline-success" for="typ_einnahme">
                                            <i class="bi bi-arrow-down-circle me-1"></i>Einnahme
                                        </label>
                                        <input type="radio" class="btn-check" name="typ" id="typ_ausgabe" value="ausgabe"
                                               <?= ($rechnung['typ'] ?? $typ) === 'ausgabe' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger" for="typ_ausgabe">
                                            <i class="bi bi-arrow-up-circle me-1"></i>Ausgabe
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Buchungsnr.</label>
                                    <input type="number" class="form-control" name="buchungsnummer" 
                                           value="<?= htmlspecialchars($rechnung['buchungsnummer'] ?? '') ?>"
                                           placeholder="<?= $action === 'new' ? 'Auto' : '' ?>">
                                    <small class="text-muted">Leer = automatisch</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Rechnungsnr.</label>
                                    <input type="text" class="form-control" name="rechnungsnummer" 
                                           value="<?= htmlspecialchars($rechnung['rechnungsnummer'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Buchungsart (nur bei Ausgaben relevant) -->
                            <div class="row mb-3" id="buchungsart_row">
                                <div class="col-md-12">
                                    <label class="form-label">Buchungsart</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="buchungsart" id="ba_inland" value="inland" 
                                               <?= ($rechnung['buchungsart'] ?? 'inland') === 'inland' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-secondary" for="ba_inland">
                                            <i class="bi bi-house me-1"></i>Inland (AT)
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="buchungsart" id="ba_eu_ige" value="eu_ige"
                                               <?= ($rechnung['buchungsart'] ?? '') === 'eu_ige' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary" for="ba_eu_ige" title="Rechnung ohne USt von EU-Firma mit UID (Reverse Charge)">
                                            <i class="bi bi-globe-europe-africa me-1"></i>EU igE
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="buchungsart" id="ba_eu_b2c" value="eu_b2c"
                                               <?= ($rechnung['buchungsart'] ?? '') === 'eu_b2c' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-warning" for="ba_eu_b2c" title="Rechnung MIT ausländischer USt (z.B. 19% DE) - kein Vorsteuerabzug!">
                                            <i class="bi bi-cart me-1"></i>EU B2C
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="buchungsart" id="ba_drittland" value="drittland"
                                               <?= ($rechnung['buchungsart'] ?? '') === 'drittland' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-info" for="ba_drittland" title="Import aus Nicht-EU-Land">
                                            <i class="bi bi-airplane me-1"></i>Drittland
                                        </label>
                                    </div>
                                    <small class="text-muted" id="buchungsart_info">Inland: Normale Buchung mit österreichischer USt</small>
                                </div>
                            </div>
                            
                            <!-- EU-Felder (nur bei EU-Buchungen sichtbar) -->
                            <div class="row mb-3" id="eu_felder" style="display: none;">
                                <div class="col-md-4">
                                    <label class="form-label">Land des Lieferanten</label>
                                    <select class="form-select" name="lieferant_land" id="lieferant_land">
                                        <option value="">-- Wählen --</option>
                                        <option value="DE" <?= ($rechnung['lieferant_land'] ?? '') === 'DE' ? 'selected' : '' ?>>Deutschland</option>
                                        <option value="IT" <?= ($rechnung['lieferant_land'] ?? '') === 'IT' ? 'selected' : '' ?>>Italien</option>
                                        <option value="FR" <?= ($rechnung['lieferant_land'] ?? '') === 'FR' ? 'selected' : '' ?>>Frankreich</option>
                                        <option value="NL" <?= ($rechnung['lieferant_land'] ?? '') === 'NL' ? 'selected' : '' ?>>Niederlande</option>
                                        <option value="BE" <?= ($rechnung['lieferant_land'] ?? '') === 'BE' ? 'selected' : '' ?>>Belgien</option>
                                        <option value="ES" <?= ($rechnung['lieferant_land'] ?? '') === 'ES' ? 'selected' : '' ?>>Spanien</option>
                                        <option value="PL" <?= ($rechnung['lieferant_land'] ?? '') === 'PL' ? 'selected' : '' ?>>Polen</option>
                                        <option value="CZ" <?= ($rechnung['lieferant_land'] ?? '') === 'CZ' ? 'selected' : '' ?>>Tschechien</option>
                                        <option value="HU" <?= ($rechnung['lieferant_land'] ?? '') === 'HU' ? 'selected' : '' ?>>Ungarn</option>
                                        <option value="SK" <?= ($rechnung['lieferant_land'] ?? '') === 'SK' ? 'selected' : '' ?>>Slowakei</option>
                                        <option value="SI" <?= ($rechnung['lieferant_land'] ?? '') === 'SI' ? 'selected' : '' ?>>Slowenien</option>
                                        <option value="OTHER_EU" <?= ($rechnung['lieferant_land'] ?? '') === 'OTHER_EU' ? 'selected' : '' ?>>Anderes EU-Land</option>
                                        <option value="CH" <?= ($rechnung['lieferant_land'] ?? '') === 'CH' ? 'selected' : '' ?>>Schweiz</option>
                                        <option value="US" <?= ($rechnung['lieferant_land'] ?? '') === 'US' ? 'selected' : '' ?>>USA</option>
                                        <option value="CN" <?= ($rechnung['lieferant_land'] ?? '') === 'CN' ? 'selected' : '' ?>>China</option>
                                        <option value="OTHER" <?= ($rechnung['lieferant_land'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Anderes Drittland</option>
                                    </select>
                                </div>
                                <div class="col-md-4" id="uid_feld">
                                    <label class="form-label">UID-Nr. Lieferant</label>
                                    <input type="text" class="form-control" name="lieferant_uid" 
                                           value="<?= htmlspecialchars($rechnung['lieferant_uid'] ?? '') ?>"
                                           placeholder="z.B. DE123456789">
                                </div>
                                <div class="col-md-4" id="ausland_ust_feld" style="display: none;">
                                    <label class="form-label">Ausländische USt</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="ausland_ust_satz" id="ausland_ust_satz"
                                               value="<?= htmlspecialchars($rechnung['ausland_ust_satz'] ?? '19') ?>"
                                               placeholder="19" style="max-width: 80px;">
                                        <span class="input-group-text">%</span>
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control" name="ausland_ust_betrag" id="ausland_ust_betrag"
                                               value="<?= isset($rechnung['ausland_ust_betrag']) ? number_format($rechnung['ausland_ust_betrag'], 2, ',', '') : '' ?>"
                                               placeholder="0,00">
                                    </div>
                                    <small class="text-danger">Nicht als Vorsteuer abzugsfähig!</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Datum</label>
                                    <input type="date" class="form-control" name="datum" 
                                           value="<?= $rechnung['datum'] ?? date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Fällig am</label>
                                    <input type="date" class="form-control" name="faellig_am" 
                                           value="<?= $rechnung['faellig_am'] ?? '' ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Kunde/Lieferant</label>
                                <input type="text" class="form-control" name="kunde_lieferant" list="kundenLieferantenList"
                                       value="<?= htmlspecialchars($rechnung['kunde_lieferant'] ?? '') ?>" required
                                       autocomplete="off">
                                <datalist id="kundenLieferantenList">
                                    <?php foreach ($kundenLieferanten as $kl): ?>
                                    <option value="<?= htmlspecialchars($kl) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung</label>
                                <textarea class="form-control" name="beschreibung" rows="2"><?= htmlspecialchars($rechnung['beschreibung'] ?? '') ?></textarea>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label required">Netto-Betrag</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control" name="netto_betrag" id="netto_betrag"
                                               value="<?= isset($rechnung['netto_betrag']) ? number_format($rechnung['netto_betrag'], 2, ',', '') : '' ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">USt-Satz</label>
                                    <select class="form-select" name="ust_satz_id" id="ust_satz_id">
                                        <option value="">-- Kein USt --</option>
                                        <?php foreach ($ustSaetze as $ust): ?>
                                        <option value="<?= $ust['id'] ?>" data-prozent="<?= $ust['satz'] ?>"
                                                <?= ($rechnung['ust_satz_id'] ?? '') == $ust['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ust['bezeichnung']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Brutto-Betrag</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control bg-light" id="brutto_betrag" readonly
                                               value="<?= isset($rechnung['brutto_betrag']) ? number_format($rechnung['brutto_betrag'], 2, ',', '') : '' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kategorie</label>
                                    <select class="form-select" name="kategorie_id" id="kategorie_id">
                                        <option value="">-- Wählen --</option>
                                        <?php foreach ($kategorien as $kat): ?>
                                        <option value="<?= $kat['id'] ?>" data-typ="<?= $kat['typ'] ?>"
                                                <?= ($rechnung['kategorie_id'] ?? '') == $kat['id'] ? 'selected' : '' ?>>
                                            [<?= ucfirst($kat['typ']) ?>] <?= htmlspecialchars($kat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" name="bezahlt" id="bezahlt" value="1"
                                               <?= ($rechnung['bezahlt'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="bezahlt">Bezahlt</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Bezahlt am</label>
                                    <input type="date" class="form-control" name="bezahlt_am" 
                                           value="<?= $rechnung['bezahlt_am'] ?? '' ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notizen</label>
                                <textarea class="form-control" name="notizen" rows="2"><?= htmlspecialchars($rechnung['notizen'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <button type="submit" name="save" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Speichern
                            </button>
                            <a href="rechnungen.php" class="btn btn-outline-secondary">Abbrechen</a>
                        </div>
                    </form>
                </div>

                <?php else: ?>
                <!-- Liste -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-receipt me-2"></i>Rechnungen</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="rechnungen.php?action=new&typ=einnahme" class="btn btn-success me-2">
                            <i class="bi bi-plus-circle me-1"></i>Einnahme
                        </a>
                        <a href="rechnungen.php?action=new&typ=ausgabe" class="btn btn-danger">
                            <i class="bi bi-plus-circle me-1"></i>Ausgabe
                        </a>
                    </div>
                </div>

                <!-- Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET">
                            <!-- Suchzeile -->
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" name="suche" 
                                               placeholder="Suche nach Kunde/Lieferant, Rechnungsnummer, Beschreibung..."
                                               value="<?= htmlspecialchars($filters['suche'] ?? '') ?>">
                                        <?php if (!empty($filters['suche'])): 
                                            $clearSearchFilters = $filters;
                                            $clearSearchFilters['suche'] = '';
                                            // Leere Werte entfernen für saubere URL
                                            $clearSearchFilters = array_filter($clearSearchFilters, fn($v) => $v !== '' && $v !== null);
                                        ?>
                                        <a href="rechnungen.php?<?= http_build_query($clearSearchFilters) ?>" 
                                           class="btn btn-outline-secondary" title="Suche löschen">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search me-1"></i>Suchen
                                    </button>
                                    <a href="rechnungen.php?reset_filter=1" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Zurücksetzen
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Filter-Zeile -->
                            <div class="row g-2 align-items-end">
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Typ</label>
                                    <select name="typ" class="form-select form-select-sm">
                                        <option value="">Alle</option>
                                        <option value="einnahme" <?= ($filters['typ'] ?? '') === 'einnahme' ? 'selected' : '' ?>>Einnahmen</option>
                                        <option value="ausgabe" <?= ($filters['typ'] ?? '') === 'ausgabe' ? 'selected' : '' ?>>Ausgaben</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Jahr</label>
                                    <select name="jahr" class="form-select form-select-sm">
                                        <option value="">Alle Jahre</option>
                                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?= $y ?>" <?= ($filters['jahr'] ?? '') == $y ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Monat</label>
                                    <select name="monat" class="form-select form-select-sm">
                                        <option value="">Alle</option>
                                        <?php 
                                        $monate = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                                        foreach ($monate as $i => $m): ?>
                                        <option value="<?= $i+1 ?>" <?= ($filters['monat'] ?? '') == ($i+1) ? 'selected' : '' ?>><?= $m ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Kategorie</label>
                                    <select name="kategorie_id" class="form-select form-select-sm">
                                        <option value="">Alle</option>
                                        <?php foreach ($kategorien as $kat): ?>
                                        <option value="<?= $kat['id'] ?>" <?= ($filters['kategorie_id'] ?? '') == $kat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kat['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Status</label>
                                    <select name="bezahlt" class="form-select form-select-sm">
                                        <option value="">Alle</option>
                                        <option value="1" <?= ($filters['bezahlt'] ?? '') === '1' ? 'selected' : '' ?>>Bezahlt</option>
                                        <option value="0" <?= ($filters['bezahlt'] ?? '') === '0' ? 'selected' : '' ?>>Offen</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <?php 
                                    // Aktive Filter zählen
                                    $activeFilters = 0;
                                    if (!empty($filters['typ'])) $activeFilters++;
                                    if (!empty($filters['monat'])) $activeFilters++;
                                    if (!empty($filters['kategorie_id'])) $activeFilters++;
                                    if (($filters['bezahlt'] ?? '') !== '') $activeFilters++;
                                    if (!empty($filters['suche'])) $activeFilters++;
                                    ?>
                                    <?php if ($activeFilters > 0): ?>
                                    <span class="badge bg-primary"><?= $activeFilters ?> Filter aktiv</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabelle -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Buch.-Nr.</th>
                                        <th>Datum</th>
                                        <th>Typ</th>
                                        <th>RE-Nr.</th>
                                        <th>Kunde/Lieferant</th>
                                        <th>Kategorie</th>
                                        <th class="text-end">Netto</th>
                                        <th class="text-end">USt</th>
                                        <th class="text-end">Brutto</th>
                                        <th>Status</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rechnungen)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">
                                            Keine Rechnungen gefunden
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $summeEinnahmen = 0;
                                    $summeAusgaben = 0;
                                    foreach ($rechnungen as $r): 
                                        if ($r['typ'] == 'einnahme') $summeEinnahmen += $r['brutto_betrag'];
                                        else $summeAusgaben += $r['brutto_betrag'];
                                    ?>
                                    <tr>
                                        <td><strong><?= $r['buchungsnummer'] ?? '-' ?></strong></td>
                                        <td><?= formatDatum($r['datum']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $r['typ'] == 'einnahme' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($r['typ']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($r['rechnungsnummer'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($r['kunde_lieferant']) ?></td>
                                        <td><small><?= htmlspecialchars($r['kategorie_name'] ?? '-') ?></small></td>
                                        <td class="text-end"><?= formatBetrag($r['netto_betrag']) ?></td>
                                        <td class="text-end"><?= formatBetrag($r['ust_betrag']) ?></td>
                                        <td class="text-end"><strong><?= formatBetrag($r['brutto_betrag']) ?></strong></td>
                                        <td>
                                            <?php if ($r['bezahlt']): ?>
                                                <span class="badge bg-success"><i class="bi bi-check"></i></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="rechnungen.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light fw-bold">
                                        <td colspan="7" class="text-end">Summe Einnahmen:</td>
                                        <td class="text-end text-success"><?= formatBetrag($summeEinnahmen) ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr class="table-light fw-bold">
                                        <td colspan="7" class="text-end">Summe Ausgaben:</td>
                                        <td class="text-end text-danger"><?= formatBetrag($summeAusgaben) ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <tr class="table-primary fw-bold">
                                        <td colspan="7" class="text-end">Saldo:</td>
                                        <td class="text-end"><?= formatBetrag($summeEinnahmen - $summeAusgaben) ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Brutto berechnen
        function berechnebrutto() {
            const netto = parseFloat(document.getElementById('netto_betrag').value.replace(',', '.')) || 0;
            const ustSelect = document.getElementById('ust_satz_id');
            const ustOption = ustSelect.options[ustSelect.selectedIndex];
            const prozent = parseFloat(ustOption?.dataset.prozent || 0);
            
            const brutto = netto * (1 + prozent / 100);
            document.getElementById('brutto_betrag').value = brutto.toFixed(2).replace('.', ',');
            
            // Bei EU B2C: ausländische USt berechnen
            const buchungsart = document.querySelector('input[name="buchungsart"]:checked')?.value;
            if (buchungsart === 'eu_b2c') {
                const auslandSatz = parseFloat(document.getElementById('ausland_ust_satz')?.value) || 0;
                const auslandUst = netto * (auslandSatz / 100);
                if (document.getElementById('ausland_ust_betrag')) {
                    document.getElementById('ausland_ust_betrag').value = auslandUst.toFixed(2).replace('.', ',');
                }
            }
        }
        
        document.getElementById('netto_betrag')?.addEventListener('input', berechnebrutto);
        document.getElementById('ust_satz_id')?.addEventListener('change', berechnebrutto);
        document.getElementById('ausland_ust_satz')?.addEventListener('input', berechnebrutto);
        
        // Buchungsart-Logik
        const buchungsartInfos = {
            'inland': 'Inland: Normale Buchung mit österreichischer USt',
            'eu_ige': '<strong>EU igE (innergemeinschaftlicher Erwerb):</strong> Rechnung OHNE USt von EU-Firma mit UID. Erwerbsteuer 20% wird automatisch berechnet und als Vorsteuer abgezogen (Nullsumme). Muss in U30 deklariert werden!',
            'eu_b2c': '<strong>EU B2C:</strong> Rechnung MIT ausländischer USt (z.B. 19% DE). Diese USt ist in Österreich NICHT als Vorsteuer abzugsfähig! Bruttobetrag = Aufwand.',
            'drittland': '<strong>Drittland-Import:</strong> Einfuhr-USt wird beim Zoll bezahlt und ist als Vorsteuer abzugsfähig (KZ 061).'
        };
        
        function updateBuchungsart() {
            const buchungsart = document.querySelector('input[name="buchungsart"]:checked')?.value || 'inland';
            const typ = document.querySelector('input[name="typ"]:checked')?.value;
            const euFelder = document.getElementById('eu_felder');
            const uidFeld = document.getElementById('uid_feld');
            const auslandUstFeld = document.getElementById('ausland_ust_feld');
            const buchungsartInfo = document.getElementById('buchungsart_info');
            const ustSelect = document.getElementById('ust_satz_id');
            
            // Info-Text aktualisieren
            if (buchungsartInfo) {
                buchungsartInfo.innerHTML = buchungsartInfos[buchungsart] || '';
            }
            
            // Buchungsart-Zeile nur bei Ausgaben anzeigen
            const buchungsartRow = document.getElementById('buchungsart_row');
            if (buchungsartRow) {
                buchungsartRow.style.display = typ === 'ausgabe' ? '' : 'none';
            }
            
            // EU-Felder ein/ausblenden
            if (euFelder) {
                euFelder.style.display = (buchungsart !== 'inland' && typ === 'ausgabe') ? '' : 'none';
            }
            
            // UID-Feld nur bei igE
            if (uidFeld) {
                uidFeld.style.display = buchungsart === 'eu_ige' ? '' : 'none';
            }
            
            // Ausländische USt nur bei B2C
            if (auslandUstFeld) {
                auslandUstFeld.style.display = buchungsart === 'eu_b2c' ? '' : 'none';
            }
            
            // USt-Satz vorauswählen basierend auf Buchungsart
            if (ustSelect && typ === 'ausgabe') {
                const options = Array.from(ustSelect.options);
                
                if (buchungsart === 'eu_ige') {
                    // igE 20% auswählen
                    const igeOption = options.find(o => o.text.includes('igE') || o.text.includes('Innergemeinschaft'));
                    if (igeOption) ustSelect.value = igeOption.value;
                } else if (buchungsart === 'eu_b2c') {
                    // Kein Vorsteuerabzug
                    const b2cOption = options.find(o => o.text.includes('kein VSt') || o.text.includes('Ausland'));
                    if (b2cOption) ustSelect.value = b2cOption.value;
                    else ustSelect.value = ''; // Kein USt
                } else if (buchungsart === 'drittland') {
                    // Einfuhr-USt
                    const drittOption = options.find(o => o.text.includes('Einfuhr') || o.text.includes('Drittland'));
                    if (drittOption) ustSelect.value = drittOption.value;
                }
                
                berechnebrutto();
            }
        }
        
        // Event-Listener für Buchungsart
        document.querySelectorAll('input[name="buchungsart"]').forEach(radio => {
            radio.addEventListener('change', updateBuchungsart);
        });
        
        // Event-Listener für Typ (Einnahme/Ausgabe)
        document.querySelectorAll('input[name="typ"]').forEach(radio => {
            radio.addEventListener('change', function() {
                updateBuchungsart();
                // Kategorien filtern
                const typ = this.value;
                document.querySelectorAll('#kategorie_id option').forEach(opt => {
                    if (opt.value === '' || opt.dataset.typ === typ) {
                        opt.style.display = '';
                    } else {
                        opt.style.display = 'none';
                    }
                });
            });
        });
        
        // Initial ausführen
        document.addEventListener('DOMContentLoaded', function() {
            updateBuchungsart();
            berechnebrutto();
        });
    </script>
</body>
</html>
