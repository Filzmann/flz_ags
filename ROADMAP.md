# Roadmap: flz_ags

## Prüfstatus

**Funktionsstand 0.6.0 ohne offene P0-/P1-Befunde; Release-Gate in
Übernahmephase 2 blockiert.** Admin-/AJAX-
Pfade prüfen Capability und Nonce, CSV wird direkt gestreamt, Kapazität unter
Slot-Sperre geprüft und aktive Mehrfachanmeldung durch einen eindeutigen
Datenbankvertrag verhindert. Datenschutz, kurzlebige Mail-Capture-Diagnose und
DB-Upgrade sind umgesetzt. Fast-Checks, reale lokale Bestandsmigration und
Aktivieren–Deaktivieren–Aktivieren sind grün. PR-/Main-CI, branchgleiche
Shared-Plugins und die Ratschen von 16,47 Prozent PHP sowie 41,46 Prozent
JavaScript sind remote enforced; Ziel bleiben je 85 Prozent. Der
reproduzierbare ZIP-Builder ist konfiguriert. Vor einem Tag fehlen die
schrittweise Zielannäherung, die Browser-/Mail-/Zwei-Prozess-Abnahme sowie
Installation, Upgrade und Rückbau aus dem exakten Artefakt. Das Protokoll liegt
unter `docs/manual-acceptance.md`.

## P1

1. CSV-Roundtrip vervollständigen: **AGs einschließlich Slots und Anmeldungen
   erledigt** mit
   versioniertem Direktdownload, fehlertolerantem Direktimport, aussagekräftigem
   Importbericht, optionalem Slot-Export, wählbarer Schuljahrübernahme sowie
   transaktionaler Übernahme. Für den Anmeldungs-Restore bleiben echte
   WordPress-Integrations- und Rollbacktests offen.
2. **Erledigt:** Aufbewahrung ist standardmäßig deaktiviert; bestätigte
   manuelle Löschung sowie WordPress-Privacy-Exporter/-Eraser sind vorhanden.
3. **Erledigt:** DB-Version 2.0.0 ist getrennt und wird additiv, idempotent und
   konfliktbewusst migriert; Frisch-, Upgrade- und Wiederholungsfälle sind
   durch Smokes und lokalen Bestand belegt.
4. **Erledigt:** `active_student_key` plus eindeutiger Index garantiert die
   Eindeutigkeit einer aktiven Anmeldung pro Schüler*in/Schuljahr auch über
   verschiedene Slots; der Konfliktfall ist getestet.
5. **Erledigt:** Die Mail-Capture-Ausweichlösung enthält keine
   personenbezogenen Formularwerte mehr, läuft nach höchstens einer Stunde ab
   und ist im Backend sofort löschbar.
6. **Erledigt:** Direkte Includes aus Shared-Plugin-Verzeichnissen durch deren
   öffentliche, versionierte Bootstrap-/API-Verträge ersetzt und mit einem
   Verbraucher-Smoke abgesichert.
7. **Erledigt:** Neue Demo-AGs übernehmen keine Titel, Freitexte,
   Leitungsnamen oder Bilder aus veröffentlichten Seiten. Ein Privacy-Smoke
   sichert die synthetischen Felder; bestehende Datensätze bleiben unangetastet.

## P2

1. Admin-, AJAX-, öffentlicher Formular-, Mailfehler-, Export- und
   Parallelitätstests in einer echten WordPress-Testumgebung ergänzen; Allow- und
   Deny-Fälle mit ausbleibenden Nebenwirkungen belegen.
2. Alle nutzersichtbaren Texte vollständig internationalisieren und PHP-/JS-
   Übersetzungen laden.
3. `FLZ_AGS_Plugin` weiter in dünne Request-Koordination und testbare
   Anwendungsservices teilen, ohne eine breite Umschreibung vor Tests.
4. Frontendlisten, Filter, Detailformular, Adminformulare und Mailfehlerpfad in
   DDEV responsiv, per Tastatur und visuell prüfen.

## P3

1. Coverage für Modelle, Klassen-/Jahrgangslogik und Registrierung messen und
   bei wesentlich geändertem Code 85 Prozent erreichen.
2. Release-Artefakt, Upgrade von der letzten ausgelieferten Version und
   Deaktivierung ohne Daten-/Funktionsverlust reproduzierbar prüfen.
