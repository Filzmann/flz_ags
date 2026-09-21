# FLZ AG-Verwaltung 0.7.0

Initiale WordPress-Plugin-Version für AG-Verwaltung und AG-Anmeldung.

Commit-, CI- und Coverage-Gates sind enforced. Das
[Abnahmeprotokoll](docs/manual-acceptance.md) führt Installation, Upgrade,
Nebenläufigkeit, CSV, Datenschutz, Mail, Oberfläche und Rückbau zusammen.

Ein sauberer Commit wird reproduzierbar paketiert mit:

```bash
./scripts/build-release /tmp/flz_ags-release
```

## Neu in 0.7.0

- Die WordPress-Rolle „AG-Leiter“ erhält eine schreibgeschützte Ansicht der
  Anmeldungen ihrer im AG-Formular zugeordneten AGs.
- Die Einstellungen erlauben optional mehrere AG-Anmeldungen je Schüler*in
  und Schuljahr; eine doppelte aktive Anmeldung in derselben AG bleibt
  ausgeschlossen.
- Der öffentliche Anmeldedrawer zeigt keinen Raum mehr, liegt bei geöffnetem
  Formular über dem Header und enthält einen konfigurierbaren Hinweis, der
  standardmäßig auf die Gültigkeit für ein Schulhalbjahr hinweist.

## Neu in 0.6.2

- Die Backend-Anmeldungstabelle bietet Filter in sämtlichen Spaltenköpfen.
- Schüler*in, Klasse, AG, Slot, E-Mail, Status und Datum lassen sich direkt im
  Tabellenkopf auf- und absteigend sortieren; die reine Aktionsspalte bleibt
  bewusst unsortierbar.

## Neu in 0.6.1

- Die öffentliche AG-Liste kann nach Bereich, Klassenstufe, Dozent und
  Wochentag gefiltert, durchsucht und sortiert werden. Die Bedienung liegt in
  einem einklappbaren Filterbereich.
- Die Admin-Liste bietet Filter und auf-/absteigende Sortierung direkt in den
  fachlichen Tabellenköpfen.
- Inhaltsabhängige Asset-Versionen verhindern, dass Browser nach einem Update
  veraltetes JavaScript oder CSS weiterverwenden.

## Neu in 0.6.0

- Eine von der Plugin-Version getrennte DB-Version 2.0.0 führt den additiven,
  idempotenten Upgradepfad für bestehende Registrierungen.
- Ein datenschutzfreundlicher fachlicher Schlüssel und ein eindeutiger
  Datenbankindex garantieren höchstens eine aktive Anmeldung pro
  Schüler*in/Schuljahr – auch über verschiedene Slots hinweg.
- WordPress-Privacy-Exporter und -Eraser liefern beziehungsweise löschen alle
  Anmeldungen einer E-Mail-Adresse transaktional.
- Die lokale Mail-Capture-Ausweichlösung speichert keine Empfänger-, Namens-,
  Klassen-, Betreff- oder Nachrichtendaten mehr. Ein technischer Hinweis läuft
  nach höchstens einer Stunde ab und kann im Backend sofort gelöscht werden.
- Die lokale Bestandsmigration bewahrte 8 AGs, 10 Slots und 2 Anmeldungen; die
  Deaktivierung änderte diese Daten nicht.

## Neu in 0.5.0

- Der Anmeldungs-Download ist ein versioniertes, wiederherstellbares Backup und
  enthält unabhängig vom Tabellenfilter alle Status des gewählten Schuljahrs.
- Anmeldungs-Backups können direkt hochgeladen werden. Portable AG- und
  Terminschlüssel ersetzen installationsabhängige IDs; nicht auflösbare Zeilen
  werden mit Zeilennummer übersprungen und ungefährliche Lücken mit sicheren
  Platzhaltern ergänzt.
- Wiederholte Imports aktualisieren identische Backupdatensätze, versenden keine
  Bestätigungsmails und schützen eine bereits vorhandene andere aktive
  Anmeldung derselben Schüler*in.
- In den Einstellungen kann eine Aufbewahrungsfrist von 1 bis 120 Monaten
  aktiviert werden. Sie ist standardmäßig ausgeschaltet und zählt immer ab dem
  ursprünglichen Anmeldedatum.
- Ein täglicher WordPress-Cron-Job löscht abgelaufene Anmeldungen nur bei
  aktivierter Regel. Eine zusätzlich bestätigte manuelle Löschung zeigt die
  Anzahl dauerhaft gelöschter Datensätze; die Deaktivierung des Plugins löscht
  keine Daten.

## Neu in 0.4.0

- AG-Stammdaten und zugehörige Termine lassen sich als versionierte CSV-Datei
  exportieren und wieder importieren.
- Beim Export entscheidet eine standardmäßig aktivierte Checkbox, ob Termine
  beziehungsweise Slots enthalten sein sollen. Der Import erkennt beide
  Varianten automatisch.
- Der Import aktualisiert oder ergänzt Datensätze über portable fachliche
  Schlüssel; nicht aufgeführte AGs und Termine bleiben erhalten.
- Beim Import können die Schuljahre der CSV beibehalten oder alle enthaltenen
  AGs samt Slots einem ausgewählten Zielschuljahr zugeordnet werden. Kollidiert
  dabei derselbe Slug aus mehreren Quelljahren, gewinnt das neueste Quelljahr
  und der ausgelassene Datensatz wird gemeldet.
- Der Upload importiert brauchbare Datensätze direkt. Fehlende optionale Werte
  erhalten sichere Standardwerte; fehlerhafte oder doppelte Einzelzeilen werden
  ausgelassen, ohne die übrige Datei zu verwerfen.
- Nach dem Import zeigt die AG-Verwaltung eine Bilanz und zeilenbezogene Hinweise
  zu allen Korrekturen und ausgelassenen Datensätzen.
- Capability, Nonce und Grundformat werden serverseitig geprüft; alle
  akzeptierten Datensätze werden gemeinsam in einer Transaktion übernommen.

## Neu in 0.3.13

- Bei AGs ohne Detailseite öffnet der primäre Detailbutton „Anmeldung“ das
  Anmeldeformular im gleichen zugänglichen Drawer wie der Floating-Button
  einer Detailseite. Er entspricht optisch dem regulären Button „Details und
  Anmeldung“ einer AG mit Detailseite.
- Das Formular wird nicht mehr sichtbar unter der AG-Karte angehängt.
- „AG speichern“ verwendet das Speichern-Icon, „Speichern und neu“ ein
  kombiniertes Disketten-/Plus-Icon.

## Neu in 0.3.12

- AGs ohne gültige Detailseite zeigen bei geöffneter Anmeldung das
  Anmeldeformular direkt unter der AG-Karte in der öffentlichen Liste.
- Auf Listen mit mehreren Formularen werden POST-Daten nur im Formular der
  tatsächlich abgesendeten AG verarbeitet und wieder angezeigt.

## Neu in 0.3.11

- Die Gutenberg-Blöcke bieten vorhandene AG-Schuljahre als Dropdown an.
- Das Standardschuljahr wechselt mit Beginn der amtlichen Berliner
  Sommerferien auf das kommende Schuljahr. Die veröffentlichten Termine bis
  2030 sind hinterlegt; für spätere Jahre bleibt der 1. August der Fallback.

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

### Historischer Migrationshinweis

Die alten Zwischenstandstabellen `{prefix}flz_ag_courses`,
`{prefix}flz_ag_slots` und `{prefix}flz_ag_registrations` sind nicht Teil des
aktuellen DB-2.0-Vertrags. Der Upgradepfad verändert oder löscht sie nicht. Der
laufende Code verwendet ausschließlich die modellabgeleiteten Tabellen; eine
spätere Bereinigung alter Zwischenstände benötigt eine gesonderte Freigabe.

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
- Die schulische Löschfrist festlegen und unter `FLZ AGs → Einstellungen`
  aktivieren. Standard sind 24 Monate ab Anmeldedatum; die Automatik ist bis
  zur bewussten Aktivierung ausgeschaltet.
- Vor einer manuellen Löschung bei Bedarf auf `FLZ AGs → Anmeldungen` ein
  vollständiges CSV-Backup des betreffenden Schuljahrs herunterladen.
- E-Mail-Zustellbarkeit auf Staging prüfen; lokal werden Mails über DDEV
  abgefangen.
- Kein externes Captcha, keine Akismet-Weitergabe von Anmeldedaten.
- Demo-Daten vor Produktivbetrieb löschen oder fachlich prüfen.

## Noch nicht enthalten

- Wartelistenautomatik
- Frontend-Widerruf durch Eltern/Schüler*innen
- Import bestehender AG-Seiten
- erweiterte Rollenverwaltung
