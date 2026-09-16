<?php
/**
 * Datenbank-Konfiguration
 * Buchhaltungs-App für Österreich
 */

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');

define('DB_HOST', 'localhost');
define('DB_NAME', 'ekassa360');
define('DB_USER', 'root');
define('DB_PASS', '');

/* define('DB_HOST', 'localhost');
define('DB_NAME', 'ekassa360');
define('DB_USER', 'Hanner72');
define('DB_PASS', '***********'); */

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Hilfsfunktion für Datenbankzugriff
function db() {
    return Database::getInstance()->getConnection();
}

// Automatischer Migrations-Runner: wendet neue database/add_*.sql-Dateien bei Bedarf an
// (siehe includes/migrations.php) - läuft bei jedem Request, damit ein reiner Datei-Push
// auf den Live-Server ohne manuellen DB-Schritt auskommt.
//
// SKIP_AUTO_MIGRATION (vor diesem require definieren) überspringt NUR den Auto-Lauf, lädt
// aber weiterhin die Konstanten/Funktionen aus migrations.php sowie db() mit den echten,
// bereits hier oben konfigurierten Zugangsdaten. Gedacht für Diagnose-/Reparatur-Werkzeuge
// (siehe migration_bootstrap.php), damit dort NIE eigene Zugangsdaten-Kopien nötig sind.
require_once __DIR__ . '/../includes/migrations.php';
if (!defined('SKIP_AUTO_MIGRATION')) {
    fuehreAusstehendeMigrationenAus();
}
