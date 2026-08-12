# Regeln für flz_ags

Dieses Repository enthält ausschließlich das Fachplugin `flz_ags`. Es verwaltet
AG-Angebote, Schuljahre, Slots und Anmeldungen. Personenbezogene Daten,
Klassenlogik, Kapazität, CSV, E-Mail, Seitenanlage, Berechtigungen und
Schemaänderungen sind Risikogrenzen.

Harte Abhängigkeiten sind die öffentlichen, versionierten Verträge von
`flz_wpdb_objects` und `flz_ui_components`. Der Header `Requires Plugins` wird
durch defensive Klassen-/Funktions-/Versionsprüfungen ergänzt. Interne Dateien
anderer Repositories werden nie direkt eingebunden.

- Admin/AJAX prüfen engste Capability und Nonce; öffentliche Anmeldung prüft
  Nonce, Objektbezug, Klasse, Slot, Duplikat und Kapazität serverseitig.
- Kapazitäts- und Eindeutigkeitsprüfung samt Insert sind atomar.
- Deaktivierung löscht keine Daten. Schema-Upgrades sind versioniert und
  idempotent.
- AGs/Slots und Anmeldungen besitzen jeweils versionierten CSV-Import und
  -Export mit Dry-Run; Exporte werden geschützt direkt gestreamt.
- Aufbewahrung, Auskunft, Anonymisierung und Löschung sind dokumentiert und
  testbar. Logs und lokale Mail-Captures sind datensparsam und kurzlebig.
- Übersetzbare Texte verwenden `flz-ags`.

Beobachtbare Änderungen testgetrieben umsetzen. Sicherheitsgrenzen brauchen
Allow-/Deny- und Parallelitätsfälle. Mindestens `php tests/model-smoke.php` und
`./scripts/check-fast` ausführen; WordPress-/DDEV-/UI-Prüfung separat benennen.
Keine Commits, Pushes, Aktivierungen, Seitenanlagen, Imports oder Deployments
ohne ausdrückliche Freigabe; nie `git add .` verwenden.
