-- EKassa360: Migration für bestehende Rechnungen ohne "Bezahlt am"
-- Hintergrund: U30 und E1a werden ab sofort nach Zahlungsdatum (bezahlt_am)
-- statt nach Rechnungsdatum (datum) berechnet (Ist-Besteuerung, § 4 Abs. 3 EStG).
-- Rechnungen mit bezahlt = 1 aber bezahlt_am = NULL würden dadurch aus
-- U30/E1a verschwinden. Dieses Skript füllt bezahlt_am mit dem
-- Rechnungsdatum auf, damit keine Umsätze verloren gehen.
--
-- Bitte VOR dem Ausführen prüfen (SELECT), ob das Rechnungsdatum als
-- Zahlungsdatum-Näherung angemessen ist, oder die betroffenen Rechnungen
-- manuell mit dem korrekten Zahlungsdatum nachpflegen.

-- 1. Betroffene Datensätze ansehen:
SELECT id, rechnungsnummer, typ, datum, bezahlt, bezahlt_am
FROM rechnungen
WHERE bezahlt = 1 AND bezahlt_am IS NULL;

-- 2. Migration ausführen (bezahlt_am = Rechnungsdatum als Näherung):
-- UPDATE rechnungen
-- SET bezahlt_am = datum
-- WHERE bezahlt = 1 AND bezahlt_am IS NULL;
