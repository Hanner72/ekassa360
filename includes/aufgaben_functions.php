<?php
/**
 * EKassa360 - Aufgabenverwaltung mit Zuweisung an Benutzer
 */

/**
 * Aufgabenliste mit aufgelösten Namen (zugewiesen/erstellt/Kunde/Verkaufsdokument).
 * $filters: 'zugewiesen_an', 'status' ('offen'/'in_arbeit'/'erledigt'/'offen_oder_in_arbeit'), 'erstellt_von'
 */
function getAufgaben($filters = []) {
    $db = db();
    $where = [];
    $params = [];

    if (!empty($filters['zugewiesen_an'])) {
        $where[] = "a.zugewiesen_an = ?";
        $params[] = $filters['zugewiesen_an'];
    }
    if (!empty($filters['erstellt_von'])) {
        $where[] = "a.erstellt_von = ?";
        $params[] = $filters['erstellt_von'];
    }
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'offen_oder_in_arbeit') {
            $where[] = "a.status IN ('offen', 'in_arbeit')";
        } else {
            $where[] = "a.status = ?";
            $params[] = $filters['status'];
        }
    }

    $sql = "SELECT a.*,
                   zb.benutzername AS zugewiesen_benutzername, zb.vorname AS zugewiesen_vorname, zb.nachname AS zugewiesen_nachname,
                   eb.benutzername AS erstellt_benutzername,
                   COALESCE(k.id, dk.id) AS anzeige_kunde_id,
                   COALESCE(k.firma_name, dk.firma_name) AS kunde_firma_name,
                   COALESCE(k.vorname, dk.vorname) AS kunde_vorname,
                   COALESCE(k.nachname, dk.nachname) AS kunde_nachname,
                   v.typ AS verkaufsdokument_typ, v.nummer AS verkaufsdokument_nummer
            FROM aufgaben a
            LEFT JOIN benutzer zb ON a.zugewiesen_an = zb.id
            LEFT JOIN benutzer eb ON a.erstellt_von = eb.id
            LEFT JOIN kunden k ON a.kunde_id = k.id
            LEFT JOIN verkaufsdokumente v ON a.verkaufsdokument_id = v.id
            LEFT JOIN kunden dk ON v.kunde_id = dk.id";
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY (a.status = 'erledigt'), a.faellig_am IS NULL, a.faellig_am ASC, a.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAufgabe($id) {
    $db = db();
    $stmt = $db->prepare("SELECT a.*,
                                  zb.benutzername AS zugewiesen_benutzername,
                                  k.firma_name AS kunde_firma_name, k.vorname AS kunde_vorname, k.nachname AS kunde_nachname,
                                  v.typ AS verkaufsdokument_typ, v.nummer AS verkaufsdokument_nummer
                           FROM aufgaben a
                           LEFT JOIN benutzer zb ON a.zugewiesen_an = zb.id
                           LEFT JOIN kunden k ON a.kunde_id = k.id
                           LEFT JOIN verkaufsdokumente v ON a.verkaufsdokument_id = v.id
                           WHERE a.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function saveAufgabe($data) {
    $db = db();
    $params = [
        trim($data['titel']),
        $data['beschreibung'] !== '' ? $data['beschreibung'] : null,
        $data['zugewiesen_an'] ?: null,
        $data['kunde_id'] ?: null,
        $data['verkaufsdokument_id'] ?: null,
        $data['faellig_am'] ?: null,
        $data['prioritaet'] ?: 'normal',
    ];

    if (!empty($data['id'])) {
        $stmt = $db->prepare("UPDATE aufgaben SET titel = ?, beschreibung = ?, zugewiesen_an = ?, kunde_id = ?,
                               verkaufsdokument_id = ?, faellig_am = ?, prioritaet = ? WHERE id = ?");
        $params[] = $data['id'];
        return $stmt->execute($params);
    }

    $params[] = $data['erstellt_von'] ?: null;
    $stmt = $db->prepare("INSERT INTO aufgaben (titel, beschreibung, zugewiesen_an, kunde_id, verkaufsdokument_id,
                           faellig_am, prioritaet, erstellt_von) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute($params);
}

function setzeAufgabeStatus($id, $status) {
    $db = db();
    $erledigtAm = $status === 'erledigt' ? date('Y-m-d H:i:s') : null;
    $stmt = $db->prepare("UPDATE aufgaben SET status = ?, erledigt_am = ? WHERE id = ?");
    return $stmt->execute([$status, $erledigtAm, $id]);
}

function deleteAufgabe($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM aufgaben WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Anzahl offener/in Arbeit befindlicher Aufgaben, die einem Benutzer zugewiesen sind
 * (für das Badge in der Navigation).
 */
function getOffeneAufgabenAnzahl($benutzerId) {
    $db = db();
    $stmt = $db->prepare("SELECT COUNT(*) FROM aufgaben WHERE zugewiesen_an = ? AND status IN ('offen', 'in_arbeit')");
    $stmt->execute([$benutzerId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Verkaufsdokumente für die Auswahl-Dropdown beim Anlegen/Bearbeiten einer Aufgabe
 * (alle Typen gemeinsam, stornierte ausgeschlossen, neueste zuerst, begrenzt).
 */
function getVerkaufsdokumenteFuerAuswahl() {
    $db = db();
    $stmt = $db->query("
        SELECT v.id, v.typ, v.nummer, k.firma_name, k.vorname, k.nachname
        FROM verkaufsdokumente v
        LEFT JOIN kunden k ON v.kunde_id = k.id
        WHERE v.status != 'storniert'
        ORDER BY v.datum DESC, v.id DESC
        LIMIT 300
    ");
    return $stmt->fetchAll();
}
