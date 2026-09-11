<?php
/**
 * Wiederkehrende Rechnungen: Terminberechnung + Erzeugungslogik.
 *
 * Wird sowohl vom manuellen "Jetzt ausführen"-Button (wiederkehrende_rechnungen.php)
 * als auch von cron/generate_wiederkehrende_rechnungen.php aufgerufen - ein
 * einziger Code-Pfad.
 *
 * Wichtige Abgrenzung: `wiederkehrende_rechnungen_log.status` bildet nur ab, ob die
 * Rechnung selbst erzeugt und finalisiert (nummeriert, Ledger-Zeilen angelegt) werden
 * konnte. Eine fehlgeschlagene paperless-Archivierung zählt NICHT als "fehler" in
 * diesem Log, da sonst ein Retry am nächsten Lauf denselben Nummernkreis erneut
 * ziehen und eine zweite, doppelt nummerierte Rechnung anlegen würde. Ein
 * Archivierungs-Fehlschlag wird stattdessen wie überall sonst im Modul über
 * `verkaufsdokumente.paperless_document_id IS NULL` (Badge "nicht archiviert" +
 * "Erneut senden") sichtbar gemacht.
 */

/**
 * Nächstes Fälligkeitsdatum berechnen, mit Monatsende-Clamping
 * (z.B. 31.1. + monatlich -> 28./29.2.).
 */
function berechneNaechstesDatum($datum, $rhythmus) {
    $monateMap = ['monatlich' => 1, 'quartalsweise' => 3, 'halbjaehrlich' => 6, 'jaehrlich' => 12];
    $monate = $monateMap[$rhythmus] ?? 1;

    $dt = new DateTime($datum);
    $tag = (int)$dt->format('d');

    $dt->modify('first day of this month');
    $dt->modify('+' . $monate . ' months');

    $letzterTagNeuerMonat = (int)$dt->format('t');
    $neuerTag = min($tag, $letzterTagNeuerMonat);
    $dt->modify('+' . ($neuerTag - 1) . ' days');

    return $dt->format('Y-m-d');
}

/**
 * Alle fälligen Regeln verarbeiten (aktiv=1, naechstes_datum <= heute).
 * $nurId: optional auf eine einzelne Regel einschränken (manueller Button).
 */
function generateFaelligeWiederkehrendeRechnungen($nurId = null) {
    $db = db();
    $heute = date('Y-m-d');

    $sql = "SELECT * FROM wiederkehrende_rechnungen WHERE aktiv = 1 AND naechstes_datum <= ?";
    $params = [$heute];
    if ($nurId) {
        $sql .= " AND id = ?";
        $params[] = $nurId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $regeln = $stmt->fetchAll();

    $ergebnisse = [];
    foreach ($regeln as $regel) {
        $ergebnisse[] = verarbeiteWiederkehrendeRegel($regel);
    }
    return $ergebnisse;
}

/**
 * Eine einzelne Regel verarbeiten: Rechnung aus Vorlage klonen, finalisieren,
 * archivieren (best effort), Folgedatum fortschreiben.
 */
function verarbeiteWiederkehrendeRegel($regel) {
    $db = db();
    $heute = date('Y-m-d');

    // Heute bereits erfolgreich gelaufen? -> No-Op (Idempotenz pro Kalendertag)
    $stmt = $db->prepare("SELECT * FROM wiederkehrende_rechnungen_log WHERE wiederkehrend_id = ? AND lauf_datum = ?");
    $stmt->execute([$regel['id'], $heute]);
    $log = $stmt->fetch();
    if ($log && $log['status'] === 'erstellt') {
        return ['id' => $regel['id'], 'status' => 'uebersprungen', 'message' => 'Heute bereits erfolgreich ausgeführt.'];
    }

    if ($regel['end_datum'] && $regel['end_datum'] < $heute) {
        $db->prepare("UPDATE wiederkehrende_rechnungen SET aktiv = 0 WHERE id = ?")->execute([$regel['id']]);
        return ['id' => $regel['id'], 'status' => 'beendet', 'message' => 'Enddatum erreicht - Regel deaktiviert.'];
    }
    if ($regel['max_anzahl'] && $regel['anzahl_erstellt'] >= $regel['max_anzahl']) {
        $db->prepare("UPDATE wiederkehrende_rechnungen SET aktiv = 0 WHERE id = ?")->execute([$regel['id']]);
        return ['id' => $regel['id'], 'status' => 'beendet', 'message' => 'Maximale Anzahl erreicht - Regel deaktiviert.'];
    }
    if (empty($regel['vorlage_verkaufsdokument_id'])) {
        speichereWiederkehrendLog($regel['id'], $heute, 'fehler', null, 'Keine Vorlage hinterlegt.');
        return ['id' => $regel['id'], 'status' => 'fehler', 'message' => 'Keine Vorlage hinterlegt.'];
    }

    try {
        $vorlage = getVerkaufsdokument($regel['vorlage_verkaufsdokument_id']);
        if (!$vorlage) {
            throw new Exception('Vorlage nicht gefunden.');
        }
        $positionenRoh = getVerkaufsdokumentPositionen($regel['vorlage_verkaufsdokument_id']);

        $neueDaten = [
            'typ' => 'rechnung',
            'kunde_id' => $regel['kunde_id'] ?: $vorlage['kunde_id'],
            'datum' => $heute,
            'leistungsdatum' => $vorlage['leistungsdatum'],
            'faellig_am' => null,
            'betreff' => $vorlage['betreff'],
            'einleitungstext' => $vorlage['einleitungstext'],
            'schlusstext' => $vorlage['schlusstext'],
            'notizen' => 'Automatisch erstellt aus wiederkehrender Rechnung: ' . $regel['bezeichnung']
        ];

        $speichern = saveVerkaufsdokument($neueDaten, $positionenRoh);
        if (!$speichern['success']) {
            throw new Exception($speichern['message']);
        }
        $db->prepare("UPDATE verkaufsdokumente SET wiederkehrend_id = ? WHERE id = ?")->execute([$regel['id'], $speichern['id']]);

        $finalisiert = finalizeVerkaufsrechnung($speichern['id']);
        if (!$finalisiert['success']) {
            throw new Exception($finalisiert['message']);
        }

        // paperless-Archivierung: best effort, Fehler blockiert die Rechnungserstellung nicht
        // (siehe Kommentar am Dateikopf) - Badge/Retry läuft über verkaufsdokumente.paperless_document_id.
        if (function_exists('paperlessConfigured') && paperlessConfigured()) {
            try {
                archiviereVerkaufsdokumentInPaperless($speichern['id']);
            } catch (Exception $e) {
                error_log('paperless-Archivierung fehlgeschlagen für Verkaufsdokument ' . $speichern['id'] . ': ' . $e->getMessage());
            }
        }

        // E-Mail-Versand: ebenfalls best effort, aus demselben Grund wie paperless oben -
        // ein SMTP-Fehler darf die bereits finalisierte (nummerierte, verbuchte) Rechnung
        // nicht rückgängig machen. Sichtbar über versendet_am/versendet_an in der UI.
        if (!empty($regel['automatisch_pdf_versenden']) && function_exists('mailConfigured') && mailConfigured()) {
            try {
                $versand = sendeVerkaufsdokumentEmail($speichern['id'], $regel['versand_email'] ?: null);
                if (!$versand['success']) {
                    error_log('E-Mail-Versand fehlgeschlagen für Verkaufsdokument ' . $speichern['id'] . ': ' . $versand['error']);
                }
            } catch (Exception $e) {
                error_log('E-Mail-Versand fehlgeschlagen für Verkaufsdokument ' . $speichern['id'] . ': ' . $e->getMessage());
            }
        }

        $naechstesDatum = berechneNaechstesDatum($regel['naechstes_datum'], $regel['rhythmus']);
        $db->prepare("UPDATE wiederkehrende_rechnungen SET
            naechstes_datum = ?, anzahl_erstellt = anzahl_erstellt + 1,
            letzte_ausfuehrung = NOW(), letzte_erstellte_rechnung_id = ?
            WHERE id = ?")
           ->execute([$naechstesDatum, $speichern['id'], $regel['id']]);

        speichereWiederkehrendLog($regel['id'], $heute, 'erstellt', $speichern['id'], null);

        return ['id' => $regel['id'], 'status' => 'erstellt', 'nummer' => $finalisiert['nummer']];
    } catch (Exception $e) {
        speichereWiederkehrendLog($regel['id'], $heute, 'fehler', null, $e->getMessage());
        return ['id' => $regel['id'], 'status' => 'fehler', 'message' => $e->getMessage()];
    }
}

/**
 * Log-Zeile für den heutigen Lauf schreiben/überschreiben (ein Eintrag pro Regel
 * und Kalendertag - siehe UNIQUE KEY unique_lauf).
 */
function speichereWiederkehrendLog($wiederkehrendId, $laufDatum, $status, $verkaufsdokumentId, $fehlermeldung) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO wiederkehrende_rechnungen_log (wiederkehrend_id, verkaufsdokument_id, lauf_datum, status, fehlermeldung)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE verkaufsdokument_id = VALUES(verkaufsdokument_id), status = VALUES(status), fehlermeldung = VALUES(fehlermeldung)");
    $stmt->execute([$wiederkehrendId, $verkaufsdokumentId, $laufDatum, $status, $fehlermeldung]);

    if (function_exists('logAction')) {
        logAction('wiederkehrende_rechnungen', $wiederkehrendId, $status === 'erstellt' ? 'erstellt' : 'geaendert',
                  $status === 'erstellt' ? 'Wiederkehrende Rechnung erzeugt' : ('Fehler: ' . $fehlermeldung));
    }
}

function getWiederkehrendeRechnungen() {
    $db = db();
    $stmt = $db->query("SELECT w.*, k.firma_name, k.vorname, k.nachname
                        FROM wiederkehrende_rechnungen w
                        LEFT JOIN kunden k ON w.kunde_id = k.id
                        ORDER BY w.aktiv DESC, w.naechstes_datum");
    return $stmt->fetchAll();
}

function getWiederkehrendeRechnung($id) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM wiederkehrende_rechnungen WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getWiederkehrendLog($wiederkehrendId, $limit = 50) {
    $db = db();
    $stmt = $db->prepare("SELECT l.*, v.nummer
                          FROM wiederkehrende_rechnungen_log l
                          LEFT JOIN verkaufsdokumente v ON l.verkaufsdokument_id = v.id
                          WHERE l.wiederkehrend_id = ?
                          ORDER BY l.lauf_datum DESC LIMIT " . intval($limit));
    $stmt->execute([$wiederkehrendId]);
    return $stmt->fetchAll();
}

function saveWiederkehrendeRechnung($data) {
    $db = db();

    $kundeId = $data['kunde_id'] ?? null;
    $vorlageId = $data['vorlage_verkaufsdokument_id'] ?? null;
    $endDatum = $data['end_datum'] ?? null;
    $maxAnzahl = $data['max_anzahl'] ?? null;
    $aktiv = $data['aktiv'] ?? 1;
    $automatischVersenden = $data['automatisch_pdf_versenden'] ?? 0;
    $versandEmail = $data['versand_email'] ?? null;
    $notizen = $data['notizen'] ?? null;
    $naechstesDatum = ($data['naechstes_datum'] ?? null) ?: $data['start_datum'];

    if (!empty($data['id'])) {
        $stmt = $db->prepare("UPDATE wiederkehrende_rechnungen SET
            bezeichnung=?, kunde_id=?, vorlage_verkaufsdokument_id=?, rhythmus=?, start_datum=?, naechstes_datum=?,
            end_datum=?, max_anzahl=?, aktiv=?, automatisch_pdf_versenden=?, versand_email=?, notizen=?
            WHERE id=?");
        $result = $stmt->execute([
            $data['bezeichnung'], $kundeId ?: null, $vorlageId ?: null,
            $data['rhythmus'], $data['start_datum'], $naechstesDatum,
            $endDatum ?: null, $maxAnzahl ?: null, $aktiv,
            $automatischVersenden, $versandEmail ?: null, $notizen ?: null,
            $data['id']
        ]);
        if ($result && function_exists('logAction')) {
            logAction('wiederkehrende_rechnungen', $data['id'], 'geaendert', 'Regel bearbeitet: ' . $data['bezeichnung']);
        }
        return $result;
    }

    $benutzer_id = $_SESSION['benutzer_id'] ?? null;
    $stmt = $db->prepare("INSERT INTO wiederkehrende_rechnungen
        (bezeichnung, kunde_id, vorlage_verkaufsdokument_id, rhythmus, start_datum, naechstes_datum, end_datum, max_anzahl, aktiv, automatisch_pdf_versenden, versand_email, notizen, erstellt_von)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['bezeichnung'], $kundeId ?: null, $vorlageId ?: null,
        $data['rhythmus'], $data['start_datum'], $naechstesDatum,
        $endDatum ?: null, $maxAnzahl ?: null, $aktiv,
        $automatischVersenden, $versandEmail ?: null, $notizen ?: null, $benutzer_id
    ]);
    $id = $db->lastInsertId();
    if ($id && function_exists('logAction')) {
        logAction('wiederkehrende_rechnungen', $id, 'erstellt', 'Regel erstellt: ' . $data['bezeichnung']);
    }
    return $id;
}

function deleteWiederkehrendeRechnung($id) {
    $db = db();
    $stmt = $db->prepare("DELETE FROM wiederkehrende_rechnungen WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result && function_exists('logAction')) {
        logAction('wiederkehrende_rechnungen', $id, 'geloescht', 'Regel gelöscht');
    }
    return $result;
}

function toggleWiederkehrendAktiv($id) {
    $db = db();
    $stmt = $db->prepare("UPDATE wiederkehrende_rechnungen SET aktiv = NOT aktiv WHERE id = ?");
    return $stmt->execute([$id]);
}
