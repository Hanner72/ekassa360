<?php
/**
 * Aufgaben - Aufgabenliste mit Zuweisung an Benutzer, optional verknüpft mit
 * einem Kunden oder einem Verkaufsdokument (Angebot/Auftrag/Rechnung)
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/aufgaben_functions.php';

requireLogin();

$benutzerId = $_SESSION['benutzer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $titel = trim($_POST['titel'] ?? '');
        if ($titel === '') {
            setFlashMessage('danger', 'Bitte einen Titel angeben.');
        } else {
            saveAufgabe([
                'id' => $_POST['id'] ?? null,
                'titel' => $titel,
                'beschreibung' => trim($_POST['beschreibung'] ?? ''),
                'zugewiesen_an' => $_POST['zugewiesen_an'] ?? null,
                'kunde_id' => $_POST['kunde_id'] ?? null,
                'verkaufsdokument_id' => $_POST['verkaufsdokument_id'] ?? null,
                'faellig_am' => $_POST['faellig_am'] ?? null,
                'prioritaet' => $_POST['prioritaet'] ?? 'normal',
                'erstellt_von' => $benutzerId,
            ]);
            setFlashMessage('success', 'Aufgabe wurde gespeichert.');
        }
        header('Location: aufgaben.php');
        exit;
    }

    if ($action === 'status') {
        setzeAufgabeStatus((int) $_POST['id'], $_POST['status']);
        header('Location: aufgaben.php?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'delete') {
        $aufgabe = getAufgabe((int) $_POST['id']);
        if ($aufgabe && ((int) $aufgabe['erstellt_von'] === (int) $benutzerId || isAdmin())) {
            deleteAufgabe((int) $_POST['id']);
            setFlashMessage('success', 'Aufgabe gelöscht.');
        } else {
            setFlashMessage('danger', 'Nur die Ersteller einer Aufgabe oder Admins können sie löschen.');
        }
        header('Location: aufgaben.php');
        exit;
    }
}

$filter = $_GET['filter'] ?? 'mir';
$statusFilter = $_GET['status'] ?? 'offen_oder_in_arbeit';

$filters = ['status' => $statusFilter];
if ($filter === 'mir') {
    $filters['zugewiesen_an'] = $benutzerId;
} elseif ($filter === 'erstellt') {
    $filters['erstellt_von'] = $benutzerId;
}

$aufgaben = getAufgaben($filters);
$editAufgabe = isset($_GET['edit']) ? getAufgabe((int) $_GET['edit']) : null;
$benutzerListe = array_filter(getAlleBenutzer(), fn($b) => $b['aktiv']);
$kundenListe = getAlleKunden(false);
$dokumenteListe = getVerkaufsdokumenteFuerAuswahl();
$pageTitle = 'Aufgaben';

$typLabels = ['angebot' => 'Angebot', 'auftrag' => 'Auftrag', 'rechnung' => 'Rechnung'];
$prioritaetLabels = ['niedrig' => 'Niedrig', 'normal' => 'Normal', 'hoch' => 'Hoch'];
$prioritaetBadge = ['niedrig' => 'secondary', 'normal' => 'primary', 'hoch' => 'danger'];
$statusLabels = ['offen' => 'Offen', 'in_arbeit' => 'In Arbeit', 'erledigt' => 'Erledigt'];
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
                    <h1 class="h2"><i class="bi bi-check2-square me-2"></i><?= $pageTitle ?></h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#aufgabeModal">
                        <i class="bi bi-plus-lg me-1"></i>Neue Aufgabe
                    </button>
                </div>

                <?php displayFlashMessage(); ?>

                <form method="GET" class="row g-2 align-items-end mb-3">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Zeige</label>
                        <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="mir" <?= $filter === 'mir' ? 'selected' : '' ?>>Mir zugewiesen</option>
                            <option value="erstellt" <?= $filter === 'erstellt' ? 'selected' : '' ?>>Von mir erstellt</option>
                            <option value="alle" <?= $filter === 'alle' ? 'selected' : '' ?>>Alle</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Status</label>
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="offen_oder_in_arbeit" <?= $statusFilter === 'offen_oder_in_arbeit' ? 'selected' : '' ?>>Offen + In Arbeit</option>
                            <option value="offen" <?= $statusFilter === 'offen' ? 'selected' : '' ?>>Offen</option>
                            <option value="in_arbeit" <?= $statusFilter === 'in_arbeit' ? 'selected' : '' ?>>In Arbeit</option>
                            <option value="erledigt" <?= $statusFilter === 'erledigt' ? 'selected' : '' ?>>Erledigt</option>
                            <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Alle</option>
                        </select>
                    </div>
                </form>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Titel</th>
                                    <th>Zugewiesen an</th>
                                    <th>Verknüpft mit</th>
                                    <th>Fällig am</th>
                                    <th>Priorität</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($aufgaben)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Keine Aufgaben gefunden.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($aufgaben as $a): ?>
                                <tr class="<?= $a['status'] === 'erledigt' ? 'text-muted' : '' ?>">
                                    <td>
                                        <span class="<?= $a['status'] === 'erledigt' ? 'text-decoration-line-through' : '' ?>"><?= htmlspecialchars($a['titel']) ?></span>
                                        <?php if (!empty($a['beschreibung'])): ?>
                                        <div class="small text-muted"><?= nl2br(htmlspecialchars(mb_strimwidth($a['beschreibung'], 0, 120, '…'))) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($a['zugewiesen_vorname'] ? trim($a['zugewiesen_vorname'] . ' ' . $a['zugewiesen_nachname']) : ($a['zugewiesen_benutzername'] ?? '—')) ?></td>
                                    <td>
                                        <?php if (!empty($a['verkaufsdokument_id'])): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($typLabels[$a['verkaufsdokument_typ']] ?? '') ?> <?= htmlspecialchars($a['verkaufsdokument_nummer']) ?></span>
                                        <?php elseif (!empty($a['kunde_id'])): ?>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-person-vcard"></i> <?= htmlspecialchars($a['kunde_firma_name'] ?: trim($a['kunde_vorname'] . ' ' . $a['kunde_nachname'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $a['faellig_am'] ? formatDatum($a['faellig_am']) : '—' ?></td>
                                    <td><span class="badge bg-<?= $prioritaetBadge[$a['prioritaet']] ?>"><?= $prioritaetLabels[$a['prioritaet']] ?></span></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                                                <?php foreach ($statusLabels as $sv => $sl): ?>
                                                <option value="<?= $sv ?>" <?= $a['status'] === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <a href="aufgaben.php?edit=<?= $a['id'] ?>#aufgabeModal" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aufgabeModal" onclick="aufgabeBearbeiten(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ((int) $a['erstellt_von'] === (int) $benutzerId || isAdmin()): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Aufgabe wirklich löschen?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal: Aufgabe anlegen/bearbeiten -->
    <div class="modal fade" id="aufgabeModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="af_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="aufgabeModalTitel">Neue Aufgabe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titel *</label>
                        <input type="text" class="form-control" name="titel" id="af_titel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="af_beschreibung" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zugewiesen an</label>
                            <select class="form-select" name="zugewiesen_an" id="af_zugewiesen_an">
                                <option value="">—</option>
                                <?php foreach ($benutzerListe as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['vorname'] ? trim($b['vorname'] . ' ' . $b['nachname']) : $b['benutzername']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fällig am</label>
                            <input type="date" class="form-control" name="faellig_am" id="af_faellig_am">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priorität</label>
                        <select class="form-select" name="prioritaet" id="af_prioritaet">
                            <?php foreach ($prioritaetLabels as $pv => $pl): ?>
                            <option value="<?= $pv ?>" <?= $pv === 'normal' ? 'selected' : '' ?>><?= $pl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kunde (optional)</label>
                        <select class="form-select" name="kunde_id" id="af_kunde_id">
                            <option value="">—</option>
                            <?php foreach ($kundenListe as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['firma_name'] ?: trim($k['vorname'] . ' ' . $k['nachname'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Angebot/Auftrag/Rechnung (optional)</label>
                        <select class="form-select" name="verkaufsdokument_id" id="af_verkaufsdokument_id">
                            <option value="">—</option>
                            <?php foreach ($dokumenteListe as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars(($typLabels[$d['typ']] ?? '') . ' ' . $d['nummer'] . ' - ' . ($d['firma_name'] ?: trim($d['vorname'] . ' ' . $d['nachname']))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function aufgabeBearbeiten(a) {
            document.getElementById('aufgabeModalTitel').textContent = 'Aufgabe bearbeiten';
            document.getElementById('af_id').value = a.id;
            document.getElementById('af_titel').value = a.titel;
            document.getElementById('af_beschreibung').value = a.beschreibung || '';
            document.getElementById('af_zugewiesen_an').value = a.zugewiesen_an || '';
            document.getElementById('af_faellig_am').value = a.faellig_am || '';
            document.getElementById('af_prioritaet').value = a.prioritaet;
            document.getElementById('af_kunde_id').value = a.kunde_id || '';
            document.getElementById('af_verkaufsdokument_id').value = a.verkaufsdokument_id || '';
        }
        <?php if ($editAufgabe): ?>
        document.addEventListener('DOMContentLoaded', function () {
            aufgabeBearbeiten(<?= json_encode($editAufgabe) ?>);
            new bootstrap.Modal(document.getElementById('aufgabeModal')).show();
        });
        <?php endif; ?>
        document.getElementById('aufgabeModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('aufgabeModalTitel').textContent = 'Neue Aufgabe';
            this.querySelector('form').reset();
            document.getElementById('af_id').value = '';
        });
    </script>
</body>
</html>
