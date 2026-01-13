<?php
/**
 * Anlagegüter-Verwaltung
 * Version 1.1 - Angepasst an neue Datenbankstruktur
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
    if (isset($_POST['save'])) {
        $data = [
            'id' => $_POST['id'] ?? null,
            'buchungsnummer' => $_POST['buchungsnummer'] ?: null,
            'bezeichnung' => $_POST['bezeichnung'],
            'kategorie' => $_POST['kategorie'] ?? 'Sonstige',
            'anschaffungsdatum' => $_POST['anschaffungsdatum'],
            'netto_betrag' => floatval(str_replace(',', '.', $_POST['netto_betrag'])),
            'ust_satz_id' => $_POST['ust_satz_id'] ?: null,
            'nutzungsdauer' => intval($_POST['nutzungsdauer']),
            'afa_methode' => $_POST['afa_methode'],
            'e1a_kennzahl' => $_POST['e1a_kennzahl'] ?? '9130',
            'restwert' => floatval(str_replace(',', '.', $_POST['restwert'] ?? 1)),
            'status' => isset($_POST['ausgeschieden']) ? 'ausgeschieden' : 'aktiv',
            'ausscheidungsdatum' => $_POST['ausscheidungsdatum'] ?: null,
            'ausscheidungsgrund' => $_POST['ausscheidungsgrund'] ?? null,
            'notizen' => $_POST['notizen']
        ];
        
        $result = saveAnlagegut($data);
        if ($result) {
            setFlashMessage('success', 'Anlagegut erfolgreich gespeichert.');
            header('Location: anlagegueter.php');
            exit;
        } else {
            setFlashMessage('danger', 'Fehler beim Speichern.');
        }
    }
    
    if (isset($_POST['delete'])) {
        if (deleteAnlagegut($_POST['id'])) {
            setFlashMessage('success', 'Anlagegut gelöscht.');
        } else {
            setFlashMessage('danger', 'Fehler beim Löschen.');
        }
        header('Location: anlagegueter.php');
        exit;
    }
}

// Daten laden
$anlagegut = $id ? getAnlagegut($id) : null;
$anlagegueter = getAnlagegueter(false); // Alle anzeigen
$ustSaetze = getUstSaetze();

// Typische Kategorien für Anlagegüter
$kategorieOptionen = ['EDV', 'Einrichtung', 'Maschinen', 'Fahrzeuge', 'Werkzeuge', 'Software', 'Gebäude', 'Sonstige'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anlagegüter - Buchhaltung</title>
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

                <?php if ($action === 'new' || $action === 'edit'): ?>
                <!-- Formular -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-<?= $action === 'new' ? 'plus-circle' : 'pencil' ?> me-2"></i>
                        <?= $action === 'new' ? 'Neues Anlagegut' : 'Anlagegut bearbeiten' ?>
                    </h1>
                    <a href="anlagegueter.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Zurück
                    </a>
                </div>

                <div class="form-container">
                    <form method="POST" class="card">
                        <div class="card-body">
                            <input type="hidden" name="id" value="<?= $anlagegut['id'] ?? '' ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">Buchungsnr.</label>
                                    <input type="number" class="form-control" name="buchungsnummer" 
                                           value="<?= htmlspecialchars($anlagegut['buchungsnummer'] ?? '') ?>"
                                           placeholder="<?= $action === 'new' ? 'Auto' : '' ?>">
                                    <small class="text-muted">Leer = auto</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Bezeichnung</label>
                                    <input type="text" class="form-control" name="bezeichnung" 
                                           value="<?= htmlspecialchars($anlagegut['bezeichnung'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Kategorie</label>
                                    <input type="text" class="form-control" name="kategorie" list="kategorien"
                                           value="<?= htmlspecialchars($anlagegut['kategorie'] ?? 'Sonstige') ?>">
                                    <datalist id="kategorien">
                                        <?php foreach ($kategorieOptionen as $kat): ?>
                                        <option value="<?= $kat ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label required">Anschaffungsdatum</label>
                                    <input type="date" class="form-control" name="anschaffungsdatum" id="anschaffungsdatum"
                                           value="<?= $anlagegut['anschaffungsdatum'] ?? date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label required">Netto-Betrag</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control" name="netto_betrag" id="netto_betrag"
                                               value="<?= isset($anlagegut['netto_betrag']) ? number_format($anlagegut['netto_betrag'], 2, ',', '') : (isset($anlagegut['anschaffungswert']) ? number_format($anlagegut['anschaffungswert'], 2, ',', '') : '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">USt-Satz (Vorsteuer)</label>
                                    <select class="form-select" name="ust_satz_id" id="ust_satz_id">
                                        <option value="">-- Keine USt --</option>
                                        <?php foreach ($ustSaetze as $ust): ?>
                                        <option value="<?= $ust['id'] ?>" data-prozent="<?= $ust['satz'] ?>"
                                                <?= ($anlagegut['ust_satz_id'] ?? '') == $ust['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ust['bezeichnung']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Für U30 Vorsteuer</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Brutto (AfA-Basis)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control bg-light" id="brutto_betrag" readonly
                                               value="<?= isset($anlagegut['anschaffungswert']) ? number_format($anlagegut['anschaffungswert'], 2, ',', '') : '' ?>">
                                    </div>
                                    <small class="text-muted">= Anschaffungswert</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label required">Nutzungsdauer (Jahre)</label>
                                    <input type="number" class="form-control" name="nutzungsdauer" id="nutzungsdauer"
                                           value="<?= $anlagegut['nutzungsdauer'] ?? 5 ?>" min="1" max="50" required>
                                    <small class="text-muted">Gemäß AfA-Tabelle</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">AfA-Methode <i class="bi bi-info-circle text-primary" data-bs-toggle="collapse" data-bs-target="#afaInfo" style="cursor:pointer"></i></label>
                                    <select class="form-select" name="afa_methode" id="afaMethode">
                                        <option value="linear" <?= ($anlagegut['afa_methode'] ?? 'linear') == 'linear' ? 'selected' : '' ?>>Linear</option>
                                        <option value="degressiv" <?= ($anlagegut['afa_methode'] ?? '') == 'degressiv' ? 'selected' : '' ?>>Degressiv (30%)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">E1a Kennzahl</label>
                                    <select class="form-select" name="e1a_kennzahl" id="e1aKennzahl">
                                        <option value="9130" <?= ($anlagegut['e1a_kennzahl'] ?? '9130') == '9130' ? 'selected' : '' ?>>
                                            KZ 9130 - Normale AfA
                                        </option>
                                        <option value="9134" <?= ($anlagegut['e1a_kennzahl'] ?? '') == '9134' ? 'selected' : '' ?>>
                                            KZ 9134 - Degressive AfA
                                        </option>
                                        <option value="9135" <?= ($anlagegut['e1a_kennzahl'] ?? '') == '9135' ? 'selected' : '' ?>>
                                            KZ 9135 - Gebäude beschleunigt
                                        </option>
                                    </select>
                                    <small class="text-muted">Für Einkommensteuererklärung</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Restwert</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="text" class="form-control" name="restwert" 
                                               value="<?= isset($anlagegut['restwert']) ? number_format($anlagegut['restwert'], 2, ',', '') : '1,00' ?>">
                                    </div>
                                    <small class="text-muted">Erinnerungswert</small>
                                </div>
                            </div>

                            <!-- AfA-Methoden Erklärung -->
                            <div class="collapse mb-3" id="afaInfo">
                                <div class="card card-body bg-light">
                                    <h6 class="text-primary mb-3"><i class="bi bi-info-circle me-2"></i>AfA-Methoden erklärt</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold"><i class="bi bi-arrow-right me-1"></i>Lineare Abschreibung</h6>
                                            <p class="small mb-2">
                                                Der Anschaffungswert wird <strong>gleichmäßig</strong> über die Nutzungsdauer verteilt.
                                                Jedes Jahr wird der gleiche Betrag abgeschrieben.
                                            </p>
                                            <p class="small text-muted mb-0">
                                                <strong>Formel:</strong> (Anschaffungswert - Restwert) ÷ Nutzungsdauer<br>
                                                <strong>Beispiel:</strong> € 10.000 ÷ 5 Jahre = € 2.000/Jahr
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold"><i class="bi bi-graph-down me-1"></i>Degressive Abschreibung (30%)</h6>
                                            <p class="small mb-2">
                                                Jedes Jahr werden <strong>30% vom Restbuchwert</strong> abgeschrieben.
                                                Am Anfang höhere, später niedrigere Beträge. Ab einem bestimmten Zeitpunkt Wechsel zu linear.
                                            </p>
                                            <p class="small text-muted mb-0">
                                                <strong>Formel:</strong> Restbuchwert × 30%<br>
                                                <strong>Beispiel Jahr 1:</strong> € 10.000 × 30% = € 3.000<br>
                                                <strong>Beispiel Jahr 2:</strong> € 7.000 × 30% = € 2.100
                                            </p>
                                        </div>
                                    </div>
                                    <hr class="my-2">
                                    <p class="small text-muted mb-0">
                                        <i class="bi bi-lightbulb me-1"></i><strong>Tipp:</strong> Die degressive AfA ist seit 2020 für nach dem 30.6.2020 angeschaffte Wirtschaftsgüter möglich (§ 7 Abs. 1a EStG). Sie lohnt sich besonders bei Gütern, die am Anfang stark an Wert verlieren.
                                    </p>
                                </div>
                            </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input type="checkbox" class="form-check-input" name="ausgeschieden" id="ausgeschieden"
                                               <?= ($anlagegut['status'] ?? '') == 'ausgeschieden' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ausgeschieden">Ausgeschieden</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ausscheidungsdatum</label>
                                    <input type="date" class="form-control" name="ausscheidungsdatum" 
                                           value="<?= $anlagegut['ausscheidungsdatum'] ?? '' ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ausscheidungsgrund</label>
                                    <input type="text" class="form-control" name="ausscheidungsgrund" 
                                           value="<?= htmlspecialchars($anlagegut['ausscheidungsgrund'] ?? '') ?>"
                                           placeholder="z.B. Verkauf, Verschrottung">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notizen</label>
                                <textarea class="form-control" name="notizen" rows="2"><?= htmlspecialchars($anlagegut['notizen'] ?? '') ?></textarea>
                            </div>

                            <!-- Typische Nutzungsdauern -->
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>Typische Nutzungsdauern (AfA-Tabelle Österreich)</h6>
                                <div class="row small">
                                    <div class="col-md-4">
                                        <ul class="mb-0">
                                            <li>Computer, Notebooks: 3 Jahre</li>
                                            <li>Software: 3-4 Jahre</li>
                                            <li>Büromöbel: 10-13 Jahre</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <ul class="mb-0">
                                            <li>PKW: 8 Jahre</li>
                                            <li>LKW: 9 Jahre</li>
                                            <li>Maschinen: 5-10 Jahre</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-4">
                                        <ul class="mb-0">
                                            <li>Gebäude (Büro): 40 Jahre</li>
                                            <li>Werkzeuge: 5 Jahre</li>
                                            <li>Telefone/Handys: 3-5 Jahre</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- AfA-Vorschau wenn bearbeiten -->
                            <?php if ($anlagegut): 
                                $afaBuchungen = getAfaBuchungenAnlagegut($anlagegut['id']);
                                if (!empty($afaBuchungen)):
                            ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-graph-down me-2"></i>AfA-Verlauf</h6>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th>Jahr</th>
                                                <th class="text-end">AfA-Betrag</th>
                                                <th class="text-end">Restwert</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($afaBuchungen as $ab): ?>
                                            <tr>
                                                <td><?= $ab['jahr'] ?></td>
                                                <td class="text-end"><?= formatBetrag($ab['afa_betrag']) ?></td>
                                                <td class="text-end"><?= formatBetrag($ab['restwert_nach']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; endif; ?>
                        </div>
                        <div class="card-footer bg-light">
                            <button type="submit" name="save" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Speichern
                            </button>
                            <a href="anlagegueter.php" class="btn btn-outline-secondary">Abbrechen</a>
                        </div>
                    </form>
                </div>

                <?php else: ?>
                <!-- Liste -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-building me-2"></i>Anlagegüter</h1>
                    <a href="anlagegueter.php?action=new" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Neues Anlagegut
                    </a>
                </div>

                <!-- Tabelle -->
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>B.-Nr.</th>
                                        <th>Bezeichnung</th>
                                        <th>Kategorie</th>
                                        <th>Anschaffung</th>
                                        <th class="text-end">Netto</th>
                                        <th class="text-end">USt</th>
                                        <th class="text-end">Brutto</th>
                                        <th class="text-center">ND</th>
                                        <th>Status</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($anlagegueter)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            Keine Anlagegüter vorhanden
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php 
                                    $gesamtAK = 0;
                                    $gesamtUSt = 0;
                                    foreach ($anlagegueter as $a): 
                                        $gesamtAK += $a['anschaffungswert'];
                                        $gesamtUSt += $a['ust_betrag'] ?? 0;
                                        $jaehrlicheAfa = $a['nutzungsdauer'] > 0 ? ($a['anschaffungswert'] - $a['restwert']) / $a['nutzungsdauer'] : 0;
                                    ?>
                                    <tr class="<?= $a['status'] == 'ausgeschieden' ? 'table-secondary' : '' ?>">
                                        <td><strong><?= $a['buchungsnummer'] ?? '-' ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($a['bezeichnung']) ?></strong>
                                        </td>
                                        <td><small class="text-muted"><?= htmlspecialchars($a['kategorie'] ?? '-') ?></small></td>
                                        <td><?= formatDatum($a['anschaffungsdatum']) ?></td>
                                        <td class="text-end"><?= formatBetrag($a['netto_betrag'] ?? $a['anschaffungswert']) ?></td>
                                        <td class="text-end"><?= formatBetrag($a['ust_betrag'] ?? 0) ?></td>
                                        <td class="text-end"><?= formatBetrag($a['anschaffungswert']) ?></td>
                                        <td class="text-center"><?= $a['nutzungsdauer'] ?> J.</td>
                                        <td>
                                            <?php if ($a['status'] == 'ausgeschieden'): ?>
                                                <span class="badge bg-secondary">Ausgeschieden</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Aktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="anlagegueter.php?action=edit&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Wirklich löschen? AfA-Buchungen werden ebenfalls gelöscht!');">
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-primary fw-bold">
                                        <td colspan="4">Gesamt</td>
                                        <td class="text-end"><?= formatBetrag($gesamtAK - $gesamtUSt) ?></td>
                                        <td class="text-end"><?= formatBetrag($gesamtUSt) ?></td>
                                        <td class="text-end"><?= formatBetrag($gesamtAK) ?></td>
                                        <td colspan="3"></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="abschreibungen.php" class="btn btn-outline-primary">
                        <i class="bi bi-graph-down me-1"></i>Alle Abschreibungen anzeigen
                    </a>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Brutto-Berechnung für Anlagegüter
    document.addEventListener('DOMContentLoaded', function() {
        const nettoInput = document.getElementById('netto_betrag');
        const ustSelect = document.getElementById('ust_satz_id');
        const bruttoInput = document.getElementById('brutto_betrag');
        
        if (nettoInput && ustSelect && bruttoInput) {
            function berechne() {
                const netto = parseFloat(nettoInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const option = ustSelect.options[ustSelect.selectedIndex];
                const prozent = option ? parseFloat(option.dataset.prozent) || 0 : 0;
                const ust = netto * (prozent / 100);
                const brutto = netto + ust;
                bruttoInput.value = brutto.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            nettoInput.addEventListener('input', berechne);
            ustSelect.addEventListener('change', berechne);
            berechne(); // Initial
        }
    });
    </script>
</body>
</html>
