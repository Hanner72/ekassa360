<?php
/**
 * Verkauf-Modul: Kunden, Artikel, Angebote/Aufträge/Rechnungen, Finalisierung, Storno
 *
 * Die Ledger-Tabelle `rechnungen` (U30/E1a-Grundlage) bleibt die einzige Quelle für
 * Steuerberechnung und Zahlungsstatus. Dieses Modul schreibt dort nur über die
 * bestehende saveRechnung()/updateRechnungZahlung()-Funktion (includes/functions.php).
 */

// ============================================
// KUNDEN
// ============================================

function kundenAnzeigename($kunde) {
    if (!empty($kunde['firma_name'])) return $kunde['firma_name'];
    return trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));
}

/**
 * Tooltip-Text für den "Per E-Mail versenden"-Button: zeigt bei bereits versendeten
 * Dokumenten Datum/Empfänger des letzten Versands (verkaufsdokumente.versendet_am/_an).
 */
function versandButtonTitle($doc) {
    if (!empty($doc['versendet_am'])) {
        return 'Per E-Mail gesendet am ' . formatDatum($doc['versendet_am']) . ' an ' . $doc['versendet_an'] . ' - erneut versenden';
    }
    return 'Per E-Mail versenden';
}

function getAlleKunden($nurAktiv = true) {
    $db = db();
    $sql = "SELECT * FROM kunden";
    if ($nurAktiv) $sql .= " WHERE aktiv = 1";
    $sql .= " ORDER BY firma_name, nachname, vorname";
    return $db->query($sql)->fetchAll();
}

function getKunde($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM kunden WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function saveKunde($data) {
    $db = db();

    $kundennummer = $data['kundennummer'] ?? null;
    $firmaName = $data['firma_name'] ?? null;
    $anrede = $data['anrede'] ?? null;
    $vorname = $data['vorname'] ?? null;
    $nachname = $data['nachname'] ?? null;
    $strasse = $data['strasse'] ?? null;
    $plz = $data['plz'] ?? null;
    $ort = $data['ort'] ?? null;
    $land = ($data['land'] ?? null) ?: 'Österreich';
    $uidNummer = $data['uid_nummer'] ?? null;
    $email = $data['email'] ?? null;
    $telefon = $data['telefon'] ?? null;
    $notizen = $data['notizen'] ?? null;
    $aktiv = $data['aktiv'] ?? 1;

    if (!empty($data['id'])) {
        $stmt = $db->prepare("UPDATE kunden SET
            kundennummer=?, firma_name=?, anrede=?, vorname=?, nachname=?, strasse=?, plz=?, ort=?, land=?,
            uid_nummer=?, email=?, telefon=?, notizen=?, aktiv=?
            WHERE id=?");
        $result = $stmt->execute([
            $kundennummer ?: null, $firmaName ?: null, $anrede ?: null,
            $vorname ?: null, $nachname ?: null, $strasse ?: null,
            $plz ?: null, $ort ?: null, $land,
            $uidNummer ?: null, $email ?: null, $telefon ?: null,
            $notizen ?: null, $aktiv,
            $data['id']
        ]);
        if ($result && function_exists('logAction')) {
            logAction('kunden', $data['id'], 'geaendert', 'Kunde bearbeitet: ' . kundenAnzeigename($data));
        }
        return $result;
    }

    $benutzer_id = $_SESSION['benutzer_id'] ?? null;
    $stmt = $db->prepare("INSERT INTO kunden
        (kundennummer, firma_name, anrede, vorname, nachname, strasse, plz, ort, land, uid_nummer, email, telefon, notizen, aktiv, erstellt_von)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $kundennummer ?: null, $firmaName ?: null, $anrede ?: null,
        $vorname ?: null, $nachname ?: null, $strasse ?: null,
        $plz ?: null, $ort ?: null, $land,
        $uidNummer ?: null, $email ?: null, $telefon ?: null,
        $notizen ?: null, $aktiv, $benutzer_id
    ]);
    $id = $db->lastInsertId();
    if ($id && function_exists('logAction')) {
        logAction('kunden', $id, 'erstellt', 'Kunde erstellt: ' . kundenAnzeigename($data));
    }
    return $id;
}

function toggleKundeAktiv($id) {
    $db = db();
    $stmt = $db->prepare("UPDATE kunden SET aktiv = NOT aktiv WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// ARTIKEL
// ============================================

function getAlleArtikel($nurAktiv = true) {
    $db = db();
    $sql = "SELECT a.*, u.satz AS ust_prozent, u.bezeichnung AS ust_bezeichnung, k.name AS kategorie_name
            FROM artikel a
            LEFT JOIN ust_saetze u ON a.ust_satz_id = u.id
            LEFT JOIN kategorien k ON a.kategorie_id = k.id";
    if ($nurAktiv) $sql .= " WHERE a.aktiv = 1";
    $sql .= " ORDER BY a.bezeichnung";
    return $db->query($sql)->fetchAll();
}

function getArtikel($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM artikel WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function saveArtikel($data) {
    $db = db();

    $artikelnummer = $data['artikelnummer'] ?? null;
    $beschreibung = $data['beschreibung'] ?? null;
    $einheit = ($data['einheit'] ?? null) ?: 'Stk';
    $ustSatzId = $data['ust_satz_id'] ?? null;
    $kategorieId = $data['kategorie_id'] ?? null;
    $aktiv = $data['aktiv'] ?? 1;

    if (!empty($data['id'])) {
        $stmt = $db->prepare("UPDATE artikel SET
            artikelnummer=?, bezeichnung=?, beschreibung=?, einheit=?, einzelpreis_netto=?, ust_satz_id=?, kategorie_id=?, aktiv=?
            WHERE id=?");
        $result = $stmt->execute([
            $artikelnummer ?: null, $data['bezeichnung'], $beschreibung ?: null,
            $einheit, $data['einzelpreis_netto'], $ustSatzId ?: null,
            $kategorieId ?: null, $aktiv, $data['id']
        ]);
        if ($result && function_exists('logAction')) {
            logAction('artikel', $data['id'], 'geaendert', 'Artikel bearbeitet: ' . $data['bezeichnung']);
        }
        return $result;
    }

    $stmt = $db->prepare("INSERT INTO artikel
        (artikelnummer, bezeichnung, beschreibung, einheit, einzelpreis_netto, ust_satz_id, kategorie_id, aktiv)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $artikelnummer ?: null, $data['bezeichnung'], $beschreibung ?: null,
        $einheit, $data['einzelpreis_netto'], $ustSatzId ?: null,
        $kategorieId ?: null, $aktiv
    ]);
    $id = $db->lastInsertId();
    if ($id && function_exists('logAction')) {
        logAction('artikel', $id, 'erstellt', 'Artikel erstellt: ' . $data['bezeichnung']);
    }
    return $id;
}

function toggleArtikelAktiv($id) {
    $db = db();
    $stmt = $db->prepare("UPDATE artikel SET aktiv = NOT aktiv WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// VERKAUFSDOKUMENTE (Angebot/Auftrag/Rechnung) - CRUD (nur Entwürfe editierbar)
// ============================================

function getVerkaufsdokumente($typ, $filters = []) {
    $db = db();
    $where = ["v.typ = ?"];
    $params = [$typ];

    if (!empty($filters['jahr'])) {
        $where[] = "YEAR(v.datum) = ?";
        $params[] = $filters['jahr'];
    }
    if (!empty($filters['status'])) {
        $where[] = "v.status = ?";
        $params[] = $filters['status'];
    }
    if (!empty($filters['kunde_id'])) {
        $where[] = "v.kunde_id = ?";
        $params[] = $filters['kunde_id'];
    }
    if (!empty($filters['suche'])) {
        $s = '%' . $filters['suche'] . '%';
        $where[] = "(v.nummer LIKE ? OR v.betreff LIKE ? OR k.firma_name LIKE ? OR k.nachname LIKE ?)";
        array_push($params, $s, $s, $s, $s);
    }

    $whereClause = implode(' AND ', $where);
    $sql = "SELECT v.*, k.firma_name, k.vorname, k.nachname
            FROM verkaufsdokumente v
            LEFT JOIN kunden k ON v.kunde_id = k.id
            WHERE $whereClause
            ORDER BY v.datum DESC, v.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getVerkaufsdokument($id) {
    $db = db();
    $stmt = $db->prepare("SELECT v.*, k.firma_name, k.anrede, k.vorname, k.nachname, k.strasse, k.plz, k.ort, k.land, k.uid_nummer, k.email
                          FROM verkaufsdokumente v
                          LEFT JOIN kunden k ON v.kunde_id = k.id
                          WHERE v.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getVerkaufsdokumentPositionen($id) {
    $db = db();
    $stmt = $db->prepare("SELECT p.*, u.satz AS ust_prozent, u.bezeichnung AS ust_bezeichnung
                          FROM verkaufsdokument_positionen p
                          LEFT JOIN ust_saetze u ON p.ust_satz_id = u.id
                          WHERE p.verkaufsdokument_id = ?
                          ORDER BY p.position");
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

/**
 * Netto/USt/Brutto pro Position und in Summe berechnen (nicht persistiert).
 * $positionen: Liste roher Positionsdaten (artikel_id, bezeichnung, beschreibung, menge, einheit, einzelpreis_netto, ust_satz_id, rabatt_prozent)
 */
function berechnePositionsSummen($positionen) {
    $db = db();
    $result = [];
    $nettoGesamt = 0;
    $ustGesamt = 0;
    $bruttoGesamt = 0;

    $i = 0;
    foreach ($positionen as $pos) {
        $menge = floatval($pos['menge'] ?? 0);
        $preis = floatval($pos['einzelpreis_netto'] ?? 0);
        $rabatt = floatval($pos['rabatt_prozent'] ?? 0);
        $netto = $menge * $preis * (1 - $rabatt / 100);

        $ustProzent = 0;
        if (!empty($pos['ust_satz_id'])) {
            $stmt = $db->prepare("SELECT satz FROM ust_saetze WHERE id = ?");
            $stmt->execute([$pos['ust_satz_id']]);
            $ustProzent = floatval($stmt->fetchColumn() ?: 0);
        }
        $ust = $netto * ($ustProzent / 100);
        $brutto = $netto + $ust;

        $i++;
        $pos['position'] = $i;
        $pos['netto_summe'] = round($netto, 2);
        $pos['ust_summe'] = round($ust, 2);
        $pos['brutto_summe'] = round($brutto, 2);
        $result[] = $pos;

        $nettoGesamt += $pos['netto_summe'];
        $ustGesamt += $pos['ust_summe'];
        $bruttoGesamt += $pos['brutto_summe'];
    }

    return [
        'positionen' => $result,
        'netto_gesamt' => round($nettoGesamt, 2),
        'ust_gesamt' => round($ustGesamt, 2),
        'brutto_gesamt' => round($bruttoGesamt, 2)
    ];
}

/**
 * Verkaufsdokument (Entwurf) speichern - erstellt oder aktualisiert Kopf + Positionen.
 * Nur möglich solange status='entwurf'.
 */
function saveVerkaufsdokument($data, $positionenInput) {
    $db = db();
    $benutzer_id = $_SESSION['benutzer_id'] ?? null;

    if (!empty($data['id'])) {
        $bestehend = getVerkaufsdokument($data['id']);
        if (!$bestehend || $bestehend['status'] !== 'entwurf') {
            return ['success' => false, 'message' => 'Nur Entwürfe können bearbeitet werden.'];
        }
    }

    $berechnet = berechnePositionsSummen($positionenInput);

    $kundeId = $data['kunde_id'] ?? null;
    $vorgaengerId = $data['vorgaenger_id'] ?? null;
    $leistungsdatum = $data['leistungsdatum'] ?? null;
    $gueltigBis = $data['gueltig_bis'] ?? null;
    $faelligAm = $data['faellig_am'] ?? null;
    $betreff = $data['betreff'] ?? null;
    $einleitungstext = $data['einleitungstext'] ?? null;
    $schlusstext = $data['schlusstext'] ?? null;
    $notizen = $data['notizen'] ?? null;

    // Gesamtrabatt (Dokument-Ebene, zusätzlich zu den bereits in berechnePositionsSummen()
    // berücksichtigten Positions-Rabatten) - reduziert Netto/USt/Brutto proportional.
    $gesamtrabattProzent = max(0, min(100, floatval($data['gesamtrabatt_prozent'] ?? 0)));
    $rabattfaktor = 1 - ($gesamtrabattProzent / 100);
    $nettoGesamt = round($berechnet['netto_gesamt'] * $rabattfaktor, 2);
    $ustGesamt = round($berechnet['ust_gesamt'] * $rabattfaktor, 2);
    $bruttoGesamt = round($nettoGesamt + $ustGesamt, 2);

    try {
        $db->beginTransaction();

        if (!empty($data['id'])) {
            $verkaufsdokumentId = $data['id'];
            $stmt = $db->prepare("UPDATE verkaufsdokumente SET
                kunde_id=?, datum=?, leistungsdatum=?, gueltig_bis=?, faellig_am=?, betreff=?, einleitungstext=?, schlusstext=?,
                gesamtrabatt_prozent=?, netto_gesamt=?, ust_gesamt=?, brutto_gesamt=?, notizen=?, geaendert_von=?
                WHERE id=?");
            $stmt->execute([
                $kundeId ?: null, $data['datum'], $leistungsdatum ?: null, $gueltigBis ?: null,
                $faelligAm ?: null, $betreff ?: null, $einleitungstext ?: null, $schlusstext ?: null,
                $gesamtrabattProzent, $nettoGesamt, $ustGesamt, $bruttoGesamt,
                $notizen ?: null, $benutzer_id, $verkaufsdokumentId
            ]);
            $db->prepare("DELETE FROM verkaufsdokument_positionen WHERE verkaufsdokument_id = ?")->execute([$verkaufsdokumentId]);
        } else {
            $stmt = $db->prepare("INSERT INTO verkaufsdokumente
                (typ, status, kunde_id, vorgaenger_id, datum, leistungsdatum, gueltig_bis, faellig_am, betreff, einleitungstext, schlusstext,
                 gesamtrabatt_prozent, netto_gesamt, ust_gesamt, brutto_gesamt, notizen, erstellt_von)
                VALUES (?, 'entwurf', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['typ'], $kundeId ?: null, $vorgaengerId ?: null, $data['datum'],
                $leistungsdatum ?: null, $gueltigBis ?: null, $faelligAm ?: null,
                $betreff ?: null, $einleitungstext ?: null, $schlusstext ?: null,
                $gesamtrabattProzent, $nettoGesamt, $ustGesamt, $bruttoGesamt,
                $notizen ?: null, $benutzer_id
            ]);
            $verkaufsdokumentId = $db->lastInsertId();
        }

        foreach ($berechnet['positionen'] as $pos) {
            $stmt = $db->prepare("INSERT INTO verkaufsdokument_positionen
                (verkaufsdokument_id, position, artikel_id, bezeichnung, beschreibung, menge, einheit, einzelpreis_netto, ust_satz_id, rabatt_prozent, netto_summe, ust_summe, brutto_summe)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $verkaufsdokumentId, $pos['position'], ($pos['artikel_id'] ?? null) ?: null, $pos['bezeichnung'], ($pos['beschreibung'] ?? null) ?: null,
                $pos['menge'], ($pos['einheit'] ?? null) ?: 'Stk', $pos['einzelpreis_netto'], ($pos['ust_satz_id'] ?? null) ?: null,
                $pos['rabatt_prozent'] ?? 0, $pos['netto_summe'], $pos['ust_summe'], $pos['brutto_summe']
            ]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Fehler beim Speichern: ' . $e->getMessage()];
    }

    if (function_exists('logAction')) {
        $aktion = !empty($data['id']) ? 'geaendert' : 'erstellt';
        logAction('verkaufsdokumente', $verkaufsdokumentId, $aktion, ucfirst($data['typ']) . ' gespeichert: ' . ($data['betreff'] ?? ''));
    }

    return ['success' => true, 'id' => $verkaufsdokumentId];
}

function deleteVerkaufsdokument($id) {
    $doc = getVerkaufsdokument($id);
    if (!$doc || $doc['status'] !== 'entwurf') {
        return ['success' => false, 'message' => 'Nur Entwürfe können gelöscht werden.'];
    }
    $db = db();
    $stmt = $db->prepare("DELETE FROM verkaufsdokumente WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result && function_exists('logAction')) {
        logAction('verkaufsdokumente', $id, 'geloescht', ucfirst($doc['typ']) . ' gelöscht: ' . ($doc['betreff'] ?? ''));
    }
    return ['success' => $result];
}

/**
 * Angebot -> Auftrag bzw. Auftrag -> Rechnung: neues Entwurfs-Dokument klonen,
 * Positionen kopieren, Ursprungsdokument-Status aktualisieren.
 */
function umwandelnVerkaufsdokument($id, $neuerTyp) {
    $doc = getVerkaufsdokument($id);
    if (!$doc) {
        return ['success' => false, 'message' => 'Dokument nicht gefunden.'];
    }

    $erlaubt = ['angebot' => 'auftrag', 'auftrag' => 'rechnung'];
    if (($erlaubt[$doc['typ']] ?? null) !== $neuerTyp) {
        return ['success' => false, 'message' => 'Ungültige Umwandlung.'];
    }
    if ($doc['status'] === 'entwurf') {
        return ['success' => false, 'message' => ucfirst($doc['typ']) . ' muss zuerst finalisiert werden (Nummer vergeben), bevor es umgewandelt werden kann.'];
    }

    $positionen = getVerkaufsdokumentPositionen($id);
    $db = db();
    $benutzer_id = $_SESSION['benutzer_id'] ?? null;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO verkaufsdokumente
            (typ, status, kunde_id, vorgaenger_id, datum, leistungsdatum, faellig_am, betreff, einleitungstext, schlusstext,
             gesamtrabatt_prozent, netto_gesamt, ust_gesamt, brutto_gesamt, notizen, erstellt_von)
            VALUES (?, 'entwurf', ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $neuerTyp, $doc['kunde_id'], $id, $doc['leistungsdatum'], $doc['faellig_am'],
            $doc['betreff'], $doc['einleitungstext'], $doc['schlusstext'],
            $doc['gesamtrabatt_prozent'], $doc['netto_gesamt'], $doc['ust_gesamt'], $doc['brutto_gesamt'], $doc['notizen'], $benutzer_id
        ]);
        $neueId = $db->lastInsertId();

        foreach ($positionen as $pos) {
            $stmt = $db->prepare("INSERT INTO verkaufsdokument_positionen
                (verkaufsdokument_id, position, artikel_id, bezeichnung, beschreibung, menge, einheit, einzelpreis_netto, ust_satz_id, rabatt_prozent, netto_summe, ust_summe, brutto_summe)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $neueId, $pos['position'], $pos['artikel_id'], $pos['bezeichnung'], $pos['beschreibung'],
                $pos['menge'], $pos['einheit'], $pos['einzelpreis_netto'], $pos['ust_satz_id'], $pos['rabatt_prozent'],
                $pos['netto_summe'], $pos['ust_summe'], $pos['brutto_summe']
            ]);
        }

        $neuerStatus = $doc['typ'] === 'angebot' ? 'angenommen' : 'abgeschlossen';
        $db->prepare("UPDATE verkaufsdokumente SET status = ? WHERE id = ?")->execute([$neuerStatus, $id]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Fehler bei der Umwandlung: ' . $e->getMessage()];
    }

    if (function_exists('logAction')) {
        logAction('verkaufsdokumente', $neueId, 'erstellt', ucfirst($neuerTyp) . ' erstellt aus ' . ucfirst($doc['typ']) . ' #' . $id);
    }

    return ['success' => true, 'id' => $neueId];
}

/**
 * Rendert einen Nummernkreis-Format-String zu einer konkreten Belegnummer.
 * Platzhalter: {JJJJ} 4-stelliges Jahr, {JJ} 2-stelliges Jahr, {MM} Monat, {TT} Tag
 * (jeweils vom Belegdatum $datum), {N}/{NN}/{NNN}/... laufende Nummer (Stellenanzahl
 * = Anzahl der N, mit führenden Nullen aufgefüllt).
 */
function formatiereNummernkreisNummer($format, $datum, $laufendeNummer) {
    $ts = strtotime($datum) ?: time();
    $ersetzungen = [
        '{JJJJ}' => date('Y', $ts),
        '{JJ}' => date('y', $ts),
        '{MM}' => date('m', $ts),
        '{TT}' => date('d', $ts),
    ];
    $nummer = strtr($format, $ersetzungen);

    return preg_replace_callback('/\{(N+)\}/', function ($m) use ($laufendeNummer) {
        return str_pad($laufendeNummer, strlen($m[1]), '0', STR_PAD_LEFT);
    }, $nummer);
}

// ============================================
// FINALISIEREN / STORNO / ZAHLUNGSABGLEICH
// ============================================

/**
 * Zieht die nächste Nummer aus einem Nummernkreis (SELECT ... FOR UPDATE - verhindert
 * doppelte Nummern bei gleichzeitigen Finalisierungen). Muss innerhalb einer bereits
 * offenen Transaktion aufgerufen werden. Legt den Nummernkreis für schluessel+jahr mit
 * Standardformat an, falls er noch nicht existiert.
 */
function zieheNummernkreisNummer($schluessel, $jahr, $datum) {
    $db = db();
    $standardFormate = [
        'rechnung' => 'RE-{JJJJ}-{NNNN}',
        'angebot' => 'AN-{JJJJ}-{NNNN}',
        'auftrag' => 'AU-{JJJJ}-{NNNN}',
    ];

    $stmt = $db->prepare("SELECT * FROM nummernkreise WHERE schluessel = ? AND jahr = ? FOR UPDATE");
    $stmt->execute([$schluessel, $jahr]);
    $kreis = $stmt->fetch();
    if (!$kreis) {
        $db->prepare("INSERT INTO nummernkreise (schluessel, jahr, format, naechste_nummer) VALUES (?, ?, ?, 1)")
           ->execute([$schluessel, $jahr, $standardFormate[$schluessel] ?? '{JJJJ}-{NNNN}']);
        $stmt = $db->prepare("SELECT * FROM nummernkreise WHERE schluessel = ? AND jahr = ? FOR UPDATE");
        $stmt->execute([$schluessel, $jahr]);
        $kreis = $stmt->fetch();
    }

    $nummer = formatiereNummernkreisNummer($kreis['format'], $datum, $kreis['naechste_nummer']);
    $db->prepare("UPDATE nummernkreise SET naechste_nummer = naechste_nummer + 1 WHERE id = ?")->execute([$kreis['id']]);
    return $nummer;
}

/**
 * Finalisiert ein Angebot oder einen Auftrag: vergibt eine Nummer aus dem passenden
 * Nummernkreis, setzt status='versendet' (danach nicht mehr editierbar/löschbar - siehe
 * saveVerkaufsdokument()/deleteVerkaufsdokument()). Erzeugt - anders als
 * finalizeVerkaufsrechnung() - KEINE Ledger-Zeilen, da Angebote/Aufträge steuerlich
 * nicht relevant sind.
 */
function finalizeAngebotOderAuftrag($verkaufsdokumentId) {
    $db = db();
    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc) {
        return ['success' => false, 'message' => 'Dokument nicht gefunden.'];
    }
    if (!in_array($doc['typ'], ['angebot', 'auftrag'], true)) {
        return ['success' => false, 'message' => 'Nur Angebote und Aufträge können hierüber finalisiert werden.'];
    }
    if ($doc['status'] !== 'entwurf') {
        return ['success' => false, 'message' => ucfirst($doc['typ']) . ' ist bereits finalisiert.'];
    }

    $positionen = getVerkaufsdokumentPositionen($verkaufsdokumentId);
    if (empty($positionen)) {
        return ['success' => false, 'message' => ucfirst($doc['typ']) . ' hat keine Positionen.'];
    }

    $benutzer_id = $_SESSION['benutzer_id'] ?? null;
    $jahr = (int)date('Y', strtotime($doc['datum']));

    try {
        $db->beginTransaction();
        $nummer = zieheNummernkreisNummer($doc['typ'], $jahr, $doc['datum']);
        $db->prepare("UPDATE verkaufsdokumente SET status = 'versendet', nummer = ?, geaendert_von = ? WHERE id = ?")
           ->execute([$nummer, $benutzer_id, $verkaufsdokumentId]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Fehler beim Finalisieren: ' . $e->getMessage()];
    }

    if (function_exists('logAction')) {
        logAction('verkaufsdokumente', $verkaufsdokumentId, 'geaendert', ucfirst($doc['typ']) . " finalisiert: $nummer");
    }

    return ['success' => true, 'id' => $verkaufsdokumentId, 'nummer' => $nummer];
}

/**
 * Einziger Code-Pfad, der einer Rechnung eine endgültige Nummer gibt und
 * Ledger-Zeilen in `rechnungen` anlegt. Wird vom manuellen "Finalisieren"-Button
 * UND vom Cron-Skript für wiederkehrende Rechnungen aufgerufen.
 *
 * Da eine Rechnung Positionen mit unterschiedlichen USt-Sätzen haben kann, die
 * Ledger-Tabelle `rechnungen` aber pro Zeile nur einen USt-Satz kennt, wird pro
 * vorkommendem USt-Satz eine eigene Ledger-Zeile mit derselben Rechnungsnummer
 * angelegt (rechnungsnummer hat keinen UNIQUE-Constraint).
 */
function finalizeVerkaufsrechnung($verkaufsdokumentId) {
    $db = db();
    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc) {
        return ['success' => false, 'message' => 'Dokument nicht gefunden.'];
    }
    if ($doc['typ'] !== 'rechnung') {
        return ['success' => false, 'message' => 'Nur Rechnungen können finalisiert werden.'];
    }
    if ($doc['status'] !== 'entwurf') {
        return ['success' => false, 'message' => 'Dokument ist bereits finalisiert oder storniert.'];
    }

    $positionen = getVerkaufsdokumentPositionen($verkaufsdokumentId);
    if (empty($positionen)) {
        return ['success' => false, 'message' => 'Rechnung hat keine Positionen.'];
    }

    $benutzer_id = $_SESSION['benutzer_id'] ?? null;
    $jahr = (int)date('Y', strtotime($doc['datum']));

    try {
        $db->beginTransaction();

        $nummer = zieheNummernkreisNummer('rechnung', $jahr, $doc['datum']);

        // Gesamtrabatt (Dokument-Ebene) proportional auf jede USt-Gruppe anwenden, damit die
        // Ledger-Summe zur tatsächlich fakturierten (rabattierten) Rechnung passt.
        $rabattfaktor = 1 - (floatval($doc['gesamtrabatt_prozent'] ?? 0) / 100);

        // Positionen nach USt-Satz gruppieren
        $gruppen = [];
        foreach ($positionen as $pos) {
            $key = $pos['ust_satz_id'] ?? 'none';
            if (!isset($gruppen[$key])) {
                $gruppen[$key] = ['ust_satz_id' => $pos['ust_satz_id'] ?: null, 'netto' => 0, 'kategorie_id' => null];
            }
            $gruppen[$key]['netto'] += $pos['netto_summe'] * $rabattfaktor;
            if (!$gruppen[$key]['kategorie_id'] && !empty($pos['artikel_id'])) {
                $stmtA = $db->prepare("SELECT kategorie_id FROM artikel WHERE id = ?");
                $stmtA->execute([$pos['artikel_id']]);
                $artikelKat = $stmtA->fetchColumn();
                if ($artikelKat) $gruppen[$key]['kategorie_id'] = $artikelKat;
            }
        }

        // Fallback-Kategorie "Verkaufserlöse" (Seed aus add_verkauf_module.sql)
        $stmtDefault = $db->prepare("SELECT id FROM kategorien WHERE name = 'Verkaufserlöse' AND typ = 'einnahme' LIMIT 1");
        $stmtDefault->execute();
        $defaultKategorieId = $stmtDefault->fetchColumn() ?: null;

        $kundeName = kundenAnzeigename($doc) ?: ('Kunde #' . $doc['kunde_id']);

        foreach ($gruppen as $g) {
            saveRechnung([
                'typ' => 'einnahme',
                'rechnungsnummer' => $nummer,
                'datum' => $doc['datum'],
                'faellig_am' => $doc['faellig_am'],
                'kunde_lieferant' => $kundeName,
                'beschreibung' => $doc['betreff'] ?: ('Verkaufsrechnung ' . $nummer),
                'netto_betrag' => round($g['netto'], 2),
                'ust_satz_id' => $g['ust_satz_id'],
                'kategorie_id' => $g['kategorie_id'] ?: $defaultKategorieId,
                'bezahlt' => 0,
                'bezahlt_am' => null,
                'zahlungsart' => 'bankueberweisung',
                'notizen' => null,
                'verkaufsdokument_id' => $verkaufsdokumentId
            ]);
        }

        $db->prepare("UPDATE verkaufsdokumente SET status = 'abgeschlossen', nummer = ?, geaendert_von = ? WHERE id = ?")
           ->execute([$nummer, $benutzer_id, $verkaufsdokumentId]);

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Fehler beim Finalisieren: ' . $e->getMessage()];
    }

    if (function_exists('logAction')) {
        logAction('verkaufsdokumente', $verkaufsdokumentId, 'geaendert', "Rechnung finalisiert: $nummer");
    }

    return ['success' => true, 'id' => $verkaufsdokumentId, 'nummer' => $nummer];
}

/**
 * Storniert eine abgeschlossene Rechnung: legt ein Gegen-Dokument mit negierten
 * Beträgen an, finalisiert es über denselben Code-Pfad (eigene Nummer, negative
 * Ledger-Zeilen), markiert das Original als storniert.
 */
function storniereVerkaufsrechnung($verkaufsdokumentId) {
    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc || $doc['typ'] !== 'rechnung' || $doc['status'] !== 'abgeschlossen') {
        return ['success' => false, 'message' => 'Nur abgeschlossene Rechnungen können storniert werden.'];
    }
    if (!empty($doc['storno_von_id'])) {
        return ['success' => false, 'message' => 'Eine Stornorechnung kann nicht selbst storniert werden.'];
    }

    $positionen = getVerkaufsdokumentPositionen($verkaufsdokumentId);
    $db = db();
    $benutzer_id = $_SESSION['benutzer_id'] ?? null;

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO verkaufsdokumente
            (typ, status, kunde_id, storno_von_id, datum, leistungsdatum, betreff,
             gesamtrabatt_prozent, netto_gesamt, ust_gesamt, brutto_gesamt, erstellt_von)
            VALUES ('rechnung', 'entwurf', ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $doc['kunde_id'], $verkaufsdokumentId, $doc['leistungsdatum'],
            'Storno zu ' . $doc['nummer'], $doc['gesamtrabatt_prozent'],
            -$doc['netto_gesamt'], -$doc['ust_gesamt'], -$doc['brutto_gesamt'],
            $benutzer_id
        ]);
        $stornoId = $db->lastInsertId();

        $i = 0;
        foreach ($positionen as $pos) {
            $i++;
            $stmt = $db->prepare("INSERT INTO verkaufsdokument_positionen
                (verkaufsdokument_id, position, artikel_id, bezeichnung, beschreibung, menge, einheit, einzelpreis_netto, ust_satz_id, rabatt_prozent, netto_summe, ust_summe, brutto_summe)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $stornoId, $i, $pos['artikel_id'], $pos['bezeichnung'], $pos['beschreibung'],
                -$pos['menge'], $pos['einheit'], $pos['einzelpreis_netto'], $pos['ust_satz_id'], $pos['rabatt_prozent'],
                -$pos['netto_summe'], -$pos['ust_summe'], -$pos['brutto_summe']
            ]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Fehler beim Stornieren: ' . $e->getMessage()];
    }

    $result = finalizeVerkaufsrechnung($stornoId);
    if (!$result['success']) {
        return $result;
    }

    $db->prepare("UPDATE verkaufsdokumente SET status = 'storniert' WHERE id = ?")->execute([$verkaufsdokumentId]);

    if (function_exists('logAction')) {
        logAction('verkaufsdokumente', $verkaufsdokumentId, 'geaendert', 'Storniert durch ' . $result['nummer']);
    }

    return ['success' => true, 'storno_id' => $stornoId, 'storno_nummer' => $result['nummer']];
}

/**
 * Zahlungsstatus einer finalisierten Verkaufsrechnung, aggregiert über alle
 * zugehörigen Ledger-Zeilen (eine pro USt-Satz, siehe finalizeVerkaufsrechnung()).
 * bezahlt = true nur wenn ALLE Zeilen bezahlt sind.
 */
function getVerkaufsrechnungZahlungsstatus($verkaufsdokumentId) {
    $db = db();
    $stmt = $db->prepare("SELECT id, bezahlt, bezahlt_am, zahlungsart, brutto_betrag FROM rechnungen WHERE verkaufsdokument_id = ?");
    $stmt->execute([$verkaufsdokumentId]);
    $zeilen = $stmt->fetchAll();

    if (empty($zeilen)) {
        return ['finalisiert' => false, 'bezahlt' => false, 'bezahlt_am' => null, 'zeilen' => []];
    }

    $bezahlt = true;
    $bezahltAm = null;
    foreach ($zeilen as $z) {
        if (!$z['bezahlt']) {
            $bezahlt = false;
        }
        if ($z['bezahlt_am'] && (!$bezahltAm || $z['bezahlt_am'] > $bezahltAm)) {
            $bezahltAm = $z['bezahlt_am'];
        }
    }

    return ['finalisiert' => true, 'bezahlt' => $bezahlt, 'bezahlt_am' => $bezahlt ? $bezahltAm : null, 'zeilen' => $zeilen];
}

/**
 * Setzt den Zahlungsstatus ALLER Ledger-Zeilen einer Verkaufsrechnung atomar
 * (eine Verkaufsrechnung kann mehrere Ledger-Zeilen haben, siehe finalizeVerkaufsrechnung()).
 */
function markVerkaufsrechnungBezahlt($verkaufsdokumentId, $bezahlt, $bezahlt_am, $zahlungsart) {
    $db = db();
    $stmt = $db->prepare("SELECT id FROM rechnungen WHERE verkaufsdokument_id = ?");
    $stmt->execute([$verkaufsdokumentId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids)) {
        return ['success' => false, 'message' => 'Keine Ledger-Zeilen zu diesem Dokument gefunden - bitte zuerst finalisieren.'];
    }

    try {
        $db->beginTransaction();
        foreach ($ids as $id) {
            updateRechnungZahlung($id, $bezahlt, $bezahlt_am, $zahlungsart);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
    }

    return ['success' => true];
}

/**
 * Kassabuch-Kurzaktion: Verkaufsrechnung als heute bezahlt markieren UND zugleich allen
 * noch unnummerierten Ledger-Zeilen dieses Dokuments eine Buchungsnummer vergeben (siehe
 * vergebeBuchungsnummer() in functions.php) - beide Schritte passieren beim Bankabgleich im
 * Kassabuch typischerweise im selben Moment. Eine Verkaufsrechnung mit gemischten USt-Sätzen
 * hat mehrere Ledger-Zeilen (eine pro USt-Satz) und bekommt entsprechend mehrere, fortlaufende
 * Buchungsnummern.
 */
function markVerkaufsrechnungBezahltUndVergebeBuchungsnummer($verkaufsdokumentId, $bezahlt_am, $zahlungsart) {
    $ergebnis = markVerkaufsrechnungBezahlt($verkaufsdokumentId, true, $bezahlt_am, $zahlungsart);
    if (!$ergebnis['success']) {
        return $ergebnis;
    }

    $db = db();
    $stmt = $db->prepare("SELECT id FROM rechnungen WHERE verkaufsdokument_id = ? AND buchungsnummer IS NULL");
    $stmt->execute([$verkaufsdokumentId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $buchungsnummern = [];
    foreach ($ids as $id) {
        $vergabe = vergebeBuchungsnummer($id);
        if ($vergabe['success']) {
            $buchungsnummern[] = $vergabe['buchungsnummer'];
        }
    }

    return ['success' => true, 'buchungsnummern' => $buchungsnummern];
}
