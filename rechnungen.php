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
            'notizen' => $_POST['notizen']
        ];
        
        if (saveRechnung($data)) {
            setFlashMessage('success', 'Rechnung erfolgreich gespeichert.');
            header('Location: rechnungen.php');
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
        header('Location: rechnungen.php');
        exit;
    }
}

// Daten laden
$kategorien = getKategorien();
$ustSaetze = getUstSaetze();
$kundenLieferanten = getKundenLieferanten();
$rechnung = $id ? getRechnung($id) : null;

// Filter
$filters = [
    'typ' => $_GET['filter_typ'] ?? '',
    'jahr' => $_GET['filter_jahr'] ?? date('Y'),
    'monat' => $_GET['filter_monat'] ?? '',
    'kategorie_id' => $_GET['filter_kategorie'] ?? '',
    'bezahlt' => $_GET['filter_bezahlt'] ?? ''
];
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
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Typ</label>
                                <select name="filter_typ" class="form-select form-select-sm">
                                    <option value="">Alle</option>
                                    <option value="einnahme" <?= $filters['typ'] === 'einnahme' ? 'selected' : '' ?>>Einnahmen</option>
                                    <option value="ausgabe" <?= $filters['typ'] === 'ausgabe' ? 'selected' : '' ?>>Ausgaben</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Jahr</label>
                                <select name="filter_jahr" class="form-select form-select-sm">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?= $y ?>" <?= $filters['jahr'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Monat</label>
                                <select name="filter_monat" class="form-select form-select-sm">
                                    <option value="">Alle</option>
                                    <?php 
                                    $monate = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                                    foreach ($monate as $i => $m): ?>
                                    <option value="<?= $i+1 ?>" <?= $filters['monat'] == ($i+1) ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="filter_bezahlt" class="form-select form-select-sm">
                                    <option value="">Alle</option>
                                    <option value="1" <?= $filters['bezahlt'] === '1' ? 'selected' : '' ?>>Bezahlt</option>
                                    <option value="0" <?= $filters['bezahlt'] === '0' ? 'selected' : '' ?>>Offen</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-search me-1"></i>Filtern
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="rechnungen.php" class="btn btn-outline-secondary btn-sm w-100">Zurücksetzen</a>
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
        }
        
        document.getElementById('netto_betrag')?.addEventListener('input', berechnebrutto);
        document.getElementById('ust_satz_id')?.addEventListener('change', berechnebrutto);
        
        // Kategorien nach Typ filtern
        document.querySelectorAll('input[name="typ"]').forEach(radio => {
            radio.addEventListener('change', function() {
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
    </script>
</body>
</html>
