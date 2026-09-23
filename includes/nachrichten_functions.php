<?php
/**
 * EKassa360 - interner Chat zwischen Benutzern
 */

/**
 * Liste aller anderen aktiven Benutzer für die Gesprächsübersicht, mit Zeitpunkt der
 * letzten Nachricht (falls vorhanden) und Anzahl ungelesener Nachrichten von diesem Benutzer.
 * Sortiert: zuerst nach letzter Nachricht (neueste oben), dann alphabetisch.
 */
function getNachrichtenUebersicht($benutzerId) {
    $db = db();
    $stmt = $db->prepare("
        SELECT b.id, b.benutzername, b.vorname, b.nachname,
               (SELECT MAX(n.created_at) FROM nachrichten n
                 WHERE (n.von_benutzer_id = b.id AND n.an_benutzer_id = ?)
                    OR (n.von_benutzer_id = ? AND n.an_benutzer_id = b.id)) AS letzte_nachricht_am,
               (SELECT COUNT(*) FROM nachrichten n
                 WHERE n.von_benutzer_id = b.id AND n.an_benutzer_id = ? AND n.gelesen = 0) AS ungelesen
        FROM benutzer b
        WHERE b.id != ? AND b.aktiv = 1
        ORDER BY letzte_nachricht_am IS NULL, letzte_nachricht_am DESC, b.benutzername ASC
    ");
    $stmt->execute([$benutzerId, $benutzerId, $benutzerId, $benutzerId]);
    return $stmt->fetchAll();
}

/**
 * Gesamtzahl ungelesener Nachrichten eines Benutzers (für das Badge in der Navigation).
 */
function getUngeleseneNachrichtenAnzahl($benutzerId) {
    $db = db();
    $stmt = $db->prepare("SELECT COUNT(*) FROM nachrichten WHERE an_benutzer_id = ? AND gelesen = 0");
    $stmt->execute([$benutzerId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Kompletter Gesprächsverlauf zwischen zwei Benutzern, chronologisch aufsteigend.
 */
function getNachrichtenVerlauf($benutzerId, $partnerId) {
    $db = db();
    $stmt = $db->prepare("
        SELECT * FROM nachrichten
        WHERE (von_benutzer_id = ? AND an_benutzer_id = ?)
           OR (von_benutzer_id = ? AND an_benutzer_id = ?)
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$benutzerId, $partnerId, $partnerId, $benutzerId]);
    return $stmt->fetchAll();
}

function sendeNachricht($vonBenutzerId, $anBenutzerId, $inhalt) {
    $inhalt = trim($inhalt);
    if ($inhalt === '' || $vonBenutzerId == $anBenutzerId) {
        return false;
    }
    $db = db();
    $stmt = $db->prepare("INSERT INTO nachrichten (von_benutzer_id, an_benutzer_id, inhalt) VALUES (?, ?, ?)");
    return $stmt->execute([$vonBenutzerId, $anBenutzerId, $inhalt]);
}

/**
 * Markiert alle Nachrichten eines Gesprächspartners an den aktuellen Benutzer als gelesen.
 */
function markiereNachrichtenGelesen($benutzerId, $partnerId) {
    $db = db();
    $stmt = $db->prepare("UPDATE nachrichten SET gelesen = 1 WHERE an_benutzer_id = ? AND von_benutzer_id = ? AND gelesen = 0");
    $stmt->execute([$benutzerId, $partnerId]);
}
