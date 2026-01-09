<?php
/**
 * Abschreibungen (AfA) - Übersicht
 * Zeigt alle Abschreibungen nach Jahren, AfA-Spiegel
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();

// Aktuelles Jahr oder aus Parameter
$jahr = isset($_GET['jahr']) ? (int)$_GET['jahr'] : date('Y');

// Verfügbare Jahre ermitteln
$stmtJahre = $db->query("
    SELECT DISTINCT YEAR(anschaffungsdatum) as jahr FROM anlagegueter
    UNION
    SELECT DISTINCT jahr FROM afa_buchungen
    ORDER BY jahr DESC
");
$verfuegbareJahre = $stmtJahre->fetchAll(PDO::FETCH_COLUMN);
if (empty($verfuegbareJahre)) {
    $verfuegbareJahre = [date('Y')];
}

// AfA-Buchungen für das Jahr laden
$stmtAfa = $db->prepare("
    SELECT 
        ab.*,
        ag.bezeichnung,
        ag.anschaffungswert,
        ag.anschaffungsdatum,
        ag.nutzungsdauer,
        ag.afa_methode,
        ag.restwert
    FROM afa_buchungen ab
    JOIN anlagegueter ag ON ab.anlagegut_id = ag.id
    WHERE ab.jahr = ?
    ORDER BY ag.bezeichnung
");
$stmtAfa->execute([$jahr]);
$afaBuchungen = $stmtAfa->fetchAll(PDO::FETCH_ASSOC);

// Gesamt-AfA berechnen
$gesamtAfa = array_sum(array_column($afaBuchungen, 'afa_betrag'));

// AfA-Spiegel: Alle aktiven Anlagegüter mit kumulierten Werten
$stmtSpiegel = $db->prepare("
    SELECT 
        ag.*,
        COALESCE(SUM(ab.afa_betrag), 0) as kumulierte_afa,
        ag.anschaffungswert - COALESCE(SUM(ab.afa_betrag), 0) as buchwert
    FROM anlagegueter ag
    LEFT JOIN afa_buchungen ab ON ag.id = ab.anlagegut_id AND ab.jahr <= ?
    WHERE ag.status = 'aktiv' OR (ag.status = 'ausgeschieden' AND YEAR(ag.ausscheidungsdatum) >= ?)
    GROUP BY ag.id
    ORDER BY ag.anschaffungsdatum
");
$stmtSpiegel->execute([$jahr, $jahr]);
$afaSpiegel = $stmtSpiegel->fetchAll(PDO::FETCH_ASSOC);

// Summen für AfA-Spiegel
$summeAnschaffung = array_sum(array_column($afaSpiegel, 'anschaffungswert'));
$summeKumuliert = array_sum(array_column($afaSpiegel, 'kumulierte_afa'));
$summeBuchwert = array_sum(array_column($afaSpiegel, 'buchwert'));

// AfA nach Kategorien
$stmtKategorien = $db->prepare("
    SELECT 
        ag.kategorie,
        COUNT(*) as anzahl,
        SUM(ab.afa_betrag) as afa_summe
    FROM afa_buchungen ab
    JOIN anlagegueter ag ON ab.anlagegut_id = ag.id
    WHERE ab.jahr = ?
    GROUP BY ag.kategorie
    ORDER BY afa_summe DESC
");
$stmtKategorien->execute([$jahr]);
$afaKategorien = $stmtKategorien->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Abschreibungen (AfA)';
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
                    <h1 class="h2"><i class="bi bi-graph-down me-2"></i><?= $pageTitle ?></h1>
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

                <?php displayFlashMessage(); ?>

                <!-- Übersichtskarten -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">AfA <?= $jahr ?></h6>
                                <h3 class="card-title mb-0"><?= formatBetrag($gesamtAfa) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Anlagegüter</h6>
                                <h3 class="card-title mb-0"><?= count($afaSpiegel) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Anschaffungswerte</h6>
                                <h3 class="card-title mb-0"><?= formatBetrag($summeAnschaffung) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2">Buchwerte</h6>
                                <h3 class="card-title mb-0"><?= formatBetrag($summeBuchwert) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AfA-Buchungen des Jahres -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>AfA-Buchungen <?= $jahr ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($afaBuchungen)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>Keine AfA-Buchungen für <?= $jahr ?> vorhanden.
                                <a href="anlagegueter.php" class="alert-link">Anlagegüter verwalten</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Bezeichnung</th>
                                            <th>Anschaffung</th>
                                            <th class="text-end">Ansch.-Wert</th>
                                            <th class="text-center">ND</th>
                                            <th>Methode</th>
                                            <th class="text-end">AfA <?= $jahr ?></th>
                                            <th class="text-end">Restwert</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($afaBuchungen as $buchung): ?>
                                            <tr>
                                                <td>
                                                    <a href="anlagegueter.php?edit=<?= $buchung['anlagegut_id'] ?>">
                                                        <?= htmlspecialchars($buchung['bezeichnung']) ?>
                                                    </a>
                                                </td>
                                                <td><?= formatDatum($buchung['anschaffungsdatum']) ?></td>
                                                <td class="text-end"><?= formatBetrag($buchung['anschaffungswert']) ?></td>
                                                <td class="text-center"><?= $buchung['nutzungsdauer'] ?> J.</td>
                                                <td>
                                                    <span class="badge bg-<?= $buchung['afa_methode'] == 'linear' ? 'primary' : 'warning' ?>">
                                                        <?= ucfirst($buchung['afa_methode']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end fw-bold"><?= formatBetrag($buchung['afa_betrag']) ?></td>
                                                <td class="text-end"><?= formatBetrag($buchung['restwert_nach']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <th colspan="5">Gesamt AfA <?= $jahr ?></th>
                                            <th class="text-end"><?= formatBetrag($gesamtAfa) ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- AfA-Spiegel -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>AfA-Spiegel per 31.12.<?= $jahr ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($afaSpiegel)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>Keine Anlagegüter vorhanden.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Bezeichnung</th>
                                            <th>Kategorie</th>
                                            <th>Anschaffung</th>
                                            <th class="text-end">Ansch.-Wert</th>
                                            <th class="text-end">Kum. AfA</th>
                                            <th class="text-end">Buchwert</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($afaSpiegel as $gut): ?>
                                            <tr class="<?= $gut['status'] == 'ausgeschieden' ? 'table-secondary' : '' ?>">
                                                <td><?= htmlspecialchars($gut['bezeichnung']) ?></td>
                                                <td><small><?= htmlspecialchars($gut['kategorie']) ?></small></td>
                                                <td><?= formatDatum($gut['anschaffungsdatum']) ?></td>
                                                <td class="text-end"><?= formatBetrag($gut['anschaffungswert']) ?></td>
                                                <td class="text-end"><?= formatBetrag($gut['kumulierte_afa']) ?></td>
                                                <td class="text-end fw-bold"><?= formatBetrag($gut['buchwert']) ?></td>
                                                <td class="text-center">
                                                    <?php if ($gut['status'] == 'aktiv'): ?>
                                                        <span class="badge bg-success">Aktiv</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ausgeschieden</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-dark">
                                        <tr>
                                            <th colspan="3">Summen</th>
                                            <th class="text-end"><?= formatBetrag($summeAnschaffung) ?></th>
                                            <th class="text-end"><?= formatBetrag($summeKumuliert) ?></th>
                                            <th class="text-end"><?= formatBetrag($summeBuchwert) ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- AfA nach Kategorien -->
                <?php if (!empty($afaKategorien)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>AfA nach Kategorien <?= $jahr ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Kategorie</th>
                                            <th class="text-center">Anzahl</th>
                                            <th class="text-end">AfA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($afaKategorien as $kat): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($kat['kategorie']) ?></td>
                                                <td class="text-center"><?= $kat['anzahl'] ?></td>
                                                <td class="text-end"><?= formatBetrag($kat['afa_summe']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <canvas id="afaKategorienChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <?php if (!empty($afaKategorien)): ?>
    <script>
        // AfA nach Kategorien Chart
        const kategorienCtx = document.getElementById('afaKategorienChart').getContext('2d');
        new Chart(kategorienCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($afaKategorien, 'kategorie')) ?>,
                datasets: [{
                    data: <?= json_encode(array_map('floatval', array_column($afaKategorien, 'afa_summe'))) ?>,
                    backgroundColor: [
                        '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', 
                        '#20c997', '#fd7e14', '#6c757d', '#0dcaf0', '#d63384'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
