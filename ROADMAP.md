# Roadmap: flz_ags

## Prüfstatus

**Teilweise regelkonform / P1.** Das Plugin hat die stärkste aktuelle
Sicherheitsbasis: Admin-/AJAX-Pfade prüfen Capability und Nonce, der CSV-Export
wird direkt gestreamt, Kapazität wird unter einer Slot-Sperre geprüft,
Abhängigkeiten werden defensiv gemeldet und der Modell-Smoke besteht. Offen sind
vor allem Datenschutzlebenszyklus, Migrationsnachweise, vollständige
Nebenläufigkeitsinvarianten und echte WordPress-/UI-Tests.

## P1

1. CSV-Roundtrip vervollständigen: **AGs einschließlich Slots und Anmeldungen
   erledigt** mit
   versioniertem Direktdownload, fehlertolerantem Direktimport, aussagekräftigem
   Importbericht, optionalem Slot-Export, wählbarer Schuljahrübernahme sowie
   transaktionaler Übernahme. Für den Anmeldungs-Restore bleiben echte
   WordPress-Integrations- und Rollbacktests offen.
2. **Teilweise erledigt:** Konfigurierbare Aufbewahrungsfrist, standardmäßig
   deaktivierte tägliche Löschung und bestätigte manuelle Löschung basieren auf
   dem ursprünglichen Anmeldedatum. Auskunft, Anonymisierung sowie
   WordPress-Privacy-Exporter/-Eraser bleiben offen.
3. DB-Schemaversion von der allgemeinen Plugin-Version trennen. Jede Änderung
   als benannten, additiven Upgradepfad mit Frischinstallations-, Upgrade-,
   Wiederholungs- und synthetischem Bestandsdatentest ausführen.
4. Eindeutigkeit einer aktiven Anmeldung pro Schüler/Schuljahr auch bei
   parallelen Anmeldungen in verschiedenen Slots garantieren. Slot-Sperre allein
   serialisiert diesen Fall nicht; geeigneten Schlüssel/Lock-Vertrag plus
   Negativtest festlegen.
5. Lokale Mock-Mails mit personenbezogenen Formularwerten nur kurzlebig und
   löschbar speichern; keine Übernahme in andere Umgebungen, Backups oder Logs.
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
