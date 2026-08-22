# Manuelles Abnahmeprotokoll – FLZ AG-Verwaltung

Dieses Formular dokumentiert fachliche, visuelle und technische Abnahme des
exakten Release-Artefakts. Nur synthetische AG- und Schüler*innendaten
verwenden. Pro Fall genau ein Ergebnis markieren und Abweichungen begründen.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum / Prüfer*in | |
| Umgebung / WordPress / PHP / Datenbank | |
| Browser / Version / Viewport / Zoom | |
| Plugin-Version / vollständiger Git-Commit | |
| Ausgangsversion / Artefakt / SHA-256 | |
| DDEV-Snapshot / Rückbaupunkt | |

## Automatisierte Nachweise

| Nachweis | Kommando / Lauf | Ergebnis / Beleg |
|---|---|---|
| PR-/`main`-CI / PHP 8.1 und 8.5 | Workflow-Lauf / vollständiger Commit | |
| Komponenten-, Security- und Migrations-Smokes | `./scripts/check-fast` | |
| PHP-Line-Coverage / Baseline / Ziel 85 % | | |
| JavaScript-Line-Coverage / Baseline / Ziel 85 % | | |
| Shared-Provider-/Consumer-Verträge | | |
| Reproduzierbarkeit und Archivinhalt | | |

## Manuelle Prüffälle

| ID | Prüfschritte | Erwartetes Ergebnis | Ergebnis | Warum / Beleg / Abweichung |
|---|---|---|---|---|
| AG-01 | Frischinstallation und Upgrade aus der relevanten Vorversion mit synthetischem Bestand durchführen. | DB-Version 2.0.0 und eindeutiger aktiver Schlüssel sind vollständig; Wiederholung ist idempotent. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-02 | Deaktivieren, Bestand und Optionen prüfen und erneut aktivieren. | AGs, Slots, Anmeldungen und fachliche Konfiguration bleiben erhalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-03 | Dieselbe Schüler*in im selben Schuljahr nahezu gleichzeitig für verschiedene Slots anmelden. | Höchstens eine aktive Anmeldung entsteht; der Konflikt ist verständlich und nebenwirkungsfrei. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-04 | Admin-/AJAX-Aktion berechtigt, unberechtigt und mit manipuliertem Nonce ausführen. | Nur berechtigte, bestätigte Aktion mutiert Daten oder Seiten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-05 | AG-/Slot- sowie Anmeldungs-CSV exportieren und atomar wiederherstellen. | Portable Schlüssel werden korrekt aufgelöst; Fehler sind zeilenbezogen und datensparsam; keine öffentliche Datei entsteht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-06 | WordPress-Privacy-Export und -Löschung mit neutraler Adresse ausführen. | Alle zugehörigen Anmeldungen werden vollständig ausgegeben und transaktional gelöscht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-07 | Aufbewahrung ausgeschaltet sowie bewusst aktiviert/manuell bestätigt prüfen. | Standard löscht nichts; aktivierte beziehungsweise bestätigte Bereinigung folgt dem ursprünglichen Anmeldedatum. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-08 | Bestätigungsmail und absichtlichen Mailfehler in Mailpit prüfen. | Mailinhalt ist korrekt; technischer Fallback enthält keine Empfänger-, Namens-, Klassen- oder Nachrichtendaten und läuft ab. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-09 | Liste, Detailseite, Drawer, Filter und Adminformulare mobil und nur per Tastatur bedienen. | Fokus, Labels, Dialoge und Fehler sind verständlich; keine Tastaturfalle oder unkontrolliertes Seitenscrollen entsteht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| AG-10 | Rückbau auf dokumentierten Snapshot beziehungsweise Vorartefakt durchführen. | Ausgangscode und dokumentierter Datenstand sind nachvollziehbar wiederherstellbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Erfolgreich / nicht erfolgreich / nicht geprüft | |
| Kritische Abweichungen / Tickets | |
| Datenschutz und Rückbau freigegeben | [ ] ja [ ] nein |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Name / Datum | |
