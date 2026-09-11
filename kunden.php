<?php
/**
 * Kunden - Verwaltung des Kundenstamms für das Verkauf-Modul
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';

requireLogin();

$editKunde = null;
if (isset($_GET['edit'])) {
    $editKunde = getKunde((int)$_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $data = [
            'id' => $_POST['id'] ?? null,
            'kundennummer' => trim($_POST['kundennummer'] ?? ''),
            'firma_name' => trim($_POST['firma_name'] ?? ''),
            'anrede' => $_POST['anrede'] ?? '',
            'vorname' => trim($_POST['vorname'] ?? ''),
            'nachname' => trim($_POST['nachname'] ?? ''),
            'strasse' => trim($_POST['strasse'] ?? ''),
            'plz' => trim($_POST['plz'] ?? ''),
            'ort' => trim($_POST['ort'] ?? ''),
            'land' => trim($_POST['land'] ?? '') ?: 'Österreich',
            'uid_nummer' => trim($_POST['uid_nummer'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'telefon' => trim($_POST['telefon'] ?? ''),
            'notizen' => trim($_POST['notizen'] ?? ''),
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0
        ];

        if (empty($data['firma_name']) && empty($data['nachname'])) {
            setFlashMessage('danger', 'Bitte Firmenname oder Nachname angeben.');
        } else {
            try {
                saveKunde($data);
                setFlashMessage('success', 'Kunde wurde gespeichert.');
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Fehler beim Speichern: ' . $e->getMessage());
            }
        }
        header('Location: kunden.php');
        exit;
    }

    if ($action === 'toggle') {
        toggleKundeAktiv((int)$_POST['id']);
        header('Location: kunden.php');
        exit;
    }
}

$kunden = getAlleKunden(false);
$pageTitle = 'Kunden';
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
                    <h1 class="h2"><i class="bi bi-person-vcard me-2"></i><?= $pageTitle ?></h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kundeModal">
                        <i class="bi bi-plus-lg me-1"></i>Neuer Kunde
                    </button>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="kundenSuche" placeholder="Suche nach Kundennummer, Name, Ort, UID, E-Mail...">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="kundenTabelle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="sortierbar" data-spalte="0">Kd.-Nr. <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="1">Name <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="2">Ort <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="3">UID <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="4">E-Mail <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="5">Status <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($kunden)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Keine Kunden vorhanden</td></tr>
                                    <?php else: foreach ($kunden as $k): ?>
                                    <tr class="<?= !$k['aktiv'] ? 'table-light text-muted' : '' ?>">
                                        <td><?= htmlspecialchars($k['kundennummer'] ?: '-') ?></td>
                                        <td><strong><?= htmlspecialchars(kundenAnzeigename($k)) ?></strong></td>
                                        <td><?= htmlspecialchars($k['ort'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($k['uid_nummer'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($k['email'] ?: '-') ?></td>
                                        <td data-sortwert="<?= $k['aktiv'] ? 1 : 0 ?>">
                                            <?php if ($k['aktiv']): ?>
                                                <span class="badge bg-success">Aktiv</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="?edit=<?= $k['id'] ?>" class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="modal" data-bs-target="#kundeModal"
                                               onclick="editKunde(<?= htmlspecialchars(json_encode($k)) ?>); return false;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $k['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                    <i class="bi bi-<?= $k['aktiv'] ? 'eye-slash' : 'eye' ?>"></i>
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

    <!-- Kunde Modal -->
    <div class="modal fade" id="kundeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="k_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="k_modalTitle"><i class="bi bi-person-plus me-2"></i>Neuer Kunde</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Kundennummer</label>
                                <input type="text" class="form-control" name="kundennummer" id="k_kundennummer" placeholder="Automatisch">
                                <div class="form-text">Leer = wird automatisch aus dem Nummernkreis vergeben</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Firmenname</label>
                                <input type="text" class="form-control" name="firma_name" id="k_firma_name">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-2">
                                <label class="form-label">Anrede</label>
                                <select class="form-select" name="anrede" id="k_anrede">
                                    <option value="">-</option>
                                    <option value="Herr">Herr</option>
                                    <option value="Frau">Frau</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Vorname</label>
                                <input type="text" class="form-control" name="vorname" id="k_vorname">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Nachname</label>
                                <input type="text" class="form-control" name="nachname" id="k_nachname">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Straße</label>
                            <input type="text" class="form-control" name="strasse" id="k_strasse">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">PLZ</label>
                                <input type="text" class="form-control" name="plz" id="k_plz">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Ort</label>
                                <input type="text" class="form-control" name="ort" id="k_ort">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Land</label>
                                <input type="text" class="form-control" name="land" id="k_land" value="Österreich">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">UID-Nummer</label>
                                <input type="text" class="form-control" name="uid_nummer" id="k_uid_nummer">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">E-Mail</label>
                                <input type="email" class="form-control" name="email" id="k_email">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Telefon</label>
                                <input type="text" class="form-control" name="telefon" id="k_telefon">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notizen</label>
                            <textarea class="form-control" name="notizen" id="k_notizen" rows="2"></textarea>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="aktiv" id="k_aktiv" checked>
                            <label class="form-check-label" for="k_aktiv">Aktiv</label>
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
        function editKunde(k) {
            document.getElementById('k_modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Kunde bearbeiten';
            document.getElementById('k_id').value = k.id;
            document.getElementById('k_kundennummer').value = k.kundennummer || '';
            document.getElementById('k_firma_name').value = k.firma_name || '';
            document.getElementById('k_anrede').value = k.anrede || '';
            document.getElementById('k_vorname').value = k.vorname || '';
            document.getElementById('k_nachname').value = k.nachname || '';
            document.getElementById('k_strasse').value = k.strasse || '';
            document.getElementById('k_plz').value = k.plz || '';
            document.getElementById('k_ort').value = k.ort || '';
            document.getElementById('k_land').value = k.land || 'Österreich';
            document.getElementById('k_uid_nummer').value = k.uid_nummer || '';
            document.getElementById('k_email').value = k.email || '';
            document.getElementById('k_telefon').value = k.telefon || '';
            document.getElementById('k_notizen').value = k.notizen || '';
            document.getElementById('k_aktiv').checked = k.aktiv == 1;
        }

        document.getElementById('kundeModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || !event.relatedTarget.hasAttribute('onclick')) {
                document.getElementById('k_modalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Neuer Kunde';
                this.querySelector('form').reset();
                document.getElementById('k_id').value = '';
                document.getElementById('k_land').value = 'Österreich';
            }
        });

        // Suche: filtert Zeilen client-seitig über alle sichtbaren Spalten
        const kundenSucheFeld = document.getElementById('kundenSuche');
        const kundenTbody = document.querySelector('#kundenTabelle tbody');
        kundenSucheFeld?.addEventListener('input', function() {
            const suchbegriff = this.value.trim().toLowerCase();
            kundenTbody.querySelectorAll('tr').forEach(function(zeile) {
                if (!zeile.cells || zeile.cells.length < 6) return; // "Keine Kunden vorhanden"-Zeile überspringen
                const text = zeile.textContent.toLowerCase();
                zeile.classList.toggle('d-none', suchbegriff !== '' && !text.includes(suchbegriff));
            });
        });

        // Sortierung: Klick auf Spaltenkopf sortiert die Tabellenzeilen auf-/absteigend
        let kundenSortSpalte = null;
        let kundenSortAufsteigend = true;
        document.querySelectorAll('#kundenTabelle th.sortierbar').forEach(function(th) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const spalte = parseInt(th.dataset.spalte, 10);
                kundenSortAufsteigend = (kundenSortSpalte === spalte) ? !kundenSortAufsteigend : true;
                kundenSortSpalte = spalte;

                const zeilen = Array.from(kundenTbody.querySelectorAll('tr')).filter(z => z.cells && z.cells.length >= 6);
                zeilen.sort(function(a, b) {
                    const zelleA = a.cells[spalte];
                    const zelleB = b.cells[spalte];
                    const wertA = zelleA.dataset.sortwert ?? zelleA.textContent.trim().toLowerCase();
                    const wertB = zelleB.dataset.sortwert ?? zelleB.textContent.trim().toLowerCase();
                    let vergleich;
                    if (zelleA.dataset.sortwert !== undefined) {
                        vergleich = parseFloat(wertA) - parseFloat(wertB);
                    } else {
                        vergleich = wertA.localeCompare(wertB, 'de');
                    }
                    return kundenSortAufsteigend ? vergleich : -vergleich;
                });
                zeilen.forEach(z => kundenTbody.appendChild(z));

                document.querySelectorAll('#kundenTabelle th.sortierbar i').forEach(i => i.className = 'bi bi-arrow-down-up small text-muted');
                th.querySelector('i').className = 'bi bi-arrow-' + (kundenSortAufsteigend ? 'up' : 'down') + ' small';
            });
        });
    </script>
</body>
</html>
