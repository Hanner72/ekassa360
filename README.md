# EKassa360 - Buchhaltung für österreichische Kleinunternehmer

**Version 0.1.7** | Für österreichische Einnahmen-Ausgaben-Rechnung mit USt-Voranmeldung (U30) und Einkommensteuer (E1a)

## Anforderungen

![PHP](https://img.shields.io/badge/dynamic/json?url=https://raw.githubusercontent.com/Hanner72/ekassa360/refs/heads/V0.01.06/versionen&query=$.require.php&label=PHP&color=777BB4&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/dynamic/json?url=https://raw.githubusercontent.com/Hanner72/ekassa360/refs/heads/V0.01.06/versionen&query=$.require.mysql&label=MySQL&color=777BB4&logo=MySQL&logoColor=white)
![PhpSpreadsheet](https://img.shields.io/badge/dynamic/json?url=https://raw.githubusercontent.com/Hanner72/ekassa360/refs/heads/V0.01.06/versionen&query=$.require.phpoffice/phpspreadsheet&label=PhpSpreadsheet&logoColor=white&color=orange)

![License](https://img.shields.io/badge/License-GPLv3-green)
![Latest Release](https://img.shields.io/github/v/release/Hanner72/ekassa360?include_prereleases)
![Latest Tag](https://img.shields.io/github/v/tag/Hanner72/ekassa360)
![Default Branch](https://img.shields.io/github/repo-size/Hanner72/ekassa360?label=default%20branch)
![Last Commit](https://img.shields.io/github/last-commit/Hanner72/ekassa360/main?label=last%20commit)

## Features

### Kernfunktionen
- **Einnahmen/Ausgaben-Verwaltung** mit automatischer Buchungsnummerierung pro Jahr
- **USt-Voranmeldung (U30)** - Automatische Berechnung aller Kennzahlen
- **Einkommensteuer (E1a)** - Beilage zur Steuererklärung mit dynamischen Kennzahlen
- **Anlagenverwaltung** mit automatischer AfA-Berechnung (linear/degressiv)
- **PDF-Export** für U30 und E1a Formulare

### EU-Buchungen (v1.6)
- Innergemeinschaftliche Lieferungen (B2B EU-Export)
- Innergemeinschaftliche Erwerbe (B2B EU-Import)
- Reverse Charge Verfahren
- Drittland Export/Import
- Automatische Erwerbsteuer (KZ072) und Einfuhr-USt (KZ061)

### Weitere Features
- **Benutzerverwaltung** mit Rollen (Admin/Benutzer)
- **Änderungsprotokoll** für alle Buchungen
- **Excel-Import** für Massendaten (xlsx, xls, csv)
- **Kategorien** mit E1a-Kennzahl-Zuordnung
- **USt-Sätze** konfigurierbar mit U30-Kennzahlen
- **Dashboard** mit Jahresübersicht

---

## Installation

### Voraussetzungen
- PHP 8.1 oder höher
- MySQL 5.7+ / MariaDB 10.3+
- PHP-Erweiterungen: PDO, pdo_mysql, json, mbstring, zip

### Schritt 1: Dateien hochladen
Entpacken Sie das ZIP-Archiv in Ihr Webserver-Verzeichnis:
```
/var/www/html/ekassa360/
```

### Schritt 2: Installationsassistent
Öffnen Sie im Browser:
```
https://ihre-domain.at/ekassa360/install.php
```

Der Assistent führt Sie durch:
1. Datenbankverbindung eingeben
2. Tabellen werden automatisch erstellt
3. Admin-Benutzer anlegen
4. Konfiguration wird gespeichert

### Schritt 3: Sicherheit
**WICHTIG:** Löschen Sie nach der Installation die Datei `install.php`:
```bash
rm install.php
```

### Schritt 4: Firmendaten
Melden Sie sich an und tragen Sie unter **Einstellungen → Firma** Ihre Daten ein.

---

## Benutzeranleitung

### Erste Schritte
1. **Einloggen** mit dem bei der Installation erstellten Admin-Account
2. **Firmendaten** unter Einstellungen eintragen
3. **Kategorien** prüfen und ggf. anpassen
4. **Erste Buchung** über den Menüpunkt "Rechnungen" erfassen

### USt-Voranmeldung (U30)
1. Menü → USt-Voranmeldung
2. Jahr und Monat/Quartal wählen
3. Werte werden automatisch aus Buchungen berechnet
4. "Speichern" und ggf. "PDF erstellen"

### Einkommensteuer (E1a)
1. Menü → Einkommensteuer
2. Jahr auswählen
3. Alle Kennzahlen werden automatisch berechnet
4. "Speichern" und "PDF exportieren"

### EU-Buchungen erfassen
Bei **Rechnungen → Neu** den passenden USt-Satz wählen:
- `0% ig Lieferung (EU B2B)` - für EU-Verkäufe an Unternehmer
- `0% Reverse Charge (EU B2B)` - für EU-Einkäufe (Dienstleistungen)
- `20% innergemeinschaftl. Erwerb` - für EU-Wareneinkäufe
- `0% Drittland Export` - für Nicht-EU Export
- `20% Drittland Import` - für Nicht-EU Import

---

## Konfiguration

### database.php
Nach der Installation enthält `config/database.php`:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ekassa360');
define('DB_USER', 'ihr_benutzer');
define('DB_PASS', 'ihr_passwort');
```

### USt-Periode
Unter **Einstellungen → Firma** wählen Sie:
- `monatlich` - für Unternehmen mit mehr als €100.000 Jahresumsatz
- `quartalsweise` - für Unternehmen unter €100.000 Jahresumsatz

---

## Kennzahlen-Referenz

### U30 - USt-Voranmeldung

| Kennzahl | Beschreibung |
|----------|--------------|
| 000 | Gesamtbetrag der Lieferungen/Leistungen |
| 022 | Bemessungsgrundlage 20% |
| 029 | USt 20% |
| 027 | USt 10% |
| 035 | USt 13% |
| 070 | Innergemeinschaftliche Erwerbe |
| 072 | Erwerbsteuer (20% von KZ070) |
| 060 | Vorsteuer |
| 061 | Einfuhr-USt (Drittland) |
| 065 | VSt aus ig Erwerben |
| 066 | VSt Reverse Charge |
| 095 | **Zahllast / Gutschrift** |

### E1a - Einkommensteuer

| Kennzahl | Beschreibung |
|----------|--------------|
| 9040 | Erlöse aus Waren/Erzeugnissen |
| 9050 | Erlöse aus Dienstleistungen |
| 9100 | Wareneinkauf/Rohstoffe |
| 9110 | Fremdleistungen |
| 9120 | Personalaufwand |
| 9130 | AfA (linear, GWG) |
| 9134 | AfA degressiv |
| 9135 | AfA Gebäude beschleunigt |
| 9140 | Betriebsräumlichkeiten |
| 9150 | Sonstige Betriebsausgaben |
| 9160 | Reisekosten |
| 9170 | Werbung/Marketing |
| 9180 | Versicherungen |
| 9190 | Fortbildung |
| 9200 | Beratungskosten |
| 9225 | SVS-Beiträge |
| 9230 | Übrige Ausgaben |

---

## Wartung

### Beispieldaten erstellen
Unter **Einstellungen → Wartung** können Sie Testdaten erstellen:
- 24 Rechnungen für 2025 (12 Einnahmen, 12 Ausgaben)
- 4 Rechnungen für 2026
- 1 Anlagegut

### Daten löschen
**Einstellungen → Wartung → Alle Daten löschen**

Löscht: Rechnungen, Anlagegüter, AfA, E1a, U30, Protokoll

Behält: Kategorien, USt-Sätze, Firmendaten, Benutzer

### Backup
Sichern Sie regelmäßig:
1. Die MySQL-Datenbank (`mysqldump`)
2. Den Ordner `uploads/` (falls Belege hochgeladen)

```bash
mysqldump -u user -p ekassa360 > backup_$(date +%Y%m%d).sql
```

---

## Dateistruktur

```
ekassa360/
├── assets/css/style.css    # Stylesheet
├── config/database.php     # DB-Konfiguration (nach Install)
├── database/
│   ├── install.sql         # Neuinstallation
│   ├── update_v1.6.sql     # Update von v1.5
│   └── database.sql        # Vollständiger DB-Dump
├── includes/
│   ├── auth.php           # Authentifizierung
│   ├── functions.php      # Kernfunktionen
│   ├── navbar.php         # Navigation
│   └── sidebar.php        # Seitenleiste
├── lib/fpdf/              # PDF-Bibliothek
├── vendor/                # Composer Dependencies
├── index.php              # Dashboard
├── rechnungen.php         # Einnahmen/Ausgaben
├── anlagegueter.php       # Anlagenverwaltung
├── abschreibungen.php     # AfA-Übersicht
├── ust_voranmeldung.php   # U30
├── einkommensteuer.php    # E1a
├── einstellungen.php      # Einstellungen
├── import.php             # Excel-Import
├── install.php            # Installationsassistent
└── README.md              # Diese Datei
```

---

## Sicherheit

- Passwörter werden mit `password_hash()` (bcrypt) gespeichert
- Alle SQL-Queries verwenden Prepared Statements
- Session-basierte Authentifizierung
- Änderungsprotokoll für alle Buchungen
- Account-Sperrung nach 5 Fehlversuchen

---

## Changelog

### v1.6 (aktuell)
- EU-Buchungen: igE, Reverse Charge, Drittland
- Neue U30-Kennzahlen: KZ072, KZ061
- Dynamische E1a-Kennzahlen (JSON-Speicherung)
- Wartungs-Tab: Beispieldaten & Reset
- Installationsassistent

### v1.5
- Einheitliches blaues Design
- Benutzerverwaltung mit Rollen
- Excel-Import (xlsx, xls, csv)
- Änderungsprotokoll

### v1.0-1.4
- Basis-Buchhaltung
- USt-Voranmeldung (U30)
- Einkommensteuer (E1a)
- Anlagenverwaltung mit AfA

---

## Lizenz

MIT License - Frei für private und kommerzielle Nutzung.

---

## Support

Bei Fragen oder Problemen:
- GitHub Issues erstellen
- Dokumentation auf docs.ekassa360.at

**Hinweis:** EKassa360 ersetzt keine steuerliche Beratung. Für verbindliche Auskünfte wenden Sie sich an Ihren Steuerberater.
