<?php
/**
 * Änderungsprotokoll
 * EKassa360
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireAdmin();

// Filter
$filterTabelle = $_GET['tabelle'] ?? '';
$filterBenutzer = $_GET['benutzer'] ?? '';
$limit = intval($_GET['limit'] ?? 100);

// Protokoll laden
$protokoll = getAenderungsprotokoll($limit, $filterTabelle ?: null, $filterBenutzer ?: null);

// Alle Benutzer für Filter
$benutzer = getAlleBenutzer();

// Tabellen für Filter
$tabellen = ['rechnungen', 'anlagegueter', 'kategorien', 'benutzer', 'login', 'ust_voranmeldungen', 'einkommensteuer', 'firmendaten'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Änderungsprotokoll - EKassa360</title>
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
                    <h1 class="h2"><i class="bi bi-journal-text me-2"></i>Änderungsprotokoll</h1>
                </div>

                <!-- Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Bereich</label>
                                <select name="tabelle" class="form-select">
                                    <option value="">Alle Bereiche</option>
                                    <?php foreach ($tabellen as $t): ?>
                                        <option value="<?= $t ?>" <?= $filterTabelle === $t ? 'selected' : '' ?>>
                                            <?= ucfirst($t) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Benutzer</label>
                                <select name="benutzer" class="form-select">
                                    <option value="">Alle Benutzer</option>
                                    <?php foreach ($benutzer as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= $filterBenutzer == $b['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['benutzername']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Anzahl</label>
                                <select name="limit" class="form-select">
                                    <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                    <option value="250" <?= $limit == 250 ? 'selected' : '' ?>>250</option>
                                    <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter me-1"></i>Filtern
                                </button>
                                <a href="protokoll.php" class="btn btn-secondary">
                                    <i class="bi bi-x-lg me-1"></i>Zurücksetzen
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Protokoll-Liste -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul me-2"></i>
                            <?= count($protokoll) ?> Einträge
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 150px;">Zeitpunkt</th>
                                        <th style="width: 120px;">Benutzer</th>
                                        <th style="width: 100px;">Bereich</th>
                                        <th style="width: 100px;">Aktion</th>
                                        <th>Beschreibung</th>
                                        <th style="width: 100px;">IP-Adresse</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($protokoll)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                Keine Einträge gefunden.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($protokoll as $p): ?>
                                            <tr>
                                                <td>
                                                    <small><?= date('d.m.Y H:i:s', strtotime($p['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($p['benutzer_name']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($p['tabelle']) ?></small>
                                                    <?php if ($p['datensatz_id']): ?>
                                                        <br><small class="text-muted">#<?= $p['datensatz_id'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $aktionBadge = [
                                                        'erstellt' => 'success',
                                                        'geaendert' => 'primary',
                                                        'geloescht' => 'danger',
                                                        'login' => 'info',
                                                        'logout' => 'secondary',
                                                        'passwort_geaendert' => 'warning'
                                                    ];
                                                    $badge = $aktionBadge[$p['aktion']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $badge ?>">
                                                        <?= htmlspecialchars($p['aktion']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($p['beschreibung'] ?? '-') ?>
                                                    <?php if ($p['alte_werte'] || $p['neue_werte']): ?>
                                                        <button class="btn btn-sm btn-link p-0 ms-2" type="button" 
                                                                data-bs-toggle="collapse" data-bs-target="#details<?= $p['id'] ?>">
                                                            <i class="bi bi-info-circle"></i>
                                                        </button>
                                                        <div class="collapse mt-2" id="details<?= $p['id'] ?>">
                                                            <?php if ($p['alte_werte']): ?>
                                                                <div class="small bg-light p-2 mb-1">
                                                                    <strong>Alt:</strong>
                                                                    <pre class="mb-0 small"><?= htmlspecialchars(json_encode(json_decode($p['alte_werte']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if ($p['neue_werte']): ?>
                                                                <div class="small bg-light p-2">
                                                                    <strong>Neu:</strong>
                                                                    <pre class="mb-0 small"><?= htmlspecialchars(json_encode(json_decode($p['neue_werte']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($p['ip_adresse'] ?? '-') ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
