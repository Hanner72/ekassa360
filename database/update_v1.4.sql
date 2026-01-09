-- ============================================
-- EKassa360 Update v1.4
-- Buchungsnummer + USt für Anlagegüter
-- ============================================

-- Buchungsnummer für Rechnungen
ALTER TABLE rechnungen 
ADD COLUMN buchungsnummer INT DEFAULT NULL AFTER rechnungsnummer,
ADD INDEX idx_buchungsnummer (buchungsnummer, datum);

-- Buchungsnummer für Anlagegüter
ALTER TABLE anlagegueter 
ADD COLUMN buchungsnummer INT DEFAULT NULL AFTER id;

-- USt für Anlagegüter (für U30 Vorsteuer)
ALTER TABLE anlagegueter 
ADD COLUMN ust_satz_id INT DEFAULT NULL AFTER anschaffungswert,
ADD COLUMN ust_betrag DECIMAL(12,2) DEFAULT 0 AFTER ust_satz_id,
ADD COLUMN netto_betrag DECIMAL(12,2) DEFAULT NULL AFTER ust_betrag,
ADD FOREIGN KEY (ust_satz_id) REFERENCES ust_saetze(id) ON DELETE SET NULL;

-- Bestehende Anlagegüter: Netto = Anschaffungswert (ohne USt)
UPDATE anlagegueter SET netto_betrag = anschaffungswert WHERE netto_betrag IS NULL;

-- ============================================
-- Hinweis: Falls Fehler "Duplicate column" erscheint,
-- existiert die Spalte bereits - einfach ignorieren.
-- ============================================
