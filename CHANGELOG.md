# Changelog

Alle wesentlichen Änderungen an `flz_ags` werden in dieser Datei dokumentiert.
Ein Datum wird erst bei einer tatsächlichen Veröffentlichung ergänzt.

## Unreleased

## 0.7.0 – 2026-09-21

- Rolle „AG-Leiter“ ergänzt. Über einen WordPress-Benutzer je AG zugeordnet,
  sieht sie ausschließlich die zugehörigen Anmeldungen und kann keine Daten
  ändern, importieren oder exportieren.
- Mehrfachanmeldungen sind im Backend je Schuljahr einstellbar. Bei Freigabe
  bleiben doppelte aktive Anmeldungen derselben AG verhindert.
- Raumangaben aus dem öffentlichen Anmeldeformular entfernt, das geöffnete
  Formular über den Sticky Header gehoben und einen konfigurierbaren,
  standardmäßig halbjahresbezogenen Hinweis ergänzt.

## 0.6.2 – 2026-09-09

- Filter in allen Spaltenköpfen sowie auf-/absteigende Sortierung in allen
  fachlich sinnvollen Spalten der Backend-Anmeldungstabelle ergänzt.

## 0.6.1 – 2026-09-08

- Suche, Filter nach Bereich, Klassenstufe, Dozent und Wochentag sowie passende
  Sortieroptionen in öffentlicher AG-Liste und Backend ergänzt. Im Backend
  liegen sie direkt in den Tabellenköpfen; im Frontend in einem einklappbaren
  Filterbereich. Gefilterte Karten werden trotz ihres Flex-Layouts zuverlässig
  ausgeblendet.
- Backend-Aktion „Speichern und schließen“ mit eigenem kombiniertem Icon und
  Rückkehr zur AG-Liste ergänzt.
- Reproduzierbare PR-/Main-CI für PHP 8.1 und 8.5 ergänzt.
- Branchgleicher Checkout beider Shared-Plugins mit sicherem `main`-Fallback
  ergänzt.
- Formale Lizenz- und Abnahmenachweise in den Delivery-Vertrag aufgenommen.
- PHP-/JavaScript-No-Regression-Ratschen bei 16,47 beziehungsweise
  41,46 Prozent remote enforced.
- Reproduzierbaren Ein-Wurzel-ZIP-Bau mit Manifest, SHA-256 und CI-Prüfung
  ergänzt.

## 0.6.0

- Additiven, idempotenten DB-Upgradepfad und einen eindeutigen fachlichen
  Anmeldeschlüssel ergänzt.
- Datenschutzexport, Datenlöschung und datensparsames Mail-Fallback ergänzt.
- AG-/Slot- und Anmeldungs-CSV-Verträge für geschützte Direktimporte und
  -exporte gehärtet.
