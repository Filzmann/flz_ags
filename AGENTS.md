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

- Der aktuelle Übernahmestand ist Phase 2: PR-/Main-CI, branchgleiche
  Provider-Checkouts sowie PHP- und JavaScript-Coverage-Ratschen sind remote
  belegt. Normale Produktcommits brauchen das enforced Commit-Gate;
  Releasecommits bleiben bis zur Abnahme und zum Artefakt-Gate blockiert.
- Vor jedem normalen Commit sind Status, Diff-Statistik und vollständige
  Dateiliste zu zeigen; fokussierte Tests, `./scripts/check-fast`, Shared-
  Provider-/Consumer-Tests, CI und Coverage-Gates müssen grün sein. Dateien
  werden einzeln gestaged; `git add .` bleibt verboten.
- PHP- und JavaScript-Line-Coverage werden getrennt gegen gemessene
  No-Regression-Baselines geprüft. Neuer oder wesentlich geänderter Code
  erreicht mindestens 85 Prozent; Sicherheits-, Datenschutz-, Migrations- und
  Nebenläufigkeitsinvarianten sind unabhängig davon vollständig abgedeckt.
- Die enforced Baselines betragen 16,47 Prozent für PHP und 41,46 Prozent für
  JavaScript; das Ziel für neuen oder wesentlich geänderten Code bleibt je
  Sprache 85 Prozent.
- `scripts/build-release` erzeugt über den kanonischen Workspace-Builder ein
  reproduzierbares Ein-Wurzel-ZIP mit Manifest und SHA-256. Das Artefakt-Gate
  bleibt bis zur Prüfung des exakten ZIP in WordPress `configured`.
- Ein Fast- oder Diagnosecheck ist kein Releaseurteil. Ein Release braucht ein
  sauberes Repository, konsistente Version/Changelog/Lizenz, vollständig
  ausgefülltes `docs/manual-acceptance.md`, ein reproduzierbares Ein-Wurzel-
  Archiv, Manifest und SHA-256 sowie geprüfte Installation, Upgrade,
  Deaktivierung, Datenschutz, Mail, sichtbare UI und Rückbau aus dem Artefakt.
- Bauen, Signieren, Taggen, Pushen, Publizieren und Deployen bleiben getrennte,
  ausdrücklich zu autorisierende Aktionen.

## Parent-Governance-Vertrag: 1

- Die für dieses Komponenten-Repository anwendbaren Regeln des
  Parent-Workspaces sind verbindlich. Dazu gehören insbesondere gemeinsame
  Architektur-, Sicherheits-, Plugin- und Theme-Verträge,
  Repositorygrenzen sowie Workspace-, Quality-, Delivery- und Release-Gates.
- Diese lokale `AGENTS.md` und die lokalen Skills bleiben die vollständige,
  ohne Parent-Checkout arbeitsfähige Repository-Steuerung. Die anwendbaren
  Parent-Regeln werden dafür hier oder in den lokalen Skills mitgeführt.
- Vor Arbeit an der Komponente ist der lokale Skill
  `work-in-wordpress-extension` zu verwenden. Für beobachtbare Änderungen
  gilt zusätzlich der lokale Skill `test-driven-wordpress-change`.
- Repository-lokale Regeln dürfen Parent-Verträge konkretisieren und
  verschärfen, aber nicht abschwächen oder umgehen.
- Bei einem Widerspruch gilt bis zur Klärung die strengere Regel. Die Arbeit
  stoppt, bis die kanonische Quelle bestimmt, die Regelprojektionen
  synchronisiert und eine erforderliche Entscheidung dokumentiert ist.
- Ist der Parent-Workspace nicht verfügbar, bleibt die lokale Steuerung
  wirksam. Vor Cross-Component-, Release- oder Delivery-Arbeit muss ein
  vermuteter neuerer Parent-Stand oder eine Regelungslücke zuerst gegen den
  Parent geprüft werden.
