<?php
/**
 * Angebote - Verkauf-Modul
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';
require_once 'includes/paperless.php';
require_once 'includes/mail.php';

requireLogin();

$TYP = 'angebot';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $data = [
            'id' => $_POST['id'] ?: null,
            'typ' => $TYP,
            'kunde_id' => $_POST['kunde_id'] ?: null,
            'datum' => $_POST['datum'],
            'gueltig_bis' => $_POST['gueltig_bis'] ?: null,
            'betreff' => trim($_POST['betreff'] ?? ''),
            'einleitungstext' => trim($_POST['einleitungstext'] ?? ''),
            'schlusstext' => trim($_POST['schlusstext'] ?? ''),
            'notizen' => trim($_POST['notizen'] ?? ''),
            'gesamtrabatt_prozent' => floatval(str_replace(',', '.', $_POST['gesamtrabatt_prozent'] ?? '0'))
        ];

        $positionen = [];
        $anzahl = count($_POST['pos_bezeichnung'] ?? []);
        for ($i = 0; $i < $anzahl; $i++) {
            if (trim($_POST['pos_bezeichnung'][$i] ?? '') === '') continue;
            $positionen[] = [
                'artikel_id' => $_POST['pos_artikel_id'][$i] ?: null,
                'bezeichnung' => trim($_POST['pos_bezeichnung'][$i]),
                'beschreibung' => trim($_POST['pos_beschreibung'][$i] ?? ''),
                'menge' => floatval(str_replace(',', '.', $_POST['pos_menge'][$i] ?? '1')),
                'einheit' => trim($_POST['pos_einheit'][$i] ?? '') ?: 'Stk',
                'einzelpreis_netto' => floatval(str_replace(',', '.', $_POST['pos_einzelpreis_netto'][$i] ?? '0')),
                'ust_satz_id' => $_POST['pos_ust_satz_id'][$i] ?: null,
                'rabatt_prozent' => floatval(str_replace(',', '.', $_POST['pos_rabatt_prozent'][$i] ?? '0'))
            ];
        }

        if (empty($positionen)) {
            setFlashMessage('danger', 'Bitte mindestens eine Position mit Bezeichnung angeben.');
            header('Location: angebote.php?action=' . ($data['id'] ? 'edit&id=' . $data['id'] : 'new'));
            exit;
        }

        $result = saveVerkaufsdokument($data, $positionen);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Angebot gespeichert.' : $result['message']);
        header('Location: angebote.php' . ($result['success'] ? '' : ''));
        exit;
    }

    if ($postAction === 'delete') {
        $result = deleteVerkaufsdokument((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Angebot gelöscht.' : $result['message']);
        header('Location: angebote.php');
        exit;
    }

    if ($postAction === 'umwandeln') {
        $result = umwandelnVerkaufsdokument((int)$_POST['id'], 'auftrag');
        if ($result['success']) {
            setFlashMessage('success', 'Angebot wurde in einen Auftrag umgewandelt.');
            header('Location: auftraege.php?action=edit&id=' . $result['id']);
        } else {
            setFlashMessage('danger', $result['message']);
            header('Location: angebote.php');
        }
        exit;
    }

    if ($postAction === 'finalisieren') {
        $result = finalizeAngebotOderAuftrag((int)$_POST['id']);
        if ($result['success']) {
            $meldung = 'Angebot ' . $result['nummer'] . ' finalisiert.';
            if (paperlessConfigured()) {
                $archiv = archiviereVerkaufsdokumentInPaperless($result['id']);
                $meldung .= $archiv['success'] ? ' In paperless-ngx archiviert.' : (' Archivierung fehlgeschlagen: ' . $archiv['error']);
            }
            setFlashMessage($result['success'] ? 'success' : 'warning', $meldung);
        } else {
            setFlashMessage('danger', $result['message']);
        }
        header('Location: angebote.php');
        exit;
    }

    if ($postAction === 'versenden') {
        $result = sendeVerkaufsdokumentEmail(
            (int)$_POST['id'],
            trim($_POST['empfaenger_email'] ?? '') ?: null,
            trim($_POST['betreff'] ?? '') ?: null,
            trim($_POST['nachricht'] ?? '') ?: null,
            $_POST['signatur'] ?? null
        );
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Angebot per E-Mail an ' . $result['empfaenger'] . ' versendet.' : $result['error']);
        header('Location: angebote.php');
        exit;
    }
}

$kunden = getAlleKunden(true);
$artikelListe = getAlleArtikel(true);
$ustSaetze = getUstSaetze();
$emailSignaturen = getAlleEmailSignaturen();

$dokument = null;
$positionen = [];
if ($id && ($action === 'edit' || $action === 'view')) {
    $dokument = getVerkaufsdokument((int)$id);
    $positionen = getVerkaufsdokumentPositionen((int)$id);
    if ($dokument && $dokument['status'] !== 'entwurf' && $action === 'edit') {
        $action = 'view';
    }
}

$liste = getVerkaufsdokumente($TYP, ['jahr' => $_GET['jahr'] ?? '']);
$pageTitle = 'Angebote';
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
                <?php displayFlashMessage(); ?>

                <?php if ($action === 'new' || $action === 'edit'): ?>
                <!-- Formular -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-file-earmark-text me-2"></i><?= $dokument ? 'Angebot bearbeiten' : 'Neues Angebot' ?></h1>
                    <a href="angebote.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $dokument['id'] ?? '' ?>">

                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kunde</label>
                                    <select class="form-select" name="kunde_id" required>
                                        <option value="">-- Wählen --</option>
                                        <?php foreach ($kunden as $k): ?>
                                        <option value="<?= $k['id'] ?>" <?= ($dokument['kunde_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(kundenAnzeigename($k)) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Datum</label>
                                    <input type="date" class="form-control" name="datum" value="<?= $dokument['datum'] ?? date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gültig bis</label>
                                    <input type="date" class="form-control" name="gueltig_bis" value="<?= $dokument['gueltig_bis'] ?? date('Y-m-d', strtotime('+30 days')) ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Betreff</label>
                                <input type="text" class="form-control" name="betreff" value="<?= htmlspecialchars($dokument['betreff'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Einleitungstext</label>
                                <textarea class="form-control" name="einleitungstext" rows="2"><?= htmlspecialchars($dokument['einleitungstext'] ?? 'Vielen Dank für Ihre Anfrage. Wir erlauben uns, Ihnen wie folgt anzubieten:') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Positionen</span>
                            <button type="button" class="btn btn-primary" onclick="addPositionRow()">
                                <i class="bi bi-plus-lg me-1"></i>Position hinzufügen
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0" id="positionenTabelle">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 4%"></th>
                                            <th style="width: 18%">Artikel</th>
                                            <th>Bezeichnung</th>
                                            <th style="width: 8%">Menge</th>
                                            <th style="width: 8%">Einheit</th>
                                            <th style="width: 12%">Einzelpreis</th>
                                            <th style="width: 10%">USt-Satz</th>
                                            <th style="width: 8%">Rabatt %</th>
                                            <th style="width: 5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="positionenBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-end align-items-center flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0 small" for="gesamtrabatt_prozent">Gesamtrabatt %</label>
                                <input type="text" class="form-control form-control-sm" id="gesamtrabatt_prozent" name="gesamtrabatt_prozent" style="width: 70px;" value="<?= number_format($dokument['gesamtrabatt_prozent'] ?? 0, 2, ',', '') ?>">
                            </div>
                            <div id="liveZwischensummeWrap" class="text-muted small d-none">Zwischensumme: <span id="liveZwischensumme">0,00 €</span></div>
                            <div>Netto: <strong id="liveSumNetto">0,00 €</strong></div>
                            <div>USt: <strong id="liveSumUst">0,00 €</strong></div>
                            <div>Brutto: <strong id="liveSumBrutto" class="fs-6">0,00 €</strong></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Schlusstext</label>
                        <textarea class="form-control" name="schlusstext" rows="2"><?= htmlspecialchars($dokument['schlusstext'] ?? 'Wir freuen uns auf Ihre Rückmeldung.') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interne Notizen</label>
                        <textarea class="form-control" name="notizen" rows="2"><?= htmlspecialchars($dokument['notizen'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Speichern</button>
                    <a href="angebote.php" class="btn btn-outline-secondary">Abbrechen</a>
                </form>

                <?php elseif ($action === 'view' && $dokument): ?>
                <!-- Ansicht (finalisiert / nicht mehr editierbar) -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-file-earmark-text me-2"></i>Angebot <?= htmlspecialchars($dokument['nummer'] ?: '(Entwurf)') ?></h1>
                    <div>
                        <a href="pdf_verkaufsdokument.php?id=<?= $dokument['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-file-pdf me-1"></i>PDF
                        </a>
                        <button type="button" class="btn btn-outline-<?= !empty($dokument['versendet_am']) ? 'success' : 'secondary' ?>" data-bs-toggle="modal" data-bs-target="#versandModal"
                                onclick="oeffneVersandModal(<?= versandModalOnclickArgs($dokument) ?>)"
                                title="<?= htmlspecialchars(versandButtonTitle($dokument)) ?>">
                            <i class="bi bi-envelope<?= !empty($dokument['versendet_am']) ? '-check' : '' ?> me-1"></i>Versenden
                        </button>
                        <?php if ($dokument['status'] === 'angenommen'): ?>
                        <span class="badge bg-success align-middle">In Auftrag umgewandelt</span>
                        <?php endif; ?>
                        <a href="angebote.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p><strong>Kunde:</strong> <?= htmlspecialchars(kundenAnzeigename($dokument)) ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst($dokument['status'])) ?></p>
                        <?php if (!empty($dokument['versendet_am'])): ?>
                        <p class="text-muted small"><i class="bi bi-envelope-check me-1"></i>Per E-Mail gesendet am <?= formatDatum($dokument['versendet_am']) ?> an <?= htmlspecialchars($dokument['versendet_an']) ?></p>
                        <?php endif; ?>
                        <table class="table">
                            <thead><tr><th>Pos</th><th>Bezeichnung</th><th>Menge</th><th class="text-end">Netto</th><th class="text-end">Brutto</th></tr></thead>
                            <tbody>
                                <?php foreach ($positionen as $pos): ?>
                                <tr>
                                    <td><?= $pos['position'] ?></td>
                                    <td><?= htmlspecialchars($pos['bezeichnung']) ?></td>
                                    <td><?= number_format($pos['menge'], 2, ',', '.') ?> <?= htmlspecialchars($pos['einheit']) ?></td>
                                    <td class="text-end"><?= formatBetrag($pos['netto_summe']) ?></td>
                                    <td class="text-end"><?= formatBetrag($pos['brutto_summe']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="text-end"><strong>Gesamt (Brutto): <?= formatBetrag($dokument['brutto_gesamt']) ?></strong></p>
                    </div>
                </div>

                <?php else: ?>
                <!-- Liste -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-file-earmark-text me-2"></i>Angebote</h1>
                    <a href="angebote.php?action=new" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Neues Angebot</a>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nummer</th><th>Datum</th><th>Kunde</th><th>Betreff</th>
                                        <th class="text-end">Brutto</th><th>Status</th><th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($liste)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Keine Angebote vorhanden</td></tr>
                                    <?php else: foreach ($liste as $d): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($d['nummer'] ?: '(Entwurf #' . $d['id'] . ')') ?></td>
                                        <td><?= formatDatum($d['datum']) ?></td>
                                        <td><?= htmlspecialchars(kundenAnzeigename($d)) ?></td>
                                        <td><?= htmlspecialchars($d['betreff'] ?: '-') ?></td>
                                        <td class="text-end"><?= formatBetrag($d['brutto_gesamt']) ?></td>
                                        <td>
                                            <?php
                                            $statusBadges = ['entwurf' => 'secondary', 'versendet' => 'info', 'angenommen' => 'success', 'abgelehnt' => 'danger'];
                                            ?>
                                            <span class="badge bg-<?= $statusBadges[$d['status']] ?? 'secondary' ?>"><?= ucfirst($d['status']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($d['status'] === 'entwurf'): ?>
                                            <a href="angebote.php?action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="finalisieren">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Angebot finalisieren? Danach ist keine Bearbeitung mehr möglich.')">
                                                    <i class="bi bi-check-circle"></i> Finalisieren
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Angebot wirklich löschen?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php else: ?>
                                            <a href="angebote.php?action=view&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                            <a href="pdf_verkaufsdokument.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-pdf"></i></a>
                                            <button type="button" class="btn btn-sm btn-outline-<?= !empty($d['versendet_am']) ? 'success' : 'secondary' ?>" data-bs-toggle="modal" data-bs-target="#versandModal"
                                                    onclick="oeffneVersandModal(<?= versandModalOnclickArgs($d) ?>)" title="<?= htmlspecialchars(versandButtonTitle($d)) ?>">
                                                <i class="bi bi-envelope<?= !empty($d['versendet_am']) ? '-check' : '' ?>"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($d['status'] === 'versendet'): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Angebot in Auftrag umwandeln?');">
                                                <input type="hidden" name="action" value="umwandeln">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="In Auftrag umwandeln">
                                                    <i class="bi bi-arrow-right-circle"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Versand Modal -->
    <div class="modal fade" id="versandModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="versenden">
                    <input type="hidden" name="id" id="v_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Per E-Mail versenden</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!mailConfigured()): ?>
                        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>E-Mail-Versand ist nicht konfiguriert (config/mail.php).</div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Empfänger-E-Mail</label>
                            <input type="email" class="form-control" name="empfaenger_email" id="v_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Betreff</label>
                            <input type="text" class="form-control" name="betreff" id="v_betreff" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nachricht</label>
                            <textarea class="form-control" name="nachricht" id="v_nachricht" rows="6"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label class="form-label">Signatur</label>
                                <select class="form-select" id="v_signatur_id" onchange="signaturWechseln()">
                                    <option value="">-- Keine --</option>
                                    <?php foreach ($emailSignaturen as $sig): ?>
                                    <option value="<?= $sig['id'] ?>"><?= htmlspecialchars($sig['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Signatur-Text</label>
                                <textarea class="form-control" name="signatur" id="v_signatur" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="form-text">
                            <a href="einstellungen.php?tab=email_vorlagen" target="_blank">Vorlagen &amp; Signaturen verwalten</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary" <?= mailConfigured() ? '' : 'disabled' ?>><i class="bi bi-send me-1"></i>Senden</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const emailSignaturenMap = <?= json_encode(array_column($emailSignaturen, 'inhalt', 'id')) ?>;

        function signaturWechseln() {
            const sigId = document.getElementById('v_signatur_id').value;
            document.getElementById('v_signatur').value = sigId ? (emailSignaturenMap[sigId] || '') : '';
        }

        function oeffneVersandModal(id, email, betreff, nachricht, signaturId, signaturText) {
            document.getElementById('v_id').value = id;
            document.getElementById('v_email').value = email || '';
            document.getElementById('v_betreff').value = betreff || '';
            document.getElementById('v_nachricht').value = nachricht || '';
            document.getElementById('v_signatur_id').value = signaturId || '';
            document.getElementById('v_signatur').value = signaturText || '';
        }

        const artikelDaten = <?= json_encode($artikelListe) ?>;
        const ustSaetze = <?= json_encode($ustSaetze) ?>;
        const bestehendePositionen = <?= json_encode($positionen) ?>;

        function ustOptions(selectedId) {
            let html = '<option value="">-</option>';
            ustSaetze.forEach(u => {
                html += `<option value="${u.id}" ${u.id == selectedId ? 'selected' : ''}>${u.bezeichnung}</option>`;
            });
            return html;
        }

        function artikelOptions(selectedId) {
            let html = '<option value="">-- frei --</option>';
            artikelDaten.forEach(a => {
                html += `<option value="${a.id}" ${a.id == selectedId ? 'selected' : ''}>${a.bezeichnung}</option>`;
            });
            return html;
        }

        function escapeHtml(str) {
            return (str || '').toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function addPositionRow(pos) {
            pos = pos || {};
            const tbody = document.getElementById('positionenBody');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="verschiebeZeile(this, -1)" title="Nach oben"><i class="bi bi-caret-up-fill"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="verschiebeZeile(this, 1)" title="Nach unten"><i class="bi bi-caret-down-fill"></i></button>
                </td>
                <td><select class="form-select form-select-sm" name="pos_artikel_id[]" onchange="uebernehmeArtikel(this)">${artikelOptions(pos.artikel_id)}</select></td>
                <td>
                    <input type="text" class="form-control form-control-sm mb-1" name="pos_bezeichnung[]" value="${escapeHtml(pos.bezeichnung)}" placeholder="Bezeichnung">
                    <textarea class="form-control form-control-sm" name="pos_beschreibung[]" rows="2" placeholder="Beschreibung (optional, mehrzeilig)" style="font-size: 0.8rem;">${escapeHtml(pos.beschreibung)}</textarea>
                </td>
                <td><input type="text" class="form-control form-control-sm" name="pos_menge[]" value="${pos.menge ? parseFloat(pos.menge).toString().replace('.', ',') : '1'}"></td>
                <td><input type="text" class="form-control form-control-sm" name="pos_einheit[]" value="${pos.einheit || 'Stk'}"></td>
                <td><input type="text" class="form-control form-control-sm" name="pos_einzelpreis_netto[]" value="${pos.einzelpreis_netto ? parseFloat(pos.einzelpreis_netto).toFixed(2).replace('.', ',') : '0,00'}"></td>
                <td><select class="form-select form-select-sm" name="pos_ust_satz_id[]">${ustOptions(pos.ust_satz_id)}</select></td>
                <td><input type="text" class="form-control form-control-sm" name="pos_rabatt_prozent[]" value="${pos.rabatt_prozent || '0'}"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); berechneGesamtsummen();"><i class="bi bi-x"></i></button></td>
            `;
            tbody.appendChild(tr);
            berechneGesamtsummen();
        }

        // Position in der Tabelle nach oben (-1) oder unten (+1) verschieben - die gespeicherte
        // Reihenfolge (verkaufsdokument_positionen.position) folgt einfach der DOM-Reihenfolge beim Speichern.
        function verschiebeZeile(btn, richtung) {
            const tr = btn.closest('tr');
            if (richtung < 0 && tr.previousElementSibling) {
                tr.parentNode.insertBefore(tr, tr.previousElementSibling);
            } else if (richtung > 0 && tr.nextElementSibling) {
                tr.parentNode.insertBefore(tr.nextElementSibling, tr);
            }
        }

        function uebernehmeArtikel(select) {
            const artikel = artikelDaten.find(a => a.id == select.value);
            if (!artikel) return;
            const tr = select.closest('tr');
            tr.querySelector('[name="pos_bezeichnung[]"]').value = artikel.bezeichnung;
            tr.querySelector('[name="pos_beschreibung[]"]').value = artikel.beschreibung || '';
            tr.querySelector('[name="pos_einheit[]"]').value = artikel.einheit || 'Stk';
            tr.querySelector('[name="pos_einzelpreis_netto[]"]').value = parseFloat(artikel.einzelpreis_netto).toFixed(2).replace('.', ',');
            tr.querySelector('[name="pos_ust_satz_id[]"]').value = artikel.ust_satz_id || '';
            berechneGesamtsummen();
        }

        // Live Netto/USt/Brutto-Vorschau beim Bearbeiten der Positionen (vor dem Speichern),
        // inkl. Gesamtrabatt auf Dokument-Ebene (zusätzlich zu den Positions-Rabatten).
        function berechneGesamtsummen() {
            let netto = 0, ust = 0;
            document.querySelectorAll('#positionenBody tr').forEach(tr => {
                const menge = parseFloat((tr.querySelector('[name="pos_menge[]"]')?.value || '0').replace(',', '.')) || 0;
                const preis = parseFloat((tr.querySelector('[name="pos_einzelpreis_netto[]"]')?.value || '0').replace(',', '.')) || 0;
                const rabatt = parseFloat((tr.querySelector('[name="pos_rabatt_prozent[]"]')?.value || '0').replace(',', '.')) || 0;
                const ustId = tr.querySelector('[name="pos_ust_satz_id[]"]')?.value;
                const ustSatz = ustSaetze.find(u => u.id == ustId);
                const ustProzent = ustSatz ? parseFloat(ustSatz.satz) : 0;

                const zeilenNetto = menge * preis * (1 - rabatt / 100);
                netto += zeilenNetto;
                ust += zeilenNetto * (ustProzent / 100);
            });

            const zwischensummeNetto = netto;
            const gesamtrabatt = parseFloat((document.getElementById('gesamtrabatt_prozent')?.value || '0').replace(',', '.')) || 0;
            const rabattfaktor = 1 - Math.min(100, Math.max(0, gesamtrabatt)) / 100;
            netto *= rabattfaktor;
            ust *= rabattfaktor;
            const brutto = netto + ust;

            const fmt = n => n.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const elZwischensummeWrap = document.getElementById('liveZwischensummeWrap');
            const elZwischensumme = document.getElementById('liveZwischensumme');
            const elNetto = document.getElementById('liveSumNetto');
            const elUst = document.getElementById('liveSumUst');
            const elBrutto = document.getElementById('liveSumBrutto');
            if (elZwischensummeWrap) elZwischensummeWrap.classList.toggle('d-none', gesamtrabatt <= 0);
            if (elZwischensumme) elZwischensumme.textContent = fmt(zwischensummeNetto) + ' €';
            if (elNetto) elNetto.textContent = fmt(netto) + ' €';
            if (elUst) elUst.textContent = fmt(ust) + ' €';
            if (elBrutto) elBrutto.textContent = fmt(brutto) + ' €';
        }

        const positionenTabelleEl = document.getElementById('positionenTabelle');
        if (positionenTabelleEl) {
            positionenTabelleEl.addEventListener('input', berechneGesamtsummen);
            positionenTabelleEl.addEventListener('change', berechneGesamtsummen);
        }
        document.getElementById('gesamtrabatt_prozent')?.addEventListener('input', berechneGesamtsummen);

        if (bestehendePositionen.length > 0) {
            bestehendePositionen.forEach(p => addPositionRow(p));
        } else if (document.getElementById('positionenBody')) {
            addPositionRow();
        }
        berechneGesamtsummen();
    </script>
</body>
</html>
