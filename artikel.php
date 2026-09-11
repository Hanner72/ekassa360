<?php
/**
 * Artikel/Leistungen - Stammdaten für Verkaufsdokument-Positionen
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $data = [
            'id' => $_POST['id'] ?? null,
            'artikelnummer' => trim($_POST['artikelnummer'] ?? ''),
            'bezeichnung' => trim($_POST['bezeichnung'] ?? ''),
            'beschreibung' => trim($_POST['beschreibung'] ?? ''),
            'einheit' => trim($_POST['einheit'] ?? '') ?: 'Stk',
            'einzelpreis_netto' => floatval(str_replace(',', '.', $_POST['einzelpreis_netto'] ?? '0')),
            'ust_satz_id' => $_POST['ust_satz_id'] ?: null,
            'kategorie_id' => $_POST['kategorie_id'] ?: null,
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0
        ];

        if (empty($data['bezeichnung'])) {
            setFlashMessage('danger', 'Bitte eine Bezeichnung angeben.');
        } else {
            try {
                saveArtikel($data);
                setFlashMessage('success', 'Artikel wurde gespeichert.');
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Fehler beim Speichern: ' . $e->getMessage());
            }
        }
        header('Location: artikel.php');
        exit;
    }

    if ($action === 'toggle') {
        toggleArtikelAktiv((int)$_POST['id']);
        header('Location: artikel.php');
        exit;
    }
}

$artikelListe = getAlleArtikel(false);
$ustSaetze = getUstSaetze();
$kategorien = getKategorien('einnahme');
$pageTitle = 'Artikel';
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
                    <h1 class="h2"><i class="bi bi-box-seam me-2"></i><?= $pageTitle ?></h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#artikelModal">
                        <i class="bi bi-plus-lg me-1"></i>Neuer Artikel
                    </button>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Art.-Nr.</th>
                                        <th>Bezeichnung</th>
                                        <th>Einheit</th>
                                        <th class="text-end">Einzelpreis (Netto)</th>
                                        <th>USt-Satz</th>
                                        <th>Status</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($artikelListe)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Keine Artikel vorhanden</td></tr>
                                    <?php else: foreach ($artikelListe as $a): ?>
                                    <tr class="<?= !$a['aktiv'] ? 'table-light text-muted' : '' ?>">
                                        <td><?= htmlspecialchars($a['artikelnummer'] ?: '-') ?></td>
                                        <td><strong><?= htmlspecialchars($a['bezeichnung']) ?></strong></td>
                                        <td><?= htmlspecialchars($a['einheit']) ?></td>
                                        <td class="text-end"><?= formatBetrag($a['einzelpreis_netto']) ?></td>
                                        <td><?= htmlspecialchars($a['ust_bezeichnung'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($a['aktiv']): ?>
                                                <span class="badge bg-success">Aktiv</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="?edit=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="modal" data-bs-target="#artikelModal"
                                               onclick="editArtikel(<?= htmlspecialchars(json_encode($a)) ?>); return false;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $a['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                    <i class="bi bi-<?= $a['aktiv'] ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Artikel Modal -->
    <div class="modal fade" id="artikelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="a_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="a_modalTitle"><i class="bi bi-box-seam me-2"></i>Neuer Artikel</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Artikelnummer</label>
                                <input type="text" class="form-control" name="artikelnummer" id="a_artikelnummer">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label required">Bezeichnung</label>
                                <input type="text" class="form-control" name="bezeichnung" id="a_bezeichnung" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Beschreibung</label>
                            <textarea class="form-control" name="beschreibung" id="a_beschreibung" rows="2"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Einheit</label>
                                <input type="text" class="form-control" name="einheit" id="a_einheit" value="Stk">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Einzelpreis (Netto)</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="text" class="form-control" name="einzelpreis_netto" id="a_einzelpreis_netto" value="0,00">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">USt-Satz</label>
                                <select class="form-select" name="ust_satz_id" id="a_ust_satz_id">
                                    <option value="">-- Kein USt --</option>
                                    <?php foreach ($ustSaetze as $ust): ?>
                                    <option value="<?= $ust['id'] ?>"><?= htmlspecialchars($ust['bezeichnung']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Kategorie (für Ledger-Buchung bei Rechnungsstellung)</label>
                                <select class="form-select" name="kategorie_id" id="a_kategorie_id">
                                    <option value="">-- Standard (Verkaufserlöse) --</option>
                                    <?php foreach ($kategorien as $kat): ?>
                                    <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="aktiv" id="a_aktiv" checked>
                                    <label class="form-check-label" for="a_aktiv">Aktiv</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editArtikel(a) {
            document.getElementById('a_modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Artikel bearbeiten';
            document.getElementById('a_id').value = a.id;
            document.getElementById('a_artikelnummer').value = a.artikelnummer || '';
            document.getElementById('a_bezeichnung').value = a.bezeichnung || '';
            document.getElementById('a_beschreibung').value = a.beschreibung || '';
            document.getElementById('a_einheit').value = a.einheit || 'Stk';
            document.getElementById('a_einzelpreis_netto').value = parseFloat(a.einzelpreis_netto || 0).toFixed(2).replace('.', ',');
            document.getElementById('a_ust_satz_id').value = a.ust_satz_id || '';
            document.getElementById('a_kategorie_id').value = a.kategorie_id || '';
            document.getElementById('a_aktiv').checked = a.aktiv == 1;
        }

        document.getElementById('artikelModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || !event.relatedTarget.hasAttribute('onclick')) {
                document.getElementById('a_modalTitle').innerHTML = '<i class="bi bi-box-seam me-2"></i>Neuer Artikel';
                this.querySelector('form').reset();
                document.getElementById('a_id').value = '';
                document.getElementById('a_einzelpreis_netto').value = '0,00';
            }
        });
    </script>
</body>
</html>
