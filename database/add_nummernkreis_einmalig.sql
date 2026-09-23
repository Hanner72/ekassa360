-- EKassa360: Nummernkreise werden nur noch einmal pro Dokumenttyp konfiguriert (nicht mehr
-- pro Jahr). Ob und wann der Zähler zurückgesetzt wird, entscheidet ab jetzt allein das
-- eingestellte Format: enthält es einen Jahres-Platzhalter ({JJJJ}/{JJ}), setzt
-- zieheNummernkreisNummer() (includes/verkauf_functions.php) den Zähler beim ersten Beleg
-- eines neuen Jahres automatisch auf 1 zurück - ansonsten läuft er unbegrenzt weiter. Die
-- bisherige automatische Neuanlage mit fixem Standardformat bei jedem Jahreswechsel entfällt.
--
-- Bestehende Zeilen pro schluessel werden auf eine einzige zusammengeführt (die mit dem
-- höchsten jahr, bei Gleichstand die mit der höchsten id - ältere Jahres-Zeilen sind durch
-- den Umstieg gegenstandslos, ihr Zähler wird nicht mehr gebraucht).
SET NAMES utf8mb4;

DELETE nk1 FROM nummernkreise nk1
INNER JOIN nummernkreise nk2
  ON nk1.schluessel = nk2.schluessel
  AND (nk1.jahr < nk2.jahr OR (nk1.jahr = nk2.jahr AND nk1.id < nk2.id));

ALTER TABLE nummernkreise DROP INDEX unique_schluessel_jahr;
ALTER TABLE nummernkreise ADD UNIQUE KEY unique_schluessel (schluessel);
