# Roadmap: flz_ags

## Prüfstatus

**Teilweise regelkonform / P1.** Das Plugin hat die stärkste aktuelle
Sicherheitsbasis: Admin-/AJAX-Pfade prüfen Capability und Nonce, der CSV-Export
wird direkt gestreamt, Kapazität wird unter einer Slot-Sperre geprüft,
Abhängigkeiten werden defensiv gemeldet und der Modell-Smoke besteht. Offen sind
vor allem Datenschutzlebenszyklus, Migrationsnachweise, vollständige
Nebenläufigkeitsinvarianten und echte WordPress-/UI-Tests.

## P1

1. CSV-Import und -Export als versionierten Roundtrip-Vertrag ergänzen: AGs
   einschließlich Slots sowie Anmeldungen jeweils mit Dry-Run, Referenz-,
   Duplikat-, Kapazitäts- und Rollbacktests. Exporte direkt streamen.
2. Aufbewahrungs-, Auskunfts-, Widerrufs-, Anonymisierungs- und Löschvertrag für
   Schülerdaten definieren; WordPress-Privacy-Exporter/-Eraser und eine
   idempotente schuljahrbezogene Löschroutine ergänzen.
3. DB-Schemaversion von der allgemeinen Plugin-Version trennen. Jede Änderung
   als benannten, additiven Upgradepfad mit Frischinstallations-, Upgrade-,
   Wiederholungs- und synthetischem Bestandsdatentest ausführen.
4. Eindeutigkeit einer aktiven Anmeldung pro Schüler/Schuljahr auch bei
   parallelen Anmeldungen in verschiedenen Slots garantieren. Slot-Sperre allein
   serialisiert diesen Fall nicht; geeigneten Schlüssel/Lock-Vertrag plus
   Negativtest festlegen.
5. Lokale Mock-Mails mit personenbezogenen Formularwerten nur kurzlebig und
   löschbar speichern; keine Übernahme in andere Umgebungen, Backups oder Logs.
6. Direkte Includes aus Shared-Plugin-Verzeichnissen durch deren öffentliche
   Bootstrap-/API-Verträge ersetzen; Provider- und Verbrauchertests koppeln.

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
