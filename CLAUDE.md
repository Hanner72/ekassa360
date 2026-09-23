# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

EKassa360 ist eine PHP-Webanwendung für österreichische Einnahmen-Ausgaben-Rechnung (E/A) mit automatischer USt-Voranmeldung (U30) und Einkommensteuererklärung (E1a). Kein Framework, kein Build-System.

## Local Development

Läuft auf **Laragon** (Windows). Kein Build-Schritt erforderlich — PHP-Dateien direkt bearbeiten, im Browser neu laden.

- **URL:** `http://localhost/ekassa360/`
- **DB:** MySQL auf `localhost`, Datenbank `ekassa360`, User `root`, kein Passwort ([config/database.php](config/database.php))
- **DB-Schema:** [database/ekassa360.sql](database/ekassa360.sql) importieren für Neuinstallation
- **Standard-Login:** `admin` / `admin123`
- **Composer:** `composer install` (nur für PHPSpreadsheet/Excel-Import nötig; FPDF liegt direkt in `lib/fpdf/`)

Keine Tests, kein Linter konfiguriert.

## Architecture

### Request Flow

Jede Seite ist eine eigenständige PHP-Datei (kein Router). Jede Seite beginnt mit:
```php
session_start();
require_once 'config/database.php';   // PDO-Singleton, db()-Hilfsfunktion
require_once 'includes/functions.php'; // Business-Logik
require_once 'includes/auth.php';      // Session/Benutzer
requireLogin();                         // oder requireAdmin()
```

POST-Verarbeitung, Datenladen und HTML-Ausgabe passieren alle in derselben Datei.

### Key Files

- **`includes/functions.php`** — Gesamte Business-Logik: Rechnungen CRUD, U30-Berechnung, E1a-Berechnung, AfA-Berechnung, alle DB-Abfragen
- **`includes/auth.php`** — Login/Logout, Passwort-Hashing, `logAction()` (Audit-Log), Benutzerverwaltung
- **`config/database.php`** — PDO-Singleton. Überall via globale Funktion `db()` aufgerufen

### Database Pattern

Alle Seiten greifen via `db()` auf eine PDO-Instanz zu. Kein ORM. Prepared Statements durchgehend.

```php
$stmt = db()->prepare("SELECT ... WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(); // PDO::FETCH_ASSOC ist der Default
```

### U30 Calculation — Wichtige Besonderheit

`berechneUstVoranmeldung()` in `functions.php` gruppiert Einnahmen nach `ust_saetze.u30_kennzahl_bemessung`. **Nur die Werte `'022'`, `'029'`, `'006'`, `'021'` werden explizit behandelt.** Einnahmen mit anderen oder NULL-Kennzahlen fließen in KZ000 (Gesamtbemessungsgrundlage) aber deren USt wird **nicht** deklariert — das ist eine bekannte Fehlerquelle.

**Periodenzuordnung nach Zahlungsdatum (Ist-Besteuerung):** Sowohl `berechneUstVoranmeldung()` als auch `berechneEinkommensteuer()` filtern `rechnungen` nach `bezahlt = 1 AND bezahlt_am` (nicht nach `datum`, dem Rechnungsdatum). Eine Rechnung fließt also erst in dem Monat/Jahr in U30 bzw. E1a ein, in dem sie tatsächlich bezahlt wurde. Unbezahlte Rechnungen (`bezahlt = 0` oder `bezahlt_am IS NULL`) werden komplett ausgeschlossen, bis sie als bezahlt markiert sind. Das Formular in `rechnungen.php` erzwingt `bezahlt_am`, sobald `bezahlt` angehakt wird. Anlagegüter/AfA sind davon nicht betroffen (dort zählt weiterhin `anschaffungsdatum` bzw. `afa_buchungen.jahr`).

**Interne DB-Felder weichen von den U30-Formular-KZ ab** (historisch gewachsen):

| Offizielles U30-Formular | Internes DB-Feld |
|---|---|
| KZ022 Bemessung 20% | `kz022` |
| KZ029 Steuer 20% | `kz029` (intern) |
| KZ029 Bemessung 10% | `kz025` |
| KZ027 Steuer 10% | `kz027` |
| KZ006 Bemessung 13% | `kz035` |
| KZ052 Steuer 13% | `kz052` |

### U30 Draft-Problem (bekannter Bug)

Wenn eine U30 als Entwurf gespeichert wurde, zeigt der Code immer die **gespeicherten Werte**, auch wenn danach Buchungen geändert wurden:

```php
} elseif ($gespeichert) {
    $u30 = $gespeichert; // ← stale draft, kein Neuberechnen!
} else {
    $u30 = berechneUstVoranmeldung(...); // nur ohne Entwurf
}
```

Entwurf löschen → dann wird neu berechnet.

### AfA-Logik

`berechneAfaBuchungen()` löscht und regeneriert alle `afa_buchungen`-Einträge bei jedem Speichern eines Anlageguts. Halbjahresregel: Anschaffungsmonat > 6 → halbe Jahres-AfA im ersten Jahr. Restwert fällt nie unter `anlagegueter.restwert` (Standard: 1 €).

### Flash Messages & Filter-Persistenz

- Flash Messages über `$_SESSION['flash']` (gesetzt mit `setFlashMessage()`, gelesen+gelöscht mit `getFlashMessage()`)
- Rechnungsfilter werden in `$_SESSION['rechnungen_filter']` gespeichert und seitenübergreifend beibehalten

### EU-Buchungsarten

`rechnungen.buchungsart` steuert die U30-Zuordnung:

| Wert | Bedeutung | U30-Auswirkung |
|---|---|---|
| `inland` | Standard AT | KZ060 Vorsteuer |
| `eu_ige` | ig. Erwerb (Reverse Charge, B2B EU-Einkauf) | KZ070/072/065 (Nullsumme) |
| `eu_b2c` | EU-Kauf mit ausländ. USt (kein Vorsteuerabzug) | Ausland-USt = Aufwand |
| `drittland` | Drittland-Import, Einfuhr-USt abzugsfähig | KZ061 |

### Buchungsnummern

Jahresbezogen, automatisch inkrementiert über `getNextBuchungsnummer()`. Gilt für `rechnungen` und `anlagegueter` getrennt.

## Austrian Tax Law Context

- **E/A-Rechnung** (§ 4 Abs. 3 EStG): Einnahmen und Ausgaben werden zum Zahlungszeitpunkt erfasst (Ist-Besteuerung). Gewinn = Einnahmen (Netto) − Ausgaben (Netto) − AfA.
- **U30** Pflicht: monatlich bei Jahresumsatz > 100.000 €, quartalsweise darunter (einstellbar unter Firma-Settings).
- **igE** (innergemeinschaftlicher Erwerb): AT-Unternehmen kauft Waren von EU-Lieferant mit UID → Erwerbsteuer 20% wird geschuldet (KZ072), gleichzeitig als Vorsteuer abzugsfähig (KZ065) → Netto-Effekt 0.
- **AfA Halbjahresregel**: Anschaffung in 2. Jahreshälfte (Monat > 6) → nur 50% der Jahres-AfA im Anschaffungsjahr.
- **KZ000** ist immer **Netto** (Bemessungsgrundlage), nicht Brutto.

## Pages & Their Purpose

| Datei | Funktion |
|---|---|
| `index.php` | Dashboard, Jahresstatistik |
| `rechnungen.php` | Einnahmen/Ausgaben CRUD + Filter |
| `ust_voranmeldung.php` | U30-Formular (berechnen, speichern, PDF) |
| `einkommensteuer.php` | E1a-Formular (berechnen, speichern, PDF) |
| `anlagegueter.php` | Anlagegüter verwalten |
| `abschreibungen.php` | AfA-Übersicht |
| `kategorien.php` | Kategorien mit E1a-Kennzahl-Zuordnung |
| `einstellungen.php` | Firmendaten, Wartung (Testdaten/Reset) |
| `import.php` | Excel-Import (PHPSpreadsheet) |
| `pdf_u30.php` / `pdf_e1a.php` | PDF-Generierung via FPDF |
| `benutzerverwaltung.php` | Nur Admin: Benutzerverwaltung |
| `protokoll.php` | Nur Admin: Änderungsprotokoll |
