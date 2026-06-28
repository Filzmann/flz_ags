# FLZ AG-Verwaltung 0.3.10

Initiale WordPress-Plugin-Version für AG-Verwaltung und AG-Anmeldung.

## Neu in 0.3.10

- Backend- und Frontend-Markup liegt in Templates unter `templates/`; die
  Plugin-Klasse übernimmt nur noch Datenfluss, Validierung und Aktionen.
- Admin-Tabellen, CSV-Export und Frontend-Karten nutzen die gemeinsamen
  Komponenten aus `flz_ui_components`.
- AG-Modelle verwenden die kontrollierten Custom-Query-Helper aus
  `flz_wpdb_objects`; eigene SQL-Infrastruktur im Fachplugin entfällt.

## Neu in 0.3.9

- Die AG-Bestätigungsmail wird an die E-Mail-Adresse der Schülerin bzw. des
  Schülers adressiert.
- Der erläuternde Panel-Satz zur jederzeit erreichbaren Anmeldung wurde
  entfernt.
- AG- und Slot-Kacheln werden auf größeren Geräten in ihrer Breite begrenzt,
  damit sie nicht überdimensioniert wirken.
- Die modellbasierte Aktivierung ersetzt die frühere Datenbank-Orchestrierung;
  App-Code nutzt keine eigene SQL-Aufräumschicht mehr.

## Neu in 0.3.8

- Die Einstellungsseite ist in verständliche Bereiche für Schuljahr,
  AG-Hauptseite und Klassenliste gegliedert.
- Die AG-Hauptseite kann per Seitensuche festgelegt werden. Neue
  AG-Detailseiten werden darunter angelegt, und das Demo-Setup liest
  veröffentlichte Unterseiten dieser Seite.
- Die Klassenliste für das Anmeldeformular kann gepflegt oder per Checkbox auf
  die Standardliste zurückgesetzt werden.

## Neu in 0.3.7

- Die AG-Zielgruppen und öffentlichen AG-Filter bleiben reine Jahrgänge
  (`Klasse 7` bis `Klasse 12`).
- Das Anmeldeformular verwendet wieder die konkrete Klasse der Schüler*innen,
  z. B. `7.1` oder `8.5`.
- Beim Absenden wird aus der gewählten Klasse der Jahrgang abgeleitet und
  gegen die freigegebenen AG-Jahrgänge validiert. Gespeichert werden Klasse
  und Jahrgangsschlüssel getrennt.

## Neu in 0.3.6

- Zielgruppen und Frontendfilter verwenden nur noch Jahrgänge, z. B.
  `Klasse 7`, `Klasse 8` usw.
- Alte Einzelklassenwerte in AG-Zielgruppen wie `7.1`, `8.3` oder `11_BENK`
  werden beim Lesen und Speichern automatisch auf den Jahrgang `7`, `8` bzw.
  `11` reduziert.
- Kurs-/WKK-Werte sind nicht mehr Teil der AG-Zielgruppenlogik.

## Neu in 0.3.5

- Die automatische Anmeldung auf AG-Detailseiten wird als Sticky-Button mit
  seitlichem Panel bzw. mobilem Bottom-Sheet angezeigt. Dadurch bleibt die
  Anmeldung auch bei langen AG-Beschreibungen gut erreichbar.
- Nach fehlgeschlagenem oder erfolgreichem Formular-POST öffnet das Panel
  direkt wieder, damit Meldungen und Eingaben sichtbar bleiben.
- Die Slot-Auswahl nutzt weiterhin echte Radio-Inputs für Validierung und
  Barrierefreiheit, zeigt aber nur noch die hervorgehobene Kartenzeile als
  sichtbare Auswahl.

## Neu in 0.3.4

- Öffentliche AG-Anmeldungen benötigen die E-Mail-Adresse der Schülerin bzw.
  des Schülers.
- Nach erfolgreicher Anmeldung wird eine Bestätigung per `wp_mail()` an diese
  Adresse gesendet.
- Schlägt der Mailversand fehl, bleibt die Anmeldung gespeichert, wird aber
  mit technischem Kontext protokolliert und im Frontend klar gemeldet.
- Lokal können Bestätigungsmails über DDEV-Mailpit/MailHog geprüft werden,
  ohne echte E-Mails zu versenden. Für automatisierte Tests kann zusätzlich
  der WordPress-Filter `pre_wp_mail` oder der Plugin-Filter
  `flz_ags_confirmation_mail` genutzt werden.

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
  Sie wird automatisch als Unterseite der eingestellten AG-Hauptseite
  veröffentlicht.
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
`{prefix}flz_ag_slots` und `{prefix}flz_ag_registrations` statt. Der laufende
Code kennt nur noch die modellabgeleiteten Tabellen. Falls lokale Altbestände
aus Zwischenständen vorhanden sind, können sie in der nicht produktiven
Entwicklungsumgebung gezielt manuell entfernt werden.

Die Shortcodes, Optionen und Administrations-URLs bleiben unverändert. Das
Plugin setzt `flz_wpdb_objects` voraus; WordPress erhält diese Abhängigkeit
zusätzlich über den Plugin-Header `Requires Plugins`.

## Fachmodell

- AGs gelten jeweils für ein Schuljahr.
- Jede AG hat ein Vorschaubild.
- Eine AG kann einen oder mehrere wöchentliche Slots haben.
- Die AG-Liste wird als Kachelübersicht ausgegeben: eine Kachel pro AG, die verfügbaren Slots stehen innerhalb der Kachel.
- Eine Anmeldung bezieht sich auf genau einen wöchentlichen Slot und gilt bis auf Widerruf.
- Jahrgang 7 kann als Pflichtwahl abgebildet werden, indem AGs zielgruppenseitig auf Klasse 7 eingeschränkt oder für Klasse 7 freigegeben werden.
- Die Klassenliste für das Anmeldeformular liegt in den Plugin-Einstellungen; AG-Zielgruppen verwenden unabhängig davon nur die Jahrgänge 7 bis 12.

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

Der Anmeldung-Shortcode wird normalerweise nicht mehr manuell platziert und
erscheint deshalb auch nicht im Gutenberg-Editor der AG-Detailseite. Die
Detailseite ist über `detail_page_id` mit der AG verknüpft; das Plugin ergänzt
auf dieser Seite automatisch einen Sticky-Button mit Anmelde-Panel. Wird der
Shortcode dennoch direkt verwendet, muss er auf der verknüpften Detailseite der
AG stehen. Eine explizite `course_id` kann nur dort sinnvoll sein, wenn die AG
nicht automatisch aus der aktuellen Seite ableitbar ist:

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

Das Demo-Setup liest die veröffentlichten Unterseiten der eingestellten
AG-Hauptseite aus.
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
- E-Mail-Zustellbarkeit auf Staging prüfen; lokal werden Mails über DDEV
  abgefangen.
- Kein externes Captcha, keine Akismet-Weitergabe von Anmeldedaten.
- Demo-Daten vor Produktivbetrieb löschen oder fachlich prüfen.

## Noch nicht enthalten

- Wartelistenautomatik
- Frontend-Widerruf durch Eltern/Schüler*innen
- automatische Löschroutine
- Import bestehender AG-Seiten
- erweiterte Rollenverwaltung
