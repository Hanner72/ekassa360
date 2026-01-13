<?php
/**
 * Excel-Import für Rechnungen
 * EKassa360 - Flexibles Spalten-Mapping
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireLogin();

// PhpSpreadsheet einbinden
$spreadsheetLoaded = false;
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    $spreadsheetLoaded = class_exists('PhpOffice\PhpSpreadsheet\IOFactory');
}

$error = '';
$success = '';
$step = intval($_GET['step'] ?? 1);
$excelData = [];
$excelColumns = [];
$importResults = null;

// Kategorien und USt-Sätze laden
$kategorien = getKategorien();
$ustSaetze = getUstSaetze();

// Session-Daten für mehrstufigen Import
if ($step == 1 && isset($_SESSION['import_data'])) {
    unset($_SESSION['import_data']);
}

// SCHRITT 1: Datei hochladen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    if (!$spreadsheetLoaded) {
        $error = 'PhpSpreadsheet ist nicht installiert. Bitte führen Sie "composer require phpoffice/phpspreadsheet" aus.';
    } elseif (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excel_file']['tmp_name'];
        $filename = $_FILES['excel_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $error = 'Nur Excel-Dateien (.xlsx, .xls) oder CSV erlaubt.';
        } else {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                if (count($rows) < 2) {
                    $error = 'Die Datei enthält keine Daten (mindestens Kopfzeile + 1 Datenzeile erforderlich).';
                } else {
                    // Kopfzeile = Spaltennamen
                    $excelColumns = [];
                    foreach ($rows[0] as $idx => $col) {
                        $colName = trim($col ?? '');
                        if (!empty($colName)) {
                            $excelColumns[$idx] = $colName;
                        }
                    }
                    
                    // Datenzeilen (ohne Kopfzeile)
                    $excelData = array_slice($rows, 1);
                    
                    // In Session speichern
                    $_SESSION['import_data'] = [
                        'columns' => $excelColumns,
                        'data' => $excelData,
                        'filename' => $filename
                    ];
                    
                    // Weiterleitung zu Schritt 2
                    header('Location: import.php?step=2');
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Fehler beim Lesen der Datei: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Bitte wählen Sie eine Datei aus.';
    }
}

// Daten aus Session laden für Schritt 2+
if ($step >= 2 && isset($_SESSION['import_data'])) {
    $excelColumns = $_SESSION['import_data']['columns'];
    $excelData = $_SESSION['import_data']['data'];
} elseif ($step >= 2) {
    // Keine Daten in Session - zurück zu Schritt 1
    header('Location: import.php?step=1');
    exit;
}

// SCHRITT 2: Mapping speichern (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mapping'])) {
    $mapping = [
        'typ' => $_POST['map_typ'] ?? '',
        'typ_wert' => $_POST['typ_wert'] ?? 'einnahme',
        'rechnungsnummer' => $_POST['map_rechnungsnummer'] ?? '',
        'datum' => $_POST['map_datum'] ?? '',
        'faellig_am' => $_POST['map_faellig_am'] ?? '',
        'kunde_lieferant' => $_POST['map_kunde_lieferant'] ?? '',
        'beschreibung' => $_POST['map_beschreibung'] ?? '',
        'netto_betrag' => $_POST['map_netto_betrag'] ?? '',
        'brutto_betrag' => $_POST['map_brutto_betrag'] ?? '',
        'ust_betrag' => $_POST['map_ust_betrag'] ?? '',
        'ust_satz_id' => $_POST['map_ust_satz_id'] ?? '',
        'ust_satz_id_wert' => $_POST['ust_satz_id_wert'] ?? '',
        'kategorie' => $_POST['map_kategorie'] ?? '',
        'kategorie_id_wert' => $_POST['kategorie_id_wert'] ?? '',
        'bezahlt' => $_POST['map_bezahlt'] ?? '',
        'bezahlt_wert' => $_POST['bezahlt_wert'] ?? '1',
        'bezahlt_am' => $_POST['map_bezahlt_am'] ?? '',
        'notizen' => $_POST['map_notizen'] ?? ''
    ];
    
    $_SESSION['import_data']['mapping'] = $mapping;
    
    // Weiterleitung zu Schritt 3
    header('Location: import.php?step=3');
    exit;
}

// SCHRITT 3: Import durchführen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_import'])) {
    $mapping = $_SESSION['import_data']['mapping'] ?? [];
    $data = $_SESSION['import_data']['data'] ?? [];
    $columns = $_SESSION['import_data']['columns'] ?? [];
    
    if (empty($mapping) || empty($data)) {
        header('Location: import.php?step=1');
        exit;
    }
    
    $imported = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($data as $rowNum => $row) {
        try {
            // Leere Zeilen überspringen
            $rowData = array_filter($row, function($v) { return $v !== null && $v !== ''; });
            if (empty($rowData)) {
                $skipped++;
                continue;
            }
            
            // Werte aus Mapping extrahieren
            $getValue = function($field) use ($mapping, $columns, $row) {
                $mapCol = $mapping[$field] ?? '';
                if (empty($mapCol)) {
                    return null;
                }
                // Spaltenindex finden
                $colIdx = array_search($mapCol, $columns);
                if ($colIdx === false) {
                    return null;
                }
                return $row[$colIdx] ?? null;
            };
            
            // Typ bestimmen
            $typ = $mapping['typ_wert'];
            if (!empty($mapping['typ'])) {
                $typValue = strtolower(trim($getValue('typ') ?? ''));
                if (strpos($typValue, 'ausgabe') !== false || strpos($typValue, 'expense') !== false || strpos($typValue, 'out') !== false) {
                    $typ = 'ausgabe';
                } elseif (strpos($typValue, 'einnahme') !== false || strpos($typValue, 'income') !== false || strpos($typValue, 'in') !== false) {
                    $typ = 'einnahme';
                }
            }
            
            // Datum parsen
            $datum = $getValue('datum');
            if ($datum instanceof \DateTime) {
                $datum = $datum->format('Y-m-d');
            } elseif (is_numeric($datum) && $datum > 25569) {
                // Excel-Datum (Tage seit 1900)
                $datum = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($datum)->format('Y-m-d');
            } elseif ($datum) {
                $parsed = strtotime($datum);
                $datum = $parsed ? date('Y-m-d', $parsed) : date('Y-m-d');
            } else {
                $datum = date('Y-m-d');
            }
            
            // Fälligkeitsdatum
            $faellig_am = $getValue('faellig_am');
            if ($faellig_am instanceof \DateTime) {
                $faellig_am = $faellig_am->format('Y-m-d');
            } elseif (is_numeric($faellig_am) && $faellig_am > 25569) {
                $faellig_am = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($faellig_am)->format('Y-m-d');
            } elseif ($faellig_am) {
                $parsed = strtotime($faellig_am);
                $faellig_am = $parsed ? date('Y-m-d', $parsed) : null;
            } else {
                $faellig_am = null;
            }
            
            // Bezahlt am
            $bezahlt_am = $getValue('bezahlt_am');
            if ($bezahlt_am instanceof \DateTime) {
                $bezahlt_am = $bezahlt_am->format('Y-m-d');
            } elseif (is_numeric($bezahlt_am) && $bezahlt_am > 25569) {
                $bezahlt_am = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($bezahlt_am)->format('Y-m-d');
            } elseif ($bezahlt_am) {
                $parsed = strtotime($bezahlt_am);
                $bezahlt_am = $parsed ? date('Y-m-d', $parsed) : null;
            } else {
                $bezahlt_am = null;
            }
            
            // Beträge parsen (€-Zeichen und Tausendertrennzeichen entfernen)
            $parseBetrag = function($val) {
                if ($val === null || $val === '') return 0;
                $val = str_replace(['€', ' ', "\xc2\xa0"], '', $val);
                $val = str_replace('.', '', $val); // Tausendertrennzeichen
                $val = str_replace(',', '.', $val); // Dezimalkomma zu Punkt
                return abs(floatval($val));
            };
            
            $netto = $parseBetrag($getValue('netto_betrag'));
            $brutto = $parseBetrag($getValue('brutto_betrag'));
            $ust = $parseBetrag($getValue('ust_betrag'));
            
            // Netto aus Brutto berechnen falls nur Brutto vorhanden
            if ($netto == 0 && $brutto > 0) {
                if ($ust > 0) {
                    $netto = $brutto - $ust;
                } else {
                    // Standard 20% annehmen
                    $netto = $brutto / 1.20;
                    $ust = $brutto - $netto;
                }
            }
            
            // USt-Satz bestimmen
            $ust_satz_id = null;
            if (!empty($mapping['ust_satz_id_wert'])) {
                $ust_satz_id = $mapping['ust_satz_id_wert'];
            } elseif ($netto > 0 && $ust > 0) {
                // Aus Beträgen berechnen
                $ustProzent = round(($ust / $netto) * 100, 0);
                foreach ($ustSaetze as $satz) {
                    if (abs($satz['satz'] - $ustProzent) < 2) {
                        $ust_satz_id = $satz['id'];
                        break;
                    }
                }
            }
            
            // Kategorie bestimmen
            $kategorie_id = null;
            if (!empty($mapping['kategorie_id_wert'])) {
                $kategorie_id = $mapping['kategorie_id_wert'];
            } elseif (!empty($mapping['kategorie'])) {
                $katName = strtolower(trim($getValue('kategorie') ?? ''));
                if (!empty($katName)) {
                    foreach ($kategorien as $kat) {
                        if (strtolower($kat['name']) == $katName) {
                            $kategorie_id = $kat['id'];
                            break;
                        }
                    }
                }
            }
            
            // Bezahlt-Status
            $bezahlt = $mapping['bezahlt_wert'] ?? 1;
            if (!empty($mapping['bezahlt'])) {
                $bezahltVal = strtolower(trim($getValue('bezahlt') ?? ''));
                $bezahlt = in_array($bezahltVal, ['ja', 'yes', '1', 'true', 'bezahlt', 'paid']) ? 1 : 0;
            }
            
            // Rechnungsdaten zusammenstellen
            $rechnung = [
                'typ' => $typ,
                'rechnungsnummer' => trim($getValue('rechnungsnummer') ?? ''),
                'datum' => $datum,
                'faellig_am' => $faellig_am,
                'kunde_lieferant' => trim($getValue('kunde_lieferant') ?? ''),
                'beschreibung' => trim($getValue('beschreibung') ?? ''),
                'netto_betrag' => $netto,
                'ust_satz_id' => $ust_satz_id,
                'kategorie_id' => $kategorie_id,
                'bezahlt' => $bezahlt,
                'bezahlt_am' => $bezahlt_am ?: ($bezahlt ? $datum : null),
                'notizen' => trim($getValue('notizen') ?? '')
            ];
            
            // Nur importieren wenn Mindestdaten vorhanden
            if (empty($rechnung['kunde_lieferant']) && empty($rechnung['beschreibung'])) {
                $skipped++;
                continue;
            }
            
            if ($rechnung['netto_betrag'] <= 0) {
                $skipped++;
                continue;
            }
            
            // Speichern
            $id = saveRechnung($rechnung);
            if ($id) {
                $imported++;
            } else {
                $errors[] = "Zeile " . ($rowNum + 2) . ": Fehler beim Speichern";
            }
            
        } catch (Exception $e) {
            $errors[] = "Zeile " . ($rowNum + 2) . ": " . $e->getMessage();
        }
    }
    
    $importResults = [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ];
    
    if ($imported > 0 && function_exists('logAction')) {
        logAction('import', null, 'erstellt', "Excel-Import: $imported Rechnungen importiert");
    }
    
    // Session aufräumen
    unset($_SESSION['import_data']);
    $step = 4;
}

// EKassa360 Felder für Mapping - ALLE Felder
$ekassaFelder = [
    'typ' => ['label' => 'Typ (Einnahme/Ausgabe)', 'required' => false, 'hint' => 'Oder Standard-Typ unten wählen'],
    'rechnungsnummer' => ['label' => 'Rechnungsnummer', 'required' => false],
    'datum' => ['label' => 'Rechnungsdatum', 'required' => true],
    'faellig_am' => ['label' => 'Fälligkeitsdatum', 'required' => false],
    'kunde_lieferant' => ['label' => 'Kunde/Lieferant', 'required' => true],
    'beschreibung' => ['label' => 'Beschreibung', 'required' => false],
    'netto_betrag' => ['label' => 'Netto-Betrag', 'required' => false, 'hint' => 'Oder Brutto angeben'],
    'brutto_betrag' => ['label' => 'Brutto-Betrag', 'required' => false, 'hint' => 'Falls Netto nicht vorhanden'],
    'ust_betrag' => ['label' => 'USt-Betrag', 'required' => false],
    'kategorie' => ['label' => 'Kategorie (Name)', 'required' => false, 'hint' => 'Muss mit vorhandener Kategorie übereinstimmen'],
    'bezahlt' => ['label' => 'Bezahlt (Ja/Nein)', 'required' => false],
    'bezahlt_am' => ['label' => 'Bezahlt am (Datum)', 'required' => false],
    'notizen' => ['label' => 'Notizen/Hinweise', 'required' => false]
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel-Import - EKassa360</title>
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-file-earmark-excel me-2"></i>Excel-Import</h1>
                    <a href="rechnungen.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Zurück zu Rechnungen
                    </a>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <!-- Fortschrittsanzeige -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="badge <?= $step >= 1 ? 'bg-primary' : 'bg-secondary' ?> p-2">1. Datei hochladen</span>
                        <span class="badge <?= $step >= 2 ? 'bg-primary' : 'bg-secondary' ?> p-2">2. Spalten zuordnen</span>
                        <span class="badge <?= $step >= 3 ? 'bg-primary' : 'bg-secondary' ?> p-2">3. Vorschau</span>
                        <span class="badge <?= $step >= 4 ? 'bg-primary' : 'bg-secondary' ?> p-2">4. Ergebnis</span>
                    </div>
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar" style="width: <?= ($step / 4) * 100 ?>%"></div>
                    </div>
                </div>

                <?php if (!$spreadsheetLoaded && $step == 1): ?>
                    <!-- PhpSpreadsheet nicht installiert -->
                    <div class="alert alert-warning">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>PhpSpreadsheet erforderlich</h5>
                        <p>Für den Excel-Import wird die PhpSpreadsheet-Bibliothek benötigt.</p>
                        <p>Installieren Sie diese mit:</p>
                        <pre class="bg-dark text-white p-2 mb-0">composer require phpoffice/phpspreadsheet</pre>
                    </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                    <!-- SCHRITT 1: Datei hochladen -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-upload me-2"></i>Schritt 1: Excel-Datei hochladen</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="upload_file" value="1">
                                
                                <div class="mb-4">
                                    <label class="form-label">Excel-Datei auswählen</label>
                                    <input type="file" name="excel_file" class="form-control form-control-lg" accept=".xlsx,.xls,.csv" required>
                                    <small class="text-muted">Unterstützte Formate: .xlsx, .xls, .csv</small>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-info-circle me-2"></i>Hinweise</h6>
                                    <ul class="mb-0">
                                        <li>Die erste Zeile muss die Spaltenüberschriften enthalten</li>
                                        <li>Im nächsten Schritt können Sie <strong>jede Spalte</strong> einem EKassa360-Feld zuordnen</li>
                                        <li>Beträge können mit €-Zeichen und Tausendertrennzeichen formatiert sein</li>
                                        <li>Verschiedene Excel-Formate werden unterstützt</li>
                                    </ul>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-lg" <?= !$spreadsheetLoaded ? 'disabled' : '' ?>>
                                    <i class="bi bi-arrow-right me-1"></i>Weiter zu Spalten-Zuordnung
                                </button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($step == 2): ?>
                    <!-- SCHRITT 2: Spalten-Mapping -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-arrows-collapse me-2"></i>Schritt 2: Spalten zuordnen</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-success mb-4">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Datei geladen:</strong> <?= htmlspecialchars($_SESSION['import_data']['filename'] ?? '') ?>
                                (<?= count($excelData) ?> Datenzeilen, <?= count($excelColumns) ?> Spalten)
                            </div>
                            
                            <p class="text-muted mb-4">
                                Ordnen Sie die Spalten Ihrer Excel-Datei den EKassa360-Feldern zu.
                                Felder ohne Zuordnung werden leer gelassen oder mit Standardwerten befüllt.
                            </p>
                            
                            <form method="POST">
                                <input type="hidden" name="save_mapping" value="1">
                                
                                <!-- Gefundene Excel-Spalten anzeigen -->
                                <div class="card mb-4 bg-light">
                                    <div class="card-body">
                                        <h6><i class="bi bi-table me-2"></i>Gefundene Spalten in Ihrer Excel-Datei:</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($excelColumns as $idx => $col): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($col) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feld-Mapping Tabelle -->
                                <h6 class="mb-3"><i class="bi bi-list-columns me-2"></i>Feld-Zuordnung</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width: 250px;">EKassa360 Feld</th>
                                                <th>Ihre Excel-Spalte</th>
                                                <th style="width: 250px;">Beispielwerte (erste 3 Zeilen)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ekassaFelder as $field => $info): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= $info['label'] ?></strong>
                                                        <?php if ($info['required']): ?>
                                                            <span class="text-danger">*</span>
                                                        <?php endif; ?>
                                                        <?php if (isset($info['hint'])): ?>
                                                            <br><small class="text-muted"><?= $info['hint'] ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <select name="map_<?= $field ?>" class="form-select" id="select_<?= $field ?>" onchange="updatePreview('<?= $field ?>')">
                                                            <option value="">-- Nicht zuordnen --</option>
                                                            <?php foreach ($excelColumns as $idx => $col): ?>
                                                                <option value="<?= htmlspecialchars($col) ?>" data-index="<?= $idx ?>"><?= htmlspecialchars($col) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td class="text-muted small" id="preview_<?= $field ?>">
                                                        <em>Spalte wählen...</em>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Standard-Einstellungen -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header"><i class="bi bi-tag me-2"></i>Buchungstyp</div>
                                            <div class="card-body">
                                                <label class="form-label">Standard-Typ (wenn nicht aus Spalte)</label>
                                                <select name="typ_wert" class="form-select">
                                                    <option value="einnahme">Alle als Einnahme</option>
                                                    <option value="ausgabe">Alle als Ausgabe</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header"><i class="bi bi-check-circle me-2"></i>Bezahlt-Status</div>
                                            <div class="card-body">
                                                <label class="form-label">Standard (wenn nicht aus Spalte)</label>
                                                <select name="bezahlt_wert" class="form-select">
                                                    <option value="1">Alle als bezahlt markieren</option>
                                                    <option value="0">Alle als offen markieren</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header"><i class="bi bi-percent me-2"></i>USt-Satz</div>
                                            <div class="card-body">
                                                <label class="form-label">Standard USt-Satz</label>
                                                <select name="ust_satz_id_wert" class="form-select">
                                                    <option value="">Automatisch aus Beträgen erkennen</option>
                                                    <?php foreach ($ustSaetze as $satz): ?>
                                                        <option value="<?= $satz['id'] ?>"><?= htmlspecialchars($satz['bezeichnung']) ?> (<?= $satz['satz'] ?>%)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-4">
                                            <div class="card-header"><i class="bi bi-folder me-2"></i>Kategorie</div>
                                            <div class="card-body">
                                                <label class="form-label">Standard-Kategorie</label>
                                                <select name="kategorie_id_wert" class="form-select">
                                                    <option value="">Aus Spalte oder keine</option>
                                                    <?php foreach ($kategorien as $kat): ?>
                                                        <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="import.php?step=1" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left me-1"></i>Zurück
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-arrow-right me-1"></i>Weiter zur Vorschau
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- JavaScript für Live-Vorschau -->
                    <script>
                        const excelData = <?= json_encode(array_slice($excelData, 0, 3)) ?>;
                        const excelColumns = <?= json_encode($excelColumns) ?>;
                        const columnIndices = <?= json_encode(array_flip($excelColumns)) ?>;
                        
                        function updatePreview(field) {
                            const select = document.getElementById('select_' + field);
                            const previewEl = document.getElementById('preview_' + field);
                            const colName = select.value;
                            
                            if (!colName) {
                                previewEl.innerHTML = '<em>Spalte wählen...</em>';
                                return;
                            }
                            
                            const colIndex = columnIndices[colName];
                            if (colIndex === undefined) {
                                previewEl.innerHTML = '<em>-</em>';
                                return;
                            }
                            
                            const examples = excelData
                                .map(row => row[colIndex])
                                .filter(v => v !== null && v !== '')
                                .slice(0, 3)
                                .map(v => '<code>' + String(v).substring(0, 30) + '</code>')
                                .join(', ');
                            
                            previewEl.innerHTML = examples || '<em>leer</em>';
                        }
                    </script>

                <?php elseif ($step == 3 && isset($_SESSION['import_data']['mapping'])): ?>
                    <!-- SCHRITT 3: Vorschau -->
                    <?php
                    $mapping = $_SESSION['import_data']['mapping'];
                    $columns = $_SESSION['import_data']['columns'];
                    ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Schritt 3: Vorschau & Import starten</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong><i class="bi bi-info-circle me-2"></i><?= count($excelData) ?> Zeilen</strong> werden importiert.
                            </div>
                            
                            <h6>Ihre Zuordnung:</h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>EKassa360 Feld</th>
                                            <th>Excel-Spalte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ekassaFelder as $field => $info): ?>
                                            <tr>
                                                <td><?= $info['label'] ?></td>
                                                <td>
                                                    <?php if (!empty($mapping[$field])): ?>
                                                        <strong class="text-success"><?= htmlspecialchars($mapping[$field]) ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">-- nicht zugeordnet --</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <h6>Standard-Einstellungen:</h6>
                            <ul>
                                <li>Typ: <strong><?= $mapping['typ_wert'] == 'einnahme' ? 'Einnahme' : 'Ausgabe' ?></strong></li>
                                <li>Bezahlt: <strong><?= $mapping['bezahlt_wert'] == '1' ? 'Ja' : 'Nein' ?></strong></li>
                            </ul>
                            
                            <h6 class="mt-4">Daten-Vorschau (erste 5 Zeilen):</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>#</th>
                                            <?php foreach ($columns as $col): ?>
                                                <th><?= htmlspecialchars($col) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $previewCount = min(5, count($excelData));
                                        for ($i = 0; $i < $previewCount; $i++): 
                                            $row = $excelData[$i];
                                        ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <?php foreach ($columns as $idx => $col): ?>
                                                    <td class="small"><?= htmlspecialchars(substr($row[$idx] ?? '', 0, 25)) ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="do_import" value="1">
                                
                                <div class="alert alert-warning mt-4">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Achtung:</strong> Der Import kann nicht automatisch rückgängig gemacht werden!
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="import.php?step=2" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left me-1"></i>Zurück zur Zuordnung
                                    </a>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-lg me-1"></i>Jetzt <?= count($excelData) ?> Zeilen importieren
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($step == 4 && $importResults): ?>
                    <!-- SCHRITT 4: Ergebnis -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Import abgeschlossen!</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-4">
                                <div class="col-md-4">
                                    <div class="display-4 text-success"><?= $importResults['imported'] ?></div>
                                    <div class="text-muted">Erfolgreich importiert</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="display-4 text-warning"><?= $importResults['skipped'] ?></div>
                                    <div class="text-muted">Übersprungen (leer/ungültig)</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="display-4 text-danger"><?= count($importResults['errors']) ?></div>
                                    <div class="text-muted">Fehler</div>
                                </div>
                            </div>
                            
                            <?php if (!empty($importResults['errors'])): ?>
                                <div class="alert alert-danger">
                                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Import:</h6>
                                    <ul class="mb-0">
                                        <?php foreach (array_slice($importResults['errors'], 0, 10) as $err): ?>
                                            <li><?= htmlspecialchars($err) ?></li>
                                        <?php endforeach; ?>
                                        <?php if (count($importResults['errors']) > 10): ?>
                                            <li class="text-muted">... und <?= count($importResults['errors']) - 10 ?> weitere Fehler</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <a href="rechnungen.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-list me-1"></i>Zu den Rechnungen
                                </a>
                                <a href="import.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-repeat me-1"></i>Weiteren Import starten
                                </a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Fallback - zurück zu Schritt 1 -->
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Sitzung abgelaufen. Bitte starten Sie den Import erneut.
                        <a href="import.php?step=1" class="btn btn-primary btn-sm ms-3">Neu starten</a>
                    </div>
                <?php endif; ?>
                
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
