<?php
/**
 * Benutzerverwaltung
 * EKassa360 - Nur für Administratoren
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

requireAdmin();

$error = '';
$success = '';

// Benutzer löschen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Kann sich nicht selbst löschen
    if ($id == $_SESSION['benutzer_id']) {
        setFlashMessage('danger', 'Sie können sich nicht selbst löschen.');
    } else {
        $result = deleteBenutzer($id);
        if ($result['success']) {
            setFlashMessage('success', 'Benutzer wurde gelöscht.');
        } else {
            setFlashMessage('danger', $result['message'] ?? 'Fehler beim Löschen.');
        }
    }
    header('Location: benutzerverwaltung.php');
    exit;
}

// Benutzer speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id' => $_POST['id'] ?? null,
        'benutzername' => trim($_POST['benutzername'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'vorname' => trim($_POST['vorname'] ?? ''),
        'nachname' => trim($_POST['nachname'] ?? ''),
        'rolle' => $_POST['rolle'] ?? 'benutzer',
        'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
        'passwort' => $_POST['passwort'] ?? '',
        'passwort_muss_geaendert' => isset($_POST['passwort_muss_geaendert']) ? 1 : 0
    ];
    
    // Validierung
    if (empty($data['benutzername'])) {
        $error = 'Benutzername ist erforderlich.';
    } elseif (!$data['id'] && empty($data['passwort'])) {
        $error = 'Bei neuen Benutzern ist ein Passwort erforderlich.';
    } elseif (!empty($data['passwort']) && strlen($data['passwort']) < 8) {
        $error = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } else {
        // Prüfen ob Benutzername bereits existiert
        $db = db();
        $stmt = $db->prepare("SELECT id FROM benutzer WHERE benutzername = ? AND id != ?");
        $stmt->execute([$data['benutzername'], $data['id'] ?? 0]);
        if ($stmt->fetch()) {
            $error = 'Dieser Benutzername ist bereits vergeben.';
        }
    }
    
    if (empty($error)) {
        if (saveBenutzer($data)) {
            setFlashMessage('success', $data['id'] ? 'Benutzer wurde aktualisiert.' : 'Benutzer wurde erstellt.');
            header('Location: benutzerverwaltung.php');
            exit;
        } else {
            $error = 'Fehler beim Speichern.';
        }
    }
}

// Benutzer zum Bearbeiten laden
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editUser = getBenutzer(intval($_GET['edit']));
}

// Alle Benutzer laden
$benutzer = getAlleBenutzer();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzerverwaltung - EKassa360</title>
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
                    <h1 class="h2"><i class="bi bi-people me-2"></i>Benutzerverwaltung</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#benutzerModal" onclick="resetForm()">
                        <i class="bi bi-person-plus me-1"></i>Neuer Benutzer
                    </button>
                </div>
                
                <?php if ($msg = getFlashMessage()): ?>
                    <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                        <?= htmlspecialchars($msg['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Benutzer-Liste -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Alle Benutzer</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Benutzername</th>
                                        <th>Name</th>
                                        <th>E-Mail</th>
                                        <th class="text-center">Rolle</th>
                                        <th class="text-center">Status</th>
                                        <th>Letzter Login</th>
                                        <th class="text-end">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($benutzer as $b): ?>
                                        <tr class="<?= !$b['aktiv'] ? 'table-secondary' : '' ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($b['benutzername']) ?></strong>
                                                <?php if ($b['passwort_muss_geaendert']): ?>
                                                    <span class="badge bg-warning text-dark ms-1" title="Muss Passwort ändern">
                                                        <i class="bi bi-exclamation-triangle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars(trim($b['vorname'] . ' ' . $b['nachname'])) ?></td>
                                            <td><?= htmlspecialchars($b['email']) ?></td>
                                            <td class="text-center">
                                                <?php if ($b['rolle'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Benutzer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($b['aktiv']): ?>
                                                    <span class="badge bg-success">Aktiv</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inaktiv</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $b['letzter_login'] ? formatDatum($b['letzter_login'], true) : '<span class="text-muted">Noch nie</span>' ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editBenutzer(<?= htmlspecialchars(json_encode($b)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($b['id'] != $_SESSION['benutzer_id']): ?>
                                                    <a href="?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Benutzer wirklich löschen?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Benutzer Modal -->
    <div class="modal fade" id="benutzerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="bi bi-person-plus me-2"></i>Neuer Benutzer
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="userId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vorname</label>
                                <input type="text" name="vorname" id="vorname" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nachname</label>
                                <input type="text" name="nachname" id="nachname" class="form-control">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Benutzername <span class="text-danger">*</span></label>
                            <input type="text" name="benutzername" id="benutzername" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">E-Mail</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Passwort <span class="text-danger" id="pwRequired">*</span></label>
                            <input type="password" name="passwort" id="passwort" class="form-control" minlength="8">
                            <small class="text-muted">Mindestens 8 Zeichen. <span id="pwHint">Bei bestehendem Benutzer leer lassen um Passwort zu behalten.</span></small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rolle</label>
                                <select name="rolle" id="rolle" class="form-select">
                                    <option value="benutzer">Benutzer</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="aktiv" id="aktiv" class="form-check-input" value="1" checked>
                                    <label class="form-check-label" for="aktiv">Aktiv</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" name="passwort_muss_geaendert" id="passwort_muss_geaendert" class="form-check-input" value="1">
                            <label class="form-check-label" for="passwort_muss_geaendert">
                                Benutzer muss Passwort beim nächsten Login ändern
                            </label>
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
        function resetForm() {
            document.getElementById('userId').value = '';
            document.getElementById('vorname').value = '';
            document.getElementById('nachname').value = '';
            document.getElementById('benutzername').value = '';
            document.getElementById('email').value = '';
            document.getElementById('passwort').value = '';
            document.getElementById('rolle').value = 'benutzer';
            document.getElementById('aktiv').checked = true;
            document.getElementById('passwort_muss_geaendert').checked = true;
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Neuer Benutzer';
            document.getElementById('pwRequired').style.display = 'inline';
            document.getElementById('pwHint').style.display = 'none';
            document.getElementById('passwort').required = true;
        }
        
        function editBenutzer(user) {
            document.getElementById('userId').value = user.id;
            document.getElementById('vorname').value = user.vorname || '';
            document.getElementById('nachname').value = user.nachname || '';
            document.getElementById('benutzername').value = user.benutzername;
            document.getElementById('email').value = user.email || '';
            document.getElementById('passwort').value = '';
            document.getElementById('rolle').value = user.rolle;
            document.getElementById('aktiv').checked = user.aktiv == 1;
            document.getElementById('passwort_muss_geaendert').checked = user.passwort_muss_geaendert == 1;
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Benutzer bearbeiten';
            document.getElementById('pwRequired').style.display = 'none';
            document.getElementById('pwHint').style.display = 'inline';
            document.getElementById('passwort').required = false;
            
            new bootstrap.Modal(document.getElementById('benutzerModal')).show();
        }
        
        <?php if ($editUser): ?>
        document.addEventListener('DOMContentLoaded', function() {
            editBenutzer(<?= json_encode($editUser) ?>);
        });
        <?php endif; ?>
    </script>
</body>
</html>
