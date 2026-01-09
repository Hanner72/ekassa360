<?php
/**
 * PDF-Export für USt-Voranmeldung (U30)
 * EKassa360
 * 
 * Benötigt FPDF: https://github.com/Setasign/FPDF
 * Download: composer require setasign/fpdf
 * Oder manuell: http://www.fpdf.org/
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// FPDF einbinden - passe den Pfad an!
if (file_exists('lib/fpdf/fpdf.php')) {
    require_once 'lib/fpdf/fpdf.php';
} elseif (file_exists('vendor/setasign/fpdf/fpdf.php')) {
    require_once 'vendor/setasign/fpdf/fpdf.php';
} else {
    die('FPDF nicht gefunden! Bitte installieren: composer require setasign/fpdf');
}

// Parameter
$jahr = $_GET['jahr'] ?? date('Y');
$monat = $_GET['monat'] ?? date('n');
$zeitraumTyp = $_GET['zeitraum'] ?? 'monat';

// Daten laden
$gespeichert = getUstVoranmeldung($jahr, $monat, $zeitraumTyp);
if (!$gespeichert) {
    $u30 = berechneUstVoranmeldung($jahr, $monat, $zeitraumTyp);
} else {
    $u30 = $gespeichert;
}
$firma = getFirmendaten();

// Monatsnamen
$monatsnamen = ['', 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 
                'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$quartalsnamen = ['', 'Q1 (Jänner - März)', 'Q2 (April - Juni)', 'Q3 (Juli - September)', 'Q4 (Oktober - Dezember)'];

$zeitraumText = $zeitraumTyp == 'quartal' 
    ? $quartalsnamen[$monat] . ' ' . $jahr
    : $monatsnamen[$monat] . ' ' . $jahr;

// Custom PDF Klasse
class U30_PDF extends FPDF {
    protected $firma;
    protected $zeitraum;
    
    function setFirma($firma) {
        $this->firma = $firma;
    }
    
    function setZeitraum($zeitraum) {
        $this->zeitraum = $zeitraum;
    }
    
    function Header() {
        // Logo/Titel
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(0, 10, 'USt-Voranmeldung (U30)', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'Republik Österreich - Bundesministerium für Finanzen', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, 'Zeitraum: ' . $this->zeitraum, 0, 1, 'C');
        
        $this->Ln(5);
        $this->SetDrawColor(0, 51, 102);
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
    
    function SectionHeader($title) {
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(0, 51, 102);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, ' ' . $title, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }
    
    function KennzahlRow($kz, $beschreibung, $wert, $highlight = false) {
        if ($highlight) {
            $this->SetFillColor(230, 240, 250);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(15, 7, 'KZ ' . $kz, 1, 0, 'C', true);
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(130, 7, ' ' . $beschreibung, 1, 0, 'L', true);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(45, 7, number_format($wert, 2, ',', '.') . ' EUR', 1, 1, 'R', true);
    }
    
    function ResultRow($beschreibung, $wert, $isPositive = true) {
        if ($isPositive) {
            $this->SetFillColor(220, 53, 69); // Rot für Zahllast
        } else {
            $this->SetFillColor(40, 167, 69); // Grün für Gutschrift
        }
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        
        $this->Cell(145, 10, ' ' . $beschreibung, 1, 0, 'L', true);
        $this->Cell(45, 10, number_format($wert, 2, ',', '.') . ' EUR', 1, 1, 'R', true);
        
        $this->SetTextColor(0, 0, 0);
    }
}

// PDF erstellen
$pdf = new U30_PDF();
$pdf->setFirma($firma);
$pdf->setZeitraum($zeitraumText);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 25);

// Firmendaten Box
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(95, 6, 'Steuerpflichtiger:', 0, 0, 'L');
$pdf->Cell(95, 6, 'Finanzamt:', 0, 1, 'L');

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 5, $firma['name'] ?? '', 0, 0, 'L');
$pdf->Cell(95, 5, $firma['finanzamt'] ?? '', 0, 1, 'L');

$pdf->Cell(95, 5, ($firma['strasse'] ?? ''), 0, 0, 'L');
$pdf->Cell(95, 5, 'Steuernummer: ' . ($firma['steuernummer'] ?? ''), 0, 1, 'L');

$pdf->Cell(95, 5, ($firma['plz'] ?? '') . ' ' . ($firma['ort'] ?? ''), 0, 0, 'L');
$pdf->Cell(95, 5, 'UID: ' . ($firma['uid_nummer'] ?? ''), 0, 1, 'L');

$pdf->Ln(8);

// Lieferungen und Leistungen
$pdf->SectionHeader('Lieferungen und sonstige Leistungen');
$pdf->Ln(2);

$pdf->KennzahlRow('000', 'Gesamtbetrag der Bemessungsgrundlage', $u30['kz000'], true);
$pdf->KennzahlRow('021', 'Innergemeinschaftliche Lieferungen (steuerfrei)', $u30['kz021'] ?? 0);

$pdf->Ln(5);

// Steuerberechnung
$pdf->SectionHeader('Berechnung der Umsatzsteuer');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(15, 6, 'KZ', 1, 0, 'C', true);
$pdf->Cell(85, 6, 'Steuersatz', 1, 0, 'C', true);
$pdf->Cell(45, 6, 'Bemessungsgrundlage', 1, 0, 'C', true);
$pdf->Cell(45, 6, 'Steuer', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

// 20%
$pdf->Cell(15, 7, '022/029', 1, 0, 'C');
$pdf->Cell(85, 7, ' 20% Normalsteuersatz', 1, 0, 'L');
$pdf->Cell(45, 7, number_format($u30['kz022'], 2, ',', '.') . ' EUR', 1, 0, 'R');
$pdf->Cell(45, 7, number_format($u30['kz029'], 2, ',', '.') . ' EUR', 1, 1, 'R');

// 10%
$pdf->Cell(15, 7, '025/027', 1, 0, 'C');
$pdf->Cell(85, 7, ' 10% ermaessigt', 1, 0, 'L');
$pdf->Cell(45, 7, number_format($u30['kz025'], 2, ',', '.') . ' EUR', 1, 0, 'R');
$pdf->Cell(45, 7, number_format($u30['kz027'], 2, ',', '.') . ' EUR', 1, 1, 'R');

// 13%
$pdf->Cell(15, 7, '035/052', 1, 0, 'C');
$pdf->Cell(85, 7, ' 13% ermaessigt', 1, 0, 'L');
$pdf->Cell(45, 7, number_format($u30['kz035'], 2, ',', '.') . ' EUR', 1, 0, 'R');
$pdf->Cell(45, 7, number_format($u30['kz052'], 2, ',', '.') . ' EUR', 1, 1, 'R');

// Summe USt
$ustGesamt = ($u30['kz029'] ?? 0) + ($u30['kz027'] ?? 0) + ($u30['kz052'] ?? 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 240, 250);
$pdf->Cell(100, 7, ' Summe Umsatzsteuer', 1, 0, 'L', true);
$pdf->Cell(45, 7, '', 1, 0, 'C', true);
$pdf->Cell(45, 7, number_format($ustGesamt, 2, ',', '.') . ' EUR', 1, 1, 'R', true);

$pdf->Ln(5);

// Vorsteuer
$pdf->SectionHeader('Vorsteuer');
$pdf->Ln(2);

$pdf->KennzahlRow('060', 'Gesamtbetrag der Vorsteuer', $u30['kz060'], true);
if (($u30['kz065'] ?? 0) > 0) {
    $pdf->KennzahlRow('065', 'Vorsteuer aus ig. Erwerb', $u30['kz065']);
}
if (($u30['kz066'] ?? 0) > 0) {
    $pdf->KennzahlRow('066', 'Einfuhrumsatzsteuer', $u30['kz066']);
}

$pdf->Ln(8);

// Ergebnis
$zahllast = $u30['zahllast'] ?? 0;
$isZahllast = $zahllast >= 0;

$pdf->SectionHeader($isZahllast ? 'Zahllast (KZ 095)' : 'Gutschrift (KZ 095)');
$pdf->Ln(2);

$pdf->ResultRow(
    $isZahllast ? 'An das Finanzamt zu entrichten' : 'Gutschrift vom Finanzamt',
    abs($zahllast),
    $isZahllast
);

// Berechnung anzeigen
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 5, 'Berechnung: USt (' . number_format($ustGesamt, 2, ',', '.') . ' EUR) - Vorsteuer (' . number_format($u30['kz060'], 2, ',', '.') . ' EUR) = ' . number_format($zahllast, 2, ',', '.') . ' EUR');

// Status
if ($gespeichert && $gespeichert['eingereicht']) {
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(40, 167, 69);
    $pdf->Cell(0, 8, 'STATUS: GESENDET', 0, 1, 'C');
    if ($gespeichert['eingereicht_am']) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, 'Gesendet am: ' . date('d.m.Y', strtotime($gespeichert['eingereicht_am'])), 0, 1, 'C');
    }
}

// PDF ausgeben
$filename = 'U30_' . $jahr . '_' . str_pad($monat, 2, '0', STR_PAD_LEFT) . '.pdf';
$pdf->Output('I', $filename);
