<?php
/**
 * Einkommensteuererklärung (E1a) für Österreich
 * EKassa360 - Version 1.2
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'list';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Löschen
    if (isset($_POST['delete'])) {
        if (deleteEinkommensteuer($_POST['id'])) {
            setFlashMessage('success', 'Einkommensteuererklärung gelöscht.');
        } else {
            setFlashMessage('danger', 'Fehler beim Löschen.');
        }
        header('Location: einkommensteuer.php');
        exit;
    }
    
    // Speichern
    if (isset($_POST['speichern'])) {
        $data = [
            'jahr' => $_POST['jahr'],
            'kz9040' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9040'])),
            'kz9050' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9050'])),
            'kz9100' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9100'])),
            'kz9110' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9110'])),
            'kz9120' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9120'])),
            'kz9130' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9130'])),
            'kz9134' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9134'] ?? 0)),
            'kz9135' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9135'] ?? 0)),
            'kz9140' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9140'])),
            'kz9150' => floatval(str_replace(['.', ','], ['', '.'], $_POST['kz9150'])),
            'gewinn_verlust' => floatval(str_replace(['.', ','], ['', '.'], $_POST['gewinn_verlust'])),
            'eingereicht' => isset($_POST['eingereicht']) ? 1 : 0,
            'eingereicht_am' => $_POST['eingereicht_am'] ?: null,
            'notizen' => $_POST['notizen'] ?? null
        ];
        
        if (saveEinkommensteuer($data)) {
            setFlashMessage('success', 'Einkommensteuererklärung gespeichert.');
            header('Location: einkommensteuer.php');
            exit;
        } else {
            setFlashMessage('danger', 'Fehler beim Speichern.');
        }
    }
}

// Daten laden
$firma = getFirmendaten();
$alleErklaerungen = getEinkommensteuern();

// Für Bearbeitung/Ansicht
$jahr = $_GET['jahr'] ?? date('Y') - 1;

// Bestehende Erklärung laden oder neu berechnen
$gespeichert = null;
$e1a = null;
$readonly = false;
$afaBuchungen = [];

if ($action === 'view' || $action === 'edit' || $action === 'new') {
    $gespeichert = getEinkommensteuerJahr($jahr);
    $afaBuchungen = getAfaBuchungenJahr($jahr);
    
    if ($gespeichert && $gespeichert['eingereicht']) {
        $readonly = true;
        $e1a = $gespeichert;
    } elseif ($gespeichert) {
        $e1a = $gespeichert;
    } else {
        $e1a = berechneEinkommensteuer($jahr);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einkommensteuer (E1a) - EKassa360</title>
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
                    <h1 class="h2"><i class="bi bi-file-earmark-ruled me-2"></i>Einkommensteuererklärungen (E1a)</h1>
                    <a href="?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Neue Erklärung
                    </a>
                </div>

                <!-- Schnellauswahl -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Neue Erklärung berechnen</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="action" value="new">
                            <div class="col-md-4">
                                <label class="form-label">Jahr</label>
                                <select name="jahr" class="form-select">
                                    <?php for ($j = date('Y'); $j >= date('Y') - 10; $j--): ?>
                                    <option value="<?= $j ?>"><?= $j ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-calculator me-1"></i>Berechnen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Liste -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Gespeicherte Erklärungen</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($alleErklaerungen)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Noch keine Einkommensteuererklärungen gespeichert.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Jahr</th>
                                            <th class="text-end">Einnahmen</th>
                                            <th class="text-end">Ausgaben</th>
                                            <th class="text-end">Gewinn/Verlust</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-end">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alleErklaerungen as $e): 
                                            $einnahmen = $e['kz9040'] + $e['kz9050'];
                                            $ausgaben = $e['kz9100'] + $e['kz9110'] + $e['kz9120'] + $e['kz9130'] + ($e['kz9134'] ?? 0) + ($e['kz9135'] ?? 0) + $e['kz9140'] + $e['kz9150'];
                                        ?>
                                        <tr>
                                            <td><strong><?= $e['jahr'] ?></strong></td>
                                            <td class="text-end text-success"><?= formatBetrag($einnahmen) ?></td>
                                            <td class="text-end text-danger"><?= formatBetrag($ausgaben) ?></td>
                                            <td class="text-end <?= $e['gewinn_verlust'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <strong><?= formatBetrag($e['gewinn_verlust']) ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($e['eingereicht']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-check-circle me-1"></i>Gesendet
                                                    </span>
                                                    <?php if ($e['eingereicht_am']): ?>
                                                    <br><small class="text-muted"><?= formatDatum($e['eingereicht_am']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-clock me-1"></i>Entwurf
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($e['eingereicht']): ?>
                                                    <a href="?action=view&jahr=<?= $e['jahr'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Ansehen">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="pdf_e1a.php?jahr=<?= $e['jahr'] ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="PDF" target="_blank">
                                                        <i class="bi bi-file-pdf"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                                        <button type="submit" name="delete" class="btn btn-sm btn-outline-secondary" title="Löschen">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <a href="?action=edit&jahr=<?= $e['jahr'] ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Bearbeiten">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="pdf_e1a.php?jahr=<?= $e['jahr'] ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="PDF" target="_blank">
                                                        <i class="bi bi-file-pdf"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich löschen?');">
                                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
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
                <!-- FORMULAR -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-file-earmark-ruled me-2"></i>Einkommensteuer (E1a) <?= $jahr ?>
                        <?php if ($readonly): ?>
                            <span class="badge bg-success ms-2">Gesendet</span>
                        <?php endif; ?>
                    </h1>
                    <div>
                        <?php if ($readonly): ?>
                            <a href="pdf_e1a.php?jahr=<?= $jahr ?>" 
                               class="btn btn-danger me-2" target="_blank">
                                <i class="bi bi-file-pdf me-1"></i>PDF
                            </a>
                        <?php endif; ?>
                        <a href="einkommensteuer.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Zurück
                        </a>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="jahr" value="<?= $jahr ?>">

                    <!-- Firmendaten -->
                    <div class="card mb-4 <?= $readonly ? 'border-success' : '' ?>">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">E1a - Beilage zur Einkommensteuererklärung <?= $jahr ?></h5>
                                <?php if ($readonly && $gespeichert['eingereicht_am']): ?>
                                    <span>Gesendet am: <?= formatDatum($gespeichert['eingereicht_am']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><?= htmlspecialchars($firma['name'] ?? '') ?></strong><br>
                                    <?= htmlspecialchars($firma['strasse'] ?? '') ?><br>
                                    <?= htmlspecialchars($firma['plz'] ?? '') ?> <?= htmlspecialchars($firma['ort'] ?? '') ?>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <strong>Steuernummer:</strong> <?= htmlspecialchars($firma['steuernummer'] ?? '-') ?><br>
                                    <strong>Finanzamt:</strong> <?= htmlspecialchars($firma['finanzamt'] ?? '-') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Betriebseinnahmen -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-arrow-down-circle me-2"></i>Betriebseinnahmen</h5>
                        </div>
                        <div class="card-body">
                            <table class="table mb-0">
                                <tr>
                                    <td width="70%">Erlöse aus Lieferungen und Leistungen (Waren, Erzeugnisse)</td>
                                    <td width="10%" class="text-center"><strong>KZ 9040</strong></td>
                                    <td width="20%">
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end einnahme" name="kz9040" 
                                                   value="<?= number_format($e1a['kz9040'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Erlöse aus Dienstleistungen</td>
                                    <td class="text-center"><strong>KZ 9050</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end einnahme" name="kz9050" 
                                                   value="<?= number_format($e1a['kz9050'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="table-success">
                                    <td><strong>Summe Betriebseinnahmen</strong></td>
                                    <td></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end fw-bold" id="summeEinnahmen" 
                                                   value="<?= number_format($e1a['kz9040'] + $e1a['kz9050'], 2, ',', '.') ?>" readonly>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Betriebsausgaben -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Betriebsausgaben</h5>
                        </div>
                        <div class="card-body">
                            <table class="table mb-0">
                                <tr>
                                    <td width="70%">Wareneinkauf, Rohstoffe, Hilfsstoffe</td>
                                    <td width="10%" class="text-center"><strong>KZ 9100</strong></td>
                                    <td width="20%">
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9100" 
                                                   value="<?= number_format($e1a['kz9100'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Fremdleistungen (Fremdpersonal, Subunternehmer)</td>
                                    <td class="text-center"><strong>KZ 9110</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9110" 
                                                   value="<?= number_format($e1a['kz9110'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Personalaufwand (eigenes Personal, Löhne, Gehälter, SV)</td>
                                    <td class="text-center"><strong>KZ 9120</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9120" 
                                                   value="<?= number_format($e1a['kz9120'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="3"><strong><i class="bi bi-graph-down me-2"></i>Abschreibungen (AfA)</strong></td>
                                </tr>
                                <tr>
                                    <td class="ps-4">
                                        AfA normal (linear, GWG)
                                        <?php 
                                        $afa9130 = array_filter($afaBuchungen, fn($a) => ($a['e1a_kennzahl'] ?? '9130') == '9130');
                                        if (!empty($afa9130)): ?>
                                            <small class="text-muted">(<?= count($afa9130) ?> Anlagegüter)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><strong>KZ 9130</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9130" 
                                                   value="<?= number_format($e1a['kz9130'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4">
                                        AfA degressiv (§ 7 Abs. 1a)
                                        <?php 
                                        $afa9134 = array_filter($afaBuchungen, fn($a) => ($a['e1a_kennzahl'] ?? '') == '9134');
                                        if (!empty($afa9134)): ?>
                                            <small class="text-muted">(<?= count($afa9134) ?> Anlagegüter)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><strong>KZ 9134</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9134" 
                                                   value="<?= number_format($e1a['kz9134'] ?? 0, 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="ps-4">
                                        AfA Gebäude beschleunigt (§ 8 Abs. 1a)
                                        <?php 
                                        $afa9135 = array_filter($afaBuchungen, fn($a) => ($a['e1a_kennzahl'] ?? '') == '9135');
                                        if (!empty($afa9135)): ?>
                                            <small class="text-muted">(<?= count($afa9135) ?> Anlagegüter)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><strong>KZ 9135</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9135" 
                                                   value="<?= number_format($e1a['kz9135'] ?? 0, 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Aufwendungen für Betriebsräumlichkeiten (Miete, Betriebskosten)</td>
                                    <td class="text-center"><strong>KZ 9140</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9140" 
                                                   value="<?= number_format($e1a['kz9140'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Sonstige Betriebsausgaben</td>
                                    <td class="text-center"><strong>KZ 9150</strong></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end ausgabe" name="kz9150" 
                                                   value="<?= number_format($e1a['kz9150'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="table-danger">
                                    <td><strong>Summe Betriebsausgaben</strong></td>
                                    <td></td>
                                    <td>
                                        <div class="input-group">
                                            <span class="input-group-text">€</span>
                                            <input type="text" class="form-control text-end fw-bold" id="summeAusgaben" 
                                                   value="<?= number_format($e1a['kz9100'] + $e1a['kz9110'] + $e1a['kz9120'] + $e1a['kz9130'] + ($e1a['kz9134'] ?? 0) + ($e1a['kz9135'] ?? 0) + $e1a['kz9140'] + $e1a['kz9150'], 2, ',', '.') ?>" readonly>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Ergebnis -->
                    <div class="card mb-4 <?= $e1a['gewinn_verlust'] >= 0 ? 'border-success' : 'border-danger' ?>">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-<?= $e1a['gewinn_verlust'] >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?> me-2"></i>
                                <?= $e1a['gewinn_verlust'] >= 0 ? 'Gewinn' : 'Verlust' ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <p class="mb-0 fs-5">Betriebseinnahmen − Betriebsausgaben</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control text-end fw-bold fs-4" name="gewinn_verlust" id="gewinnVerlust"
                                               value="<?= number_format($e1a['gewinn_verlust'], 2, ',', '.') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AfA Details -->
                    <?php if (!empty($afaBuchungen)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>AfA-Details für <?= $jahr ?></h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Anlagegut</th>
                                        <th>Anschaffung</th>
                                        <th class="text-end">Wert</th>
                                        <th class="text-end">AfA <?= $jahr ?></th>
                                        <th class="text-end">Restwert</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($afaBuchungen as $afa): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($afa['bezeichnung']) ?></td>
                                        <td><?= formatDatum($afa['anschaffungsdatum']) ?></td>
                                        <td class="text-end"><?= formatBetrag($afa['anschaffungswert']) ?></td>
                                        <td class="text-end"><?= formatBetrag($afa['afa_betrag']) ?></td>
                                        <td class="text-end"><?= formatBetrag($afa['restwert_nach']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <th colspan="3">Gesamt AfA</th>
                                        <th class="text-end"><?= formatBetrag($e1a['kz9120']) ?></th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

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
                                        <div class="form-text">Nach dem Senden kann die Erklärung nicht mehr bearbeitet werden.</div>
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
                            <a href="pdf_e1a.php?jahr=<?= $jahr ?>" 
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
    <?php if (!$readonly): ?>
    <script>
        // Automatische Berechnung
        function berechne() {
            const parseNum = (val) => parseFloat(val.replace(/\./g, '').replace(',', '.')) || 0;
            const formatNum = (num) => num.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            let einnahmen = 0;
            document.querySelectorAll('.einnahme').forEach(el => einnahmen += parseNum(el.value));
            
            let ausgaben = 0;
            document.querySelectorAll('.ausgabe').forEach(el => ausgaben += parseNum(el.value));
            
            document.getElementById('summeEinnahmen').value = formatNum(einnahmen);
            document.getElementById('summeAusgaben').value = formatNum(ausgaben);
            document.getElementById('gewinnVerlust').value = formatNum(einnahmen - ausgaben);
        }
        
        document.querySelectorAll('.einnahme, .ausgabe').forEach(el => {
            el.addEventListener('input', berechne);
        });
    </script>
    <?php endif; ?>
</body>
</html>
