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
- AGs/Slots besitzen einen versionierten, fehlertoleranten CSV-Direktimport mit
  zeilenbezogenem Bericht und wählbarer Schuljahrübernahme. Exporte werden
  geschützt direkt gestreamt und können Slots optional einschließen.
  Personenbezogene Anmeldungen verwenden ebenfalls einen direkten,
  ausdrücklich bestätigten und atomaren Backup-Import ohne Mailversand; nicht
  auflösbare Einzelzeilen werden datensparsam und verständlich gemeldet.
- Aufbewahrung, Auskunft, Anonymisierung und Löschung sind dokumentiert und
  testbar. Logs und lokale Mail-Captures sind datensparsam und kurzlebig.
- Übersetzbare Texte verwenden `flz-ags`.

Beobachtbare Änderungen testgetrieben umsetzen. Sicherheitsgrenzen brauchen
Allow-/Deny- und Parallelitätsfälle. Mindestens `php tests/model-smoke.php` und
`./scripts/check-fast` ausführen; WordPress-/DDEV-/UI-Prüfung separat benennen.
Keine Commits, Pushes, Aktivierungen, Seitenanlagen, Imports oder Deployments
ohne ausdrückliche Freigabe; nie `git add .` verwenden.

## Commit-, Coverage- und Release-Gates

- Der aktuelle Übernahmestand ist Phase 1: PR-/Main-CI, Lizenz, Changelog und
  branchgleiche Provider-Checkouts sind lokal konfiguriert. Bis zum ersten
  grünen Remote-Lauf und den Coverage-Gates bleiben normale Produkt- und
  Releasecommits blockiert; ausdrücklich beauftragte Quality-Rollout-Commits
  dürfen die fehlende Infrastruktur schrittweise herstellen.
- Vor einem späteren normalen Commit sind Status, Diff-Statistik und vollständige
  Dateiliste zu zeigen; fokussierte Tests, `./scripts/check-fast`, Shared-
  Provider-/Consumer-Tests, CI und Coverage-Gates müssen grün sein. Dateien
  werden einzeln gestaged; `git add .` bleibt verboten.
- PHP- und JavaScript-Line-Coverage werden getrennt gegen gemessene
  No-Regression-Baselines geprüft. Neuer oder wesentlich geänderter Code
  erreicht mindestens 85 Prozent; Sicherheits-, Datenschutz-, Migrations- und
  Nebenläufigkeitsinvarianten sind unabhängig davon vollständig abgedeckt.
- Der PHPCOV-/Xdebug-Messjob ist vorbereitet; die PHP-Baseline bleibt bis zum
  Remote-Lauf `pending`. Die lokal reproduzierte JavaScript-Baseline beträgt
  41,46 Prozent und wird bereits als No-Regression-Ratsche geprüft.
- Ein Fast- oder Diagnosecheck ist kein Releaseurteil. Ein Release braucht ein
  sauberes Repository, konsistente Version/Changelog/Lizenz, vollständig
  ausgefülltes `docs/manual-acceptance.md`, ein reproduzierbares Ein-Wurzel-
  Archiv, Manifest und SHA-256 sowie geprüfte Installation, Upgrade,
  Deaktivierung, Datenschutz, Mail, sichtbare UI und Rückbau aus dem Artefakt.
- Bauen, Signieren, Taggen, Pushen, Publizieren und Deployen bleiben getrennte,
  ausdrücklich zu autorisierende Aktionen.
