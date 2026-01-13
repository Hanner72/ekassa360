<?php
/**
 * Hilfsfunktionen für EKassa360
 * Version 1.2
 */

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Flash Message setzen
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Flash Message holen
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Flash Message anzeigen (HTML ausgeben)
 */
function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>';
        echo '</div>';
    }
}

/**
 * Formatierung: Betrag
 */
function formatBetrag($betrag) {
    return '€ ' . number_format((float)$betrag, 2, ',', '.');
}

/**
 * Formatierung: Datum
 */
function formatDatum($datum) {
    return $datum ? date('d.m.Y', strtotime($datum)) : '-';
}

// ============================================
// STATISTIKEN
// ============================================

/**
 * Statistiken für ein Jahr laden
 */
function getStatistics($year) {
    $db = db();
    
    // Einnahmen (NETTO - für E/A Rechnung in Österreich)
    $stmt = $db->prepare("SELECT COALESCE(SUM(netto_betrag), 0) as total FROM rechnungen WHERE typ = 'einnahme' AND YEAR(datum) = ?");
    $stmt->execute([$year]);
    $einnahmen = $stmt->fetch()['total'];
    
    // Ausgaben (NETTO)
    $stmt = $db->prepare("SELECT COALESCE(SUM(netto_betrag), 0) as total FROM rechnungen WHERE typ = 'ausgabe' AND YEAR(datum) = ?");
    $stmt->execute([$year]);
    $ausgaben = $stmt->fetch()['total'];
    
    // USt Zahllast
    $stmt = $db->prepare("SELECT COALESCE(SUM(ust_betrag), 0) as total FROM rechnungen WHERE typ = 'einnahme' AND YEAR(datum) = ?");
    $stmt->execute([$year]);
    $ustEinnahmen = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(ust_betrag), 0) as total FROM rechnungen WHERE typ = 'ausgabe' AND YEAR(datum) = ?");
    $stmt->execute([$year]);
    $vorsteuer = $stmt->fetch()['total'];
    
    // AfA für das Jahr
    $stmt = $db->prepare("SELECT COALESCE(SUM(afa_betrag), 0) as total FROM afa_buchungen WHERE jahr = ?");
    $stmt->execute([$year]);
    $afa = $stmt->fetch()['total'];
    
    return [
        'einnahmen' => $einnahmen,
        'ausgaben' => $ausgaben + $afa,  // Ausgaben inkl. AfA
        'ust_zahllast' => $ustEinnahmen - $vorsteuer,
        'gewinn' => $einnahmen - $ausgaben - $afa,  // Netto-Gewinn
        'afa' => $afa
    ];
}

/**
 * Monatsdaten für Chart
 */
function getMonthlyData($year) {
    $db = db();
    $einnahmen = array_fill(0, 12, 0);
    $ausgaben = array_fill(0, 12, 0);
    
    $stmt = $db->prepare("SELECT MONTH(datum) as monat, SUM(netto_betrag) as total FROM rechnungen WHERE typ = 'einnahme' AND YEAR(datum) = ? GROUP BY MONTH(datum)");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $einnahmen[$row['monat'] - 1] = floatval($row['total']);
    }
    
    $stmt = $db->prepare("SELECT MONTH(datum) as monat, SUM(netto_betrag) as total FROM rechnungen WHERE typ = 'ausgabe' AND YEAR(datum) = ? GROUP BY MONTH(datum)");
    $stmt->execute([$year]);
    foreach ($stmt->fetchAll() as $row) {
        $ausgaben[$row['monat'] - 1] = floatval($row['total']);
    }
    
    return ['einnahmen' => $einnahmen, 'ausgaben' => $ausgaben];
}

// ============================================
// RECHNUNGEN
// ============================================

/**
 * Alle Rechnungen laden mit Filter
 */
function getRechnungen($filters = []) {
    $db = db();
    $where = [];
    $params = [];
    
    if (!empty($filters['typ'])) {
        $where[] = "r.typ = ?";
        $params[] = $filters['typ'];
    }
    if (!empty($filters['jahr'])) {
        $where[] = "YEAR(r.datum) = ?";
        $params[] = $filters['jahr'];
    }
    if (!empty($filters['monat'])) {
        $where[] = "MONTH(r.datum) = ?";
        $params[] = $filters['monat'];
    }
    if (!empty($filters['kategorie_id'])) {
        $where[] = "r.kategorie_id = ?";
        $params[] = $filters['kategorie_id'];
    }
    if (isset($filters['bezahlt']) && $filters['bezahlt'] !== '') {
        $where[] = "r.bezahlt = ?";
        $params[] = $filters['bezahlt'];
    }
    
    // Volltextsuche
    if (!empty($filters['suche'])) {
        $suchbegriff = '%' . $filters['suche'] . '%';
        $where[] = "(r.kunde_lieferant LIKE ? OR r.rechnungsnummer LIKE ? OR r.beschreibung LIKE ? OR r.notizen LIKE ? OR r.buchungsnummer LIKE ?)";
        $params[] = $suchbegriff;
        $params[] = $suchbegriff;
        $params[] = $suchbegriff;
        $params[] = $suchbegriff;
        $params[] = $suchbegriff;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT r.*, k.name as kategorie_name, u.bezeichnung as ust_name, u.satz as ust_prozent
            FROM rechnungen r
            LEFT JOIN kategorien k ON r.kategorie_id = k.id
            LEFT JOIN ust_saetze u ON r.ust_satz_id = u.id
            $whereClause
            ORDER BY r.datum DESC, r.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Einzelne Rechnung laden
 */
function getRechnung($id) {
    $db = db();
    $stmt = $db->prepare("SELECT r.*, k.name as kategorie_name, u.bezeichnung as ust_name, u.satz as ust_prozent
                          FROM rechnungen r
                          LEFT JOIN kategorien k ON r.kategorie_id = k.id
                          LEFT JOIN ust_saetze u ON r.ust_satz_id = u.id
                          WHERE r.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Letzte Rechnungen laden
 */
function getRecentInvoices($limit = 10) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM rechnungen ORDER BY datum DESC, created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Offene Rechnungen laden
 */
function getOpenInvoices() {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM rechnungen WHERE bezahlt = 0 ORDER BY datum DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Rechnung speichern
 */
function saveRechnung($data) {
    $db = db();
    
    // USt-Betrag berechnen
    $ustProzent = 0;
    if (!empty($data['ust_satz_id'])) {
        $stmt = $db->prepare("SELECT satz FROM ust_saetze WHERE id = ?");
        $stmt->execute([$data['ust_satz_id']]);
        $result = $stmt->fetch();
        $ustProzent = $result ? $result['satz'] : 0;
    }
    
    $ustBetrag = $data['netto_betrag'] * ($ustProzent / 100);
    $bruttoBetrag = $data['netto_betrag'] + $ustBetrag;
    
    // Bei EU B2C: Bruttobetrag inkl. ausländischer USt (nicht abzugsfähig)
    $buchungsart = $data['buchungsart'] ?? 'inland';
    if ($buchungsart === 'eu_b2c' && !empty($data['ausland_ust_betrag'])) {
        // Ausländische USt zum Brutto addieren (als Aufwand)
        $auslandUst = str_replace(',', '.', $data['ausland_ust_betrag']);
        $bruttoBetrag = $data['netto_betrag'] + floatval($auslandUst);
        $ustBetrag = 0; // Keine österreichische Vorsteuer!
    }
    
    // Buchungsnummer ermitteln
    $buchungsnummer = $data['buchungsnummer'] ?? null;
    if (empty($buchungsnummer) && empty($data['id'])) {
        // Neue Buchung: nächste Buchungsnummer für das Jahr ermitteln
        $jahr = date('Y', strtotime($data['datum']));
        $buchungsnummer = getNextBuchungsnummer('rechnungen', $jahr);
    }
    
    // Benutzer-ID für Protokoll
    $benutzer_id = $_SESSION['benutzer_id'] ?? null;
    
    // EU-Felder aufbereiten
    $lieferant_land = $data['lieferant_land'] ?? null;
    $lieferant_uid = $data['lieferant_uid'] ?? null;
    $ausland_ust_satz = !empty($data['ausland_ust_satz']) ? str_replace(',', '.', $data['ausland_ust_satz']) : null;
    $ausland_ust_betrag = !empty($data['ausland_ust_betrag']) ? str_replace(',', '.', $data['ausland_ust_betrag']) : null;
    
    if (!empty($data['id'])) {
        // Update
        $stmt = $db->prepare("UPDATE rechnungen SET 
            typ = ?, rechnungsnummer = ?, buchungsnummer = ?, datum = ?, faellig_am = ?,
            kunde_lieferant = ?, beschreibung = ?, netto_betrag = ?,
            ust_satz_id = ?, ust_betrag = ?, brutto_betrag = ?,
            kategorie_id = ?, bezahlt = ?, bezahlt_am = ?, notizen = ?, geaendert_von = ?,
            buchungsart = ?, lieferant_land = ?, lieferant_uid = ?, ausland_ust_satz = ?, ausland_ust_betrag = ?
            WHERE id = ?");
        $result = $stmt->execute([
            $data['typ'], $data['rechnungsnummer'], $buchungsnummer, $data['datum'], $data['faellig_am'] ?: null,
            $data['kunde_lieferant'], $data['beschreibung'], $data['netto_betrag'],
            $data['ust_satz_id'] ?: null, $ustBetrag, $bruttoBetrag,
            $data['kategorie_id'] ?: null, $data['bezahlt'] ?? 0, $data['bezahlt_am'] ?: null, $data['notizen'],
            $benutzer_id, $buchungsart, $lieferant_land, $lieferant_uid, $ausland_ust_satz, $ausland_ust_betrag,
            $data['id']
        ]);
        
        if ($result && function_exists('logAction')) {
            logAction('rechnungen', $data['id'], 'geaendert', 
                      $data['typ'] . ': ' . $data['kunde_lieferant'] . ' - ' . number_format($bruttoBetrag, 2) . ' €');
        }
        return $result;
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO rechnungen 
            (typ, rechnungsnummer, buchungsnummer, datum, faellig_am, kunde_lieferant, beschreibung, 
             netto_betrag, ust_satz_id, ust_betrag, brutto_betrag, kategorie_id, bezahlt, bezahlt_am, notizen, erstellt_von,
             buchungsart, lieferant_land, lieferant_uid, ausland_ust_satz, ausland_ust_betrag)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['typ'], $data['rechnungsnummer'], $buchungsnummer, $data['datum'], $data['faellig_am'] ?: null,
            $data['kunde_lieferant'], $data['beschreibung'], $data['netto_betrag'],
            $data['ust_satz_id'] ?: null, $ustBetrag, $bruttoBetrag,
            $data['kategorie_id'] ?: null, $data['bezahlt'] ?? 0, $data['bezahlt_am'] ?: null, $data['notizen'],
            $benutzer_id, $buchungsart, $lieferant_land, $lieferant_uid, $ausland_ust_satz, $ausland_ust_betrag
        ]);
        $id = $db->lastInsertId();
        
        if ($id && function_exists('logAction')) {
            logAction('rechnungen', $id, 'erstellt', 
                      $data['typ'] . ': ' . $data['kunde_lieferant'] . ' - ' . number_format($bruttoBetrag, 2) . ' €');
        }
        return $id;
    }
}

/**
 * Nächste Buchungsnummer für das Jahr ermitteln
 */
function getNextBuchungsnummer($tabelle, $jahr) {
    $db = db();
    
    if ($tabelle === 'rechnungen') {
        $stmt = $db->prepare("SELECT MAX(buchungsnummer) as max_nr FROM rechnungen WHERE YEAR(datum) = ?");
    } else {
        $stmt = $db->prepare("SELECT MAX(buchungsnummer) as max_nr FROM anlagegueter WHERE YEAR(anschaffungsdatum) = ?");
    }
    $stmt->execute([$jahr]);
    $result = $stmt->fetch();
    
    return ($result['max_nr'] ?? 0) + 1;
}

/**
 * Rechnung löschen
 */
function deleteRechnung($id) {
    $db = db();
    
    // Rechnungsdaten für Protokoll laden
    if (function_exists('logAction')) {
        $stmt = $db->prepare("SELECT typ, kunde_lieferant, brutto_betrag FROM rechnungen WHERE id = ?");
        $stmt->execute([$id]);
        $rechnung = $stmt->fetch();
        if ($rechnung) {
            logAction('rechnungen', $id, 'geloescht', 
                      $rechnung['typ'] . ': ' . $rechnung['kunde_lieferant'] . ' - ' . number_format($rechnung['brutto_betrag'], 2) . ' €');
        }
    }
    
    $stmt = $db->prepare("DELETE FROM rechnungen WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// KATEGORIEN & UST-SÄTZE
// ============================================

/**
 * Kategorien laden
 */
function getKategorien($typ = null) {
    $db = db();
    if ($typ) {
        $stmt = $db->prepare("SELECT * FROM kategorien WHERE typ = ? AND aktiv = 1 ORDER BY name");
        $stmt->execute([$typ]);
    } else {
        $stmt = $db->prepare("SELECT * FROM kategorien WHERE aktiv = 1 ORDER BY typ, name");
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

/**
 * USt-Sätze laden
 */
function getUstSaetze() {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM ust_saetze WHERE aktiv = 1 ORDER BY satz DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Firmendaten laden
 */
function getFirmendaten() {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM firma LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Alle bisherigen Kunden/Lieferanten laden (für Autocomplete)
 */
function getKundenLieferanten() {
    $db = db();
    $stmt = $db->prepare("SELECT DISTINCT kunde_lieferant FROM rechnungen WHERE kunde_lieferant != '' ORDER BY kunde_lieferant");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================
// UST-VORANMELDUNG (U30)
// ============================================

/**
 * USt-Voranmeldung berechnen
 */
function berechneUstVoranmeldung($jahr, $monat, $typ = 'monat') {
    $db = db();
    
    // Interne DB-Felder für Bemessung und Steuer
    // Mapping zu offiziellem U30 2025:
    // KZ 022 (20%): Bemessung in kz022, Steuer in kz029
    // KZ 029 (10%): Bemessung in kz025, Steuer in kz027
    // KZ 006 (13%): Bemessung in kz035, Steuer in kz052
    $u30 = [
        'kz000' => 0,   // Gesamtbetrag Bemessungsgrundlage
        'kz001' => 0,   // Eigenverbrauch
        'kz021' => 0,   // Steuerfrei (ig. Lieferungen)
        'kz022' => 0,   // Bemessung 20%
        'kz029' => 0,   // Steuer 20%
        'kz025' => 0,   // Bemessung 10%
        'kz027' => 0,   // Steuer 10%
        'kz035' => 0,   // Bemessung 13%
        'kz052' => 0,   // Steuer 13%
        'kz060' => 0,   // Vorsteuer gesamt
        'kz061' => 0,   // Einfuhr-USt Drittland
        'kz065' => 0,   // Vorsteuer ig. Erwerb
        'kz066' => 0,   // Vorsteuer §19
        'kz070' => 0,   // IG Erwerbe Bemessung
        'kz072' => 0,   // IG Erwerbe Steuer (Erwerbsteuer)
        'kz082' => 0,   // Vorsteuerberichtigung
        'kz095' => 0,   // Zahllast/Gutschrift
        'zahllast' => 0
    ];
    
    // Zeitraum bestimmen
    if ($typ == 'quartal') {
        $startMonat = ($monat - 1) * 3 + 1;
        $endMonat = $monat * 3;
        $datumFilter = "YEAR(r.datum) = ? AND MONTH(r.datum) BETWEEN ? AND ?";
        $params = [$jahr, $startMonat, $endMonat];
    } else {
        $datumFilter = "YEAR(r.datum) = ? AND MONTH(r.datum) = ?";
        $params = [$jahr, $monat];
    }
    
    // Einnahmen nach USt-Satz gruppiert
    $sql = "SELECT u.u30_kennzahl_bemessung, u.satz,
                   SUM(r.netto_betrag) as netto, SUM(r.ust_betrag) as ust
            FROM rechnungen r
            LEFT JOIN ust_saetze u ON r.ust_satz_id = u.id
            WHERE r.typ = 'einnahme' AND $datumFilter
            GROUP BY u.u30_kennzahl_bemessung, u.satz";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    $gesamtLieferungen = 0;
    foreach ($stmt->fetchAll() as $row) {
        $netto = $row['netto'] ?? 0;
        $ust = $row['ust'] ?? 0;
        $kz = $row['u30_kennzahl_bemessung'] ?? '';
        
        $gesamtLieferungen += $netto;
        
        // Mapping: Offizielle KZ → Interne DB-Felder
        if ($kz == '022') {
            // 20% → Bemessung in kz022, Steuer in kz029
            $u30['kz022'] += $netto;
            $u30['kz029'] += $ust;
        } elseif ($kz == '029') {
            // 10% → Bemessung in kz025, Steuer in kz027
            $u30['kz025'] += $netto;
            $u30['kz027'] += $ust;
        } elseif ($kz == '006') {
            // 13% → Bemessung in kz035, Steuer in kz052
            $u30['kz035'] += $netto;
            $u30['kz052'] += $ust;
        } elseif ($kz == '021') {
            // Innergemeinschaftlich steuerfrei
            $u30['kz021'] += $netto;
        }
    }
    $u30['kz000'] = $gesamtLieferungen;
    
    // Vorsteuer (Ausgaben) - nur Inland und Drittland-Import
    $sql = "SELECT SUM(r.ust_betrag) as vorsteuer
            FROM rechnungen r
            WHERE r.typ = 'ausgabe' 
            AND (r.buchungsart IS NULL OR r.buchungsart IN ('inland', 'drittland'))
            AND $datumFilter";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vorsteuerRechnungen = $stmt->fetch()['vorsteuer'] ?? 0;
    
    // Einfuhr-USt Drittland separat (KZ 061)
    $sql = "SELECT SUM(r.ust_betrag) as vorsteuer
            FROM rechnungen r
            WHERE r.typ = 'ausgabe' 
            AND r.buchungsart = 'drittland'
            AND $datumFilter";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vorsteuerDrittland = $stmt->fetch()['vorsteuer'] ?? 0;
    $u30['kz061'] = $vorsteuerDrittland;
    
    // Innergemeinschaftliche Erwerbe (igE) - Buchungsart 'eu_ige'
    $sql = "SELECT SUM(r.netto_betrag) as netto
            FROM rechnungen r
            WHERE r.typ = 'ausgabe' 
            AND r.buchungsart = 'eu_ige'
            AND $datumFilter";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $igeNetto = $stmt->fetch()['netto'] ?? 0;
    
    if ($igeNetto > 0) {
        // igE: Bemessungsgrundlage (Netto aus EU-Einkäufen)
        $u30['kz070'] = $igeNetto;
        // igE: Erwerbsteuer 20% (diese wird geschuldet) - KZ 072
        $u30['kz072'] = $igeNetto * 0.20;
        // igE: Gleichzeitig Vorsteuer daraus (gleicht sich aus) - KZ 065
        $u30['kz065'] = $igeNetto * 0.20;
    }
    
    // Vorsteuer aus Anlagegütern
    if ($typ == 'quartal') {
        $datumFilterAnlagen = "YEAR(anschaffungsdatum) = ? AND MONTH(anschaffungsdatum) BETWEEN ? AND ?";
    } else {
        $datumFilterAnlagen = "YEAR(anschaffungsdatum) = ? AND MONTH(anschaffungsdatum) = ?";
    }
    $sql = "SELECT SUM(ust_betrag) as vorsteuer FROM anlagegueter WHERE $datumFilterAnlagen";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $vorsteuerAnlagen = $stmt->fetch()['vorsteuer'] ?? 0;
    
    // Vorsteuer gesamt (ohne igE-Vorsteuer, die separat in kz065 steht)
    $u30['kz060'] = $vorsteuerRechnungen - $vorsteuerDrittland + $vorsteuerAnlagen;
    
    // Zahllast berechnen: Summe aller Steuerbeträge - Vorsteuer
    $ustGesamt = $u30['kz029'] + $u30['kz027'] + $u30['kz052'] + $u30['kz072'];
    $vorsteuerGesamt = $u30['kz060'] + $u30['kz061'] + $u30['kz065'] + $u30['kz066'];
    $u30['zahllast'] = $ustGesamt - $vorsteuerGesamt;
    $u30['kz095'] = $u30['zahllast'];
    
    return $u30;
}

/**
 * USt-Voranmeldung speichern
 */
function saveUstVoranmeldung($data) {
    $db = db();
    
    $stmt = $db->prepare("SELECT id FROM ust_voranmeldungen WHERE jahr = ? AND monat = ? AND zeitraum_typ = ?");
    $stmt->execute([$data['jahr'], $data['monat'], $data['zeitraum_typ']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $db->prepare("UPDATE ust_voranmeldungen SET 
            kz000 = ?, kz001 = ?, kz021 = ?, kz022 = ?, kz029 = ?, kz025 = ?, kz027 = ?,
            kz035 = ?, kz052 = ?, kz060 = ?, kz065 = ?, kz066 = ?, kz070 = ?, kz071 = ?,
            kz082 = ?, kz095 = ?, zahllast = ?, eingereicht = ?, eingereicht_am = ?, notizen = ?
            WHERE id = ?");
        return $stmt->execute([
            $data['kz000'], $data['kz001'], $data['kz021'], $data['kz022'], $data['kz029'],
            $data['kz025'], $data['kz027'], $data['kz035'], $data['kz052'],
            $data['kz060'], $data['kz065'], $data['kz066'], $data['kz070'], $data['kz071'],
            $data['kz082'], $data['kz095'], $data['zahllast'],
            $data['eingereicht'] ?? 0, $data['eingereicht_am'] ?? null, $data['notizen'] ?? null,
            $existing['id']
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO ust_voranmeldungen 
            (jahr, monat, zeitraum_typ, kz000, kz001, kz021, kz022, kz029, kz025, kz027,
             kz035, kz052, kz060, kz065, kz066, kz070, kz071, kz082, kz095, zahllast, eingereicht, eingereicht_am, notizen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['jahr'], $data['monat'], $data['zeitraum_typ'],
            $data['kz000'], $data['kz001'], $data['kz021'], $data['kz022'], $data['kz029'],
            $data['kz025'], $data['kz027'], $data['kz035'], $data['kz052'],
            $data['kz060'], $data['kz065'], $data['kz066'], $data['kz070'], $data['kz071'],
            $data['kz082'], $data['kz095'], $data['zahllast'],
            $data['eingereicht'] ?? 0, $data['eingereicht_am'] ?? null, $data['notizen'] ?? null
        ]);
    }
}

// ============================================
// EINKOMMENSTEUER (E1a)
// ============================================

/**
 * Einkommensteuer berechnen - dynamisch für alle Kennzahlen
 */
function berechneEinkommensteuer($jahr) {
    $db = db();
    
    // Alle verwendeten E1a-Kennzahlen aus Kategorien laden
    $stmt = $db->query("SELECT DISTINCT e1a_kennzahl, typ FROM kategorien WHERE e1a_kennzahl IS NOT NULL AND e1a_kennzahl != ''");
    $kategorieKennzahlen = $stmt->fetchAll();
    
    // E1a Array dynamisch aufbauen - Standard-Kennzahlen + alle aus Kategorien
    $e1a = [
        // Standard Einnahmen-Kennzahlen
        'kz9040' => 0, // Erlöse Lieferungen/Leistungen
        'kz9050' => 0, // Erlöse Dienstleistungen
        // Standard Ausgaben-Kennzahlen
        'kz9100' => 0, // Wareneinkauf
        'kz9110' => 0, // Fremdleistungen
        'kz9120' => 0, // Personalaufwand
        'kz9130' => 0, // AfA normal (linear, GWG)
        'kz9134' => 0, // AfA degressiv
        'kz9135' => 0, // AfA Gebäude beschleunigt
        'kz9140' => 0, // Betriebsräumlichkeiten
        'kz9150' => 0, // Sonstige Ausgaben
        'gewinn_verlust' => 0,
        // Metadaten für Typ-Zuordnung
        '_einnahmen_kz' => ['9040', '9050'],
        '_ausgaben_kz' => ['9100', '9110', '9120', '9130', '9134', '9135', '9140', '9150']
    ];
    
    // Dynamisch Kennzahlen aus Kategorien hinzufügen
    foreach ($kategorieKennzahlen as $kat) {
        $kz = 'kz' . $kat['e1a_kennzahl'];
        if (!isset($e1a[$kz])) {
            $e1a[$kz] = 0;
        }
        // Typ merken für Gewinnberechnung
        if ($kat['typ'] === 'einnahme' && !in_array($kat['e1a_kennzahl'], $e1a['_einnahmen_kz'])) {
            $e1a['_einnahmen_kz'][] = $kat['e1a_kennzahl'];
        } elseif ($kat['typ'] === 'ausgabe' && !in_array($kat['e1a_kennzahl'], $e1a['_ausgaben_kz'])) {
            $e1a['_ausgaben_kz'][] = $kat['e1a_kennzahl'];
        }
    }
    
    // Einnahmen nach Kategorie - ALLE Kennzahlen
    $sql = "SELECT k.e1a_kennzahl, COALESCE(SUM(r.netto_betrag), 0) as summe
            FROM rechnungen r
            LEFT JOIN kategorien k ON r.kategorie_id = k.id
            WHERE r.typ = 'einnahme' AND YEAR(r.datum) = ?
            GROUP BY k.e1a_kennzahl";
    $stmt = $db->prepare($sql);
    $stmt->execute([$jahr]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['e1a_kennzahl']) {
            $kz = 'kz' . $row['e1a_kennzahl'];
            // Kennzahl dynamisch hinzufügen falls nicht vorhanden
            if (!isset($e1a[$kz])) {
                $e1a[$kz] = 0;
                $e1a['_einnahmen_kz'][] = $row['e1a_kennzahl'];
            }
            $e1a[$kz] += $row['summe'];
        }
    }
    
    // Ausgaben nach Kategorie - ALLE Kennzahlen
    // Bei EU B2C: Netto + ausländische USt (da nicht abzugsfähig)
    $sql = "SELECT k.e1a_kennzahl, r.buchungsart, r.netto_betrag, r.ausland_ust_betrag
            FROM rechnungen r
            LEFT JOIN kategorien k ON r.kategorie_id = k.id
            WHERE r.typ = 'ausgabe' AND YEAR(r.datum) = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$jahr]);
    foreach ($stmt->fetchAll() as $row) {
        if ($row['e1a_kennzahl']) {
            $kz = 'kz' . $row['e1a_kennzahl'];
            // Kennzahl dynamisch hinzufügen falls nicht vorhanden
            if (!isset($e1a[$kz])) {
                $e1a[$kz] = 0;
                $e1a['_ausgaben_kz'][] = $row['e1a_kennzahl'];
            }
            // Bei EU B2C: Bruttobetrag als Aufwand (ausländische USt nicht abzugsfähig!)
            if ($row['buchungsart'] === 'eu_b2c' && $row['ausland_ust_betrag'] > 0) {
                $e1a[$kz] += $row['netto_betrag'] + $row['ausland_ust_betrag'];
            } else {
                $e1a[$kz] += $row['netto_betrag'];
            }
        }
    }
    
    // AfA nach E1a-Kennzahl aufschlüsseln
    $sql = "SELECT a.e1a_kennzahl, COALESCE(SUM(ab.afa_betrag), 0) as afa
            FROM afa_buchungen ab
            JOIN anlagegueter a ON ab.anlagegut_id = a.id
            WHERE ab.jahr = ?
            GROUP BY a.e1a_kennzahl";
    $stmt = $db->prepare($sql);
    $stmt->execute([$jahr]);
    foreach ($stmt->fetchAll() as $row) {
        $kennzahl = $row['e1a_kennzahl'] ?: '9130';
        $kz = 'kz' . $kennzahl;
        if (!isset($e1a[$kz])) {
            $e1a[$kz] = 0;
            $e1a['_ausgaben_kz'][] = $kennzahl;
        }
        $e1a[$kz] += $row['afa'];
    }
    
    // Gewinn berechnen - DYNAMISCH alle Einnahmen minus alle Ausgaben
    $einnahmen = 0;
    foreach ($e1a['_einnahmen_kz'] as $kz) {
        $einnahmen += $e1a['kz' . $kz] ?? 0;
    }
    
    $ausgaben = 0;
    foreach ($e1a['_ausgaben_kz'] as $kz) {
        $ausgaben += $e1a['kz' . $kz] ?? 0;
    }
    
    $e1a['gewinn_verlust'] = $einnahmen - $ausgaben;
    $e1a['_summe_einnahmen'] = $einnahmen;
    $e1a['_summe_ausgaben'] = $ausgaben;
    
    return $e1a;
}

/**
 * Einkommensteuer speichern
 */
function saveEinkommensteuer($data) {
    $db = db();
    
    $stmt = $db->prepare("SELECT id FROM einkommensteuer WHERE jahr = ?");
    $stmt->execute([$data['jahr']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $stmt = $db->prepare("UPDATE einkommensteuer SET 
            kz9040 = ?, kz9050 = ?, kz9100 = ?, kz9110 = ?, kz9120 = ?, 
            kz9130 = ?, kz9134 = ?, kz9135 = ?, kz9140 = ?, kz9150 = ?,
            gewinn_verlust = ?, eingereicht = ?, eingereicht_am = ?, notizen = ?
            WHERE id = ?");
        return $stmt->execute([
            $data['kz9040'], $data['kz9050'], $data['kz9100'], $data['kz9110'], $data['kz9120'],
            $data['kz9130'], $data['kz9134'] ?? 0, $data['kz9135'] ?? 0, 
            $data['kz9140'], $data['kz9150'], $data['gewinn_verlust'],
            $data['eingereicht'] ?? 0, $data['eingereicht_am'] ?? null, $data['notizen'] ?? null,
            $existing['id']
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO einkommensteuer 
            (jahr, kz9040, kz9050, kz9100, kz9110, kz9120, kz9130, kz9134, kz9135, kz9140, kz9150, 
             gewinn_verlust, eingereicht, eingereicht_am, notizen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([
            $data['jahr'], $data['kz9040'], $data['kz9050'], $data['kz9100'], $data['kz9110'], $data['kz9120'],
            $data['kz9130'], $data['kz9134'] ?? 0, $data['kz9135'] ?? 0,
            $data['kz9140'], $data['kz9150'], $data['gewinn_verlust'],
            $data['eingereicht'] ?? 0, $data['eingereicht_am'] ?? null, $data['notizen'] ?? null
        ]);
    }
}

// ============================================
// ANLAGEGÜTER
// ============================================

/**
 * Anlagegüter laden
 */
function getAnlagegueter($nurAktiv = true) {
    $db = db();
    $sql = "SELECT * FROM anlagegueter";
    if ($nurAktiv) {
        $sql .= " WHERE status = 'aktiv'";
    }
    $sql .= " ORDER BY anschaffungsdatum DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Einzelnes Anlagegut laden
 */
function getAnlagegut($id) {
    $db = db();
    $stmt = $db->prepare("SELECT a.*, u.satz as ust_prozent, u.bezeichnung as ust_bezeichnung 
                          FROM anlagegueter a 
                          LEFT JOIN ust_saetze u ON a.ust_satz_id = u.id 
                          WHERE a.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Anlagegut speichern
 */
function saveAnlagegut($data) {
    $db = db();
    
    // USt-Betrag berechnen
    $ustProzent = 0;
    if (!empty($data['ust_satz_id'])) {
        $stmt = $db->prepare("SELECT satz FROM ust_saetze WHERE id = ?");
        $stmt->execute([$data['ust_satz_id']]);
        $result = $stmt->fetch();
        $ustProzent = $result ? $result['satz'] : 0;
    }
    
    $nettoBetrag = $data['netto_betrag'] ?? $data['anschaffungswert'];
    $ustBetrag = $nettoBetrag * ($ustProzent / 100);
    $bruttoBetrag = $nettoBetrag + $ustBetrag;
    
    // Buchungsnummer ermitteln
    $buchungsnummer = $data['buchungsnummer'] ?? null;
    if (empty($buchungsnummer) && empty($data['id'])) {
        $jahr = date('Y', strtotime($data['anschaffungsdatum']));
        $buchungsnummer = getNextBuchungsnummer('anlagegueter', $jahr);
    }
    
    if (!empty($data['id'])) {
        $stmt = $db->prepare("UPDATE anlagegueter SET 
            buchungsnummer = ?, bezeichnung = ?, kategorie = ?, anschaffungsdatum = ?, 
            netto_betrag = ?, ust_satz_id = ?, ust_betrag = ?, anschaffungswert = ?,
            nutzungsdauer = ?, afa_methode = ?, e1a_kennzahl = ?, restwert = ?, status = ?, 
            ausscheidungsdatum = ?, ausscheidungsgrund = ?, notizen = ?
            WHERE id = ?");
        $result = $stmt->execute([
            $buchungsnummer, $data['bezeichnung'], $data['kategorie'] ?? 'Sonstige', 
            $data['anschaffungsdatum'], $nettoBetrag, $data['ust_satz_id'] ?: null, $ustBetrag, $bruttoBetrag,
            $data['nutzungsdauer'], $data['afa_methode'] ?? 'linear',
            $data['e1a_kennzahl'] ?? '9130',
            $data['restwert'] ?? 1, $data['status'] ?? 'aktiv',
            $data['ausscheidungsdatum'] ?: null, $data['ausscheidungsgrund'] ?? null, 
            $data['notizen'] ?? null, $data['id']
        ]);
        
        // AfA neu berechnen
        berechneAfaBuchungen($data['id']);
        return $result;
    } else {
        $stmt = $db->prepare("INSERT INTO anlagegueter 
            (buchungsnummer, bezeichnung, kategorie, anschaffungsdatum, netto_betrag, 
             ust_satz_id, ust_betrag, anschaffungswert, nutzungsdauer, 
             afa_methode, e1a_kennzahl, restwert, status, notizen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktiv', ?)");
        $stmt->execute([
            $buchungsnummer, $data['bezeichnung'], $data['kategorie'] ?? 'Sonstige',
            $data['anschaffungsdatum'], $nettoBetrag, $data['ust_satz_id'] ?: null, $ustBetrag, $bruttoBetrag,
            $data['nutzungsdauer'], $data['afa_methode'] ?? 'linear',
            $data['e1a_kennzahl'] ?? '9130',
            $data['restwert'] ?? 1, $data['notizen'] ?? null
        ]);
        
        $id = $db->lastInsertId();
        berechneAfaBuchungen($id);
        return $id;
    }
}

/**
 * Anlagegut löschen
 */
function deleteAnlagegut($id) {
    $db = db();
    // AfA-Buchungen werden durch CASCADE automatisch gelöscht
    $stmt = $db->prepare("DELETE FROM anlagegueter WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// AFA-BUCHUNGEN
// ============================================

/**
 * AfA-Buchungen für ein Anlagegut berechnen
 */
function berechneAfaBuchungen($anlagegutId) {
    $db = db();
    $anlagegut = getAnlagegut($anlagegutId);
    
    if (!$anlagegut) return false;
    
    // Bestehende Buchungen löschen
    $stmt = $db->prepare("DELETE FROM afa_buchungen WHERE anlagegut_id = ?");
    $stmt->execute([$anlagegutId]);
    
    $anschaffungsJahr = (int)date('Y', strtotime($anlagegut['anschaffungsdatum']));
    $anschaffungsMonat = (int)date('n', strtotime($anlagegut['anschaffungsdatum']));
    $abschreibbar = $anlagegut['anschaffungswert'] - $anlagegut['restwert'];
    $nutzungsdauer = $anlagegut['nutzungsdauer'];
    
    if ($nutzungsdauer <= 0 || $abschreibbar <= 0) return true;
    
    // Jährliche AfA (linear)
    $jahresAfa = $abschreibbar / $nutzungsdauer;
    
    $restwert = $anlagegut['anschaffungswert'];
    
    for ($i = 0; $i <= $nutzungsdauer; $i++) {
        $jahr = $anschaffungsJahr + $i;
        $restwertVor = $restwert;
        
        // Im ersten Jahr: Halbjahresregel (wenn Anschaffung in 2. Jahreshälfte)
        if ($i == 0) {
            if ($anschaffungsMonat > 6) {
                $afaBetrag = $jahresAfa / 2;
            } else {
                $afaBetrag = $jahresAfa;
            }
        } else {
            $afaBetrag = $jahresAfa;
        }
        
        // Nicht unter Restwert abschreiben
        if ($restwert - $afaBetrag < $anlagegut['restwert']) {
            $afaBetrag = $restwert - $anlagegut['restwert'];
        }
        
        if ($afaBetrag <= 0) break;
        
        $restwert -= $afaBetrag;
        
        $stmt = $db->prepare("INSERT INTO afa_buchungen (anlagegut_id, jahr, afa_betrag, restwert_vor, restwert_nach)
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$anlagegutId, $jahr, round($afaBetrag, 2), round($restwertVor, 2), round($restwert, 2)]);
        
        // Wenn Restwert erreicht, aufhören
        if ($restwert <= $anlagegut['restwert']) break;
    }
    
    return true;
}

/**
 * AfA-Buchungen für ein Jahr laden
 */
function getAfaBuchungenJahr($jahr) {
    $db = db();
    $stmt = $db->prepare("SELECT ab.*, a.bezeichnung, a.anschaffungsdatum, a.anschaffungswert, 
                                 a.nutzungsdauer, a.e1a_kennzahl, a.afa_methode
                          FROM afa_buchungen ab
                          JOIN anlagegueter a ON ab.anlagegut_id = a.id
                          WHERE ab.jahr = ? AND a.status = 'aktiv'
                          ORDER BY a.e1a_kennzahl, a.anschaffungsdatum");
    $stmt->execute([$jahr]);
    return $stmt->fetchAll();
}

/**
 * Alle AfA-Buchungen für ein Anlagegut laden
 */
function getAfaBuchungenAnlagegut($anlagegutId) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM afa_buchungen WHERE anlagegut_id = ? ORDER BY jahr");
    $stmt->execute([$anlagegutId]);
    return $stmt->fetchAll();
}

// ============================================
// UST-VORANMELDUNGEN - Erweiterte Funktionen
// ============================================

/**
 * Alle USt-Voranmeldungen laden
 */
function getUstVoranmeldungen($jahr = null) {
    $db = db();
    $sql = "SELECT * FROM ust_voranmeldungen";
    $params = [];
    if ($jahr) {
        $sql .= " WHERE jahr = ?";
        $params[] = $jahr;
    }
    $sql .= " ORDER BY jahr DESC, monat DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Einzelne USt-Voranmeldung laden
 */
function getUstVoranmeldung($jahr, $monat, $zeitraumTyp = 'monat') {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM ust_voranmeldungen WHERE jahr = ? AND monat = ? AND zeitraum_typ = ?");
    $stmt->execute([$jahr, $monat, $zeitraumTyp]);
    return $stmt->fetch();
}

/**
 * USt-Voranmeldung löschen
 */
function deleteUstVoranmeldung($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM ust_voranmeldungen WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// EINKOMMENSTEUER - Erweiterte Funktionen
// ============================================

/**
 * Alle Einkommensteuererklärungen laden
 */
function getEinkommensteuern() {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM einkommensteuer ORDER BY jahr DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Einzelne Einkommensteuererklärung laden
 */
function getEinkommensteuerJahr($jahr) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM einkommensteuer WHERE jahr = ?");
    $stmt->execute([$jahr]);
    return $stmt->fetch();
}

/**
 * Einkommensteuererklärung löschen
 */
function deleteEinkommensteuer($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM einkommensteuer WHERE id = ?");
    return $stmt->execute([$id]);
}
