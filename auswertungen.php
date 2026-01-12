<?php
/**
 * Auswertungen - Berichte und Statistiken
 * BWA, Monatsübersicht, Jahresvergleich, Kategorieauswertung
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();

// Parameter
$jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : date('Y');
$bericht = isset($_GET['bericht']) ? $_GET['bericht'] : 'uebersicht';

// Verfügbare Jahre
$stmtJahre = $db->query("SELECT DISTINCT YEAR(datum) as jahr FROM rechnungen ORDER BY jahr DESC");
$verfuegbareJahre = $stmtJahre->fetchAll(PDO::FETCH_COLUMN);
if (empty($verfuegbareJahre)) {
    $verfuegbareJahre = [date('Y')];
}

// Monatliche Übersicht
$monatsDaten = [];
for ($m = 1; $m <= 12; $m++) {
    $stmtMonat = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN typ = 'einnahme' THEN netto_betrag ELSE 0 END), 0) as einnahmen,
            COALESCE(SUM(CASE WHEN typ = 'ausgabe' THEN netto_betrag ELSE 0 END), 0) as ausgaben,
            COALESCE(SUM(CASE WHEN typ = 'einnahme' THEN ust_betrag ELSE 0 END), 0) as ust_einnahmen,
            COALESCE(SUM(CASE WHEN typ = 'ausgabe' THEN ust_betrag ELSE 0 END), 0) as vorsteuer
        FROM rechnungen 
        WHERE YEAR(datum) = ? AND MONTH(datum) = ?
    ");
    $stmtMonat->execute([$jahr, $m]);
    $daten = $stmtMonat->fetch(PDO::FETCH_ASSOC);
    $daten['gewinn'] = $daten['einnahmen'] - $daten['ausgaben'];
    $daten['ust_zahllast'] = $daten['ust_einnahmen'] - $daten['vorsteuer'];
    $monatsDaten[$m] = $daten;
}

// Jahressummen
$jahresSummen = [
    'einnahmen' => array_sum(array_column($monatsDaten, 'einnahmen')),
    'ausgaben' => array_sum(array_column($monatsDaten, 'ausgaben')),
    'ust_einnahmen' => array_sum(array_column($monatsDaten, 'ust_einnahmen')),
    'vorsteuer' => array_sum(array_column($monatsDaten, 'vorsteuer')),
];
$jahresSummen['gewinn'] = $jahresSummen['einnahmen'] - $jahresSummen['ausgaben'];
$jahresSummen['ust_zahllast'] = $jahresSummen['ust_einnahmen'] - $jahresSummen['vorsteuer'];

// Kategorieauswertung
$stmtKategorien = $db->prepare("
    SELECT 
        k.name as kategorie,
        k.typ,
        COUNT(r.id) as anzahl,
        COALESCE(SUM(r.netto_betrag), 0) as summe
    FROM kategorien k
    LEFT JOIN rechnungen r ON k.id = r.kategorie_id AND YEAR(r.datum) = ?
    GROUP BY k.id
    ORDER BY k.typ, summe DESC
");
$stmtKategorien->execute([$jahr]);
$kategorieAuswertung = $stmtKategorien->fetchAll(PDO::FETCH_ASSOC);

// Top Kunden/Lieferanten
$stmtTopEinnahmen = $db->prepare("
    SELECT kunde_lieferant, SUM(netto_betrag) as summe, COUNT(*) as anzahl
    FROM rechnungen 
    WHERE typ = 'einnahme' AND YEAR(datum) = ? AND kunde_lieferant != ''
    GROUP BY kunde_lieferant
    ORDER BY summe DESC
    LIMIT 10
");
$stmtTopEinnahmen->execute([$jahr]);
$topKunden = $stmtTopEinnahmen->fetchAll(PDO::FETCH_ASSOC);

$stmtTopAusgaben = $db->prepare("
    SELECT kunde_lieferant, SUM(netto_betrag) as summe, COUNT(*) as anzahl
    FROM rechnungen 
    WHERE typ = 'ausgabe' AND YEAR(datum) = ? AND kunde_lieferant != ''
    GROUP BY kunde_lieferant
    ORDER BY summe DESC
    LIMIT 10
");
$stmtTopAusgaben->execute([$jahr]);
$topLieferanten = $stmtTopAusgaben->fetchAll(PDO::FETCH_ASSOC);

// Jahresvergleich (letzten 3 Jahre)
$jahresVergleich = [];
for ($j = $jahr - 2; $j <= $jahr; $j++) {
    $stmtVergleich = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN typ = 'einnahme' THEN netto_betrag ELSE 0 END), 0) as einnahmen,
            COALESCE(SUM(CASE WHEN typ = 'ausgabe' THEN netto_betrag ELSE 0 END), 0) as ausgaben
        FROM rechnungen 
        WHERE YEAR(datum) = ?
    ");
    $stmtVergleich->execute([$j]);
    $vergleichDaten = $stmtVergleich->fetch(PDO::FETCH_ASSOC);
    $vergleichDaten['gewinn'] = $vergleichDaten['einnahmen'] - $vergleichDaten['ausgaben'];
    $jahresVergleich[$j] = $vergleichDaten;
}

// Offene Rechnungen
$stmtOffen = $db->prepare("
    SELECT * FROM rechnungen 
    WHERE bezahlt = 0 AND YEAR(datum) = ?
    ORDER BY datum ASC
");
$stmtOffen->execute([$jahr]);
$offeneRechnungen = $stmtOffen->fetchAll(PDO::FETCH_ASSOC);
$summeOffenEinnahmen = 0;
$summeOffenAusgaben = 0;
foreach ($offeneRechnungen as $r) {
    if ($r['typ'] == 'einnahme') {
        $summeOffenEinnahmen += $r['brutto_betrag'];
    } else {
        $summeOffenAusgaben += $r['brutto_betrag'];
    }
}

// USt-Übersicht nach Sätzen
$stmtUst = $db->prepare("
    SELECT 
        u.satz,
        r.typ,
        COUNT(r.id) as anzahl,
        COALESCE(SUM(r.netto_betrag), 0) as netto,
        COALESCE(SUM(r.ust_betrag), 0) as ust
    FROM ust_saetze u
    LEFT JOIN rechnungen r ON u.id = r.ust_satz_id AND YEAR(r.datum) = ?
    GROUP BY u.id, r.typ
    ORDER BY u.satz DESC
");
$stmtUst->execute([$jahr]);
$ustAuswertung = $stmtUst->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Auswertungen';
$monate = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Buchhaltung</title>
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
                    <h1 class="h2"><i class="bi bi-bar-chart-line me-2"></i><?= $pageTitle ?> <?= $jahr ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="input-group me-2">
                            <label class="input-group-text" for="jahrSelect">Jahr</label>
                            <select class="form-select" id="jahrSelect" onchange="window.location.href='?jahr='+this.value">
                                <?php foreach ($verfuegbareJahre as $j): ?>
                                    <option value="<?= $j ?>" <?= $j == $jahr ? 'selected' : '' ?>><?= $j ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>Drucken
                        </button>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="berichteTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#uebersicht">
                            <i class="bi bi-grid me-1"></i>Übersicht
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#monatsuebersicht">
                            <i class="bi bi-calendar3 me-1"></i>Monatsübersicht
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#kategorien">
                            <i class="bi bi-tags me-1"></i>Kategorien
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#kundenlieferanten">
                            <i class="bi bi-people me-1"></i>Kunden/Lieferanten
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#offene">
                            <i class="bi bi-clock-history me-1"></i>Offene Posten
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Übersicht Tab -->
                    <div class="tab-pane fade show active" id="uebersicht">
                        <!-- Kennzahlen -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">Einnahmen (netto)</h6>
                                        <h3 class="card-title mb-0"><?= formatBetrag($jahresSummen['einnahmen']) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">Ausgaben (netto)</h6>
                                        <h3 class="card-title mb-0"><?= formatBetrag($jahresSummen['ausgaben']) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-<?= $jahresSummen['gewinn'] >= 0 ? 'primary' : 'warning' ?> text-white">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">Gewinn/Verlust</h6>
                                        <h3 class="card-title mb-0"><?= formatBetrag($jahresSummen['gewinn']) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-secondary text-white">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">USt-Zahllast</h6>
                                        <h3 class="card-title mb-0"><?= formatBetrag($jahresSummen['ust_zahllast']) ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Monatlicher Verlauf</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="monatsverlaufChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Jahresvergleich</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="jahresvergleichChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kurzübersicht Tabelle -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Jahresvergleich Tabelle</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-striped">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Jahr</th>
                                            <th class="text-end">Einnahmen</th>
                                            <th class="text-end">Ausgaben</th>
                                            <th class="text-end">Gewinn/Verlust</th>
                                            <th class="text-end">Veränderung</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $vorjahresGewinn = null;
                                        foreach ($jahresVergleich as $j => $daten): 
                                            $veraenderung = null;
                                            if ($vorjahresGewinn !== null && $vorjahresGewinn != 0) {
                                                $veraenderung = (($daten['gewinn'] - $vorjahresGewinn) / abs($vorjahresGewinn)) * 100;
                                            }
                                        ?>
                                            <tr class="<?= $j == $jahr ? 'table-primary' : '' ?>">
                                                <td><strong><?= $j ?></strong></td>
                                                <td class="text-end"><?= formatBetrag($daten['einnahmen']) ?></td>
                                                <td class="text-end"><?= formatBetrag($daten['ausgaben']) ?></td>
                                                <td class="text-end <?= $daten['gewinn'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    <strong><?= formatBetrag($daten['gewinn']) ?></strong>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($veraenderung !== null): ?>
                                                        <span class="<?= $veraenderung >= 0 ? 'text-success' : 'text-danger' ?>">
                                                            <?= $veraenderung >= 0 ? '+' : '' ?><?= number_format($veraenderung, 1, ',', '.') ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php 
                                            $vorjahresGewinn = $daten['gewinn'];
                                        endforeach; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Monatsübersicht Tab -->
                    <div class="tab-pane fade" id="monatsuebersicht">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Monatsübersicht <?= $jahr ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Monat</th>
                                                <th class="text-end">Einnahmen</th>
                                                <th class="text-end">Ausgaben</th>
                                                <th class="text-end">Gewinn/Verlust</th>
                                                <th class="text-end">USt</th>
                                                <th class="text-end">Vorsteuer</th>
                                                <th class="text-end">Zahllast</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($monatsDaten as $m => $daten): ?>
                                                <tr>
                                                    <td><?= $monate[$m] ?></td>
                                                    <td class="text-end text-success"><?= formatBetrag($daten['einnahmen']) ?></td>
                                                    <td class="text-end text-danger"><?= formatBetrag($daten['ausgaben']) ?></td>
                                                    <td class="text-end <?= $daten['gewinn'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                        <strong><?= formatBetrag($daten['gewinn']) ?></strong>
                                                    </td>
                                                    <td class="text-end"><?= formatBetrag($daten['ust_einnahmen']) ?></td>
                                                    <td class="text-end"><?= formatBetrag($daten['vorsteuer']) ?></td>
                                                    <td class="text-end <?= $daten['ust_zahllast'] >= 0 ? '' : 'text-success' ?>">
                                                        <?= formatBetrag($daten['ust_zahllast']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-dark">
                                            <tr>
                                                <th>Gesamt</th>
                                                <th class="text-end"><?= formatBetrag($jahresSummen['einnahmen']) ?></th>
                                                <th class="text-end"><?= formatBetrag($jahresSummen['ausgaben']) ?></th>
                                                <th class="text-end"><?= formatBetrag($jahresSummen['gewinn']) ?></th>
                                                <th class="text-end"><?= formatBetrag($jahresSummen['ust_einnahmen']) ?></th>
                                                <th class="text-end"><?= formatBetrag($jahresSummen['vorsteuer']) ?></th>
                                                <th class="text-end"><?= formatBetrag($jahresSummen['ust_zahllast']) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kategorien Tab -->
                    <div class="tab-pane fade" id="kategorien">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="bi bi-arrow-down-circle me-2"></i>Einnahmen nach Kategorien</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Kategorie</th>
                                                    <th class="text-center">Anzahl</th>
                                                    <th class="text-end">Summe</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $summeEinnahmenKat = 0;
                                                foreach ($kategorieAuswertung as $kat): 
                                                    if ($kat['typ'] == 'einnahme'):
                                                        $summeEinnahmenKat += $kat['summe'];
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($kat['kategorie']) ?></td>
                                                        <td class="text-center"><?= $kat['anzahl'] ?></td>
                                                        <td class="text-end"><?= formatBetrag($kat['summe']) ?></td>
                                                    </tr>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </tbody>
                                            <tfoot class="table-primary">
                                                <tr>
                                                    <th>Gesamt</th>
                                                    <th></th>
                                                    <th class="text-end"><?= formatBetrag($summeEinnahmenKat) ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="bi bi-arrow-up-circle me-2"></i>Ausgaben nach Kategorien</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Kategorie</th>
                                                    <th class="text-center">Anzahl</th>
                                                    <th class="text-end">Summe</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $summeAusgabenKat = 0;
                                                foreach ($kategorieAuswertung as $kat): 
                                                    if ($kat['typ'] == 'ausgabe'):
                                                        $summeAusgabenKat += $kat['summe'];
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($kat['kategorie']) ?></td>
                                                        <td class="text-center"><?= $kat['anzahl'] ?></td>
                                                        <td class="text-end"><?= formatBetrag($kat['summe']) ?></td>
                                                    </tr>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </tbody>
                                            <tfoot class="table-primary">
                                                <tr>
                                                    <th>Gesamt</th>
                                                    <th></th>
                                                    <th class="text-end"><?= formatBetrag($summeAusgabenKat) ?></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <canvas id="kategorienChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Kunden/Lieferanten Tab -->
                    <div class="tab-pane fade" id="kundenlieferanten">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>Top 10 Kunden</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($topKunden)): ?>
                                            <p class="text-muted">Keine Kundendaten vorhanden.</p>
                                        <?php else: ?>
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Kunde</th>
                                                        <th class="text-center">Rechnungen</th>
                                                        <th class="text-end">Umsatz</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($topKunden as $i => $kunde): ?>
                                                        <tr>
                                                            <td><?= $i + 1 ?></td>
                                                            <td><?= htmlspecialchars($kunde['kunde_lieferant']) ?></td>
                                                            <td class="text-center"><?= $kunde['anzahl'] ?></td>
                                                            <td class="text-end"><?= formatBetrag($kunde['summe']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="bi bi-truck me-2"></i>Top 10 Lieferanten</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($topLieferanten)): ?>
                                            <p class="text-muted">Keine Lieferantendaten vorhanden.</p>
                                        <?php else: ?>
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Lieferant</th>
                                                        <th class="text-center">Rechnungen</th>
                                                        <th class="text-end">Ausgaben</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($topLieferanten as $i => $lief): ?>
                                                        <tr>
                                                            <td><?= $i + 1 ?></td>
                                                            <td><?= htmlspecialchars($lief['kunde_lieferant']) ?></td>
                                                            <td class="text-center"><?= $lief['anzahl'] ?></td>
                                                            <td class="text-end"><?= formatBetrag($lief['summe']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Offene Posten Tab -->
                    <div class="tab-pane fade" id="offene">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card bg-warning">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">Offene Forderungen</h6>
                                        <h3 class="card-title mb-0"><?= formatBetrag($summeOffenEinnahmen) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2">Offene Verbindlichkeiten</h6>
                                        <h3 class="card-title mb-0"><?= formatBetrag($summeOffenAusgaben) ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Offene Rechnungen</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($offeneRechnungen)): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle me-2"></i>Keine offenen Rechnungen vorhanden!
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Datum</th>
                                                    <th>Nr.</th>
                                                    <th>Typ</th>
                                                    <th>Kunde/Lieferant</th>
                                                    <th>Beschreibung</th>
                                                    <th class="text-end">Brutto</th>
                                                    <th>Aktion</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($offeneRechnungen as $r): ?>
                                                    <tr>
                                                        <td><?= formatDatum($r['datum']) ?></td>
                                                        <td><?= htmlspecialchars($r['rechnungsnummer']) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $r['typ'] == 'einnahme' ? 'success' : 'danger' ?>">
                                                                <?= ucfirst($r['typ']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($r['kunde_lieferant']) ?></td>
                                                        <td><?= htmlspecialchars(substr($r['beschreibung'], 0, 50)) ?></td>
                                                        <td class="text-end"><?= formatBetrag($r['brutto_betrag']) ?></td>
                                                        <td>
                                                            <a href="rechnungen.php?edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Monatsverlauf Chart
        const monatsverlaufCtx = document.getElementById('monatsverlaufChart').getContext('2d');
        new Chart(monatsverlaufCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
                datasets: [
                    {
                        label: 'Einnahmen',
                        data: <?= json_encode(array_values(array_map(fn($d) => $d['einnahmen'], $monatsDaten))) ?>,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        borderColor: 'rgb(25, 135, 84)',
                        borderWidth: 1
                    },
                    {
                        label: 'Ausgaben',
                        data: <?= json_encode(array_values(array_map(fn($d) => $d['ausgaben'], $monatsDaten))) ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgb(220, 53, 69)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '€ ' + value.toLocaleString('de-AT');
                            }
                        }
                    }
                }
            }
        });

        // Jahresvergleich Chart
        const jahresvergleichCtx = document.getElementById('jahresvergleichChart').getContext('2d');
        new Chart(jahresvergleichCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($jahresVergleich)) ?>,
                datasets: [{
                    label: 'Gewinn/Verlust',
                    data: <?= json_encode(array_values(array_map(fn($d) => $d['gewinn'], $jahresVergleich))) ?>,
                    backgroundColor: <?= json_encode(array_values(array_map(fn($d) => $d['gewinn'] >= 0 ? 'rgba(25, 135, 84, 0.7)' : 'rgba(220, 53, 69, 0.7)', $jahresVergleich))) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return '€ ' + value.toLocaleString('de-AT');
                            }
                        }
                    }
                }
            }
        });

        // Kategorien Chart
        const kategorienCtx = document.getElementById('kategorienChart').getContext('2d');
        const einnahmenKat = <?= json_encode(array_values(array_filter($kategorieAuswertung, fn($k) => $k['typ'] == 'einnahme'))) ?>;
        const ausgabenKat = <?= json_encode(array_values(array_filter($kategorieAuswertung, fn($k) => $k['typ'] == 'ausgabe'))) ?>;
        
        new Chart(kategorienCtx, {
            type: 'bar',
            data: {
                labels: [...einnahmenKat.map(k => k.kategorie), ...ausgabenKat.map(k => k.kategorie)],
                datasets: [{
                    label: 'Betrag',
                    data: [...einnahmenKat.map(k => k.summe), ...ausgabenKat.map(k => -k.summe)],
                    backgroundColor: [...einnahmenKat.map(() => 'rgba(25, 135, 84, 0.7)'), ...ausgabenKat.map(() => 'rgba(220, 53, 69, 0.7)')]
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                scales: {
                    x: {
                        ticks: {
                            callback: function(value) {
                                return '€ ' + Math.abs(value).toLocaleString('de-AT');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
