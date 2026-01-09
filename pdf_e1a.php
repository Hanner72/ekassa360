<?php
/**
 * PDF-Export für Einkommensteuer (E1a)
 * EKassa360
 * 
 * Benötigt FPDF: https://github.com/Setasign/FPDF
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// FPDF einbinden
if (file_exists('lib/fpdf/fpdf.php')) {
    require_once 'lib/fpdf/fpdf.php';
} elseif (file_exists('vendor/setasign/fpdf/fpdf.php')) {
    require_once 'vendor/setasign/fpdf/fpdf.php';
} else {
    die('FPDF nicht gefunden! Bitte installieren: composer require setasign/fpdf');
}

// Parameter
$jahr = $_GET['jahr'] ?? date('Y') - 1;

// Daten laden
$gespeichert = getEinkommensteuerJahr($jahr);
if (!$gespeichert) {
    $e1a = berechneEinkommensteuer($jahr);
} else {
    $e1a = $gespeichert;
}
$firma = getFirmendaten();
$afaBuchungen = getAfaBuchungenJahr($jahr);

// Custom PDF Klasse
class E1a_PDF extends FPDF {
    protected $firma;
    protected $jahr;
    
    function setFirma($firma) {
        $this->firma = $firma;
    }
    
    function setJahr($jahr) {
        $this->jahr = $jahr;
    }
    
    function Header() {
        // Titel
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(139, 0, 0);
        $this->Cell(0, 10, 'Einkommensteuererklaerung (E1a)', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Beilage zur Einkommensteuererklaerung - Einkuenfte aus selbstaendiger Arbeit', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, 'Veranlagungsjahr ' . $this->jahr, 0, 1, 'C');
        
        $this->Ln(3);
        $this->SetDrawColor(139, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-20);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Erstellt mit EKassa360 am ' . date('d.m.Y H:i'), 0, 1, 'L');
        $this->Cell(0, 5, 'Seite ' . $this->PageNo(), 0, 0, 'R');
    }
    
    function SectionHeader($title, $color = 'default') {
        $this->SetFont('Arial', 'B', 11);
        
        switch ($color) {
            case 'green':
                $this->SetFillColor(40, 167, 69);
                break;
            case 'red':
                $this->SetFillColor(220, 53, 69);
                break;
            default:
                $this->SetFillColor(139, 0, 0);
        }
        
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, ' ' . $title, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    function KennzahlRow($kz, $beschreibung, $wert, $type = 'normal') {
        switch ($type) {
            case 'einnahme':
                $this->SetFillColor(232, 245, 233);
                break;
            case 'ausgabe':
                $this->SetFillColor(255, 235, 238);
                break;
            case 'summe':
                $this->SetFillColor(230, 230, 230);
                break;
            default:
                $this->SetFillColor(255, 255, 255);
        }
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 7, 'KZ ' . $kz, 1, 0, 'C', true);
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(125, 7, ' ' . $beschreibung, 1, 0, 'L', true);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(45, 7, number_format($wert, 2, ',', '.') . ' EUR', 1, 1, 'R', true);
    }
    
    function SummenRow($beschreibung, $wert, $type = 'normal') {
        switch ($type) {
            case 'einnahme':
                $this->SetFillColor(40, 167, 69);
                break;
            case 'ausgabe':
                $this->SetFillColor(220, 53, 69);
                break;
            case 'gewinn':
                $this->SetFillColor(40, 167, 69);
                break;
            case 'verlust':
                $this->SetFillColor(220, 53, 69);
                break;
            default:
                $this->SetFillColor(100, 100, 100);
        }
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 10);
        
        $this->Cell(145, 8, ' ' . $beschreibung, 1, 0, 'L', true);
        $this->Cell(45, 8, number_format($wert, 2, ',', '.') . ' EUR', 1, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
    }
}

// PDF erstellen
$pdf = new E1a_PDF();
$pdf->setFirma($firma);
$pdf->setJahr($jahr);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 25);

// Firmendaten Box
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, 'Steuerpflichtiger:', 0, 0, 'L');
$pdf->Cell(95, 6, 'Finanzamt:', 0, 1, 'L');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 5, $firma['name'] ?? '', 0, 0, 'L');
$pdf->Cell(95, 5, $firma['finanzamt'] ?? '', 0, 1, 'L');

$pdf->Cell(95, 5, ($firma['strasse'] ?? ''), 0, 0, 'L');
$pdf->Cell(95, 5, 'Steuernummer: ' . ($firma['steuernummer'] ?? ''), 0, 1, 'L');

$pdf->Cell(95, 5, ($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? ''), 0, 1, 'L');

$pdf->Ln(8);

// Betriebseinnahmen
$pdf->SectionHeader('Betriebseinnahmen', 'green');
$pdf->Ln(2);

$pdf->KennzahlRow('9040', 'Erloese aus Lieferungen und Leistungen (Waren, Erzeugnisse)', $e1a['kz9040'], 'einnahme');
$pdf->KennzahlRow('9050', 'Erloese aus Dienstleistungen', $e1a['kz9050'], 'einnahme');

$summeEinnahmen = $e1a['kz9040'] + $e1a['kz9050'];
$pdf->Ln(2);
$pdf->SummenRow('Summe Betriebseinnahmen', $summeEinnahmen, 'einnahme');

$pdf->Ln(8);

// Betriebsausgaben
$pdf->SectionHeader('Betriebsausgaben', 'red');
$pdf->Ln(2);

$pdf->KennzahlRow('9100', 'Wareneinkauf, Rohstoffe, Hilfsstoffe', $e1a['kz9100'], 'ausgabe');
$pdf->KennzahlRow('9110', 'Fremdleistungen (Fremdpersonal, Subunternehmer)', $e1a['kz9110'], 'ausgabe');
$pdf->KennzahlRow('9120', 'Personalaufwand (eigenes Personal)', $e1a['kz9120'], 'ausgabe');

// AfA-Abschnitt
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, '  Abschreibungen auf das Anlagevermoegen:', 0, 1, 'L');
$pdf->KennzahlRow('9130', '  AfA normal (linear, GWG)', $e1a['kz9130'], 'ausgabe');
$pdf->KennzahlRow('9134', '  AfA degressiv (§ 7 Abs. 1a)', $e1a['kz9134'] ?? 0, 'ausgabe');
$pdf->KennzahlRow('9135', '  AfA Gebaeude beschleunigt (§ 8 Abs. 1a)', $e1a['kz9135'] ?? 0, 'ausgabe');

$pdf->KennzahlRow('9140', 'Betriebsraeumlichkeiten (Miete, BK)', $e1a['kz9140'], 'ausgabe');
$pdf->KennzahlRow('9150', 'Sonstige Betriebsausgaben', $e1a['kz9150'], 'ausgabe');

$summeAusgaben = $e1a['kz9100'] + $e1a['kz9110'] + $e1a['kz9120'] + $e1a['kz9130'] + ($e1a['kz9134'] ?? 0) + ($e1a['kz9135'] ?? 0) + $e1a['kz9140'] + $e1a['kz9150'];
$pdf->Ln(2);
$pdf->SummenRow('Summe Betriebsausgaben', $summeAusgaben, 'ausgabe');

$pdf->Ln(10);

// Ergebnis
$gewinnVerlust = $e1a['gewinn_verlust'];
$isGewinn = $gewinnVerlust >= 0;

$pdf->SectionHeader($isGewinn ? 'GEWINN aus selbstaendiger Arbeit' : 'VERLUST aus selbstaendiger Arbeit', $isGewinn ? 'green' : 'red');
$pdf->Ln(2);

// Berechnung anzeigen
$pdf->SetFont('Arial', '', 9);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 7, ' Berechnung: Betriebseinnahmen - Betriebsausgaben', 0, 1, 'L', true);
$pdf->Cell(0, 7, ' ' . number_format($summeEinnahmen, 2, ',', '.') . ' EUR - ' . number_format($summeAusgaben, 2, ',', '.') . ' EUR = ' . number_format($gewinnVerlust, 2, ',', '.') . ' EUR', 0, 1, 'L', true);

$pdf->Ln(3);
$pdf->SummenRow($isGewinn ? 'GEWINN' : 'VERLUST', abs($gewinnVerlust), $isGewinn ? 'gewinn' : 'verlust');

// AfA Details auf neuer Seite wenn vorhanden
if (!empty($afaBuchungen)) {
    $pdf->AddPage();
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Anlage: AfA-Verzeichnis ' . $jahr, 0, 1, 'L');
    $pdf->Ln(3);
    
    // Nach E1a-Kennzahl gruppieren
    $kzLabels = [
        '9130' => 'KZ 9130 - Normale AfA (linear, GWG)',
        '9134' => 'KZ 9134 - Degressive AfA',
        '9135' => 'KZ 9135 - Gebaeude beschleunigt'
    ];
    
    $afaGrouped = [];
    foreach ($afaBuchungen as $afa) {
        $kz = $afa['e1a_kennzahl'] ?? '9130';
        if (!isset($afaGrouped[$kz])) $afaGrouped[$kz] = [];
        $afaGrouped[$kz][] = $afa;
    }
    
    $gesamtAfa = 0;
    
    foreach ($afaGrouped as $kz => $items) {
        // Überschrift für Kennzahl
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(0, 6, ' ' . ($kzLabels[$kz] ?? 'KZ ' . $kz), 1, 1, 'L', true);
        
        // Tabellenkopf
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetFillColor(139, 0, 0);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(50, 6, ' Anlagegut', 1, 0, 'L', true);
        $pdf->Cell(20, 6, 'Ansch.', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Wert', 1, 0, 'R', true);
        $pdf->Cell(12, 6, 'ND', 1, 0, 'C', true);
        $pdf->Cell(15, 6, 'Meth.', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'AfA ' . $jahr, 1, 0, 'R', true);
        $pdf->Cell(30, 6, 'Restwert', 1, 1, 'R', true);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Arial', '', 7);
        
        $summeKz = 0;
        $fill = false;
        foreach ($items as $afa) {
            $fill = !$fill;
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
            
            $pdf->Cell(50, 5, ' ' . substr($afa['bezeichnung'], 0, 28), 1, 0, 'L', true);
            $pdf->Cell(20, 5, date('d.m.y', strtotime($afa['anschaffungsdatum'])), 1, 0, 'C', true);
            $pdf->Cell(25, 5, number_format($afa['anschaffungswert'], 2, ',', '.'), 1, 0, 'R', true);
            $pdf->Cell(12, 5, $afa['nutzungsdauer'] . 'J', 1, 0, 'C', true);
            $pdf->Cell(15, 5, $afa['afa_methode'] == 'degressiv' ? 'degr.' : 'lin.', 1, 0, 'C', true);
            $pdf->Cell(30, 5, number_format($afa['afa_betrag'], 2, ',', '.'), 1, 0, 'R', true);
            $pdf->Cell(30, 5, number_format($afa['restwert_nach'], 2, ',', '.'), 1, 1, 'R', true);
            
            $summeKz += $afa['afa_betrag'];
        }
        
        // Zwischensumme pro Kennzahl
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(122, 5, ' Summe KZ ' . $kz, 1, 0, 'L', true);
        $pdf->Cell(30, 5, number_format($summeKz, 2, ',', '.'), 1, 0, 'R', true);
        $pdf->Cell(30, 5, '', 1, 1, 'R', true);
        
        $gesamtAfa += $summeKz;
        $pdf->Ln(3);
    }
    
    // Gesamtsumme
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(139, 0, 0);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(122, 7, ' GESAMT AfA ' . $jahr, 1, 0, 'L', true);
    $pdf->Cell(30, 7, number_format($gesamtAfa, 2, ',', '.') . ' EUR', 1, 0, 'R', true);
    $pdf->Cell(30, 7, '', 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
}

// Status
if ($gespeichert && $gespeichert['eingereicht']) {
    $pdf->Ln(15);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(40, 167, 69);
    $pdf->Cell(0, 8, 'STATUS: GESENDET AN FINANZAMT', 0, 1, 'C');
    if ($gespeichert['eingereicht_am']) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Uebermittelt am: ' . date('d.m.Y', strtotime($gespeichert['eingereicht_am'])), 0, 1, 'C');
    }
}

// Notizen
if ($gespeichert && !empty($gespeichert['notizen'])) {
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'Notizen:', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, $gespeichert['notizen']);
}

// PDF ausgeben
$filename = 'E1a_' . $jahr . '.pdf';
$pdf->Output('I', $filename);
