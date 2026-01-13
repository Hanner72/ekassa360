<?php
/**
 * EKassa360 Installation / Update
 * Führt Datenbankmigrationen aus und erstellt den Admin-Benutzer
 */
session_start();
require_once 'config/database.php';

$messages = [];
$errors = [];

// Prüfen ob bereits installiert (Benutzer existieren)
try {
    $db = db();
    $stmt = $db->query("SELECT COUNT(*) FROM benutzer");
    $userCount = $stmt->fetchColumn();
    $alreadyInstalled = $userCount > 0;
} catch (Exception $e) {
    $alreadyInstalled = false;
}

// Installation durchführen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'install' || $action === 'update') {
        try {
            $db = db();
            
            // Benutzer-Tabelle erstellen
            $db->exec("CREATE TABLE IF NOT EXISTS benutzer (
                id INT PRIMARY KEY AUTO_INCREMENT,
                benutzername VARCHAR(50) NOT NULL UNIQUE,
                passwort_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100),
                vorname VARCHAR(50),
                nachname VARCHAR(50),
                rolle ENUM('admin', 'benutzer') DEFAULT 'benutzer',
                aktiv TINYINT(1) DEFAULT 1,
                passwort_muss_geaendert TINYINT(1) DEFAULT 0,
                letzter_login DATETIME,
                fehlversuche INT DEFAULT 0,
                gesperrt_bis DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            $messages[] = "✓ Benutzer-Tabelle erstellt/geprüft";
            
            // Änderungsprotokoll-Tabelle erstellen
            $db->exec("CREATE TABLE IF NOT EXISTS aenderungsprotokoll (
                id INT PRIMARY KEY AUTO_INCREMENT,
                benutzer_id INT,
                benutzer_name VARCHAR(50),
                tabelle VARCHAR(50) NOT NULL,
                datensatz_id INT,
                aktion ENUM('erstellt', 'geaendert', 'geloescht', 'login', 'logout', 'passwort_geaendert') NOT NULL,
                beschreibung TEXT,
                alte_werte JSON,
                neue_werte JSON,
                ip_adresse VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_benutzer (benutzer_id),
                INDEX idx_tabelle (tabelle),
                INDEX idx_created (created_at)
            )");
            $messages[] = "✓ Änderungsprotokoll-Tabelle erstellt/geprüft";
            
            // Spalten zu bestehenden Tabellen hinzufügen
            try {
                $db->exec("ALTER TABLE rechnungen ADD COLUMN erstellt_von INT DEFAULT NULL");
                $messages[] = "✓ Spalte 'erstellt_von' zu Rechnungen hinzugefügt";
            } catch (Exception $e) {
                // Spalte existiert bereits
            }
            
            try {
                $db->exec("ALTER TABLE rechnungen ADD COLUMN geaendert_von INT DEFAULT NULL");
                $messages[] = "✓ Spalte 'geaendert_von' zu Rechnungen hinzugefügt";
            } catch (Exception $e) {
                // Spalte existiert bereits
            }
            
            try {
                $db->exec("ALTER TABLE anlagegueter ADD COLUMN erstellt_von INT DEFAULT NULL");
                $messages[] = "✓ Spalte 'erstellt_von' zu Anlagegütern hinzugefügt";
            } catch (Exception $e) {
                // Spalte existiert bereits
            }
            
            try {
                $db->exec("ALTER TABLE anlagegueter ADD COLUMN geaendert_von INT DEFAULT NULL");
                $messages[] = "✓ Spalte 'geaendert_von' zu Anlagegütern hinzugefügt";
            } catch (Exception $e) {
                // Spalte existiert bereits
            }
            
            // Admin-Benutzer erstellen falls nicht vorhanden
            $stmt = $db->query("SELECT COUNT(*) FROM benutzer WHERE benutzername = 'admin'");
            if ($stmt->fetchColumn() == 0) {
                $adminPassword = 'admin123';
                $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO benutzer (benutzername, passwort_hash, vorname, nachname, email, rolle, aktiv, passwort_muss_geaendert) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(['admin', $hash, 'System', 'Administrator', 'admin@example.com', 'admin', 1, 1]);
                $messages[] = "✓ Admin-Benutzer erstellt (Benutzername: admin, Passwort: admin123)";
            } else {
                $messages[] = "→ Admin-Benutzer existiert bereits";
            }
            
            $messages[] = "";
            $messages[] = "🎉 Installation/Update erfolgreich!";
            $messages[] = "";
            $messages[] = "Sie können sich jetzt anmelden:";
            $messages[] = "Benutzername: admin";
            $messages[] = "Passwort: admin123";
            $messages[] = "";
            $messages[] = "⚠️ Bitte ändern Sie das Passwort beim ersten Login!";
            
        } catch (Exception $e) {
            $errors[] = "Fehler: " . $e->getMessage();
        }
    }
    
    if ($action === 'reset_admin') {
        try {
            $db = db();
            $newPassword = 'admin123';
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE benutzer SET passwort_hash = ?, passwort_muss_geaendert = 1, fehlversuche = 0, gesperrt_bis = NULL WHERE benutzername = 'admin'");
            $stmt->execute([$hash]);
            
            $messages[] = "✓ Admin-Passwort zurückgesetzt auf: admin123";
        } catch (Exception $e) {
            $errors[] = "Fehler: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - EKassa360</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --ek-primary: #1e3c72;
            --ek-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        body {
            background: #f5f5f5;
            min-height: 100vh;
        }
        .install-header {
            background: var(--ek-gradient);
            color: #fff;
            padding: 2rem 0;
        }
        .card {
            border-radius: 0;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .card-header {
            background: var(--ek-gradient);
            color: #fff;
            border-radius: 0;
        }
        .btn {
            border-radius: 0;
        }
        .btn-primary {
            background: var(--ek-gradient);
            border: none;
        }
        pre {
            background: #f8f9fa;
            padding: 1rem;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="install-header text-center">
        <div class="container">
            <div class="d-inline-flex align-items-center mb-2">
                <div class="bg-white p-2 me-3" style="width: 50px; height: 50px;">
                    <i class="bi bi-cash-stack fs-3" style="color: var(--ek-primary);"></i>
                </div>
                <div class="text-start">
                    <h1 class="mb-0">EKassa360</h1>
                    <small class="opacity-75">Installation & Update</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($messages)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>Ergebnis</h5>
                        </div>
                        <div class="card-body p-0">
                            <pre class="mb-0"><?php foreach ($messages as $msg): ?><?= htmlspecialchars($msg) ?>
<?php endforeach; ?></pre>
                        </div>
                        <div class="card-footer">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Zum Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Installation / Update</h5>
                    </div>
                    <div class="card-body">
                        <p>Diese Seite erstellt die notwendigen Datenbanktabellen für die Benutzerverwaltung und das Änderungsprotokoll.</p>
                        
                        <?php if ($alreadyInstalled): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Es sind bereits <?= $userCount ?> Benutzer vorhanden. 
                                Sie können trotzdem ein Update durchführen um fehlende Tabellen/Spalten zu erstellen.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $alreadyInstalled ? 'update' : 'install' ?>">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-<?= $alreadyInstalled ? 'arrow-repeat' : 'download' ?> me-2"></i>
                                <?= $alreadyInstalled ? 'Update durchführen' : 'Installation starten' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($alreadyInstalled): ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Admin-Passwort zurücksetzen</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Falls Sie das Admin-Passwort vergessen haben, können Sie es hier auf <code>admin123</code> zurücksetzen.</p>
                        <form method="POST" onsubmit="return confirm('Admin-Passwort wirklich zurücksetzen?')">
                            <input type="hidden" name="action" value="reset_admin">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key me-2"></i>Admin-Passwort zurücksetzen
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
