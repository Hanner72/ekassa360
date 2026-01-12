<?php
/**
 * Kategorien - Verwaltung der Einnahmen- und Ausgabenkategorien
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$db = Database::getInstance()->getConnection();

// Kategorie bearbeiten?
$editKategorie = null;
if (isset($_GET['edit'])) {
    $stmtEdit = $db->prepare("SELECT * FROM kategorien WHERE id = ?");
    $stmtEdit->execute([(int)$_GET['edit']]);
    $editKategorie = $stmtEdit->fetch(PDO::FETCH_ASSOC);
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'typ' => $_POST['typ'] ?? 'ausgabe',
            'e1a_kennzahl' => trim($_POST['e1a_kennzahl'] ?? ''),
            'beschreibung' => trim($_POST['beschreibung'] ?? ''),
            'farbe' => $_POST['farbe'] ?? '#6c757d',
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0
        ];
        
        if (empty($data['name'])) {
            setFlashMessage('danger', 'Bitte geben Sie einen Namen ein!');
        } else {
            try {
                if ($id) {
                    // Update
                    $stmt = $db->prepare("
                        UPDATE kategorien SET 
                            name = ?, typ = ?, e1a_kennzahl = ?, beschreibung = ?, farbe = ?, aktiv = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $data['name'], $data['typ'], $data['e1a_kennzahl'], 
                        $data['beschreibung'], $data['farbe'], $data['aktiv'], $id
                    ]);
                    setFlashMessage('success', 'Kategorie wurde aktualisiert!');
                } else {
                    // Insert
                    $stmt = $db->prepare("
                        INSERT INTO kategorien (name, typ, e1a_kennzahl, beschreibung, farbe, aktiv)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $data['name'], $data['typ'], $data['e1a_kennzahl'],
                        $data['beschreibung'], $data['farbe'], $data['aktiv']
                    ]);
                    setFlashMessage('success', 'Kategorie wurde erstellt!');
                }
                header('Location: kategorien.php');
                exit;
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Fehler beim Speichern: ' . $e->getMessage());
            }
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        
        // Prüfen ob Kategorie verwendet wird
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM rechnungen WHERE kategorie_id = ?");
        $stmtCheck->execute([$id]);
        $count = $stmtCheck->fetchColumn();
        
        if ($count > 0) {
            setFlashMessage('warning', "Kategorie kann nicht gelöscht werden - wird in $count Rechnung(en) verwendet.");
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM kategorien WHERE id = ?");
                $stmt->execute([$id]);
                setFlashMessage('success', 'Kategorie wurde gelöscht!');
            } catch (PDOException $e) {
                setFlashMessage('danger', 'Fehler beim Löschen: ' . $e->getMessage());
            }
        }
        header('Location: kategorien.php');
        exit;
    }
    
    if ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("UPDATE kategorien SET aktiv = NOT aktiv WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: kategorien.php');
        exit;
    }
}

// Kategorien laden mit Statistiken
$stmtKategorien = $db->query("
    SELECT 
        k.*,
        COUNT(r.id) as anzahl_rechnungen,
        COALESCE(SUM(r.netto_betrag), 0) as summe
    FROM kategorien k
    LEFT JOIN rechnungen r ON k.id = r.kategorie_id
    GROUP BY k.id
    ORDER BY k.typ, k.name
");
$kategorien = $stmtKategorien->fetchAll(PDO::FETCH_ASSOC);

// Kategorien nach Typ trennen
$einnahmenKategorien = array_filter($kategorien, fn($k) => $k['typ'] == 'einnahme');
$ausgabenKategorien = array_filter($kategorien, fn($k) => $k['typ'] == 'ausgabe');

$pageTitle = 'Kategorien';

// E1a Kennzahlen für Dropdown
$e1aKennzahlen = [
    'einnahme' => [
        '9040' => 'KZ 9040 - Betriebseinnahmen (Waren/Erzeugnisse)',
        '9050' => 'KZ 9050 - Betriebseinnahmen (Dienstleistungen)',
    ],
    'ausgabe' => [
        '9100' => 'KZ 9100 - Wareneinkauf/Rohstoffe',
        '9110' => 'KZ 9110 - Personalaufwand',
        '9120' => 'KZ 9120 - Abschreibungen (AfA)',
        '9130' => 'KZ 9130 - Fremdleistungen',
        '9140' => 'KZ 9140 - Betriebsräumlichkeiten',
        '9150' => 'KZ 9150 - Sonstige Betriebsausgaben',
    ]
];
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
                    <h1 class="h2"><i class="bi bi-tags me-2"></i><?= $pageTitle ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kategorieModal">
                            <i class="bi bi-plus-lg me-1"></i>Neue Kategorie
                        </button>
                    </div>
                </div>

                <?php displayFlashMessage(); ?>

                <div class="row">
                    <!-- Einnahmen-Kategorien -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-arrow-down-circle me-2"></i>Einnahmen-Kategorien
                                    <span class="badge bg-light text-success float-end"><?= count($einnahmenKategorien) ?></span>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($einnahmenKategorien)): ?>
                                    <div class="p-3 text-muted">Keine Einnahmen-Kategorien vorhanden.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($einnahmenKategorien as $kat): ?>
                                            <div class="list-group-item <?= !$kat['aktiv'] ? 'list-group-item-light' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge me-2" style="background-color: <?= $kat['farbe'] ?>">
                                                            &nbsp;
                                                        </span>
                                                        <strong><?= htmlspecialchars($kat['name']) ?></strong>
                                                        <?php if (!$kat['aktiv']): ?>
                                                            <span class="badge bg-secondary ms-1">Inaktiv</span>
                                                        <?php endif; ?>
                                                        <?php if ($kat['e1a_kennzahl']): ?>
                                                            <small class="text-muted ms-2">KZ <?= $kat['e1a_kennzahl'] ?></small>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= $kat['anzahl_rechnungen'] ?> Rechnung(en) · 
                                                            <?= formatBetrag($kat['summe']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="btn-group">
                                                        <a href="?edit=<?= $kat['id'] ?>" class="btn btn-sm btn-outline-primary" 
                                                           data-bs-toggle="modal" data-bs-target="#kategorieModal"
                                                           onclick="editKategorie(<?= htmlspecialchars(json_encode($kat)) ?>); return false;">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary" 
                                                                    title="<?= $kat['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                                <i class="bi bi-<?= $kat['aktiv'] ? 'eye-slash' : 'eye' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Kategorie wirklich löschen?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Ausgaben-Kategorien -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-arrow-up-circle me-2"></i>Ausgaben-Kategorien
                                    <span class="badge bg-light text-danger float-end"><?= count($ausgabenKategorien) ?></span>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($ausgabenKategorien)): ?>
                                    <div class="p-3 text-muted">Keine Ausgaben-Kategorien vorhanden.</div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($ausgabenKategorien as $kat): ?>
                                            <div class="list-group-item <?= !$kat['aktiv'] ? 'list-group-item-light' : '' ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge me-2" style="background-color: <?= $kat['farbe'] ?>">
                                                            &nbsp;
                                                        </span>
                                                        <strong><?= htmlspecialchars($kat['name']) ?></strong>
                                                        <?php if (!$kat['aktiv']): ?>
                                                            <span class="badge bg-secondary ms-1">Inaktiv</span>
                                                        <?php endif; ?>
                                                        <?php if ($kat['e1a_kennzahl']): ?>
                                                            <small class="text-muted ms-2">KZ <?= $kat['e1a_kennzahl'] ?></small>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <?= $kat['anzahl_rechnungen'] ?> Rechnung(en) · 
                                                            <?= formatBetrag($kat['summe']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="btn-group">
                                                        <a href="?edit=<?= $kat['id'] ?>" class="btn btn-sm btn-outline-primary"
                                                           data-bs-toggle="modal" data-bs-target="#kategorieModal"
                                                           onclick="editKategorie(<?= htmlspecialchars(json_encode($kat)) ?>); return false;">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                    title="<?= $kat['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                                                <i class="bi bi-<?= $kat['aktiv'] ? 'eye-slash' : 'eye' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline"
                                                              onsubmit="return confirm('Kategorie wirklich löschen?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- E1a Kennzahlen Info -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>E1a Kennzahlen (Einkommensteuer)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-success">Betriebseinnahmen</h6>
                                <ul>
                                    <li><strong>KZ 9040</strong> - Erlöse aus Waren/Erzeugnissen</li>
                                    <li><strong>KZ 9050</strong> - Erlöse aus Dienstleistungen</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-danger">Betriebsausgaben</h6>
                                <ul>
                                    <li><strong>KZ 9100</strong> - Wareneinkauf, Rohstoffe</li>
                                    <li><strong>KZ 9110</strong> - Personalaufwand</li>
                                    <li><strong>KZ 9120</strong> - Abschreibungen (AfA)</li>
                                    <li><strong>KZ 9130</strong> - Fremdleistungen</li>
                                    <li><strong>KZ 9140</strong> - Betriebsräumlichkeiten</li>
                                    <li><strong>KZ 9150</strong> - Sonstige Ausgaben</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Kategorie Modal -->
    <div class="modal fade" id="kategorieModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="kategorie_id" value="">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="bi bi-tag me-2"></i>Neue Kategorie
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="typ" class="form-label">Typ *</label>
                            <select class="form-select" id="typ" name="typ" required onchange="updateE1aKennzahlen()">
                                <option value="einnahme">Einnahme</option>
                                <option value="ausgabe">Ausgabe</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="e1a_kennzahl" class="form-label">E1a Kennzahl</label>
                            <select class="form-select" id="e1a_kennzahl" name="e1a_kennzahl">
                                <option value="">-- Keine --</option>
                            </select>
                            <div class="form-text">Für die automatische Zuordnung in der Einkommensteuererklärung</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="farbe" class="form-label">Farbe</label>
                                    <input type="color" class="form-control form-control-color w-100" 
                                           id="farbe" name="farbe" value="#6c757d">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 pt-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="aktiv" name="aktiv" checked>
                                        <label class="form-check-label" for="aktiv">Aktiv</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="beschreibung" class="form-label">Beschreibung</label>
                            <textarea class="form-control" id="beschreibung" name="beschreibung" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const e1aKennzahlen = <?= json_encode($e1aKennzahlen) ?>;
        
        function updateE1aKennzahlen() {
            const typ = document.getElementById('typ').value;
            const select = document.getElementById('e1a_kennzahl');
            const currentValue = select.value;
            
            select.innerHTML = '<option value="">-- Keine --</option>';
            
            if (e1aKennzahlen[typ]) {
                for (const [kz, label] of Object.entries(e1aKennzahlen[typ])) {
                    const option = document.createElement('option');
                    option.value = kz;
                    option.textContent = label;
                    if (kz === currentValue) option.selected = true;
                    select.appendChild(option);
                }
            }
        }
        
        function editKategorie(kat) {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Kategorie bearbeiten';
            document.getElementById('kategorie_id').value = kat.id;
            document.getElementById('name').value = kat.name;
            document.getElementById('typ').value = kat.typ;
            document.getElementById('farbe').value = kat.farbe || '#6c757d';
            document.getElementById('aktiv').checked = kat.aktiv == 1;
            document.getElementById('beschreibung').value = kat.beschreibung || '';
            
            updateE1aKennzahlen();
            document.getElementById('e1a_kennzahl').value = kat.e1a_kennzahl || '';
        }
        
        // Modal zurücksetzen beim Öffnen für neue Kategorie
        document.getElementById('kategorieModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || !event.relatedTarget.hasAttribute('onclick')) {
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-tag me-2"></i>Neue Kategorie';
                document.getElementById('kategorie_id').value = '';
                document.getElementById('name').value = '';
                document.getElementById('typ').value = 'ausgabe';
                document.getElementById('farbe').value = '#6c757d';
                document.getElementById('aktiv').checked = true;
                document.getElementById('beschreibung').value = '';
                updateE1aKennzahlen();
            }
        });
        
        // Initial E1a Kennzahlen laden
        updateE1aKennzahlen();
    </script>
</body>
</html>
