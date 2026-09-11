<?php
/**
 * Wiederkehrende Rechnungen - Regeln CRUD, manuelle Ausführung, Log-Ansicht
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';
require_once 'includes/paperless.php';
require_once 'includes/mail.php';
require_once 'includes/wiederkehrend_functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $data = [
            'id' => $_POST['id'] ?: null,
            'bezeichnung' => trim($_POST['bezeichnung'] ?? ''),
            'kunde_id' => $_POST['kunde_id'] ?: null,
            'vorlage_verkaufsdokument_id' => $_POST['vorlage_verkaufsdokument_id'] ?: null,
            'rhythmus' => $_POST['rhythmus'] ?? 'monatlich',
            'start_datum' => $_POST['start_datum'],
            'naechstes_datum' => $_POST['naechstes_datum'] ?: $_POST['start_datum'],
            'end_datum' => $_POST['end_datum'] ?: null,
            'max_anzahl' => $_POST['max_anzahl'] ?: null,
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
            'automatisch_pdf_versenden' => isset($_POST['automatisch_pdf_versenden']) ? 1 : 0,
            'versand_email' => trim($_POST['versand_email'] ?? ''),
            'notizen' => trim($_POST['notizen'] ?? '')
        ];

        if (empty($data['bezeichnung']) || empty($data['vorlage_verkaufsdokument_id'])) {
            setFlashMessage('danger', 'Bitte Bezeichnung und Vorlage angeben.');
        } else {
            saveWiederkehrendeRechnung($data);
            setFlashMessage('success', 'Regel gespeichert.');
        }
        header('Location: wiederkehrende_rechnungen.php');
        exit;
    }

    if ($action === 'delete') {
        deleteWiederkehrendeRechnung((int)$_POST['id']);
        setFlashMessage('success', 'Regel gelöscht.');
        header('Location: wiederkehrende_rechnungen.php');
        exit;
    }

    if ($action === 'toggle') {
        toggleWiederkehrendAktiv((int)$_POST['id']);
        header('Location: wiederkehrende_rechnungen.php');
        exit;
    }

    if ($action === 'ausfuehren') {
        $ergebnisse = generateFaelligeWiederkehrendeRechnungen((int)$_POST['id']);
        if (empty($ergebnisse)) {
            setFlashMessage('warning', 'Regel ist heute nicht fällig oder bereits erfolgreich ausgeführt.');
        } else {
            $r = $ergebnisse[0];
            if ($r['status'] === 'erstellt') {
                setFlashMessage('success', 'Rechnung ' . $r['nummer'] . ' wurde erstellt.');
            } else {
                setFlashMessage('danger', 'Fehler: ' . ($r['message'] ?? 'Unbekannter Fehler'));
            }
        }
        header('Location: wiederkehrende_rechnungen.php');
        exit;
    }
}

$editRegel = isset($_GET['edit']) ? getWiederkehrendeRechnung((int)$_GET['edit']) : null;
$logRegelId = isset($_GET['log']) ? (int)$_GET['log'] : null;
$logEintraege = $logRegelId ? getWiederkehrendLog($logRegelId) : [];

$regeln = getWiederkehrendeRechnungen();
$kunden = getAlleKunden(true);
// Sowohl Rechnungen als auch Aufträge können als Vorlage dienen (Positionen/Texte werden
// bei jeder Ausführung generisch kopiert - der Quelldokumenttyp spielt dafür keine Rolle).
$vorlagen = array_merge(getVerkaufsdokumente('rechnung'), getVerkaufsdokumente('auftrag'));
usort($vorlagen, fn($a, $b) => $b['id'] <=> $a['id']);

$vorausgewaehlteVorlageId = (int)($_GET['vorlage_id'] ?? 0);
$vorausgewaehlteVorlage = $vorausgewaehlteVorlageId ? getVerkaufsdokument($vorausgewaehlteVorlageId) : null;

$pageTitle = 'Wiederkehrende Rechnungen';
$rhythmusLabels = ['monatlich' => 'Monatlich', 'quartalsweise' => 'Quartalsweise', 'halbjaehrlich' => 'Halbjährlich', 'jaehrlich' => 'Jährlich'];
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
                    <h1 class="h2"><i class="bi bi-arrow-repeat me-2"></i><?= $pageTitle ?></h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#regelModal">
                        <i class="bi bi-plus-lg me-1"></i>Neue Regel
                    </button>
                </div>

                <?php displayFlashMessage(); ?>

                <?php if ($logRegelId): ?>
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-journal-text me-1"></i>Ausführungsprotokoll</span>
                        <a href="wiederkehrende_rechnungen.php" class="btn btn-sm btn-outline-secondary">Schließen</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Lauf-Datum</th><th>Status</th><th>Rechnung</th><th>Fehlermeldung</th></tr></thead>
                            <tbody>
                                <?php if (empty($logEintraege)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Noch keine Ausführungen.</td></tr>
                                <?php else: foreach ($logEintraege as $log): ?>
                                <tr>
                                    <td><?= formatDatum($log['lauf_datum']) ?></td>
                                    <td><span class="badge bg-<?= $log['status'] === 'erstellt' ? 'success' : 'danger' ?>"><?= ucfirst($log['status']) ?></span></td>
                                    <td><?= htmlspecialchars($log['nummer'] ?? '-') ?></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($log['fehlermeldung'] ?? '') ?></small></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Bezeichnung</th><th>Kunde</th><th>Rhythmus</th><th>Nächstes Datum</th>
                                        <th>Anzahl</th><th>Status</th><th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($regeln)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Keine wiederkehrenden Rechnungen konfiguriert</td></tr>
                                    <?php else: foreach ($regeln as $r): ?>
                                    <tr class="<?= !$r['aktiv'] ? 'table-light text-muted' : '' ?>">
                                        <td><strong><?= htmlspecialchars($r['bezeichnung']) ?></strong></td>
                                        <td><?= htmlspecialchars(kundenAnzeigename($r)) ?></td>
                                        <td><?= $rhythmusLabels[$r['rhythmus']] ?? $r['rhythmus'] ?></td>
                                        <td><?= formatDatum($r['naechstes_datum']) ?></td>
                                        <td><?= $r['anzahl_erstellt'] ?><?= $r['max_anzahl'] ? ' / ' . $r['max_anzahl'] : '' ?></td>
                                        <td>
                                            <?php if ($r['aktiv']): ?>
                                                <span class="badge bg-success">Aktiv</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="ausfuehren">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Jetzt manuell ausführen">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            </form>
                                            <a href="?log=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Protokoll"><i class="bi bi-journal-text"></i></a>
                                            <a href="?edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="modal" data-bs-target="#regelModal"
                                               onclick="editRegel(<?= htmlspecialchars(json_encode($r)) ?>); return false;">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $r['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                    <i class="bi bi-<?= $r['aktiv'] ? 'pause' : 'play' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Regel wirklich löschen? (Bereits erstellte Rechnungen bleiben erhalten)');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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

    <!-- Regel Modal -->
    <div class="modal fade" id="regelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="r_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="r_modalTitle"><i class="bi bi-arrow-repeat me-2"></i>Neue wiederkehrende Rechnung</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Bezeichnung</label>
                            <input type="text" class="form-control" name="bezeichnung" id="r_bezeichnung" required placeholder="z.B. Wartungsvertrag Muster GmbH">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Kunde (überschreibt Kunde der Vorlage)</label>
                                <select class="form-select" name="kunde_id" id="r_kunde_id">
                                    <option value="">-- Wie Vorlage --</option>
                                    <?php foreach ($kunden as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars(kundenAnzeigename($k)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">Vorlage (bestehender Auftrag oder Rechnung)</label>
                                <select class="form-select" name="vorlage_verkaufsdokument_id" id="r_vorlage" required>
                                    <option value="">-- Wählen --</option>
                                    <?php foreach ($vorlagen as $v): ?>
                                    <option value="<?= $v['id'] ?>">
                                        <?= htmlspecialchars('[' . ucfirst($v['typ']) . '] ' . ($v['nummer'] ?: '(Entwurf #' . $v['id'] . ')') . ' - ' . kundenAnzeigename($v) . ' - ' . formatBetrag($v['brutto_gesamt'])) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Positionen/Texte werden bei jeder Ausführung aus diesem Dokument kopiert.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Rhythmus</label>
                                <select class="form-select" name="rhythmus" id="r_rhythmus">
                                    <?php foreach ($rhythmusLabels as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Startdatum</label>
                                <input type="date" class="form-control" name="start_datum" id="r_start_datum" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Nächstes Datum</label>
                                <input type="date" class="form-control" name="naechstes_datum" id="r_naechstes_datum" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Enddatum</label>
                                <input type="date" class="form-control" name="end_datum" id="r_end_datum">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Max. Anzahl</label>
                                <input type="number" class="form-control" name="max_anzahl" id="r_max_anzahl" min="1">
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="aktiv" id="r_aktiv" checked>
                                    <label class="form-check-label" for="r_aktiv">Aktiv</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="automatisch_pdf_versenden" id="r_automatisch_pdf_versenden" <?= mailConfigured() ? '' : 'disabled' ?>>
                                    <label class="form-check-label" for="r_automatisch_pdf_versenden">Automatisch per E-Mail versenden</label>
                                    <?php if (!mailConfigured()): ?>
                                    <div class="form-text text-warning">E-Mail-Versand ist nicht konfiguriert (config/mail.php).</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Versand-E-Mail (überschreibt E-Mail des Kunden)</label>
                            <input type="email" class="form-control" name="versand_email" id="r_versand_email" placeholder="leer = Kunden-E-Mail verwenden">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notizen</label>
                            <textarea class="form-control" name="notizen" id="r_notizen" rows="2"></textarea>
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
        function editRegel(r) {
            document.getElementById('r_modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Regel bearbeiten';
            document.getElementById('r_id').value = r.id;
            document.getElementById('r_bezeichnung').value = r.bezeichnung || '';
            document.getElementById('r_kunde_id').value = r.kunde_id || '';
            document.getElementById('r_vorlage').value = r.vorlage_verkaufsdokument_id || '';
            document.getElementById('r_rhythmus').value = r.rhythmus || 'monatlich';
            document.getElementById('r_start_datum').value = r.start_datum || '';
            document.getElementById('r_naechstes_datum').value = r.naechstes_datum || '';
            document.getElementById('r_end_datum').value = r.end_datum || '';
            document.getElementById('r_max_anzahl').value = r.max_anzahl || '';
            document.getElementById('r_aktiv').checked = r.aktiv == 1;
            document.getElementById('r_automatisch_pdf_versenden').checked = r.automatisch_pdf_versenden == 1;
            document.getElementById('r_versand_email').value = r.versand_email || '';
            document.getElementById('r_notizen').value = r.notizen || '';
        }

        document.getElementById('regelModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || !event.relatedTarget.hasAttribute('onclick')) {
                document.getElementById('r_modalTitle').innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Neue wiederkehrende Rechnung';
                this.querySelector('form').reset();
                document.getElementById('r_id').value = '';
            }
        });

        <?php if ($editRegel): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editRegel(<?= json_encode($editRegel) ?>);
            new bootstrap.Modal(document.getElementById('regelModal')).show();
        });
        <?php elseif ($vorausgewaehlteVorlage): ?>
        // Von auftraege.php "In wiederkehrende Rechnung umwandeln" verlinkt - Neue-Regel-Formular vorbelegen.
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('r_vorlage').value = <?= (int)$vorausgewaehlteVorlage['id'] ?>;
            document.getElementById('r_kunde_id').value = <?= (int)($vorausgewaehlteVorlage['kunde_id'] ?? 0) ?>;
            document.getElementById('r_bezeichnung').value = <?= json_encode(($vorausgewaehlteVorlage['betreff'] ?: kundenAnzeigename($vorausgewaehlteVorlage)) ?: '') ?>;
            new bootstrap.Modal(document.getElementById('regelModal')).show();
        });
        <?php endif; ?>
    </script>
</body>
</html>
