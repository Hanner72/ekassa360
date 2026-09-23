-- EKassa360: Nummernkreise auf frei definierbares Format umstellen
-- statt starrem prefix + fixem "JAHR-NNNN"-Muster.
--
-- Platzhalter im Format-String (siehe formatiereNummernkreisNummer() in
-- includes/verkauf_functions.php): {JJJJ} 4-stelliges Jahr, {JJ} 2-stelliges Jahr,
-- {MM} Monat, {TT} Tag (jeweils vom Belegdatum), {N}/{NN}/{NNN}/... laufende Nummer
-- (Stellenanzahl = Anzahl der N, mit führenden Nullen aufgefüllt).

ALTER TABLE `nummernkreise`
  ADD COLUMN `format` VARCHAR(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' AFTER `prefix`;

UPDATE `nummernkreise` SET `format` = CONCAT(`prefix`, '{JJJJ}-{NNNN}');

ALTER TABLE `nummernkreise`
  DROP COLUMN `prefix`;
