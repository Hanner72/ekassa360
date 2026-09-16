<?php
/**
 * Verkaufsrechnungen - Verkauf-Modul
 * Entwurf-CRUD, Finalisieren (Nummernkreis + Ledger-Zeilen), Zahlungsabgleich, Storno,
 * paperless-Archivierung beim Finalisieren.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/verkauf_functions.php';
require_once 'includes/verkauf_pdf.php';
require_once 'includes/paperless.php';
require_once 'includes/mail.php';
require_once 'includes/bondrucker.php';

requireLogin();

$TYP = 'rechnung';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'save') {
        $data = [
            'id' => $_POST['id'] ?: null,
            'typ' => $TYP,
            'kunde_id' => $_POST['kunde_id'] ?: null,
            'firmenprofil_id' => $_POST['firmenprofil_id'] ?: null,
            'datum' => $_POST['datum'],
            'leistungsdatum' => $_POST['leistungsdatum'] ?: null,
            'faellig_am' => $_POST['faellig_am'] ?: null,
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
            header('Location: verkaufsrechnungen.php?action=' . ($data['id'] ? 'edit&id=' . $data['id'] : 'new'));
            exit;
        }

        $result = saveVerkaufsdokument($data, $positionen);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Rechnung gespeichert.' : $result['message']);
        header('Location: verkaufsrechnungen.php');
        exit;
    }

    if ($postAction === 'delete') {
        $result = deleteVerkaufsdokument((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Rechnung gelöscht.' : $result['message']);
        header('Location: verkaufsrechnungen.php');
        exit;
    }

    if ($postAction === 'finalisieren') {
        $result = finalizeVerkaufsrechnung((int)$_POST['id']);
        if ($result['success']) {
            if (paperlessConfigured()) {
                $archiv = archiviereVerkaufsdokumentInPaperless($result['id']);
                if ($archiv['success']) {
                    setFlashMessage('success', 'Rechnung ' . $result['nummer'] . ' finalisiert und in paperless-ngx archiviert.');
                } else {
                    setFlashMessage('warning', 'Rechnung ' . $result['nummer'] . ' finalisiert, Archivierung in paperless-ngx fehlgeschlagen: ' . $archiv['error']);
                }
            } else {
                setFlashMessage('success', 'Rechnung ' . $result['nummer'] . ' finalisiert.');
            }
        } else {
            setFlashMessage('danger', $result['message']);
        }
        header('Location: verkaufsrechnungen.php');
        exit;
    }

    if ($postAction === 'erneut_senden') {
        $result = archiviereVerkaufsdokumentInPaperless((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Dokument wurde in paperless-ngx archiviert.' : ('Archivierung fehlgeschlagen: ' . $result['error']));
        header('Location: verkaufsrechnungen.php');
        exit;
    }

    if ($postAction === 'markBezahlt') {
        $result = markVerkaufsrechnungBezahlt(
            (int)$_POST['id'],
            isset($_POST['bezahlt']) ? 1 : 0,
            $_POST['bezahlt_am'] ?: null,
            $_POST['zahlungsart'] ?? 'bankueberweisung'
        );
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Zahlungsstatus aktualisiert.' : $result['message']);
        header('Location: verkaufsrechnungen.php');
        exit;
    }

    if ($postAction === 'stornieren') {
        $result = storniereVerkaufsrechnung((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Storniert mit Nummer ' . $result['storno_nummer'] . '.' : $result['message']);
        header('Location: verkaufsrechnungen.php');
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
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Rechnung per E-Mail an ' . $result['empfaenger'] . ' versendet.' : $result['error']);
        header('Location: verkaufsrechnungen.php');
        exit;
    }

    if ($postAction === 'bondrucken') {
        $result = druckeVerkaufsrechnungAufBondrucker((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['message']);
        header('Location: verkaufsrechnungen.php');
        exit;
    }
}

$kunden = getAlleKunden(true);
$firmenprofile = getAlleFirmenprofile(true);
$emailSignaturen = getAlleEmailSignaturen();
$artikelListe = getAlleArtikel(true);
$ustSaetze = getUstSaetze();

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
$zahlungsstatusMap = [];
foreach ($liste as $d) {
    if ($d['status'] !== 'entwurf') {
        $zahlungsstatusMap[$d['id']] = getVerkaufsrechnungZahlungsstatus($d['id']);
    }
}

$statusBadges = ['entwurf' => 'secondary', 'abgeschlossen' => 'success', 'storniert' => 'dark'];
$statusLabels = ['entwurf' => 'Entwurf', 'abgeschlossen' => 'Abgeschlossen', 'storniert' => 'Storniert'];
$pageTitle = 'Verkaufsrechnungen';
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
                    <h1 class="h2"><i class="bi bi-receipt-cutoff me-2"></i><?= $dokument ? 'Rechnung bearbeiten' : 'Neue Rechnung' ?></h1>
                    <a href="verkaufsrechnungen.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
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
                                    <label class="form-label">Rechnungsdatum</label>
                                    <input type="date" class="form-control" name="datum" value="<?= $dokument['datum'] ?? date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Fällig am</label>
                                    <input type="date" class="form-control" name="faellig_am" value="<?= $dokument['faellig_am'] ?? date('Y-m-d', strtotime('+14 days')) ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Leistungsdatum</label>
                                <input type="date" class="form-control" name="leistungsdatum" value="<?= $dokument['leistungsdatum'] ?? date('Y-m-d') ?>">
                                <div class="form-text">Pflichtangabe gem. § 11 UStG</div>
                            </div>
                            <?php if (count($firmenprofile) > 1): ?>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Firmenprofil</label>
                                    <select class="form-select" name="firmenprofil_id">
                                        <?php
                                        $aktuellesProfil = $dokument['firmenprofil_id'] ?? (getStandardFirmenprofil()['id'] ?? null);
                                        foreach ($firmenprofile as $fp):
                                        ?>
                                        <option value="<?= $fp['id'] ?>" <?= $aktuellesProfil == $fp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($fp['name']) ?><?= $fp['ist_standard'] ? ' (Standard)' : '' ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Betreff</label>
                                <input type="text" class="form-control" name="betreff" value="<?= htmlspecialchars($dokument['betreff'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Einleitungstext</label>
                                <textarea class="form-control" name="einleitungstext" rows="2"><?= htmlspecialchars($dokument['einleitungstext'] ?? 'Wir erlauben uns, folgende Leistungen in Rechnung zu stellen:') ?></textarea>
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
                        <textarea class="form-control" name="schlusstext" rows="2"><?= htmlspecialchars($dokument['schlusstext'] ?? 'Vielen Dank für Ihr Vertrauen.') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Interne Notizen</label>
                        <textarea class="form-control" name="notizen" rows="2"><?= htmlspecialchars($dokument['notizen'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Als Entwurf speichern</button>
                    <a href="verkaufsrechnungen.php" class="btn btn-outline-secondary">Abbrechen</a>
                </form>

                <?php elseif ($action === 'view' && $dokument): ?>
                <!-- Ansicht (finalisiert) -->
                <?php $zs = getVerkaufsrechnungZahlungsstatus($dokument['id']); ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-receipt-cutoff me-2"></i>Rechnung <?= htmlspecialchars($dokument['nummer'] ?: '(Entwurf)') ?></h1>
                    <div>
                        <a href="pdf_verkaufsdokument.php?id=<?= $dokument['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                            <i class="bi bi-file-pdf me-1"></i>PDF
                        </a>
                        <button type="button" class="btn btn-outline-<?= !empty($dokument['versendet_am']) ? 'success' : 'secondary' ?>" data-bs-toggle="modal" data-bs-target="#versandModal"
                                onclick="oeffneVersandModal(<?= versandModalOnclickArgs($dokument) ?>)"
                                title="<?= htmlspecialchars(versandButtonTitle($dokument)) ?>">
                            <i class="bi bi-envelope<?= !empty($dokument['versendet_am']) ? '-check' : '' ?> me-1"></i>Versenden
                        </button>
                        <a href="verkaufsrechnungen.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Zurück</a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <p><strong>Kunde:</strong> <?= htmlspecialchars(kundenAnzeigename($dokument)) ?></p>
                        <p><strong>Status:</strong> <?= htmlspecialchars($statusLabels[$dokument['status']] ?? ucfirst($dokument['status'])) ?>
                            <?php if ($zs['finalisiert']): ?>
                                - <span class="badge bg-<?= $zs['bezahlt'] ? 'success' : 'warning text-dark' ?>"><?= $zs['bezahlt'] ? 'Bezahlt' : 'Offen' ?></span>
                            <?php endif; ?>
                        </p>
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
                    <h1 class="h2"><i class="bi bi-receipt-cutoff me-2"></i>Verkaufsrechnungen</h1>
                    <a href="verkaufsrechnungen.php?action=new" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Neue Rechnung</a>
                </div>

                <?php if (!paperlessConfigured()): ?>
                <div class="alert alert-secondary"><i class="bi bi-info-circle me-1"></i>paperless-ngx-Integration ist nicht konfiguriert (config/paperless.php) - Rechnungen werden ohne automatische Archivierung finalisiert.</div>
                <?php endif; ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="rechnungenSuche" placeholder="Suche nach Nummer, Kunde, Status...">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="rechnungenTabelle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="sortierbar" data-spalte="0">Nummer <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="1">Datum <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="2">Kunde <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="text-end sortierbar" data-spalte="3">Brutto <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="4">Status <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th>Zahlung</th><th>paperless</th><th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($liste)): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4">Keine Rechnungen vorhanden</td></tr>
                                    <?php else: foreach ($liste as $d): $zs = $zahlungsstatusMap[$d['id']] ?? null; ?>
                                    <tr>
                                        <td data-sortwert="<?= htmlspecialchars($d['nummer'] ?? '') ?>"><?= htmlspecialchars($d['nummer'] ?: '(Entwurf #' . $d['id'] . ')') ?></td>
                                        <td data-sortwert="<?= $d['datum'] ?>"><?= formatDatum($d['datum']) ?></td>
                                        <td><?= htmlspecialchars(kundenAnzeigename($d)) ?></td>
                                        <td class="text-end" data-sortwert="<?= $d['brutto_gesamt'] ?>"><?= formatBetrag($d['brutto_gesamt']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $statusBadges[$d['status']] ?? 'secondary' ?>"><?= $statusLabels[$d['status']] ?? ucfirst($d['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($zs && $zs['finalisiert']): ?>
                                                <span class="badge bg-<?= $zs['bezahlt'] ? 'success' : 'warning text-dark' ?>"><?= $zs['bezahlt'] ? 'Bezahlt' : 'Offen' ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($d['status'] === 'entwurf'): ?>
                                                <span class="text-muted">-</span>
                                            <?php elseif (!empty($d['paperless_document_id'])): ?>
                                                <span class="badge bg-success" title="paperless-Dokument #<?= $d['paperless_document_id'] ?>"><i class="bi bi-archive"></i></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">nicht archiviert</span>
                                                <?php if (paperlessConfigured()): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="erneut_senden">
                                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-link p-0">Erneut senden</button>
                                                </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($d['status'] === 'entwurf'): ?>
                                            <a href="verkaufsrechnungen.php?action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                            <a href="pdf_verkaufsdokument.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Vorschau (Entwurf, mit Wasserzeichen)">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="finalisieren">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Rechnung finalisieren? Danach ist keine Bearbeitung mehr möglich, nur noch Storno.')">
                                                    <i class="bi bi-check-circle"></i> Finalisieren
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Entwurf wirklich löschen?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php elseif ($d['status'] === 'abgeschlossen'): ?>
                                            <a href="verkaufsrechnungen.php?action=view&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                            <a href="pdf_verkaufsdokument.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="PDF"><i class="bi bi-file-pdf"></i></a>
                                            <a href="pdf_lieferschein.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Lieferschein"><i class="bi bi-truck"></i></a>
                                            <?php if (bondruckerKonfiguriert()): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="bondrucken">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Auf Bondrucker drucken"><i class="bi bi-printer"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-<?= !empty($d['versendet_am']) ? 'success' : 'secondary' ?>" data-bs-toggle="modal" data-bs-target="#versandModal"
                                                    onclick="oeffneVersandModal(<?= versandModalOnclickArgs($d) ?>)" title="<?= htmlspecialchars(versandButtonTitle($d)) ?>">
                                                <i class="bi bi-envelope<?= !empty($d['versendet_am']) ? '-check' : '' ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#zahlungModal"
                                                    onclick="oeffneZahlungModal(<?= $d['id'] ?>, <?= $zs['bezahlt'] ? 'true' : 'false' ?>, '<?= $zs['bezahlt_am'] ?? '' ?>')">
                                                <i class="bi bi-cash-coin"></i>
                                            </button>
                                            <?php if (empty($d['storno_von_id'])): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Rechnung stornieren? Es wird eine Storno-Rechnung mit eigener Nummer erzeugt.');">
                                                <input type="hidden" name="action" value="stornieren">
                                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Stornieren"><i class="bi bi-x-octagon"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <a href="verkaufsrechnungen.php?action=view&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                            <a href="pdf_verkaufsdokument.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="PDF"><i class="bi bi-file-pdf"></i></a>
                                            <button type="button" class="btn btn-sm btn-outline-<?= !empty($d['versendet_am']) ? 'success' : 'secondary' ?>" data-bs-toggle="modal" data-bs-target="#versandModal"
                                                    onclick="oeffneVersandModal(<?= versandModalOnclickArgs($d) ?>)" title="<?= htmlspecialchars(versandButtonTitle($d)) ?>">
                                                <i class="bi bi-envelope<?= !empty($d['versendet_am']) ? '-check' : '' ?>"></i>
                                            </button>
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

    <!-- Zahlung Modal -->
    <div class="modal fade" id="zahlungModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="markBezahlt">
                    <input type="hidden" name="id" id="z_id" value="">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Zahlungsstatus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="bezahlt" id="z_bezahlt" value="1">
                            <label class="form-check-label" for="z_bezahlt">Bezahlt</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bezahlt am</label>
                            <input type="date" class="form-control" name="bezahlt_am" id="z_bezahlt_am">
                            <div class="form-text">Maßgeblich für U30/E1a (Ist-Besteuerung)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Zahlungsart</label>
                            <select class="form-select" name="zahlungsart">
                                <option value="bankueberweisung">Banküberweisung</option>
                                <option value="bar">Barzahlung</option>
                                <option value="sonstige">Sonstige</option>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function oeffneZahlungModal(id, bezahlt, bezahltAm) {
            document.getElementById('z_id').value = id;
            document.getElementById('z_bezahlt').checked = bezahlt;
            document.getElementById('z_bezahlt_am').value = bezahltAm || new Date().toISOString().slice(0, 10);
        }

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
            if (!tbody) return;
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

        // Suche: filtert Zeilen client-seitig über alle sichtbaren Spalten
        const rechnungenSucheFeld = document.getElementById('rechnungenSuche');
        const rechnungenTbody = document.querySelector('#rechnungenTabelle tbody');
        rechnungenSucheFeld?.addEventListener('input', function() {
            const suchbegriff = this.value.trim().toLowerCase();
            rechnungenTbody.querySelectorAll('tr').forEach(function(zeile) {
                if (!zeile.cells || zeile.cells.length < 7) return; // "Keine Rechnungen vorhanden"-Zeile überspringen
                const text = zeile.textContent.toLowerCase();
                zeile.classList.toggle('d-none', suchbegriff !== '' && !text.includes(suchbegriff));
            });
        });

        // Sortierung: Klick auf Spaltenkopf sortiert die Tabellenzeilen auf-/absteigend.
        // Standard beim Laden: Nummer (Spalte 0) absteigend.
        let rechnungenSortSpalte = null;
        let rechnungenSortAufsteigend = true;
        function rechnungenSortiereNach(spalte, aufsteigend) {
            rechnungenSortSpalte = spalte;
            rechnungenSortAufsteigend = aufsteigend;

            const zeilen = Array.from(rechnungenTbody.querySelectorAll('tr')).filter(z => z.cells && z.cells.length >= 7);
            zeilen.sort(function(a, b) {
                const zelleA = a.cells[spalte];
                const zelleB = b.cells[spalte];
                const wertA = zelleA.dataset.sortwert ?? zelleA.textContent.trim().toLowerCase();
                const wertB = zelleB.dataset.sortwert ?? zelleB.textContent.trim().toLowerCase();
                const zahlenMuster = /^-?\d+(\.\d+)?$/;
                let vergleich;
                if (zahlenMuster.test(wertA) && zahlenMuster.test(wertB)) {
                    vergleich = parseFloat(wertA) - parseFloat(wertB);
                } else {
                    vergleich = wertA.localeCompare(wertB, 'de');
                }
                return aufsteigend ? vergleich : -vergleich;
            });
            zeilen.forEach(z => rechnungenTbody.appendChild(z));

            document.querySelectorAll('#rechnungenTabelle th.sortierbar i').forEach(i => i.className = 'bi bi-arrow-down-up small text-muted');
            const th = document.querySelector('#rechnungenTabelle th.sortierbar[data-spalte="' + spalte + '"]');
            if (th) th.querySelector('i').className = 'bi bi-arrow-' + (aufsteigend ? 'up' : 'down') + ' small';
        }

        document.querySelectorAll('#rechnungenTabelle th.sortierbar').forEach(function(th) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const spalte = parseInt(th.dataset.spalte, 10);
                const aufsteigend = (rechnungenSortSpalte === spalte) ? !rechnungenSortAufsteigend : true;
                rechnungenSortiereNach(spalte, aufsteigend);
            });
        });

        if (rechnungenTbody) {
            rechnungenSortiereNach(0, false);
        }
    </script>
</body>
</html>
