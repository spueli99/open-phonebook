# Kontaktimporte

## Aktueller Umfang

Der erste Import-Baustein ist ausschließlich eine Vorschau. Nicht vertrauenswürdige Eingabedaten werden geprüft und als mögliche Kontakte angezeigt, ohne Kontaktdaten zu speichern oder zu verändern.

Damit entsteht eine gemeinsame Importgrenze, auf der später vCard- und vollständige CSV-Importe aufbauen können.

## CSV-Vorschau

Für beschreibbare Adressbücher steht eine CSV-Importvorschau zur Verfügung.

Die Datei muss UTF-8-kodiert sein und eine Kopfzeile besitzen.

Pflichtspalte:

- `display_name`

Optionale Spalten:

- `given_name`
- `family_name`
- `organization_name`
- `notes`
- `phone_1`, `phone_1_label`
- `phone_2`, `phone_2_label`
- `phone_3`, `phone_3_label`

Unbekannte oder doppelte Spalten werden abgelehnt und nicht stillschweigend ignoriert.

## Grenzen

Die Vorschau begrenzt:

- Uploadgröße auf maximal 2.000.000 Byte
- maximal 2.000 Datenzeilen
- gültige UTF-8-Kodierung
- keine NUL-Bytes
- Feldlängen entsprechend der Kontakt-Domain
- Schreibberechtigung auf das Zieladressbuch

Dateiendung und vom Browser gemeldeter MIME-Typ sind lediglich Komfortangaben. Der Server prüft den tatsächlichen eingelesenen Inhalt unabhängig davon.

## Verhalten der Vorschau

Zeilen ohne `display_name` werden übersprungen und als Hinweis ausgegeben.

Eine Vorschau ohne importierbare Kontakte wird abgelehnt.

Die Vorschau legt keine Kontakte an, ändert keine Kontakte und löscht keine Kontakte. In diesem Entwicklungsstand gibt es bewusst noch keine Bestätigungs- oder Speicherfunktion.

## Sicherheit

Uploads gelten grundsätzlich als nicht vertrauenswürdig.

Die temporäre Upload-Datei wird nur gelesen, wenn PHP einen erfolgreichen Upload meldet und die serverseitige Größenprüfung bestanden wurde. Für die Vorschau wird die Datei nicht in einen dauerhaften Anwendungsspeicher verschoben.

Jede Vorschau per POST benötigt eine gültige Sitzung, Schreibzugriff auf das Zieladressbuch und einen gültigen CSRF-Token.

## Noch geplant

- tatsächliche CSV-Übernahme nach Bestätigung
- vCard-Parser und Vorschau
- Dublettenerkennung
- Zusammenführen-/Überspringen-Entscheidungen
- transaktionssichere Speicherung
- sichere Abbruch-/Rollback-Logik
- Source-Record-Zuordnung für synchronisierte Quellen
