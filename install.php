<?php
/**
 * EKassa360 Installation / Update
 * Erstellt alle Datenbanktabellen und den Admin-Benutzer
 */
session_start();
require_once 'config/database.php';

$messages = [];
$errors = [];
$alreadyInstalled = false;
$userCount = 0;

// Prüfen ob bereits installiert
try {
    $db = db();
    $stmt = $db->query("SHOW TABLES LIKE 'benutzer'");
    if ($stmt->rowCount() > 0) {
        $stmt = $db->query("SELECT COUNT(*) FROM benutzer");
        $userCount = $stmt->fetchColumn();
        $alreadyInstalled = $userCount > 0;
    }
} catch (Exception $e) {
    // Datenbank nicht erreichbar oder Tabelle existiert nicht
}

// Installation durchführen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'install' || $action === 'update') {
        try {
            $db = db();
            
            // 1. FIRMA-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS firma (
                id INT PRIMARY KEY AUTO_INCREMENT,
                firmenname VARCHAR(100),
                inhaber VARCHAR(100),
                strasse VARCHAR(100),
                plz VARCHAR(10),
                ort VARCHAR(50),
                land VARCHAR(50) DEFAULT 'Österreich',
                telefon VARCHAR(30),
                email VARCHAR(100),
                website VARCHAR(100),
                uid_nummer VARCHAR(20),
                steuernummer VARCHAR(30),
                finanzamt VARCHAR(100),
                iban VARCHAR(34),
                bic VARCHAR(11),
                bank VARCHAR(100),
                kleinunternehmer TINYINT(1) DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'firma' erstellt/geprüft";
            
            // 2. KATEGORIEN-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS kategorien (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(50) NOT NULL,
                typ ENUM('einnahme', 'ausgabe', 'beides') NOT NULL,
                e1a_kennzahl VARCHAR(10),
                beschreibung TEXT,
                aktiv TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'kategorien' erstellt/geprüft";
            
            // 3. UST-SÄTZE-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS ust_saetze (
                id INT PRIMARY KEY AUTO_INCREMENT,
                bezeichnung VARCHAR(50) NOT NULL,
                prozent DECIMAL(5,2) NOT NULL,
                kz_u30 VARCHAR(10),
                beschreibung TEXT,
                aktiv TINYINT(1) DEFAULT 1,
                sortierung INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'ust_saetze' erstellt/geprüft";
            
            // 4. RECHNUNGEN-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS rechnungen (
                id INT PRIMARY KEY AUTO_INCREMENT,
                rechnungsnummer VARCHAR(50),
                typ ENUM('einnahme', 'ausgabe') NOT NULL,
                datum DATE NOT NULL,
                faellig_am DATE,
                kunde_lieferant VARCHAR(100) NOT NULL,
                uid_kunde VARCHAR(20),
                land_kunde VARCHAR(50) DEFAULT 'AT',
                beschreibung TEXT,
                kategorie_id INT,
                netto_betrag DECIMAL(12,2) NOT NULL,
                ust_satz_id INT,
                ust_betrag DECIMAL(12,2) DEFAULT 0,
                brutto_betrag DECIMAL(12,2) NOT NULL,
                bezahlt TINYINT(1) DEFAULT 0,
                bezahlt_am DATE,
                zahlungsart VARCHAR(30),
                notizen TEXT,
                erstellt_von INT,
                geaendert_von INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE SET NULL,
                FOREIGN KEY (ust_satz_id) REFERENCES ust_saetze(id) ON DELETE SET NULL,
                INDEX idx_datum (datum),
                INDEX idx_typ (typ),
                INDEX idx_bezahlt (bezahlt)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'rechnungen' erstellt/geprüft";
            
            // 5. ANLAGEGÜTER-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS anlagegueter (
                id INT PRIMARY KEY AUTO_INCREMENT,
                bezeichnung VARCHAR(100) NOT NULL,
                beschreibung TEXT,
                kategorie_id INT,
                anschaffungsdatum DATE NOT NULL,
                anschaffungskosten DECIMAL(12,2) NOT NULL,
                nutzungsdauer INT NOT NULL,
                afa_methode ENUM('linear', 'degressiv') DEFAULT 'linear',
                afa_prozent DECIMAL(5,2),
                restwert DECIMAL(12,2) DEFAULT 0,
                standort VARCHAR(100),
                inventarnummer VARCHAR(50),
                seriennummer VARCHAR(100),
                lieferant VARCHAR(100),
                rechnungsnummer VARCHAR(50),
                status ENUM('aktiv', 'verkauft', 'verschrottet', 'verloren') DEFAULT 'aktiv',
                verkaufsdatum DATE,
                verkaufspreis DECIMAL(12,2),
                notizen TEXT,
                e1a_kennzahl VARCHAR(10) DEFAULT '9130',
                erstellt_von INT,
                geaendert_von INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE SET NULL,
                INDEX idx_status (status),
                INDEX idx_anschaffung (anschaffungsdatum)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'anlagegueter' erstellt/geprüft";
            
            // 6. AFA-BUCHUNGEN-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS afa_buchungen (
                id INT PRIMARY KEY AUTO_INCREMENT,
                anlage_id INT NOT NULL,
                jahr INT NOT NULL,
                afa_betrag DECIMAL(12,2) NOT NULL,
                buchwert_anfang DECIMAL(12,2) NOT NULL,
                buchwert_ende DECIMAL(12,2) NOT NULL,
                berechnet_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (anlage_id) REFERENCES anlagegueter(id) ON DELETE CASCADE,
                UNIQUE KEY uk_anlage_jahr (anlage_id, jahr)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'afa_buchungen' erstellt/geprüft";
            
            // 7. UST-VORANMELDUNGEN-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS ust_voranmeldungen (
                id INT PRIMARY KEY AUTO_INCREMENT,
                jahr INT NOT NULL,
                quartal INT,
                monat INT,
                zeitraum_typ ENUM('monat', 'quartal') DEFAULT 'quartal',
                kz000 DECIMAL(12,2) DEFAULT 0,
                kz022 DECIMAL(12,2) DEFAULT 0,
                kz029 DECIMAL(12,2) DEFAULT 0,
                kz006 DECIMAL(12,2) DEFAULT 0,
                kz017 DECIMAL(12,2) DEFAULT 0,
                kz070 DECIMAL(12,2) DEFAULT 0,
                kz072 DECIMAL(12,2) DEFAULT 0,
                kz060 DECIMAL(12,2) DEFAULT 0,
                kz061 DECIMAL(12,2) DEFAULT 0,
                kz065 DECIMAL(12,2) DEFAULT 0,
                kz066 DECIMAL(12,2) DEFAULT 0,
                kz083 DECIMAL(12,2) DEFAULT 0,
                kz095 DECIMAL(12,2) DEFAULT 0,
                status ENUM('entwurf', 'berechnet', 'eingereicht', 'bezahlt') DEFAULT 'entwurf',
                eingereicht_am DATE,
                bezahlt_am DATE,
                notizen TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_zeitraum (jahr, zeitraum_typ, quartal, monat)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'ust_voranmeldungen' erstellt/geprüft";
            
            // 8. EINKOMMENSTEUER-TABELLE
            $db->exec("CREATE TABLE IF NOT EXISTS einkommensteuer (
                id INT PRIMARY KEY AUTO_INCREMENT,
                jahr INT NOT NULL UNIQUE,
                kz9040 DECIMAL(12,2) DEFAULT 0,
                kz9050 DECIMAL(12,2) DEFAULT 0,
                kz9100 DECIMAL(12,2) DEFAULT 0,
                kz9110 DECIMAL(12,2) DEFAULT 0,
                kz9120 DECIMAL(12,2) DEFAULT 0,
                kz9130 DECIMAL(12,2) DEFAULT 0,
                kz9134 DECIMAL(12,2) DEFAULT 0,
                kz9135 DECIMAL(12,2) DEFAULT 0,
                kz9140 DECIMAL(12,2) DEFAULT 0,
                kz9150 DECIMAL(12,2) DEFAULT 0,
                weitere_kennzahlen JSON,
                gewinn DECIMAL(12,2) DEFAULT 0,
                status ENUM('entwurf', 'berechnet', 'eingereicht') DEFAULT 'entwurf',
                notizen TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'einkommensteuer' erstellt/geprüft";
            
            // 9. BENUTZER-TABELLE
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'benutzer' erstellt/geprüft";
            
            // 10. ÄNDERUNGSPROTOKOLL-TABELLE
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "✓ Tabelle 'aenderungsprotokoll' erstellt/geprüft";
            
            // STANDARD-KATEGORIEN einfügen (falls leer)
            $stmt = $db->query("SELECT COUNT(*) FROM kategorien");
            if ($stmt->fetchColumn() == 0) {
                $db->exec("INSERT INTO kategorien (name, typ, e1a_kennzahl, beschreibung) VALUES
                    ('Dienstleistungen', 'einnahme', '9050', 'Erlöse aus Dienstleistungen'),
                    ('Warenverkauf', 'einnahme', '9040', 'Erlöse aus Warenverkauf'),
                    ('Provisionen', 'einnahme', '9050', 'Provisionserlöse'),
                    ('Sonstige Einnahmen', 'einnahme', '9050', 'Sonstige betriebliche Einnahmen'),
                    ('Wareneinkauf', 'ausgabe', '9100', 'Waren- und Materialeinkauf'),
                    ('Fremdleistungen', 'ausgabe', '9110', 'Fremd- und Subunternehmerleistungen'),
                    ('Miete/Pacht', 'ausgabe', '9140', 'Miete für Geschäftsräume'),
                    ('Betriebskosten', 'ausgabe', '9140', 'Strom, Wasser, Heizung'),
                    ('Büromaterial', 'ausgabe', '9230', 'Büro- und Verbrauchsmaterial'),
                    ('Telefon/Internet', 'ausgabe', '9230', 'Kommunikationskosten'),
                    ('Versicherungen', 'ausgabe', '9230', 'Betriebliche Versicherungen'),
                    ('KFZ-Kosten', 'ausgabe', '9230', 'Fahrzeugkosten'),
                    ('Reisekosten', 'ausgabe', '9230', 'Reise- und Fahrtkosten'),
                    ('Werbung', 'ausgabe', '9230', 'Werbung und Marketing'),
                    ('Fortbildung', 'ausgabe', '9230', 'Aus- und Weiterbildung'),
                    ('Rechts-/Beratung', 'ausgabe', '9230', 'Rechts- und Beratungskosten'),
                    ('Bankgebühren', 'ausgabe', '9230', 'Kontoführung und Bankspesen'),
                    ('Sonstige Ausgaben', 'ausgabe', '9230', 'Sonstige betriebliche Ausgaben')
                ");
                $messages[] = "✓ 18 Standard-Kategorien eingefügt";
            }
            
            // STANDARD-UST-SÄTZE einfügen (falls leer)
            $stmt = $db->query("SELECT COUNT(*) FROM ust_saetze");
            if ($stmt->fetchColumn() == 0) {
                $db->exec("INSERT INTO ust_saetze (bezeichnung, prozent, kz_u30, beschreibung, sortierung) VALUES
                    ('20% Normalsteuersatz', 20.00, '022', 'Standard-Umsatzsteuersatz', 1),
                    ('10% ermäßigt', 10.00, '029', 'Ermäßigter Steuersatz (Lebensmittel, etc.)', 2),
                    ('13% ermäßigt', 13.00, '006', 'Ermäßigter Steuersatz (Kunst, etc.)', 3),
                    ('0% steuerfrei', 0.00, NULL, 'Steuerfreie Umsätze', 4),
                    ('0% Kleinunternehmer', 0.00, NULL, 'Kleinunternehmerregelung §6(1)27', 5),
                    ('0% ig Lieferung', 0.00, '017', 'Innergemeinschaftliche Lieferung', 6),
                    ('0% Reverse Charge', 0.00, '066', 'Reverse Charge Verfahren', 7),
                    ('20% ig Erwerb', 20.00, '070', 'Innergemeinschaftlicher Erwerb 20%', 8),
                    ('20% Einfuhr-USt', 20.00, '061', 'Einfuhrumsatzsteuer Drittland', 9)
                ");
                $messages[] = "✓ 9 Standard-USt-Sätze eingefügt";
            }
            
            // ADMIN-BENUTZER erstellen (falls nicht vorhanden)
            $stmt = $db->query("SELECT COUNT(*) FROM benutzer WHERE benutzername = 'admin'");
            if ($stmt->fetchColumn() == 0) {
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO benutzer (benutzername, passwort_hash, vorname, nachname, email, rolle, aktiv, passwort_muss_geaendert) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(['admin', $hash, 'System', 'Administrator', 'admin@example.com', 'admin', 1, 1]);
                $messages[] = "✓ Admin-Benutzer erstellt";
            } else {
                $messages[] = "→ Admin-Benutzer existiert bereits";
            }
            
            // STANDARD-FIRMA erstellen (falls nicht vorhanden)
            $stmt = $db->query("SELECT COUNT(*) FROM firma");
            if ($stmt->fetchColumn() == 0) {
                $db->exec("INSERT INTO firma (firmenname, inhaber) VALUES ('Meine Firma', 'Max Mustermann')");
                $messages[] = "✓ Standard-Firmendaten angelegt";
            }
            
            $messages[] = "";
            $messages[] = "═══════════════════════════════════════";
            $messages[] = "🎉 Installation erfolgreich abgeschlossen!";
            $messages[] = "═══════════════════════════════════════";
            $messages[] = "";
            $messages[] = "Login-Daten:";
            $messages[] = "  Benutzername: admin";
            $messages[] = "  Passwort:     admin123";
            $messages[] = "";
            $messages[] = "⚠️  Bitte Passwort beim ersten Login ändern!";
            $messages[] = "⚠️  Diese Datei (install.php) nach Installation löschen!";
            
        } catch (PDOException $e) {
            $errors[] = "Datenbankfehler: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Fehler: " . $e->getMessage();
        }
    }
    
    if ($action === 'reset_admin') {
        try {
            $db = db();
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
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
        :root { --ek-primary: #1e3c72; --ek-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        body { background: #f5f5f5; min-height: 100vh; }
        .install-header { background: var(--ek-gradient); color: #fff; padding: 2rem 0; }
        .card { border-radius: 0; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card-header { background: var(--ek-gradient); color: #fff; border-radius: 0; }
        .btn { border-radius: 0; }
        .btn-primary { background: var(--ek-gradient); border: none; }
        pre { background: #1a1a2e; color: #0f0; padding: 1.5rem; margin: 0; font-family: 'Consolas', monospace; font-size: 0.9rem; }
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
                    <small class="opacity-75">Installation & Setup v1.6</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="bi bi-x-circle me-2"></i>Fehler</h5>
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($messages)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-terminal me-2"></i>Installations-Log</h5>
                    </div>
                    <div class="card-body p-0">
                        <pre><?php foreach ($messages as $msg): ?><?= htmlspecialchars($msg) ?>
<?php endforeach; ?></pre>
                    </div>
                    <div class="card-footer">
                        <a href="login.php" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Zum Login
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Installation</h5>
                    </div>
                    <div class="card-body">
                        <p>Diese Seite erstellt alle notwendigen Datenbanktabellen und Standarddaten für EKassa360.</p>
                        
                        <div class="alert alert-info mb-4">
                            <h6><i class="bi bi-info-circle me-2"></i>Folgende Tabellen werden erstellt:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="mb-0">
                                        <li>firma</li>
                                        <li>kategorien</li>
                                        <li>ust_saetze</li>
                                        <li>rechnungen</li>
                                        <li>anlagegueter</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="mb-0">
                                        <li>afa_buchungen</li>
                                        <li>ust_voranmeldungen</li>
                                        <li>einkommensteuer</li>
                                        <li>benutzer</li>
                                        <li>aenderungsprotokoll</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($alreadyInstalled): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Bereits installiert!</strong> Es sind <?= $userCount ?> Benutzer vorhanden. 
                            Ein erneutes Ausführen erstellt nur fehlende Tabellen/Daten.
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $alreadyInstalled ? 'update' : 'install' ?>">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-<?= $alreadyInstalled ? 'arrow-repeat' : 'download' ?> me-2"></i>
                                <?= $alreadyInstalled ? 'Update / Reparatur' : 'Installation starten' ?>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($alreadyInstalled): ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-key me-2"></i>Admin-Passwort zurücksetzen</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Falls Sie das Admin-Passwort vergessen haben:</p>
                        <form method="POST" onsubmit="return confirm('Admin-Passwort wirklich zurücksetzen?')">
                            <input type="hidden" name="action" value="reset_admin">
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-arrow-clockwise me-2"></i>Passwort auf "admin123" zurücksetzen
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
