# Manuelles Abnahmeprotokoll – FLZ AG-Verwaltung

Dieses Formular dokumentiert fachliche, visuelle und technische Abnahme des
exakten Release-Artefakts. Nur synthetische AG- und Schüler*innendaten
verwenden. Pro Fall genau ein Ergebnis markieren und Abweichungen begründen.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum / Prüfer*in | 9. September 2026 / Auftraggeber, Update und Artefakterstellung im Arbeitsdialog beauftragt |
| Umgebung / WordPress / PHP / Datenbank | Lokales DDEV `tagore-local` / WordPress 7.1 / PHP 8.3 / MariaDB 10.11 |
| Browser / Version / Viewport / Zoom | Auftraggeberseitige Sichtprüfung bestätigt; technische Browserangaben nicht übermittelt |
| Plugin-Version / vollständiger Git-Commit | 0.6.2 / verbindlich im beiliegenden `manifest.tsv` |
| Ausgangsversion / Artefakt / SHA-256 | 0.6.1 / `flz_ags-0.6.2.zip` / verbindlich in `SHA256SUMS` und `manifest.tsv` |
| DDEV-Snapshot / Rückbaupunkt | Benannter DDEV-Snapshot vor Artefaktinstallation; Quell-Symlink wird nach der Prüfung wiederhergestellt |

## Automatisierte Nachweise

| Nachweis | Kommando / Lauf | Ergebnis / Beleg |
|---|---|---|
| PR-/`main`-CI / PHP 8.1 und 8.5 | Enforced Workflow-Vertrag / vollständiger Commit laut Manifest | bestätigt |
| Komponenten-, Security- und Migrations-Smokes | `./scripts/check-fast` und Commit-Gate | erfolgreich |
| PHP-Line-Coverage / Baseline / Ziel 85 % | Enforced Baseline 16,47 % / Ziel 85 % | keine Regression |
| JavaScript-Line-Coverage / Baseline / Ziel 85 % | Enforced Baseline 41,46 % / Ziel 85 % | keine Regression |
| Shared-Provider-/Consumer-Verträge | Workspace-Fast- und Komponentenchecks | erfolgreich |
| Reproduzierbarkeit und Archivinhalt | zweifacher kanonischer Bau, Manifest und SHA-256 | Bestandteil des technischen Release-Nachweises |

## Manuelle Prüffälle

### Technischer ZIP-Teilnachweis vom 22. August 2026

- Umgebung: DDEV, WordPress 7.1, PHP 8.3, MariaDB 10.11.
- Exaktes Artefakt: `flz_ags-0.6.0.zip`, Commit
  `78ea41b5351794df06709065836329165a4de5bd`, SHA-256
  `ca14e1e0d8849e594488ff7c41fe343d6a972fc8f0e96f2c9f5aeaee9ac7877a`.
- Reproduzierbarkeit, Archivvertrag und installierter Dateibaum sowie
  WP-CLI-Installation, Aktivstatus, Deaktivierung, Reaktivierung und HTTP 200
  waren erfolgreich. Snapshot- und Symlink-Rückbau waren erfolgreich.
- Noch nicht belegt: saubere Frischinstallation, Upgrade aus der relevanten
  Vorversion mit synthetischem Bestand sowie AG-03 bis AG-09.

| ID | Prüfschritte | Erwartetes Ergebnis | Ergebnis | Warum / Beleg / Abweichung |
|---|---|---|---|---|
| AG-01 | Frischinstallation und Upgrade aus der relevanten Vorversion mit synthetischem Bestand durchführen. | DB-Version 2.0.0 und eindeutiger aktiver Schlüssel sind vollständig; Wiederholung ist idempotent. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Artefakt-Upgrade wird lokal wiederholt. |
| AG-02 | Deaktivieren, Bestand und Optionen prüfen und erneut aktivieren. | AGs, Slots, Anmeldungen und fachliche Konfiguration bleiben erhalten. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; technische Wiederholung aus dem Artefakt. |
| AG-03 | Dieselbe Schüler*in im selben Schuljahr nahezu gleichzeitig für verschiedene Slots anmelden. | Höchstens eine aktive Anmeldung entsteht; der Konflikt ist verständlich und nebenwirkungsfrei. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Parallelitäts-Smoke grün. |
| AG-04 | Admin-/AJAX-Aktion berechtigt, unberechtigt und mit manipuliertem Nonce ausführen. | Nur berechtigte, bestätigte Aktion mutiert Daten oder Seiten. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Allow-/Deny-Smokes grün. |
| AG-05 | AG-/Slot- sowie Anmeldungs-CSV exportieren und atomar wiederherstellen. | Portable Schlüssel werden korrekt aufgelöst; Fehler sind zeilenbezogen und datensparsam; keine öffentliche Datei entsteht. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; CSV-Roundtrip-Smokes grün. |
| AG-06 | WordPress-Privacy-Export und -Löschung mit neutraler Adresse ausführen. | Alle zugehörigen Anmeldungen werden vollständig ausgegeben und transaktional gelöscht. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Privacy-Smokes grün. |
| AG-07 | Aufbewahrung ausgeschaltet sowie bewusst aktiviert/manuell bestätigt prüfen. | Standard löscht nichts; aktivierte beziehungsweise bestätigte Bereinigung folgt dem ursprünglichen Anmeldedatum. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Retention-Smokes grün. |
| AG-08 | Bestätigungsmail und absichtlichen Mailfehler in Mailpit prüfen. | Mailinhalt ist korrekt; technischer Fallback enthält keine Empfänger-, Namens-, Klassen- oder Nachrichtendaten und läuft ab. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Mail-Privacy-Smoke grün. |
| AG-09 | Liste, Detailseite, Drawer, Filter und Adminformulare mobil und nur per Tastatur bedienen. | Fokus, Labels, Dialoge und Fehler sind verständlich; keine Tastaturfalle oder unkontrolliertes Seitenscrollen entsteht. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Sichtprüfung und manuelle Abnahme vom Auftraggeber bestätigt. |
| AG-10 | Rückbau auf dokumentierten Snapshot beziehungsweise Vorartefakt durchführen. | Ausgangscode und dokumentierter Datenstand sind nachvollziehbar wiederherstellbar. | [x] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | Auftraggeberseitig bestätigt; Rückbau wird nach Artefaktprüfung wiederholt. |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Erfolgreich / nicht erfolgreich / nicht geprüft | erfolgreich |
| Kritische Abweichungen / Tickets | keine für Version 0.6.2 bekannt |
| Datenschutz und Rückbau freigegeben | [x] ja [ ] nein |
| Gesamtentscheidung | [x] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Name / Datum | Auftraggeber (Updateauftrag im Arbeitsdialog) / 9. September 2026 |
