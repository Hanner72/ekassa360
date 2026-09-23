-- EKassa360: Lieferschein als vierter PDF-Vorlagentyp (kein eigenes Verkaufsdokument/
-- keine eigene Nummer - wird aus einer bereits finalisierten Rechnung generiert und
-- referenziert deren Rechnungsnummer).

ALTER TABLE `pdf_vorlagen`
  MODIFY COLUMN `typ` ENUM('angebot','auftrag','rechnung','lieferschein') COLLATE utf8mb4_general_ci NOT NULL;
