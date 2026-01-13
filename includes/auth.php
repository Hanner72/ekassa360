<?php
/**
 * Authentifizierung und Session-Management
 * EKassa360 v1.5
 */

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Prüft ob Benutzer eingeloggt ist
 */
function isLoggedIn() {
    return isset($_SESSION['benutzer_id']) && !empty($_SESSION['benutzer_id']);
}

/**
 * Prüft ob Benutzer Admin ist
 */
function isAdmin() {
    return isset($_SESSION['rolle']) && $_SESSION['rolle'] === 'admin';
}

/**
 * Gibt den aktuellen Benutzer zurück
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['benutzer_id'],
        'benutzername' => $_SESSION['benutzername'],
        'vorname' => $_SESSION['vorname'] ?? '',
        'nachname' => $_SESSION['nachname'] ?? '',
        'name' => trim(($_SESSION['vorname'] ?? '') . ' ' . ($_SESSION['nachname'] ?? '')),
        'rolle' => $_SESSION['rolle'],
        'email' => $_SESSION['email'] ?? ''
    ];
}

/**
 * Gibt formatierten Benutzernamen zurück
 */
function getCurrentUserName() {
    $user = getCurrentUser();
    if (!$user) return 'Unbekannt';
    $name = trim($user['vorname'] . ' ' . $user['nachname']);
    return $name ?: $user['benutzername'];
}

/**
 * Erzwingt Login - leitet auf login.php um wenn nicht eingeloggt
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
    
    // Prüfen ob Passwort geändert werden muss
    if (isset($_SESSION['passwort_muss_geaendert']) && $_SESSION['passwort_muss_geaendert']) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage !== 'passwort_aendern.php' && $currentPage !== 'logout.php') {
            header('Location: passwort_aendern.php');
            exit;
        }
    }
}

/**
 * Erzwingt Admin-Rechte
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('danger', 'Sie haben keine Berechtigung für diese Aktion.');
        header('Location: index.php');
        exit;
    }
}

/**
 * Benutzer einloggen
 */
function loginUser($benutzername, $passwort) {
    $db = db();
    
    $stmt = $db->prepare("SELECT * FROM benutzer WHERE benutzername = ? AND aktiv = 1");
    $stmt->execute([$benutzername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        logAction('login', null, 'login', "Fehlgeschlagen - Unbekannt: $benutzername");
        return ['success' => false, 'message' => 'Benutzername oder Passwort falsch.'];
    }
    
    // Prüfen ob Account gesperrt
    if ($user['gesperrt_bis'] && strtotime($user['gesperrt_bis']) > time()) {
        $bis = date('H:i', strtotime($user['gesperrt_bis']));
        return ['success' => false, 'message' => "Account gesperrt bis $bis Uhr."];
    }
    
    // Passwort prüfen
    if (!password_verify($passwort, $user['passwort_hash'])) {
        // Fehlversuch zählen
        $fehlversuche = $user['fehlversuche'] + 1;
        $gesperrt_bis = null;
        
        if ($fehlversuche >= 5) {
            $gesperrt_bis = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $fehlversuche = 0;
        }
        
        $stmt = $db->prepare("UPDATE benutzer SET fehlversuche = ?, gesperrt_bis = ? WHERE id = ?");
        $stmt->execute([$fehlversuche, $gesperrt_bis, $user['id']]);
        
        logAction('login', $user['id'], 'login', "Fehlgeschlagen - Falsches Passwort: $benutzername");
        return ['success' => false, 'message' => 'Benutzername oder Passwort falsch.'];
    }
    
    // Erfolgreicher Login
    $_SESSION['benutzer_id'] = $user['id'];
    $_SESSION['benutzername'] = $user['benutzername'];
    $_SESSION['vorname'] = $user['vorname'];
    $_SESSION['nachname'] = $user['nachname'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['rolle'] = $user['rolle'];
    $_SESSION['passwort_muss_geaendert'] = $user['passwort_muss_geaendert'];
    
    // Fehlversuche zurücksetzen und letzten Login speichern
    $stmt = $db->prepare("UPDATE benutzer SET fehlversuche = 0, gesperrt_bis = NULL, letzter_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    logAction('login', $user['id'], 'login', "Erfolgreich: $benutzername");
    
    return [
        'success' => true, 
        'message' => 'Login erfolgreich.',
        'passwort_muss_geaendert' => $user['passwort_muss_geaendert']
    ];
}

/**
 * Benutzer ausloggen
 */
function logoutUser() {
    if (isLoggedIn()) {
        logAction('login', $_SESSION['benutzer_id'], 'logout', "Abgemeldet: " . $_SESSION['benutzername']);
    }
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Passwort ändern
 */
function changePassword($benutzer_id, $neues_passwort) {
    $db = db();
    
    $hash = password_hash($neues_passwort, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE benutzer SET passwort_hash = ?, passwort_muss_geaendert = 0 WHERE id = ?");
    $result = $stmt->execute([$hash, $benutzer_id]);
    
    if ($result) {
        $_SESSION['passwort_muss_geaendert'] = false;
        logAction('benutzer', $benutzer_id, 'passwort_geaendert', "Passwort geändert");
    }
    
    return $result;
}

/**
 * Aktion ins Protokoll schreiben
 */
function logAction($tabelle, $datensatz_id, $aktion, $beschreibung = null, $alte_werte = null, $neue_werte = null) {
    try {
        $db = db();
        
        $benutzer_id = $_SESSION['benutzer_id'] ?? null;
        $benutzer_name = $_SESSION['benutzername'] ?? 'System';
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $stmt = $db->prepare("INSERT INTO aenderungsprotokoll 
            (benutzer_id, benutzer_name, tabelle, datensatz_id, aktion, beschreibung, alte_werte, neue_werte, ip_adresse)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $benutzer_id,
            $benutzer_name,
            $tabelle,
            $datensatz_id,
            $aktion,
            $beschreibung,
            $alte_werte ? json_encode($alte_werte, JSON_UNESCAPED_UNICODE) : null,
            $neue_werte ? json_encode($neue_werte, JSON_UNESCAPED_UNICODE) : null,
            $ip
        ]);
    } catch (Exception $e) {
        // Fehler beim Loggen sollte App nicht blockieren
        error_log("Fehler beim Protokollieren: " . $e->getMessage());
    }
}

/**
 * Änderungsprotokoll abrufen
 */
function getAenderungsprotokoll($limit = 100, $tabelle = null, $benutzer_id = null) {
    $db = db();
    
    $sql = "SELECT * FROM aenderungsprotokoll WHERE 1=1";
    $params = [];
    
    if ($tabelle) {
        $sql .= " AND tabelle = ?";
        $params[] = $tabelle;
    }
    if ($benutzer_id) {
        $sql .= " AND benutzer_id = ?";
        $params[] = $benutzer_id;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT " . intval($limit);
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Alle Benutzer abrufen
 */
function getAlleBenutzer() {
    $db = db();
    $stmt = $db->query("SELECT id, benutzername, email, vorname, nachname, rolle, aktiv, 
                               letzter_login, passwort_muss_geaendert, created_at 
                        FROM benutzer ORDER BY benutzername");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Benutzer abrufen
 */
function getBenutzer($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM benutzer WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Benutzer speichern (erstellen oder aktualisieren)
 */
function saveBenutzer($data) {
    $db = db();
    
    if (isset($data['id']) && $data['id']) {
        // Update
        $sql = "UPDATE benutzer SET benutzername = ?, email = ?, vorname = ?, nachname = ?, rolle = ?, aktiv = ?";
        $params = [
            $data['benutzername'],
            $data['email'],
            $data['vorname'],
            $data['nachname'],
            $data['rolle'],
            $data['aktiv'] ?? 1
        ];
        
        // Passwort nur ändern wenn angegeben
        if (!empty($data['passwort'])) {
            $sql .= ", passwort_hash = ?, passwort_muss_geaendert = ?";
            $params[] = password_hash($data['passwort'], PASSWORD_DEFAULT);
            $params[] = $data['passwort_muss_geaendert'] ?? 0;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $data['id'];
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            logAction('benutzer', $data['id'], 'geaendert', "Benutzer bearbeitet: " . $data['benutzername']);
        }
        
        return $result;
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO benutzer (benutzername, passwort_hash, email, vorname, nachname, rolle, aktiv, passwort_muss_geaendert)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $data['benutzername'],
            password_hash($data['passwort'], PASSWORD_DEFAULT),
            $data['email'],
            $data['vorname'],
            $data['nachname'],
            $data['rolle'],
            $data['aktiv'] ?? 1,
            $data['passwort_muss_geaendert'] ?? 1
        ]);
        
        if ($result) {
            $id = $db->lastInsertId();
            logAction('benutzer', $id, 'erstellt', "Benutzer erstellt: " . $data['benutzername']);
        }
        
        return $result;
    }
}

/**
 * Benutzer löschen
 */
function deleteBenutzer($id) {
    $db = db();
    
    // Prüfen ob es der letzte Admin ist
    $stmt = $db->prepare("SELECT rolle FROM benutzer WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user && $user['rolle'] === 'admin') {
        $stmt = $db->query("SELECT COUNT(*) FROM benutzer WHERE rolle = 'admin' AND aktiv = 1");
        if ($stmt->fetchColumn() <= 1) {
            return ['success' => false, 'message' => 'Der letzte Administrator kann nicht gelöscht werden.'];
        }
    }
    
    $stmt = $db->prepare("DELETE FROM benutzer WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        logAction('benutzer', $id, 'geloescht', "Benutzer gelöscht");
    }
    
    return ['success' => $result];
}
