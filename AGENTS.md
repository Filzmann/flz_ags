# Komponentenregeln: flz_ags

Zusätzlich gelten die Root-Regeln und die dortigen Skills
`work-in-wordpress-extension` und `test-driven-wordpress-change`.

Dieses Fachplugin verwaltet AG-Angebote, Schuljahre, Slots und Anmeldungen.
Klassenlogik, Kapazität, personenbezogene Daten, CSV-Export, Berechtigungen und
Schemaänderungen sind Risikogrenzen. Gemeinsame Datenbank- und UI-Komponenten
nur über öffentliche Verträge verwenden. Relevante Baseline:
`./scripts/phpcs-flz-ags.sh` aus dem Root und `php tests/model-smoke.php`;
WordPress-/Datenbank- und UI-Verhalten zusätzlich in DDEV prüfen.
