<?php
/**
 * USt-Voranmeldung (U30) für Österreich
 * EKassa360 - Version 1.2
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Löschen
    if (isset($_POST['delete'])) {
        if (deleteUstVoranmeldung($_POST['id'])) {
            setFlashMessage('success', 'USt-Voranmeldung gelöscht.');
        } else {
            setFlashMessage('danger', 'Fehler beim Löschen.');
        }
        header('Location: ust_voranmeldung.php');
        exit;
    }
    
    // Speichern
    if (isset($_POST['speichern'])) {
        $data = [
            'jahr' => $_POST['jahr'],
            'monat' => $_POST['monat'],
            'zeitraum_typ' => $_POST['zeitraum_typ'],
            'kz000' => floatval($_POST['kz000']),
            'kz001' => floatval($_POST['kz001'] ?? 0),
            'kz021' => floatval($_POST['kz021']),
            'kz022' => floatval($_POST['kz022']),
            'kz029' => floatval($_POST['kz029']),
            'kz025' => floatval($_POST['kz025']),
            'kz027' => floatval($_POST['kz027']),
            'kz035' => floatval($_POST['kz035']),
            'kz052' => floatval($_POST['kz052']),
            'kz060' => floatval($_POST['kz060']),
            'kz065' => floatval($_POST['kz065'] ?? 0),
            'kz066' => floatval($_POST['kz066'] ?? 0),
            'kz070' => floatval($_POST['kz070'] ?? 0),
            'kz072' => floatval($_POST['kz072'] ?? 0),
            'kz082' => floatval($_POST['kz082'] ?? 0),
            'kz095' => floatval($_POST['zahllast']),
            'zahllast' => floatval($_POST['zahllast']),
            'eingereicht' => isset($_POST['eingereicht']) ? 1 : 0,
            'eingereicht_am' => $_POST['eingereicht_am'] ?: null,
            'notizen' => $_POST['notizen'] ?? null
        ];
        
        if (saveUstVoranmeldung($data)) {
            setFlashMessage('success', 'USt-Voranmeldung gespeichert.');
            header('Location: ust_voranmeldung.php');
            exit;
        } else {
            setFlashMessage('danger', 'Fehler beim Speichern.');
        }
    }
}

// Daten laden
$firma = getFirmendaten();
$alleMeldungen = getUstVoranmeldungen();

// Für Bearbeitung/Ansicht
$jahr = $_GET['jahr'] ?? date('Y');
$monat = $_GET['monat'] ?? date('n');
$zeitraumTyp = $_GET['zeitraum'] ?? 'monat';

// Bestehende Meldung laden oder neu berechnen
$gespeichert = null;
$u30 = null;
$readonly = false;

if ($action === 'view' || $action === 'edit' || $action === 'new') {
    $gespeichert = getUstVoranmeldung($jahr, $monat, $zeitraumTyp);
    
    if ($gespeichert && $gespeichert['eingereicht']) {
        $readonly = true;
        $u30 = $gespeichert;
    } elseif ($gespeichert) {
        $u30 = $gespeichert;
    } else {
        $u30 = berechneUstVoranmeldung($jahr, $monat, $zeitraumTyp);
    }
}

// Monatsnamen
$monatsnamen = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$quartalsnamen = ['', 'Q1 (Jan-Mär)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Okt-Dez)'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USt-Voranmeldung (U30) - EKassa360</title>
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

                <?php if ($action === 'list'): ?>
                <!-- ÜBERSICHT -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-file-earmark-text me-2"></i>USt-Voranmeldungen (U30)</h1>
                    <a href="?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Neue Meldung
                    </a>
                </div>

                <!-- Schnellauswahl für neue Meldung -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Neue Meldung berechnen</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="action" value="new">
                            <div class="col-md-3">
                                <label class="form-label">Jahr</label>
                                <select name="jahr" class="form-select">
                                    <?php for ($j = date('Y'); $j >= date('Y') - 5; $j--): ?>
                                    <option value="<?= $j ?>"><?= $j ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Zeitraum</label>
                                <select name="zeitraum" class="form-select" id="zeitraumSelect">
                                    <option value="monat">Monatlich</option>
                                    <option value="quartal">Quartalsweise</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Monat/Quartal</label>
                                <select name="monat" class="form-select" id="monatSelect">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') - 1 ? 'selected' : '' ?>><?= $monatsnamen[$m] ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-calculator me-1"></i>Berechnen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste der Meldungen -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Gespeicherte Meldungen</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($alleMeldungen)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Noch keine USt-Voranmeldungen gespeichert.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Zeitraum</th>
                                            <th class="text-end">Umsatz (KZ000)</th>
                                            <th class="text-end">USt</th>
                                            <th class="text-end">Vorsteuer</th>
                                            <th class="text-end">Zahllast</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-end">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alleMeldungen as $m): 
                                            // USt = Zahllast + Vorsteuer (immer korrekt)
                                            $ustGesamt = $m['zahllast'] + $m['kz060'];
                                            $zeitraumText = $m['zeitraum_typ'] == 'quartal' 
                                                ? $quartalsnamen[$m['monat']] . ' ' . $m['jahr']
                                                : $monatsnamen[$m['monat']] . ' ' . $m['jahr'];
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= $zeitraumText ?></strong>
                                                <br><small class="text-muted"><?= ucfirst($m['zeitraum_typ']) ?></small>
                                            </td>
                                            <td class="text-end"><?= formatBetrag($m['kz000']) ?></td>
                                            <td class="text-end"><?= formatBetrag($ustGesamt) ?></td>
                                            <td class="text-end"><?= formatBetrag($m['kz060']) ?></td>
                                            <td class="text-end <?= $m['zahllast'] >= 0 ? 'text-danger' : 'text-success' ?>">
                                                <strong><?= formatBetrag($m['zahllast']) ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($m['eingereicht']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Gesendet
                                                    </span>
                                                    <?php if ($m['eingereicht_am']): ?>
                                                    <br><small class="text-muted"><?= formatDatum($m['eingereicht_am']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-clock me-1"></i>Entwurf
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($m['eingereicht']): ?>
                                                    <!-- Nur Ansicht und Löschen für gesendete -->
                                                    <a href="?action=view&jahr=<?= $m['jahr'] ?>&monat=<?= $m['monat'] ?>&zeitraum=<?= $m['zeitraum_typ'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Ansehen">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="pdf_u30.php?jahr=<?= $m['jahr'] ?>&monat=<?= $m['monat'] ?>&zeitraum=<?= $m['zeitraum_typ'] ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="PDF" target="_blank">
                                                        <i class="bi bi-file-pdf"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <button type="submit" name="delete" class="btn btn-sm btn-outline-secondary" title="Löschen">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Bearbeiten für Entwürfe -->
                                                    <a href="?action=edit&jahr=<?= $m['jahr'] ?>&monat=<?= $m['monat'] ?>&zeitraum=<?= $m['zeitraum_typ'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="pdf_u30.php?jahr=<?= $m['jahr'] ?>&monat=<?= $m['monat'] ?>&zeitraum=<?= $m['zeitraum_typ'] ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="PDF" target="_blank">
                                                        <i class="bi bi-file-pdf"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <button type="submit" name="delete" class="btn btn-sm btn-outline-secondary" title="Löschen">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- FORMULAR (Neu/Bearbeiten/Ansicht) -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-file-earmark-text me-2"></i>USt-Voranmeldung (U30)
                        <?php if ($readonly): ?>
                            <span class="badge bg-success ms-2">Gesendet</span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <?php if ($readonly): ?>
                            <a href="pdf_u30.php?jahr=<?= $jahr ?>&monat=<?= $monat ?>&zeitraum=<?= $zeitraumTyp ?>" 
                               class="btn btn-danger me-2" target="_blank">
                                <i class="bi bi-file-pdf me-1"></i>PDF
                            </a>
                        <?php endif; ?>
                        <a href="ust_voranmeldung.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Zurück
                        </a>
                    </div>
                </div>

                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="jahr" value="<?= $jahr ?>">
                    <input type="hidden" name="monat" value="<?= $monat ?>">
                    <input type="hidden" name="zeitraum_typ" value="<?= $zeitraumTyp ?>">

                    <!-- Zeitraum Info -->
                    <div class="card mb-4 <?= $readonly ? 'border-success' : '' ?>">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?= $zeitraumTyp == 'quartal' ? $quartalsnamen[$monat] : $monatsnamen[$monat] ?> <?= $jahr ?>
                                </h5>
                                <?php if ($readonly && $gespeichert['eingereicht_am']): ?>
                                    <span>Gesendet am: <?= formatDatum($gespeichert['eingereicht_am']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Firmendaten -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong><?= htmlspecialchars($firma['name'] ?? 'Firma') ?></strong><br>
                                    <?= htmlspecialchars($firma['strasse'] ?? '') ?><br>
                                    <?= htmlspecialchars($firma['plz'] ?? '') ?> <?= htmlspecialchars($firma['ort'] ?? '') ?>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <strong>UID:</strong> <?= htmlspecialchars($firma['uid_nummer'] ?? '-') ?><br>
                                    <strong>Steuernummer:</strong> <?= htmlspecialchars($firma['steuernummer'] ?? '-') ?><br>
                                    <strong>Finanzamt:</strong> <?= htmlspecialchars($firma['finanzamt'] ?? '-') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kennzahlen -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Lieferungen und Leistungen</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <tr>
                                    <td width="60%">Gesamtbetrag der Bemessungsgrundlage für Lieferungen und Leistungen</td>
                                    <td width="15%" class="text-center"><strong>KZ 000</strong></td>
                                    <td width="25%">
                                        <input type="number" step="0.01" class="form-control text-end" name="kz000" 
                                               value="<?= number_format($u30['kz000'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="3"><strong>Davon steuerfrei MIT Vorsteuerabzug:</strong></td>
                                </tr>
                                <tr>
                                    <td>Innergemeinschaftliche Lieferungen (Art. 7 UStG)</td>
                                    <td class="text-center">KZ 021</td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control text-end" name="kz021" 
                                               value="<?= number_format($u30['kz021'] ?? 0, 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Berechnung der Umsatzsteuer</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Kennzahlen gemäß U30-Formular 2025
                            </p>
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Steuersatz</th>
                                        <th class="text-center">KZ</th>
                                        <th>Bemessungsgrundlage</th>
                                        <th>Umsatzsteuer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>20%</strong> Normalsteuersatz</td>
                                        <td class="text-center"><strong>022</strong></td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end" name="kz022" 
                                                   value="<?= number_format($u30['kz022'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end" name="kz029" 
                                                   value="<?= number_format($u30['kz029'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>10%</strong> ermäßigt</td>
                                        <td class="text-center"><strong>029</strong></td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end" name="kz025" 
                                                   value="<?= number_format($u30['kz025'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end" name="kz027" 
                                                   value="<?= number_format($u30['kz027'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>13%</strong> ermäßigt</td>
                                        <td class="text-center"><strong>006</strong></td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end" name="kz035" 
                                                   value="<?= number_format($u30['kz035'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control text-end" name="kz052" 
                                                   value="<?= number_format($u30['kz052'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Innergemeinschaftliche Erwerbe (igE) -->
                    <?php if (($u30['kz070'] ?? 0) > 0): ?>
                    <div class="card mb-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-globe-europe-africa me-2"></i>Innergemeinschaftliche Erwerbe (igE)</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                EU-Einkäufe mit Reverse Charge. Die Erwerbsteuer (KZ 072) wird gleichzeitig als Vorsteuer (KZ 065) abgezogen.
                            </p>
                            <table class="table">
                                <tr>
                                    <td width="60%">Bemessungsgrundlage igE (Netto aus EU)</td>
                                    <td width="15%" class="text-center"><strong>KZ 070</strong></td>
                                    <td width="25%">
                                        <input type="number" step="0.01" class="form-control text-end" name="kz070" 
                                               value="<?= number_format($u30['kz070'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Erwerbsteuer 20% daraus</td>
                                    <td class="text-center"><strong>KZ 072</strong></td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control text-end bg-light" 
                                               value="<?= number_format($u30['kz072'] ?? 0, 2, '.', '') ?>" readonly>
                                    </td>
                                </tr>
                                <tr class="table-success">
                                    <td>Vorsteuer aus igE (gleicht Erwerbsteuer aus)</td>
                                    <td class="text-center"><strong>KZ 065</strong></td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control text-end" name="kz065" 
                                               value="<?= number_format($u30['kz065'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                            </table>
                            <div class="alert alert-info mb-0">
                                <small><strong>Ergebnis igE:</strong> Erwerbsteuer <?= formatBetrag($u30['kz072'] ?? 0) ?> − Vorsteuer <?= formatBetrag($u30['kz065']) ?> = <strong><?= formatBetrag(($u30['kz072'] ?? 0) - $u30['kz065']) ?></strong> (Nullsumme)</small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Vorsteuer</h5>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <tr>
                                    <td width="60%">Vorsteuer aus Inlandsrechnungen</td>
                                    <td width="15%" class="text-center"><strong>KZ 060</strong></td>
                                    <td width="25%">
                                        <input type="number" step="0.01" class="form-control text-end" name="kz060" 
                                               value="<?= number_format($u30['kz060'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                                <?php if (($u30['kz061'] ?? 0) > 0): ?>
                                <tr>
                                    <td>Einfuhr-USt (Drittland-Importe)</td>
                                    <td class="text-center"><strong>KZ 061</strong></td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control text-end" name="kz061" 
                                               value="<?= number_format($u30['kz061'], 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php if (($u30['kz065'] ?? 0) > 0): ?>
                                <tr>
                                    <td>Vorsteuer aus igE (siehe oben)</td>
                                    <td class="text-center"><strong>KZ 065</strong></td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control text-end bg-light" 
                                               value="<?= number_format($u30['kz065'], 2, '.', '') ?>" readonly>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-light fw-bold">
                                    <td>Vorsteuer gesamt</td>
                                    <td></td>
                                    <td class="text-end">
                                        <?= formatBetrag(($u30['kz060'] ?? 0) + ($u30['kz061'] ?? 0) + ($u30['kz065'] ?? 0)) ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Ergebnis -->
                    <?php 
                    $ustGesamt = ($u30['kz029'] ?? 0) + ($u30['kz027'] ?? 0) + ($u30['kz052'] ?? 0) + ($u30['kz072'] ?? 0);
                    $vorsteuerGesamt = ($u30['kz060'] ?? 0) + ($u30['kz061'] ?? 0) + ($u30['kz065'] ?? 0);
                    ?>
                    <div class="card mb-4 <?= ($u30['zahllast'] ?? 0) >= 0 ? 'border-danger' : 'border-success' ?>">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?= ($u30['zahllast'] ?? 0) >= 0 ? 'Zahllast' : 'Gutschrift' ?> (KZ 095)
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <p class="mb-0">
                                        USt (<?= formatBetrag($ustGesamt) ?>)
                                        − Vorsteuer (<?= formatBetrag($vorsteuerGesamt) ?>)
                                    </p>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" step="0.01" class="form-control form-control-lg text-end fw-bold" 
                                           name="zahllast" value="<?= number_format($u30['zahllast'] ?? 0, 2, '.', '') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!$readonly): ?>
                    <!-- Status & Speichern -->
                    <div class="card mb-4 no-print">
                        <div class="card-header">
                            <h5 class="mb-0">Status & Speichern</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="eingereicht" id="eingereicht"
                                               <?= ($gespeichert['eingereicht'] ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="eingereicht">
                                            <strong>Als gesendet markieren</strong>
                                        </label>
                                        <div class="form-text">Nach dem Senden kann die Meldung nicht mehr bearbeitet werden.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gesendet am</label>
                                    <input type="date" class="form-control" name="eingereicht_am" 
                                           value="<?= $gespeichert['eingereicht_am'] ?? date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notizen</label>
                                <textarea class="form-control" name="notizen" rows="2"><?= htmlspecialchars($gespeichert['notizen'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="speichern" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg me-1"></i>Speichern
                            </button>
                            <a href="pdf_u30.php?jahr=<?= $jahr ?>&monat=<?= $monat ?>&zeitraum=<?= $zeitraumTyp ?>" 
                               class="btn btn-outline-danger btn-lg" target="_blank">
                                <i class="bi bi-file-pdf me-1"></i>PDF Vorschau
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Zeitraum-Auswahl wechseln
        document.getElementById('zeitraumSelect')?.addEventListener('change', function() {
            const monatSelect = document.getElementById('monatSelect');
            monatSelect.innerHTML = '';
            
            if (this.value === 'quartal') {
                const quartale = ['Q1 (Jan-Mär)', 'Q2 (Apr-Jun)', 'Q3 (Jul-Sep)', 'Q4 (Okt-Dez)'];
                quartale.forEach((q, i) => {
                    const opt = document.createElement('option');
                    opt.value = i + 1;
                    opt.textContent = q;
                    monatSelect.appendChild(opt);
                });
            } else {
                const monate = ['Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                               'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
                monate.forEach((m, i) => {
                    const opt = document.createElement('option');
                    opt.value = i + 1;
                    opt.textContent = m;
                    monatSelect.appendChild(opt);
                });
            }
        });
    </script>
</body>
</html>
