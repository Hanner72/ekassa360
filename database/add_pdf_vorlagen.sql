-- EKassa360: HTML/CSS-Vorlagen-Editor für Angebot-/Auftrag-/Rechnung-PDFs
-- Ersetzt die FPDF-basierte Erzeugung dieser drei Dokumenttypen durch Dompdf +
-- vom Nutzer editierbare HTML/CSS-Vorlagen mit {{platzhalter}}-Ersetzung.
-- pdf_u30.php/pdf_e1a.php bleiben unverändert auf FPDF.

SET NAMES utf8mb4;

CREATE TABLE `pdf_vorlagen` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `typ` ENUM('angebot','auftrag','rechnung') COLLATE utf8mb4_general_ci NOT NULL,
  `vorlage` LONGTEXT COLLATE utf8mb4_general_ci NOT NULL,
  `geaendert_von` INT DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_typ` (`typ`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pdf_vorlagen` (`typ`, `vorlage`) VALUES
('angebot', '<style>
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
  .summenblock { text-align: right; margin-top: 12px; }
  .summenblock .brutto { font-size: 12pt; font-weight: bold; }
  .fusszeile { margin-top: 24px; font-size: 8pt; color: #666; }
  .wasserzeichen { position: fixed; top: 250px; left: 60px; font-size: 80pt; color: #eee; transform: rotate(-25deg); z-index: -1; }
</style>
{{wasserzeichen}}
<div class="kopf">
  <div>
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
  <div>Datum: {{datum}}</div>
  <div>Gültig bis: {{gueltig_bis}}</div>
</div>
<div class="betreff">{{betreff}}</div>
<p>{{einleitungstext}}</p>
{{positionen_tabelle}}
{{summenblock}}
<p>{{schlusstext}}</p>
<div class="fusszeile">{{firma_name}} &middot; {{firma_adresse}} &middot; UID {{firma_uid}}</div>'),

('auftrag', '<style>
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
  .summenblock { text-align: right; margin-top: 12px; }
  .summenblock .brutto { font-size: 12pt; font-weight: bold; }
  .fusszeile { margin-top: 24px; font-size: 8pt; color: #666; }
  .wasserzeichen { position: fixed; top: 250px; left: 60px; font-size: 80pt; color: #eee; transform: rotate(-25deg); z-index: -1; }
</style>
{{wasserzeichen}}
<div class="kopf">
  <div>
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
  <div>Datum: {{datum}}</div>
  <div>Leistungsdatum: {{leistungsdatum}}</div>
</div>
<div class="betreff">{{betreff}}</div>
<p>{{einleitungstext}}</p>
{{positionen_tabelle}}
{{summenblock}}
<p>{{schlusstext}}</p>
<div class="fusszeile">{{firma_name}} &middot; {{firma_adresse}} &middot; UID {{firma_uid}}</div>'),

('rechnung', '<style>
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
  .summenblock { text-align: right; margin-top: 12px; }
  .summenblock .brutto { font-size: 12pt; font-weight: bold; }
  .zahlungshinweis { margin-top: 16px; font-size: 9pt; }
  .fusszeile { margin-top: 24px; font-size: 8pt; color: #666; }
  .wasserzeichen { position: fixed; top: 250px; left: 60px; font-size: 80pt; color: #eee; transform: rotate(-25deg); z-index: -1; }
</style>
{{wasserzeichen}}
<div class="kopf">
  <div>
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
  <div>Datum: {{datum}}</div>
  <div>Leistungsdatum: {{leistungsdatum}}</div>
  <div>Fällig am: {{faellig_am}}</div>
</div>
<div class="betreff">{{betreff}}</div>
<p>{{einleitungstext}}</p>
{{positionen_tabelle}}
{{summenblock}}
<p>{{schlusstext}}</p>
<div class="zahlungshinweis">{{zahlungshinweis}}</div>
<div class="fusszeile">{{firma_name}} &middot; {{firma_adresse}} &middot; UID {{firma_uid}}</div>');
