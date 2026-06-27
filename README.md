# Tagore AG-Verwaltung 0.3.3

Initiale WordPress-Plugin-Version für AG-Verwaltung und AG-Anmeldung.

## Neu in 0.3.3

- Die Detailseite ist ausschließlich über `detail_page_id` mit einer AG
  verknüpft; alte URL-Fallbacks wurden entfernt.
- Demo-Daten werden aus den vorhandenen veröffentlichten AG-Unterseiten im
  WordPress-Seitenbaum erzeugt. Es gibt keine harte Demo-AG-Liste und keine
  erfundenen Demo-Termine mehr.
- Zeiten, Räume, Jahrgänge und Teilnehmerzahlen werden aus den Tabellenfeldern
  der AG-Seiten gelesen, soweit sie dort vorhanden sind.
- Die Bilder in der Slot-Auswahl sind im Frontend kompakt begrenzt.

## Neu in 0.3.2

- AG-Anmeldungen laufen nicht mehr über eine globale Sammelseite, sondern
  immer auf der Detailseite der jeweiligen AG.
- AGs speichern eine robuste WordPress-Seiten-Verknüpfung (`detail_page_id`).
- In der AG-Bearbeitung ersetzt eine Seitensuche das manuelle Eintragen von
  URLs. Während der Eingabe werden passende WordPress-Seiten angeboten.
- Direkt aus der AG-Bearbeitung kann eine neue Detailseite angelegt werden.
  Sie wird automatisch als Unterseite der Seite „AGs“ veröffentlicht.
- Die AG-Liste verlinkt je AG auf „Details und Anmeldung“.

## Neu in 0.3.0

- Vollständige Datenzugriffsmigration auf Modelle aus `flz_wpdb_objects`.
- Tabellen werden wie im Shared-Plugin üblich aus den Modellnamen abgeleitet:
  `{prefix}flz_ags_courses`, `{prefix}flz_ags_slots` und
  `{prefix}flz_ags_registrations`.
- Schreibvorgänge für AG plus Termine, Demo-Daten und öffentliche Anmeldungen
  laufen in Datenbanktransaktionen. Fehler hinterlassen keine fachlichen
  Teilstände.
- Technische Ursachen werden mit ihrer Exception-Kette protokolliert;
  Frontend und Backend zeigen getrennte, sichere Fehlermeldungen.
- CSV-Exporte werden vor dem Senden vollständig geprüft. Führende
  Tabellenkalkulations-Formeln in Nutzwerten werden neutralisiert.

### Einmaliger Migrationshinweis

Die Version ist noch nicht produktiv. Deshalb findet bewusst **keine
Datenmigration** aus den bisherigen Tabellen `{prefix}flz_ag_courses`,
`{prefix}flz_ag_slots` und `{prefix}flz_ag_registrations` statt. Beim
Schemaaufbau werden die neuen, modellabgeleiteten Tabellen angelegt und die
drei alten Tabellen anschließend entfernt. Vorhandene Testdaten gehen dabei
verloren und Demo-/Testdaten müssen neu angelegt werden.

Die Shortcodes, Optionen und Administrations-URLs bleiben unverändert. Das
Plugin setzt `flz_wpdb_objects` voraus; WordPress erhält diese Abhängigkeit
zusätzlich über den Plugin-Header `Requires Plugins`.

## Fachmodell

- AGs gelten jeweils für ein Schuljahr.
- Jede AG hat ein Vorschaubild.
- Eine AG kann einen oder mehrere wöchentliche Slots haben.
- Die AG-Liste wird als Kachelübersicht ausgegeben: eine Kachel pro AG, die verfügbaren Slots stehen innerhalb der Kachel.
- Eine Anmeldung bezieht sich auf genau einen wöchentlichen Slot und gilt bis auf Widerruf.
- Klasse 7 kann als Pflichtwahl abgebildet werden, indem AGs zielgruppenseitig auf Klasse 7 eingeschränkt oder für Klasse 7 freigegeben werden.
- Die Klassenliste liegt in den Plugin-Einstellungen und ist mit dem aktuellen Krankmeldungsformular-Stand vorbelegt.

## Neu in 0.2.0

- Feld `Vorschaubild` pro AG.
- Mediathek-Auswahl im Backend für Vorschaubilder.
- Kachel-Layout der AG-Liste.
- AG-Liste gruppiert jetzt nach AG, nicht mehr nach einzelnen Slots.
- Detailseite pro AG.
- Demo-Setup aus vorhandenen AG-Seiten.
- Datenbank-Upgrade ergänzt `image_url`.

## Shortcodes

AG-Liste:

```text
[flz_ag_liste]
```

AG-Anmeldung:

```text
[flz_ag_anmeldung]
```

Der Anmeldung-Shortcode wird normalerweise nicht mehr manuell platziert. Er
wird auf der in der AG gespeicherten Detailseite automatisch ergänzt. Wird der
Shortcode dennoch direkt verwendet, muss er auf der verknüpften Detailseite
der AG stehen. Eine explizite `course_id` kann nur dort sinnvoll sein, wenn die
AG nicht automatisch aus der aktuellen Seite ableitbar ist:

```text
[flz_ag_anmeldung course_id="123"]
```

Optionales Schuljahr:

```text
[flz_ag_liste school_year="2026/2027"]
[flz_ag_anmeldung school_year="2026/2027"]
```

## Demo-Setup

Nach Aktivierung:

`FLZ AGs` → `Demo-Setup` → Schuljahr wählen → Demo-AGs aus AG-Seiten anlegen.

Das Demo-Setup liest die veröffentlichten Unterseiten der Seite „AGs“ aus.
Vorhandene AGs mit gleichem Slug und Schuljahr werden nicht dupliziert. Termine
werden nur angelegt, wenn auf der AG-Seite eine erkennbare Zeitangabe vorhanden
ist.

## Installation lokal in DDEV

```bash
cd ~/projects/tagore-local
ddev wp plugin activate flz_ags
```

Danach im Backend:

`FLZ AGs` → `Einstellungen` prüfen, aktuelles Schuljahr setzen, Klassenliste prüfen.

## Datenschutz/Prüfpunkte vor produktivem Einsatz

- Datenschutzhinweis der Schule für AG-Anmeldungen ergänzen/verlinken.
- Festlegen, wer Anmeldungen sehen/exportieren darf. Aktuell: `manage_options`, per Filter änderbar.
- Lösch-/Anonymisierungsfrist nach Schuljahr definieren. Eine automatische Löschroutine ist in 0.2.0 noch nicht enthalten.
- E-Mail-Bestätigungen sind in 0.2.0 bewusst nicht aktiviert.
- Kein externes Captcha, keine Akismet-Weitergabe von Anmeldedaten.
- Demo-Daten vor Produktivbetrieb löschen oder fachlich prüfen.

## Noch nicht enthalten

- Wartelistenautomatik
- E-Mail-Bestätigungen
- Frontend-Widerruf durch Eltern/Schüler*innen
- automatische Löschroutine
- Import bestehender AG-Seiten
- erweiterte Rollenverwaltung
