<?php
/**
 * Passwort ändern
 * EKassa360
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$muss_aendern = $_SESSION['passwort_muss_geaendert'] ?? false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aktuell = $_POST['aktuelles_passwort'] ?? '';
    $neu = $_POST['neues_passwort'] ?? '';
    $bestaetigung = $_POST['passwort_bestaetigung'] ?? '';
    
    if (empty($neu) || empty($bestaetigung)) {
        $error = 'Bitte alle Felder ausfüllen.';
    } elseif ($neu !== $bestaetigung) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } elseif (strlen($neu) < 8) {
        $error = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($neu === 'admin123') {
        $error = 'Das Standard-Passwort kann nicht verwendet werden.';
    } else {
        // Bei erzwungener Änderung kein aktuelles Passwort prüfen
        if (!$muss_aendern) {
            $db = db();
            $stmt = $db->prepare("SELECT passwort_hash FROM benutzer WHERE id = ?");
            $stmt->execute([$_SESSION['benutzer_id']]);
            $user = $stmt->fetch();
            
            if (!password_verify($aktuell, $user['passwort_hash'])) {
                $error = 'Das aktuelle Passwort ist falsch.';
            }
        }
        
        if (empty($error)) {
            if (changePassword($_SESSION['benutzer_id'], $neu)) {
                if ($muss_aendern) {
                    setFlashMessage('success', 'Passwort erfolgreich geändert. Willkommen bei EKassa360!');
                    header('Location: index.php');
                    exit;
                }
                $success = 'Passwort erfolgreich geändert.';
            } else {
                $error = 'Fehler beim Speichern des Passworts.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort ändern - EKassa360</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if (!$muss_aendern): ?>
        <?php include 'includes/navbar.php'; ?>
    <?php endif; ?>
    
    <div class="container<?= $muss_aendern ? ' mt-5' : '-fluid' ?>">
        <div class="row<?= !$muss_aendern ? '' : ' justify-content-center' ?>">
            <?php if (!$muss_aendern): ?>
                <?php include 'includes/sidebar.php'; ?>
            <?php endif; ?>
            
            <main class="<?= $muss_aendern ? 'col-md-6' : 'col-md-9 ms-sm-auto col-lg-10 px-md-4' ?>">
                <?php if ($muss_aendern): ?>
                    <div class="text-center mb-4 mt-4">
                        <div class="bg-primary text-white p-2 d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-cash-stack" style="font-size: 2rem;"></i>
                        </div>
                        <h2>Willkommen bei EKassa360!</h2>
                        <p class="text-muted">Bitte ändern Sie Ihr Standard-Passwort, um fortzufahren.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2"><i class="bi bi-key me-2"></i>Passwort ändern</h1>
                    </div>
                <?php endif; ?>
                
                <div class="row justify-content-center">
                    <div class="col-md-<?= $muss_aendern ? '10' : '6' ?>">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Neues Passwort festlegen</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($success): ?>
                                    <div class="alert alert-success">
                                        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <?php if (!$muss_aendern): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Aktuelles Passwort <span class="text-danger">*</span></label>
                                            <input type="password" name="aktuelles_passwort" class="form-control" required>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Neues Passwort <span class="text-danger">*</span></label>
                                        <input type="password" name="neues_passwort" class="form-control" minlength="8" required>
                                        <small class="text-muted">Mindestens 8 Zeichen</small>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label">Passwort bestätigen <span class="text-danger">*</span></label>
                                        <input type="password" name="passwort_bestaetigung" class="form-control" minlength="8" required>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg me-1"></i>Passwort ändern
                                        </button>
                                        <?php if (!$muss_aendern): ?>
                                            <a href="einstellungen.php" class="btn btn-secondary">Abbrechen</a>
                                        <?php else: ?>
                                            <a href="logout.php" class="btn btn-outline-secondary">Abmelden</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
