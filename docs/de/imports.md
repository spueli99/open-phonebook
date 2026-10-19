# Kontaktimporte

## Aktueller Umfang

CSV-Importe verwenden jetzt einen ausdrücklichen Vorschau-und-Bestätigen-Ablauf. Nicht vertrauenswürdige Eingabedaten werden zunächst geprüft und angezeigt. Erst nach einer ausdrücklichen Bestätigung wird derselbe serverseitige Entwurf erneut geprüft und gespeichert.

Die gemeinsame Importgrenze bleibt providerneutral, damit spätere vCard- und externe Quellen dieselbe Anwendungsschicht nutzen können.

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

## Vorschau und Bestätigung

Zeilen ohne `display_name` werden übersprungen und als Hinweis ausgegeben.

Eine Vorschau ohne importierbare Kontakte wird abgelehnt.

Nach erfolgreicher Vorschau bleibt der CSV-Inhalt nur als ein kurzlebiger serverseitiger Session-Entwurf erhalten. Der Browser erhält ausschließlich einen zufälligen Entwurfs-Token, nicht den CSV-Inhalt. In der Session wird nur ein HMAC des Tokens zusammen mit Benutzer-ID, Zieladressbuch-ID, CSV-Inhalt und einer Ablaufzeit von 15 Minuten gespeichert.

Die Bestätigung erfolgt über einen separaten CSRF-geschützten POST. Der Entwurf muss weiterhin demselben Benutzer und Adressbuch gehören, darf nicht abgelaufen sein und der Benutzer muss weiterhin Schreibzugriff besitzen. Die CSV-Datei wird bei der Bestätigung erneut vollständig geparst und validiert.

Anschließend werden alle Kontakte innerhalb genau einer äußeren Datenbanktransaktion angelegt. Das Kontakt-Repository beteiligt sich an dieser Transaktion und committet nicht jeden einzelnen Kontakt separat. Der Entwurf wird erst nach erfolgreichem Commit verworfen.

## Sicherheit

Uploads gelten grundsätzlich als nicht vertrauenswürdig.

Die temporäre Upload-Datei wird nur gelesen, wenn PHP einen erfolgreichen Upload meldet und die serverseitige Größenprüfung bestanden wurde. Für die Vorschau wird die Datei nicht in einen dauerhaften Anwendungsspeicher verschoben.

Jede Vorschau per POST benötigt eine gültige Sitzung, Schreibzugriff auf das Zieladressbuch und einen gültigen CSRF-Token.

## Noch geplant

- vCard-Parser und Vorschau
- Dublettenerkennung
- Zusammenführen-/Überspringen-Entscheidungen
- ausdrückliche Behandlung doppelter Importzeilen vor der Speicherung
- Source-Record-Zuordnung für synchronisierte Quellen
