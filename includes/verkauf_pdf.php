<?php
/**
 * PDF-Erzeugung für Angebot/Auftrag/Rechnung über vom Nutzer editierbare
 * HTML/CSS-Vorlagen (Tabelle `pdf_vorlagen`) + Dompdf. Einfache {{platzhalter}}-
 * Ersetzung (kein Loop-fähiger Template-Engine) - die einzige Liste (Positionen)
 * wird serverseitig fertig als HTML-Tabelle gerendert und als ein Platzhalter
 * eingesetzt.
 *
 * pdf_u30.php/pdf_e1a.php bleiben bewusst auf FPDF (siehe dort) - hier geht es nur
 * um die drei optisch anpassbaren Verkaufsdokument-Typen.
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Aktuelle Vorlage für einen Dokumenttyp laden. Fällt auf die eingebaute
 * Standardvorlage zurück, falls (noch) keine DB-Zeile existiert.
 */
function getPdfVorlage($typ) {
    $db = db();
    $stmt = $db->prepare("SELECT vorlage FROM pdf_vorlagen WHERE typ = ?");
    $stmt->execute([$typ]);
    $vorlage = $stmt->fetchColumn();
    return $vorlage !== false ? $vorlage : standardPdfVorlage($typ);
}

/**
 * Vorlage speichern (Insert-or-Update über den UNIQUE KEY auf typ).
 */
function savePdfVorlage($typ, $inhalt, $benutzerId = null) {
    $db = db();
    $stmt = $db->prepare("INSERT INTO pdf_vorlagen (typ, vorlage, geaendert_von) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE vorlage = VALUES(vorlage), geaendert_von = VALUES(geaendert_von)");
    $result = $stmt->execute([$typ, $inhalt, $benutzerId]);
    if ($result && function_exists('logAction')) {
        logAction('pdf_vorlagen', 0, 'geaendert', "PDF-Vorlage für '$typ' geändert");
    }
    return $result;
}

/**
 * Eingebaute Standardvorlage (Fallback + "Auf Standard zurücksetzen").
 */
function standardPdfVorlage($typ) {
    $gemeinsamesCss = '<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; position: relative; }
  .kopf { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 16px; }
  .firma-name { font-size: 14pt; font-weight: bold; color: #0d6efd; }
  .firma-adresse { font-size: 8pt; color: #666; }
  .doktyp { font-size: 16pt; font-weight: bold; text-align: right; }
  .kunde-block { margin-bottom: 16px; }
  .meta-block { text-align: right; margin-bottom: 16px; }
  .betreff { font-weight: bold; font-size: 11pt; margin-bottom: 8px; }
  table.positionen-tabelle { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  table.positionen-tabelle th { background: #0d6efd; color: #fff; text-align: left; padding: 5px; font-size: 9pt; }
  table.positionen-tabelle td { border-bottom: 1px solid #ddd; padding: 5px; font-size: 9pt; }
  .positionen-beschreibung { font-size: 8pt; color: #666; }
  .summenblock { text-align: right; margin-top: 12px; }
  .summenblock .brutto { font-size: 12pt; font-weight: bold; }
  .zahlungshinweis { margin-top: 16px; font-size: 9pt; }
  .fusszeile { margin-top: 24px; font-size: 8pt; color: #666; }
  .wasserzeichen { position: fixed; top: 250px; left: 60px; font-size: 80pt; color: #eee; transform: rotate(-25deg); z-index: -1; }
  .firma-logo { max-height: 50px; max-width: 200px; margin-bottom: 4px; }
</style>';

    if ($typ === 'lieferschein') {
        return $gemeinsamesCss . '
<div class="kopf">
  <div>
    {{firma_logo}}
    <div class="firma-name">{{firma_name}}</div>
    <div class="firma-adresse">{{firma_adresse}}</div>
  </div>
  <div class="doktyp">{{dokument_typ_label}}</div>
</div>
<div class="kunde-block">
  {{kunde_adresse}}
</div>
<div class="meta-block">
  <div><strong>Lieferschein zu Rechnung {{nummer}}</strong></div>
  <div>Datum: {{datum}}</div>
</div>
<div class="betreff">{{betreff}}</div>
{{positionen_tabelle}}
<p style="margin-top: 40px;">Ware ordnungsgemäß erhalten:</p>
<p style="margin-top: 30px;">_____________________________________<br>Datum, Unterschrift</p>
<div class="fusszeile">{{firma_name}} &middot; {{firma_adresse}} &middot; UID {{firma_uid}}</div>';
    }

    $metaZeilen = [
        'angebot' => '<div>Datum: {{datum}}</div><div>Gültig bis: {{gueltig_bis}}</div>',
        'auftrag' => '<div>Datum: {{datum}}</div><div>Leistungsdatum: {{leistungsdatum}}</div>',
        'rechnung' => '<div>Datum: {{datum}}</div><div>Leistungsdatum: {{leistungsdatum}}</div><div>Fällig am: {{faellig_am}}</div>',
    ];
    $zahlungshinweisBlock = $typ === 'rechnung' ? '<div class="zahlungshinweis">{{zahlungshinweis}}</div>' : '';

    return $gemeinsamesCss . '
{{wasserzeichen}}
<div class="kopf">
  <div>
    {{firma_logo}}
    <div class="firma-name">{{firma_name}}</div>
    <div class="firma-adresse">{{firma_adresse}}</div>
  </div>
  <div class="doktyp">{{dokument_typ_label}}</div>
</div>
<div class="kunde-block">
  {{kunde_adresse}}
</div>
<div class="meta-block">
  <div><strong>{{dokument_typ_label}} {{nummer}}</strong></div>
  ' . ($metaZeilen[$typ] ?? '') . '
</div>
<div class="betreff">{{betreff}}</div>
<p>{{einleitungstext}}</p>
{{positionen_tabelle}}
{{summenblock}}
<p>{{schlusstext}}</p>
' . $zahlungshinweisBlock . '
<div class="fusszeile">{{firma_name}} &middot; {{firma_adresse}} &middot; UID {{firma_uid}}</div>';
}

/**
 * Baut die {{platzhalter}} => Wert-Zuordnung für ein Verkaufsdokument.
 * $doc: Ergebnis von getVerkaufsdokument(). $positionen: getVerkaufsdokumentPositionen().
 * $firma: getFirmendaten().
 */
function baueDokumentPlatzhalter($doc, $positionen, $firma) {
    $typLabels = ['angebot' => 'Angebot', 'auftrag' => 'Auftragsbestätigung', 'rechnung' => 'Rechnung', 'lieferschein' => 'Lieferschein'];
    $typLabel = $typLabels[$doc['typ']] ?? ucfirst($doc['typ']);
    $zeigePreise = $doc['typ'] !== 'lieferschein';

    $firmaAdresse = trim(($firma['strasse'] ?? '') . ', ' . ($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? ''), ' ,');

    $kundeName = kundenAnzeigename($doc);
    $kundeAdresseZeilen = array_filter([
        $kundeName,
        $doc['strasse'] ?? '',
        trim(($doc['plz'] ?? '') . ' ' . ($doc['ort'] ?? '')),
        !empty($doc['uid_nummer']) ? 'UID: ' . $doc['uid_nummer'] : ''
    ], fn($z) => $z !== '');
    $kundeAdresse = implode('<br>', array_map('htmlspecialchars', $kundeAdresseZeilen));

    $kopfZellen = '<th>Pos</th><th>Bezeichnung</th><th>Menge</th>'
        . ($zeigePreise ? '<th>Einzelpreis</th><th>Rabatt</th><th>USt%</th><th>Netto</th><th>Brutto</th>' : '');
    $tabelle = '<table class="positionen-tabelle"><thead><tr>' . $kopfZellen . '</tr></thead><tbody>';
    foreach ($positionen as $pos) {
        $bezeichnungZelle = htmlspecialchars($pos['bezeichnung']);
        if (!empty($pos['beschreibung'])) {
            $bezeichnungZelle .= '<br><span class="positionen-beschreibung">' . nl2br(htmlspecialchars($pos['beschreibung'])) . '</span>';
        }

        $tabelle .= '<tr>'
            . '<td>' . htmlspecialchars($pos['position']) . '</td>'
            . '<td>' . $bezeichnungZelle . '</td>'
            . '<td>' . number_format($pos['menge'], 2, ',', '.') . ' ' . htmlspecialchars($pos['einheit']) . '</td>';
        if ($zeigePreise) {
            $rabattProzent = floatval($pos['rabatt_prozent'] ?? 0);
            $tabelle .= '<td>' . formatBetrag($pos['einzelpreis_netto']) . '</td>'
                . '<td>' . ($rabattProzent > 0 ? number_format($rabattProzent, 0) . '%' : '-') . '</td>'
                . '<td>' . number_format($pos['ust_prozent'] ?? 0, 0) . '%</td>'
                . '<td>' . formatBetrag($pos['netto_summe']) . '</td>'
                . '<td>' . formatBetrag($pos['brutto_summe']) . '</td>';
        }
        $tabelle .= '</tr>';
    }
    $tabelle .= '</tbody></table>';

    $gesamtrabattProzent = floatval($doc['gesamtrabatt_prozent'] ?? 0);
    $zwischensummeNetto = array_reduce($positionen, fn($summe, $p) => $summe + $p['netto_summe'], 0);
    $rabattZeile = '';
    if ($gesamtrabattProzent > 0) {
        $rabattBetrag = $zwischensummeNetto - $zwischensummeNetto * (1 - $gesamtrabattProzent / 100);
        $rabattZeile = '<div>Zwischensumme (Netto): ' . formatBetrag($zwischensummeNetto) . '</div>'
            . '<div>Gesamtrabatt (' . number_format($gesamtrabattProzent, 0) . '%): -' . formatBetrag($rabattBetrag) . '</div>';
    }

    if (!empty($firma['kleinunternehmer'])) {
        $summenblock = '<div class="summenblock">'
            . '<p><em>Kleinunternehmer gemäß § 6 Abs. 1 Z 27 UStG - es wird keine Umsatzsteuer ausgewiesen.</em></p>'
            . $rabattZeile
            . '<div class="brutto">Gesamtbetrag: ' . formatBetrag($doc['netto_gesamt']) . '</div>'
            . '</div>';
    } else {
        $summenblock = '<div class="summenblock">'
            . $rabattZeile
            . '<div>Netto gesamt: ' . formatBetrag($doc['netto_gesamt']) . '</div>'
            . '<div>USt gesamt: ' . formatBetrag($doc['ust_gesamt']) . '</div>'
            . '<div class="brutto">Bruttobetrag: ' . formatBetrag($doc['brutto_gesamt']) . '</div>'
            . '</div>';
    }

    $zahlungshinweis = '';
    if ($doc['typ'] === 'rechnung' && !empty($firma['iban'])) {
        $zahlungshinweis = 'Bitte überweisen Sie den Betrag bis '
            . ($doc['faellig_am'] ? formatDatum($doc['faellig_am']) : 'zum genannten Fälligkeitsdatum')
            . ' auf IBAN ' . htmlspecialchars($firma['iban'])
            . (!empty($firma['bic']) ? ' (BIC ' . htmlspecialchars($firma['bic']) . ')' : '') . '.';
    }

    $wasserzeichen = ($doc['status'] === 'entwurf') ? '<div class="wasserzeichen">ENTWURF</div>' : '';

    $firmaLogo = '';
    if (!empty($firma['logo_data']) && !empty($firma['logo_mime'])) {
        $firmaLogo = '<img src="data:' . htmlspecialchars($firma['logo_mime']) . ';base64,' . $firma['logo_data'] . '" class="firma-logo" alt="Logo">';
    }

    return [
        '{{firma_logo}}' => $firmaLogo,
        '{{firma_name}}' => htmlspecialchars($firma['name'] ?? ''),
        '{{firma_adresse}}' => htmlspecialchars($firmaAdresse),
        '{{firma_uid}}' => htmlspecialchars($firma['uid_nummer'] ?? ''),
        '{{firma_iban}}' => htmlspecialchars($firma['iban'] ?? ''),
        '{{firma_bic}}' => htmlspecialchars($firma['bic'] ?? ''),
        '{{firma_bank}}' => htmlspecialchars($firma['bank'] ?? ''),
        '{{dokument_typ_label}}' => $typLabel,
        '{{nummer}}' => htmlspecialchars($doc['nummer'] ?: '(Entwurf)'),
        '{{status}}' => htmlspecialchars($doc['status']),
        '{{datum}}' => formatDatum($doc['datum'] ?? null),
        '{{leistungsdatum}}' => formatDatum($doc['leistungsdatum'] ?? null),
        '{{gueltig_bis}}' => formatDatum($doc['gueltig_bis'] ?? null),
        '{{faellig_am}}' => formatDatum($doc['faellig_am'] ?? null),
        '{{kunde_name}}' => htmlspecialchars($kundeName),
        '{{kunde_adresse}}' => $kundeAdresse,
        '{{kunde_uid}}' => htmlspecialchars($doc['uid_nummer'] ?? ''),
        '{{betreff}}' => htmlspecialchars($doc['betreff'] ?? ''),
        '{{einleitungstext}}' => nl2br(htmlspecialchars($doc['einleitungstext'] ?? '')),
        '{{schlusstext}}' => nl2br(htmlspecialchars($doc['schlusstext'] ?? '')),
        '{{positionen_tabelle}}' => $tabelle,
        '{{netto_gesamt}}' => formatBetrag($doc['netto_gesamt']),
        '{{ust_gesamt}}' => formatBetrag($doc['ust_gesamt']),
        '{{brutto_gesamt}}' => formatBetrag($doc['brutto_gesamt']),
        '{{gesamtrabatt_prozent}}' => $gesamtrabattProzent > 0 ? number_format($gesamtrabattProzent, 0) . '%' : '',
        '{{summenblock}}' => $summenblock,
        '{{zahlungshinweis}}' => $zahlungshinweis,
        '{{wasserzeichen}}' => $wasserzeichen,
    ];
}

/**
 * Fertiges HTML für ein bestehendes Verkaufsdokument (Vorlage + echte Daten).
 */
function renderVerkaufsdokumentHtml($verkaufsdokumentId) {
    $doc = getVerkaufsdokument($verkaufsdokumentId);
    if (!$doc) {
        throw new Exception('Verkaufsdokument nicht gefunden.');
    }
    $positionen = getVerkaufsdokumentPositionen($verkaufsdokumentId);
    $firma = getFirmendaten();
    $vorlage = getPdfVorlage($doc['typ']);

    return strtr($vorlage, baueDokumentPlatzhalter($doc, $positionen, $firma));
}

/**
 * Vorschau eines (noch ungespeicherten) Vorlagentexts mit Beispieldaten:
 * neuestes existierendes Dokument des Typs, sonst eine feste Dummy-Zeile.
 */
function renderVorlageVorschau($typ, $vorlageInhalt) {
    $db = db();
    $stmt = $db->prepare("SELECT id FROM verkaufsdokumente WHERE typ = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$typ]);
    $beispielId = $stmt->fetchColumn();

    $firma = getFirmendaten();

    if ($beispielId) {
        $doc = getVerkaufsdokument($beispielId);
        $positionen = getVerkaufsdokumentPositionen($beispielId);
    } else {
        $doc = [
            'typ' => $typ, 'status' => 'entwurf', 'nummer' => null,
            'datum' => date('Y-m-d'), 'leistungsdatum' => date('Y-m-d'),
            'gueltig_bis' => date('Y-m-d', strtotime('+30 days')), 'faellig_am' => date('Y-m-d', strtotime('+14 days')),
            'betreff' => 'Beispiel-Betreff', 'einleitungstext' => 'Dies ist ein Beispieltext für die Vorschau.',
            'schlusstext' => 'Vielen Dank für Ihr Vertrauen.',
            'netto_gesamt' => 250.00, 'ust_gesamt' => 50.00, 'brutto_gesamt' => 300.00,
            'firma_name' => null, 'vorname' => null, 'nachname' => null, 'strasse' => 'Musterweg 1',
            'plz' => '1010', 'ort' => 'Wien', 'land' => 'Österreich', 'uid_nummer' => 'ATU00000000'
        ];
        $doc['firma_name'] = 'Muster Kunde GmbH';
        $positionen = [
            ['position' => 1, 'bezeichnung' => 'Beratung', 'menge' => 2, 'einheit' => 'Std', 'einzelpreis_netto' => 100, 'ust_prozent' => 20, 'netto_summe' => 200, 'ust_summe' => 40, 'brutto_summe' => 240],
            ['position' => 2, 'bezeichnung' => 'Fachbuch', 'menge' => 1, 'einheit' => 'Stk', 'einzelpreis_netto' => 50, 'ust_prozent' => 20, 'netto_summe' => 50, 'ust_summe' => 10, 'brutto_summe' => 60],
        ];
    }

    return strtr($vorlageInhalt, baueDokumentPlatzhalter($doc, $positionen, $firma));
}

/**
 * HTML per Dompdf zu PDF-Bytes rendern. isRemoteEnabled bleibt bewusst false
 * (SSRF-Schutz, da Vorlagen vom Nutzer editierbar sind) - Bilder müssen als
 * Base64-Data-URI eingebettet werden.
 */
function renderHtmlZuPdfBytes($html) {
    $options = new \Dompdf\Options();
    $options->setIsRemoteEnabled(false);
    $options->setIsHtml5ParserEnabled(true);
    $options->setDefaultFont('DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

/**
 * PDF-Bytes für ein bestehendes Verkaufsdokument (Angebot/Auftrag/Rechnung).
 */
function buildVerkaufsdokumentPdf($verkaufsdokumentId) {
    return renderHtmlZuPdfBytes(renderVerkaufsdokumentHtml($verkaufsdokumentId));
}

/**
 * Lieferschein zu einer bereits finalisierten Rechnung: kein eigenes Verkaufsdokument/
 * keine eigene Nummer - referenziert die Rechnungsnummer, Positionstabelle ohne Preise.
 */
function renderLieferscheinHtml($rechnungId) {
    $doc = getVerkaufsdokument($rechnungId);
    if (!$doc || $doc['typ'] !== 'rechnung') {
        throw new Exception('Lieferschein ist nur zu einer Rechnung möglich.');
    }
    if ($doc['status'] === 'entwurf') {
        throw new Exception('Rechnung muss zuerst finalisiert werden.');
    }

    $positionen = getVerkaufsdokumentPositionen($rechnungId);
    $firma = getFirmendaten();

    $lieferscheinDoc = $doc;
    $lieferscheinDoc['typ'] = 'lieferschein';

    $vorlage = getPdfVorlage('lieferschein');
    return strtr($vorlage, baueDokumentPlatzhalter($lieferscheinDoc, $positionen, $firma));
}

function buildLieferscheinPdf($rechnungId) {
    return renderHtmlZuPdfBytes(renderLieferscheinHtml($rechnungId));
}

/**
 * Dateiname (ohne Pfad) für ein Verkaufsdokument-PDF.
 */
function verkaufsdokumentDateiname($doc) {
    $basis = $doc['nummer'] ?: ($doc['typ'] . '_entwurf_' . $doc['id']);
    $basis = preg_replace('/[^A-Za-z0-9_-]/', '_', $basis);
    return ucfirst($doc['typ']) . '_' . $basis . '.pdf';
}
