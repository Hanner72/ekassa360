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
            'artikelgruppe_id' => $_POST['artikelgruppe_id'] ?: null,
            'artikeluntergruppe_id' => $_POST['artikeluntergruppe_id'] ?: null,
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

    if ($action === 'delete') {
        $result = deleteArtikel((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Artikel gelöscht.' : $result['message']);
        header('Location: artikel.php');
        exit;
    }

    if ($action === 'delete_artikelgruppe') {
        $result = deleteArtikelgruppe((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Artikelgruppe gelöscht.' : $result['message']);
        header('Location: artikel.php');
        exit;
    }

    if ($action === 'save_artikelgruppe') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            setFlashMessage('danger', 'Bitte einen Namen für die Artikelgruppe angeben.');
        } else {
            saveArtikelgruppe([
                'id' => (int)$_POST['id'],
                'name' => $name,
                'kurzbezeichnung' => trim($_POST['kurzbezeichnung'] ?? ''),
                'aktiv' => isset($_POST['aktiv']) ? (int)$_POST['aktiv'] : 1,
            ]);
            setFlashMessage('success', 'Artikelgruppe gespeichert.');
        }
        header('Location: artikel.php');
        exit;
    }

    if ($action === 'delete_artikeluntergruppe') {
        $result = deleteArtikeluntergruppe((int)$_POST['id']);
        setFlashMessage($result['success'] ? 'success' : 'danger', $result['success'] ? 'Artikeluntergruppe gelöscht.' : $result['message']);
        header('Location: artikel.php');
        exit;
    }

    if ($action === 'save_artikeluntergruppe') {
        $name = trim($_POST['name'] ?? '');
        $gruppeId = $_POST['artikelgruppe_id'] ?? null;
        if ($name === '') {
            setFlashMessage('danger', 'Bitte einen Namen für die Artikeluntergruppe angeben.');
        } elseif (empty($_POST['id']) && empty($gruppeId)) {
            setFlashMessage('danger', 'Bitte eine Artikelgruppe auswählen.');
        } else {
            saveArtikeluntergruppe([
                'id' => (int)$_POST['id'],
                'name' => $name,
                'kurzbezeichnung' => trim($_POST['kurzbezeichnung'] ?? ''),
                'artikelgruppe_id' => $gruppeId ?: null,
                'aktiv' => isset($_POST['aktiv']) ? (int)$_POST['aktiv'] : 1,
            ]);
            setFlashMessage('success', 'Artikeluntergruppe gespeichert.');
        }
        header('Location: artikel.php');
        exit;
    }
}

$artikelListe = getAlleArtikel(false);
$artikelIdsInVerwendung = array_flip(getArtikelIdsInVerwendung());
$ustSaetze = getUstSaetze();
$kategorien = getKategorien('einnahme');
$artikelgruppen = getAlleArtikelgruppen(false);
$artikelgruppenMitAnzahl = getAlleArtikelgruppenMitAnzahl();
$artikeluntergruppen = getAlleArtikeluntergruppen(null, false);
$artikeluntergruppenMitAnzahl = getAlleArtikeluntergruppenMitAnzahl();

// Für die eingerückte Baumdarstellung im "Artikelgruppen verwalten"-Modal: Untergruppen
// nach ihrer übergeordneten Gruppe gruppieren (Reihenfolge innerhalb bleibt wie geliefert,
// bereits nach Kurzbezeichnung sortiert).
$untergruppenByGruppe = [];
foreach ($artikeluntergruppenMitAnzahl as $untergruppe) {
    $untergruppenByGruppe[$untergruppe['artikelgruppe_id']][] = $untergruppe;
}

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
                    <div>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#artikelgruppenModal">
                            <i class="bi bi-tags me-1"></i>Artikelgruppen verwalten
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#artikelModal">
                            <i class="bi bi-plus-lg me-1"></i>Neuer Artikel
                        </button>
                    </div>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="artikelSuche" placeholder="Suche nach Artikelnummer, Bezeichnung, Artikelgruppe, Untergruppe, USt-Satz...">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="artikelTabelle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="sortierbar" data-spalte="0">Art.-Nr. <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="1">Bezeichnung <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="2">Artikelgruppe <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="3">Untergruppe <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="4">Einheit <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="text-end sortierbar" data-spalte="5">Einzelpreis (Netto) <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="6">USt-Satz <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="sortierbar" data-spalte="7">Status <i class="bi bi-arrow-down-up small text-muted"></i></th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($artikelListe)): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">Keine Artikel vorhanden</td></tr>
                                    <?php else: foreach ($artikelListe as $a): ?>
                                    <tr class="<?= !$a['aktiv'] ? 'table-light text-muted' : '' ?>">
                                        <td><?= htmlspecialchars($a['artikelnummer'] ?: '-') ?></td>
                                        <td><strong><?= htmlspecialchars($a['bezeichnung']) ?></strong></td>
                                        <td><?= htmlspecialchars($a['artikelgruppe_name'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($a['artikeluntergruppe_name'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($a['einheit']) ?></td>
                                        <td class="text-end" data-sortwert="<?= $a['einzelpreis_netto'] ?>"><?= formatBetrag($a['einzelpreis_netto']) ?></td>
                                        <td><?= htmlspecialchars($a['ust_bezeichnung'] ?? '-') ?></td>
                                        <td data-sortwert="<?= $a['aktiv'] ? 1 : 0 ?>">
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
                                            <?php if (isset($artikelIdsInVerwendung[$a['id']])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                                    title="Kann nicht gelöscht werden - der Artikel wird bereits in einem Angebot, Auftrag oder einer Rechnung verwendet. Stattdessen deaktivieren.">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Artikel wirklich löschen?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Artikel löschen">
                                                    <i class="bi bi-trash"></i>
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
                                <input type="text" class="form-control" name="artikelnummer" id="a_artikelnummer" placeholder="Automatisch">
                                <div class="form-text">Leer = wird automatisch aus dem Nummernkreis vergeben</div>
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
                            <div class="col-md-3">
                                <label class="form-label">Einzelpreis (Netto)</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="text" class="form-control" name="einzelpreis_netto" id="a_einzelpreis_netto" value="0,00" oninput="preisNettoGeaendert()">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Einzelpreis (Brutto)</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="text" class="form-control" id="a_einzelpreis_brutto" value="0,00" oninput="preisBruttoGeaendert()">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">USt-Satz</label>
                                <select class="form-select" name="ust_satz_id" id="a_ust_satz_id" onchange="ustSatzGeaendert()">
                                    <option value="" data-satz="0">-- Kein USt --</option>
                                    <?php foreach ($ustSaetze as $ust): ?>
                                    <option value="<?= $ust['id'] ?>" data-satz="<?= $ust['satz'] ?>"><?= htmlspecialchars($ust['bezeichnung']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Artikelgruppe</label>
                                <div class="input-group">
                                    <select class="form-select" name="artikelgruppe_id" id="a_artikelgruppe_id" onchange="artikelgruppeGeaendert()">
                                        <option value="">-- Keine --</option>
                                        <?php foreach ($artikelgruppen as $gruppe): ?>
                                        <option value="<?= $gruppe['id'] ?>"><?= htmlspecialchars($gruppe['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" onclick="artikelgruppeSchnellAnlageUmschalten()" title="Neue Artikelgruppe anlegen">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <div id="artikelgruppeSchnellAnlage" class="d-none mt-2 p-2 border rounded bg-light">
                                    <div class="row g-2">
                                        <div class="col-7">
                                            <input type="text" class="form-control form-control-sm" id="neueArtikelgruppeName" placeholder="z.B. Textilien">
                                        </div>
                                        <div class="col-3">
                                            <input type="text" class="form-control form-control-sm" id="neueArtikelgruppeKurz" placeholder="Kurzbez." maxlength="10">
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-sm btn-success w-100" onclick="artikelgruppeSchnellAnlegen()">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="artikelgruppeSchnellAnlageFehler" class="text-danger small mt-1"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Artikeluntergruppe</label>
                                <div class="input-group">
                                    <select class="form-select" name="artikeluntergruppe_id" id="a_artikeluntergruppe_id">
                                        <option value="">-- Keine --</option>
                                        <?php foreach ($artikeluntergruppen as $untergruppe): ?>
                                        <option value="<?= $untergruppe['id'] ?>" data-gruppe="<?= $untergruppe['artikelgruppe_id'] ?>"><?= htmlspecialchars($untergruppe['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" onclick="artikeluntergruppeSchnellAnlageUmschalten()" title="Neue Artikeluntergruppe anlegen">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <div id="artikeluntergruppeSchnellAnlage" class="d-none mt-2 p-2 border rounded bg-light">
                                    <div id="artikeluntergruppeSchnellAnlageHinweis" class="text-muted small mb-1 d-none">Bitte zuerst eine Artikelgruppe auswählen.</div>
                                    <div class="row g-2">
                                        <div class="col-7">
                                            <input type="text" class="form-control form-control-sm" id="neueArtikeluntergruppeName" placeholder="z.B. T-Shirts">
                                        </div>
                                        <div class="col-3">
                                            <input type="text" class="form-control form-control-sm" id="neueArtikeluntergruppeKurz" placeholder="Kurzbez." maxlength="10">
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-sm btn-success w-100" onclick="artikeluntergruppeSchnellAnlegen()">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="artikeluntergruppeSchnellAnlageFehler" class="text-danger small mt-1"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategorie (für Ledger-Buchung bei Rechnungsstellung)</label>
                            <select class="form-select" name="kategorie_id" id="a_kategorie_id">
                                <option value="">-- Standard (Verkaufserlöse) --</option>
                                <?php foreach ($kategorien as $kat): ?>
                                <option value="<?= $kat['id'] ?>"><?= htmlspecialchars($kat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="aktiv" id="a_aktiv" checked>
                            <label class="form-check-label" for="a_aktiv">Aktiv</label>
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

    <!-- Artikelgruppen verwalten Modal -->
    <div class="modal fade" id="artikelgruppenModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tags me-2"></i>Artikelgruppen verwalten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <h6 class="px-3 pt-3 d-flex justify-content-between align-items-center">
                        <span>Artikelgruppen</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="artikelgruppeHinzufuegenUmschalten()" title="Neue Artikelgruppe">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </h6>
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Name</th><th>Kurzbez.</th><th class="text-center">Artikel</th><th></th></tr></thead>
                        <tbody>
                            <tr id="gruppe_add_row" class="d-none">
                                <td>
                                    <form method="POST" id="add_gruppe_form">
                                        <input type="hidden" name="action" value="save_artikelgruppe">
                                        <input type="hidden" name="id" value="0">
                                    </form>
                                    <input type="text" class="form-control form-control-sm" form="add_gruppe_form" name="name" placeholder="Name" required>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" form="add_gruppe_form" name="kurzbezeichnung" maxlength="10" placeholder="Kurzbez."></td>
                                <td class="text-center text-muted">-</td>
                                <td class="text-end text-nowrap">
                                    <button type="submit" form="add_gruppe_form" class="btn btn-sm btn-success" title="Anlegen"><i class="bi bi-check-lg"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="artikelgruppeHinzufuegenUmschalten()" title="Abbrechen"><i class="bi bi-x-lg"></i></button>
                                </td>
                            </tr>
                            <?php if (empty($artikelgruppenMitAnzahl)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Keine Artikelgruppen vorhanden</td></tr>
                            <?php else: foreach ($artikelgruppenMitAnzahl as $gruppe): ?>
                            <tr>
                                <td>
                                    <span class="gruppe-anzeige"><?= htmlspecialchars($gruppe['name']) ?></span>
                                    <input type="text" class="form-control form-control-sm d-none gruppe-edit-input"
                                           form="edit_form_<?= $gruppe['id'] ?>" name="name" required
                                           value="<?= htmlspecialchars($gruppe['name']) ?>">
                                </td>
                                <td>
                                    <span class="gruppe-anzeige"><?= $gruppe['kurzbezeichnung'] ? '<code>' . htmlspecialchars($gruppe['kurzbezeichnung']) . '</code>' : '-' ?></span>
                                    <input type="text" class="form-control form-control-sm d-none gruppe-edit-input"
                                           form="edit_form_<?= $gruppe['id'] ?>" name="kurzbezeichnung" maxlength="10"
                                           value="<?= htmlspecialchars($gruppe['kurzbezeichnung'] ?? '') ?>">
                                </td>
                                <td class="text-center"><?= $gruppe['anzahl_artikel'] ?></td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" id="edit_form_<?= $gruppe['id'] ?>">
                                        <input type="hidden" name="action" value="save_artikelgruppe">
                                        <input type="hidden" name="id" value="<?= $gruppe['id'] ?>">
                                        <input type="hidden" name="aktiv" value="<?= $gruppe['aktiv'] ?>">
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="artikeluntergruppeHinzufuegenUmschalten(<?= $gruppe['id'] ?>)" title="Neue Untergruppe zu dieser Gruppe">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                    <?php if ($gruppe['anzahl_artikel'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="Kann nicht bearbeitet werden - es sind bereits Artikel dieser Gruppe zugeordnet.">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="Kann nicht gelöscht werden - es sind noch Artikel dieser Gruppe zugeordnet.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary gruppe-bearbeiten-btn"
                                            onclick="artikelgruppeBearbeitenUmschalten(this)" title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="submit" form="edit_form_<?= $gruppe['id'] ?>" class="btn btn-sm btn-success d-none gruppe-speichern-btn" title="Speichern">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Artikelgruppe wirklich löschen?');">
                                        <input type="hidden" name="action" value="delete_artikelgruppe">
                                        <input type="hidden" name="id" value="<?= $gruppe['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Artikelgruppe löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php foreach ($untergruppenByGruppe[$gruppe['id']] ?? [] as $untergruppe): ?>
                            <tr class="table-light">
                                <td class="ps-4">
                                    <i class="bi bi-arrow-return-right text-muted me-1"></i>
                                    <span class="untergruppe-anzeige"><?= htmlspecialchars($untergruppe['name']) ?></span>
                                    <input type="text" class="form-control form-control-sm d-none untergruppe-edit-input"
                                           form="edit_uform_<?= $untergruppe['id'] ?>" name="name" required
                                           value="<?= htmlspecialchars($untergruppe['name']) ?>">
                                </td>
                                <td>
                                    <span class="untergruppe-anzeige"><?= $untergruppe['kurzbezeichnung'] ? '<code>' . htmlspecialchars($untergruppe['kurzbezeichnung']) . '</code>' : '-' ?></span>
                                    <input type="text" class="form-control form-control-sm d-none untergruppe-edit-input"
                                           form="edit_uform_<?= $untergruppe['id'] ?>" name="kurzbezeichnung" maxlength="10"
                                           value="<?= htmlspecialchars($untergruppe['kurzbezeichnung'] ?? '') ?>">
                                </td>
                                <td class="text-center"><?= $untergruppe['anzahl_artikel'] ?></td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" id="edit_uform_<?= $untergruppe['id'] ?>">
                                        <input type="hidden" name="action" value="save_artikeluntergruppe">
                                        <input type="hidden" name="id" value="<?= $untergruppe['id'] ?>">
                                        <input type="hidden" name="aktiv" value="<?= $untergruppe['aktiv'] ?>">
                                    </form>
                                    <?php if ($untergruppe['anzahl_artikel'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="Kann nicht bearbeitet werden - es sind bereits Artikel dieser Untergruppe zugeordnet.">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="Kann nicht gelöscht werden - es sind noch Artikel dieser Untergruppe zugeordnet.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary untergruppe-bearbeiten-btn"
                                            onclick="artikeluntergruppeBearbeitenUmschalten(this)" title="Bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="submit" form="edit_uform_<?= $untergruppe['id'] ?>" class="btn btn-sm btn-success d-none untergruppe-speichern-btn" title="Speichern">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Artikeluntergruppe wirklich löschen?');">
                                        <input type="hidden" name="action" value="delete_artikeluntergruppe">
                                        <input type="hidden" name="id" value="<?= $untergruppe['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Artikeluntergruppe löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr id="untergruppe_add_row_<?= $gruppe['id'] ?>" class="d-none table-light">
                                <td class="ps-4">
                                    <form method="POST" id="add_untergruppe_form_<?= $gruppe['id'] ?>">
                                        <input type="hidden" name="action" value="save_artikeluntergruppe">
                                        <input type="hidden" name="id" value="0">
                                        <input type="hidden" name="artikelgruppe_id" value="<?= $gruppe['id'] ?>">
                                    </form>
                                    <i class="bi bi-arrow-return-right text-muted me-1"></i>
                                    <input type="text" class="form-control form-control-sm d-inline-block" style="width: auto;"
                                           form="add_untergruppe_form_<?= $gruppe['id'] ?>" name="name" placeholder="Name der Untergruppe" required>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" form="add_untergruppe_form_<?= $gruppe['id'] ?>" name="kurzbezeichnung" maxlength="10" placeholder="Kurzbez."></td>
                                <td class="text-center text-muted">-</td>
                                <td class="text-end text-nowrap">
                                    <button type="submit" form="add_untergruppe_form_<?= $gruppe['id'] ?>" class="btn btn-sm btn-success" title="Anlegen"><i class="bi bi-check-lg"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="artikeluntergruppeHinzufuegenUmschalten(<?= $gruppe['id'] ?>)" title="Abbrechen"><i class="bi bi-x-lg"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Netto/Brutto-Preisfelder im Artikel-Modal: nur einzelpreis_netto wird tatsächlich
        // gespeichert (siehe artikel-Tabelle), das Brutto-Feld ist eine reine Eingabehilfe
        // ohne name-Attribut und wird bei jeder Eingabe live aus dem jeweils anderen Feld
        // + dem aktuell gewählten USt-Satz nachgerechnet.
        function aktuellerUstSatzProzent() {
            const select = document.getElementById('a_ust_satz_id');
            const option = select.options[select.selectedIndex];
            return parseFloat(option?.dataset.satz || 0);
        }
        function parseKommaZahl(wert) {
            const n = parseFloat(String(wert).replace(',', '.'));
            return isNaN(n) ? 0 : n;
        }
        function formatKommaZahl(wert) {
            return wert.toFixed(2).replace('.', ',');
        }
        function preisNettoGeaendert() {
            const netto = parseKommaZahl(document.getElementById('a_einzelpreis_netto').value);
            const satz = aktuellerUstSatzProzent();
            document.getElementById('a_einzelpreis_brutto').value = formatKommaZahl(netto * (1 + satz / 100));
        }
        function preisBruttoGeaendert() {
            const brutto = parseKommaZahl(document.getElementById('a_einzelpreis_brutto').value);
            const satz = aktuellerUstSatzProzent();
            document.getElementById('a_einzelpreis_netto').value = formatKommaZahl(brutto / (1 + satz / 100));
        }
        function ustSatzGeaendert() {
            // Netto bleibt bei einem Wechsel des USt-Satzes die feste Referenzgröße, nur
            // Brutto wird neu berechnet (wie beim Umsatzsteuerwechsel eines Artikels üblich).
            preisNettoGeaendert();
        }

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
            document.getElementById('a_artikelgruppe_id').value = a.artikelgruppe_id || '';
            document.getElementById('a_artikeluntergruppe_id').value = a.artikeluntergruppe_id || '';
            artikelgruppeGeaendert();
            document.getElementById('a_aktiv').checked = a.aktiv == 1;
            preisNettoGeaendert();
        }

        document.getElementById('artikelModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || !event.relatedTarget.hasAttribute('onclick')) {
                document.getElementById('a_modalTitle').innerHTML = '<i class="bi bi-box-seam me-2"></i>Neuer Artikel';
                this.querySelector('form').reset();
                document.getElementById('a_id').value = '';
                document.getElementById('a_einzelpreis_netto').value = '0,00';
                document.getElementById('a_einzelpreis_brutto').value = '0,00';
                document.getElementById('artikelgruppeSchnellAnlage').classList.add('d-none');
                document.getElementById('artikeluntergruppeSchnellAnlage').classList.add('d-none');
                artikelgruppeGeaendert();
            }
        });

        // Artikeluntergruppe-Auswahl auf die zur gewählten Artikelgruppe passenden Optionen
        // einschränken (ohne Gruppe: alle anzeigen). Bleibt die aktuelle Auswahl gültig
        // (z.B. beim Bearbeiten eines bestehenden Artikels), wird sie beibehalten.
        function artikelgruppeGeaendert() {
            const gruppeId = document.getElementById('a_artikelgruppe_id').value;
            const untergruppeSelect = document.getElementById('a_artikeluntergruppe_id');
            const aktuelleAuswahl = untergruppeSelect.value;
            let auswahlNochGueltig = false;

            Array.from(untergruppeSelect.options).forEach(function(opt) {
                if (!opt.value) { opt.hidden = false; return; }
                const passend = !gruppeId || opt.dataset.gruppe === gruppeId;
                opt.hidden = !passend;
                if (passend && opt.value === aktuelleAuswahl) auswahlNochGueltig = true;
            });

            if (!auswahlNochGueltig) {
                untergruppeSelect.value = '';
            }
        }

        // Artikelgruppe bearbeiten (nur möglich, wenn kein Artikel zugeordnet ist - siehe
        // PHP-seitige disabled-Buttons oben): blendet Name/Kurzbez. auf editierbare Felder um.
        function artikelgruppeBearbeitenUmschalten(btn) {
            const zeile = btn.closest('tr');
            zeile.querySelectorAll('.gruppe-anzeige').forEach(el => el.classList.add('d-none'));
            zeile.querySelectorAll('.gruppe-edit-input').forEach(el => el.classList.remove('d-none'));
            btn.classList.add('d-none');
            zeile.querySelector('.gruppe-speichern-btn').classList.remove('d-none');
        }

        // "+"-Zeilen im Artikelgruppen-verwalten-Modal ein-/ausblenden.
        function artikelgruppeHinzufuegenUmschalten() {
            const zeile = document.getElementById('gruppe_add_row');
            zeile.classList.toggle('d-none');
            if (!zeile.classList.contains('d-none')) {
                zeile.querySelector('input[name="name"]').focus();
            }
        }

        function artikeluntergruppeHinzufuegenUmschalten(gruppeId) {
            const zeile = document.getElementById('untergruppe_add_row_' + gruppeId);
            zeile.classList.toggle('d-none');
            if (!zeile.classList.contains('d-none')) {
                zeile.querySelector('input[name="name"]').focus();
            }
        }

        // Neue Artikelgruppe direkt aus dem Artikel-Modal anlegen (z.B. "T-Shirts", "Hoodies"),
        // ohne das bereits ausgefüllte Formular zu verlieren.
        function artikelgruppeSchnellAnlageUmschalten() {
            const box = document.getElementById('artikelgruppeSchnellAnlage');
            box.classList.toggle('d-none');
            if (!box.classList.contains('d-none')) {
                document.getElementById('neueArtikelgruppeName').focus();
            }
        }

        function artikelgruppeSchnellAnlegen() {
            const name = document.getElementById('neueArtikelgruppeName').value.trim();
            const kurz = document.getElementById('neueArtikelgruppeKurz').value.trim();
            const fehlerEl = document.getElementById('artikelgruppeSchnellAnlageFehler');
            fehlerEl.textContent = '';

            if (!name) {
                fehlerEl.textContent = 'Bitte einen Namen angeben.';
                return;
            }

            const formData = new FormData();
            formData.append('name', name);
            formData.append('kurzbezeichnung', kurz);

            fetch('artikelgruppe_schnell_anlegen.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        fehlerEl.textContent = data.message || 'Anlegen fehlgeschlagen.';
                        return;
                    }
                    const select = document.getElementById('a_artikelgruppe_id');
                    const option = document.createElement('option');
                    option.value = data.id;
                    option.textContent = data.name;
                    select.appendChild(option);
                    select.value = data.id;

                    document.getElementById('neueArtikelgruppeName').value = '';
                    document.getElementById('neueArtikelgruppeKurz').value = '';
                    document.getElementById('artikelgruppeSchnellAnlage').classList.add('d-none');
                })
                .catch(() => { fehlerEl.textContent = 'Anlegen fehlgeschlagen (Netzwerkfehler).'; });
        }

        // Artikeluntergruppe bearbeiten (nur möglich, wenn kein Artikel zugeordnet ist).
        function artikeluntergruppeBearbeitenUmschalten(btn) {
            const zeile = btn.closest('tr');
            zeile.querySelectorAll('.untergruppe-anzeige').forEach(el => el.classList.add('d-none'));
            zeile.querySelectorAll('.untergruppe-edit-input').forEach(el => el.classList.remove('d-none'));
            btn.classList.add('d-none');
            zeile.querySelector('.untergruppe-speichern-btn').classList.remove('d-none');
        }

        // Neue Artikeluntergruppe direkt aus dem Artikel-Modal anlegen - hängt immer an der
        // aktuell im Formular gewählten Artikelgruppe.
        function artikeluntergruppeSchnellAnlageUmschalten() {
            const gruppeId = document.getElementById('a_artikelgruppe_id').value;
            const box = document.getElementById('artikeluntergruppeSchnellAnlage');
            const hinweis = document.getElementById('artikeluntergruppeSchnellAnlageHinweis');
            box.classList.toggle('d-none');
            if (!box.classList.contains('d-none')) {
                if (!gruppeId) {
                    hinweis.classList.remove('d-none');
                } else {
                    hinweis.classList.add('d-none');
                    document.getElementById('neueArtikeluntergruppeName').focus();
                }
            }
        }

        function artikeluntergruppeSchnellAnlegen() {
            const gruppeId = document.getElementById('a_artikelgruppe_id').value;
            const name = document.getElementById('neueArtikeluntergruppeName').value.trim();
            const kurz = document.getElementById('neueArtikeluntergruppeKurz').value.trim();
            const fehlerEl = document.getElementById('artikeluntergruppeSchnellAnlageFehler');
            fehlerEl.textContent = '';

            if (!gruppeId) {
                fehlerEl.textContent = 'Bitte zuerst eine Artikelgruppe auswählen.';
                return;
            }
            if (!name) {
                fehlerEl.textContent = 'Bitte einen Namen angeben.';
                return;
            }

            const formData = new FormData();
            formData.append('name', name);
            formData.append('kurzbezeichnung', kurz);
            formData.append('artikelgruppe_id', gruppeId);

            fetch('artikeluntergruppe_schnell_anlegen.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        fehlerEl.textContent = data.message || 'Anlegen fehlgeschlagen.';
                        return;
                    }
                    const select = document.getElementById('a_artikeluntergruppe_id');
                    const option = document.createElement('option');
                    option.value = data.id;
                    option.textContent = data.name;
                    option.dataset.gruppe = data.artikelgruppe_id;
                    select.appendChild(option);
                    select.value = data.id;

                    document.getElementById('neueArtikeluntergruppeName').value = '';
                    document.getElementById('neueArtikeluntergruppeKurz').value = '';
                    document.getElementById('artikeluntergruppeSchnellAnlage').classList.add('d-none');
                })
                .catch(() => { fehlerEl.textContent = 'Anlegen fehlgeschlagen (Netzwerkfehler).'; });
        }

        // Suche: filtert Zeilen client-seitig über alle sichtbaren Spalten
        const artikelSucheFeld = document.getElementById('artikelSuche');
        const artikelTbody = document.querySelector('#artikelTabelle tbody');
        artikelSucheFeld?.addEventListener('input', function() {
            const suchbegriff = this.value.trim().toLowerCase();
            artikelTbody.querySelectorAll('tr').forEach(function(zeile) {
                if (!zeile.cells || zeile.cells.length < 8) return; // "Keine Artikel vorhanden"-Zeile überspringen
                const text = zeile.textContent.toLowerCase();
                zeile.classList.toggle('d-none', suchbegriff !== '' && !text.includes(suchbegriff));
            });
        });

        // Sortierung: Klick auf Spaltenkopf sortiert die Tabellenzeilen auf-/absteigend
        let artikelSortSpalte = null;
        let artikelSortAufsteigend = true;
        document.querySelectorAll('#artikelTabelle th.sortierbar').forEach(function(th) {
            th.style.cursor = 'pointer';
            th.addEventListener('click', function() {
                const spalte = parseInt(th.dataset.spalte, 10);
                artikelSortAufsteigend = (artikelSortSpalte === spalte) ? !artikelSortAufsteigend : true;
                artikelSortSpalte = spalte;

                const zeilen = Array.from(artikelTbody.querySelectorAll('tr')).filter(z => z.cells && z.cells.length >= 8);
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
                    return artikelSortAufsteigend ? vergleich : -vergleich;
                });
                zeilen.forEach(z => artikelTbody.appendChild(z));

                document.querySelectorAll('#artikelTabelle th.sortierbar i').forEach(i => i.className = 'bi bi-arrow-down-up small text-muted');
                th.querySelector('i').className = 'bi bi-arrow-' + (artikelSortAufsteigend ? 'up' : 'down') + ' small';
            });
        });
    </script>
</body>
</html>
