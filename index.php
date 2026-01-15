<?php
/**
 * Buchhaltungs-App Dashboard
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireLogin();

$currentYear = date('Y');
$currentMonth = date('n');

// Statistiken laden
$stats = getStatistics($currentYear);
$recentInvoices = getRecentInvoices(5);
$openInvoices = getOpenInvoices();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buchhaltung - Dashboard</title>
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
                    <h1 class="h2"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-primary fs-6"><?= $currentYear ?></span>
                    </div>
                </div>

                <!-- Statistik-Karten -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Einnahmen</h6>
                                        <h3 class="card-title mb-0">€ <?= number_format($stats['einnahmen'], 2, ',', '.') ?></h3>
                                    </div>
                                    <i class="bi bi-arrow-down-circle fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Ausgaben</h6>
                                        <h3 class="card-title mb-0">€ <?= number_format($stats['ausgaben'], 2, ',', '.') ?></h3>
                                    </div>
                                    <i class="bi bi-arrow-up-circle fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">USt-Zahllast</h6>
                                        <h3 class="card-title mb-0">€ <?= number_format($stats['ust_zahllast'], 2, ',', '.') ?></h3>
                                    </div>
                                    <i class="bi bi-percent fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-<?= $stats['gewinn'] >= 0 ? 'primary' : 'warning' ?> text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2">Gewinn/Verlust</h6>
                                        <h3 class="card-title mb-0">€ <?= number_format($stats['gewinn'], 2, ',', '.') ?></h3>
                                    </div>
                                    <i class="bi bi-graph-up-arrow fs-1 opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Letzte Rechnungen -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Letzte Rechnungen</h5>
                                <a href="rechnungen.php" class="btn btn-sm btn-outline-warning">Alle anzeigen</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Datum</th>
                                                <th>Typ</th>
                                                <th>Kunde/Lieferant</th>
                                                <th class="text-end">Betrag</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentInvoices)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    Noch keine Rechnungen vorhanden
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($recentInvoices as $invoice): ?>
                                            <tr>
                                                <td><?= date('d.m.Y', strtotime($invoice['datum'])) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $invoice['typ'] == 'einnahme' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($invoice['typ']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($invoice['kunde_lieferant']) ?></td>
                                                <td class="text-end">€ <?= number_format($invoice['brutto_betrag'], 2, ',', '.') ?></td>
                                                <td>
                                                    <?php if ($invoice['bezahlt']): ?>
                                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Bezahlt</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Offen</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schnellaktionen & Offene Rechnungen -->
                    <div class="col-lg-4 mb-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Schnellaktionen</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="rechnungen.php?action=new&typ=einnahme" class="btn btn-success">
                                        <i class="bi bi-plus-circle me-2"></i>Neue Einnahme
                                    </a>
                                    <a href="rechnungen.php?action=new&typ=ausgabe" class="btn btn-danger">
                                        <i class="bi bi-plus-circle me-2"></i>Neue Ausgabe
                                    </a>
                                    <a href="ust_voranmeldung.php" class="btn btn-outline-primary">
                                        <i class="bi bi-file-earmark-text me-2"></i>USt-Voranmeldung (U30)
                                    </a>
                                    <a href="einkommensteuer.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-file-earmark-ruled me-2"></i>Einkommensteuer (E1a)
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Offene Rechnungen</h5>
                                <span class="badge bg-warning text-dark"><?= count($openInvoices) ?></span>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (empty($openInvoices)): ?>
                                    <li class="list-group-item text-center text-muted">
                                        Keine offenen Rechnungen
                                    </li>
                                    <?php else: ?>
                                    <?php foreach (array_slice($openInvoices, 0, 5) as $invoice): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted"><?= date('d.m.Y', strtotime($invoice['datum'])) ?></small><br>
                                            <?= htmlspecialchars($invoice['kunde_lieferant']) ?>
                                        </div>
                                        <span class="badge bg-<?= $invoice['typ'] == 'einnahme' ? 'success' : 'danger' ?>">
                                            € <?= number_format($invoice['brutto_betrag'], 2, ',', '.') ?>
                                        </span>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monatsübersicht -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Monatsübersicht <?= $currentYear ?></h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" height="100"></canvas>
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
        // Monatsdaten für Chart
        const monthlyData = <?= json_encode(getMonthlyData($currentYear)) ?>;
        
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Einnahmen',
                    data: monthlyData.einnahmen,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgb(40, 167, 69)',
                    borderWidth: 1
                }, {
                    label: 'Ausgaben',
                    data: monthlyData.ausgaben,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgb(220, 53, 69)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '€ ' + value.toLocaleString('de-DE');
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': € ' + context.raw.toLocaleString('de-DE', {minimumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
