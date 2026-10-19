# Kontaktimporte

## Aktueller Umfang

CSV- und vCard-Importe verwenden denselben ausdrücklichen Vorschau-und-Bestätigen-Ablauf. Nicht vertrauenswürdige Eingabedaten werden zunächst geprüft und angezeigt. Erst nach einer ausdrücklichen Bestätigung wird derselbe serverseitige Entwurf mit demselben formatbezogenen Parser erneut geprüft und gespeichert.

Die gemeinsame Importgrenze bleibt providerneutral, damit spätere externe Quellen dieselbe Anwendungsschicht nutzen können.

## CSV

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

## vCard

Der vCard-Adapter verwendet `sabre/vobject` und akzeptiert UTF-8-vCard-Dateien in den Versionen 2.1, 3.0 und 4.0. Mehrere Kontakte in einer `.vcf`-Datei werden unterstützt.

Übernommen werden:

- `FN` als Anzeigename
- `N` als Vor- und Nachname sowie als Namensfallback
- `ORG`
- `NOTE`
- alle `TEL`-Einträge einschließlich `TYPE`-Bezeichnungen

Fehlt `FN`, wird der Anzeigename zunächst aus `N` und danach aus `ORG` abgeleitet. Eine Karte ohne brauchbaren Anzeigenamen wird mit einem Hinweis übersprungen. vCard-4.0-`tel:`-URIs werden vor der Rufnummernnormalisierung auf den eigentlichen Rufnummernwert reduziert.

## Grenzen

Die Vorschau begrenzt:

- Uploadgröße auf maximal 2.000.000 Byte
- maximal 2.000 CSV-Datenzeilen
- maximal 2.000 vCards pro Datei
- gültige UTF-8-Kodierung
- keine NUL-Bytes
- Feldlängen entsprechend der Kontakt-Domain
- Schreibberechtigung auf das Zieladressbuch

Dateiendung und vom Browser gemeldeter MIME-Typ sind lediglich Komfortangaben. Der Server prüft den tatsächlichen eingelesenen Inhalt unabhängig davon.

## Vorschau und Bestätigung

Zeilen ohne `display_name` werden übersprungen und als Hinweis ausgegeben.

Eine Vorschau ohne importierbare Kontakte wird abgelehnt.

Nach erfolgreicher Vorschau bleiben der Dateiinhalt und das ausgewählte Importformat nur als ein kurzlebiger serverseitiger Session-Entwurf erhalten. Der Browser erhält ausschließlich einen zufälligen Entwurfs-Token, nicht den CSV-Inhalt. In der Session wird nur ein HMAC des Tokens zusammen mit Benutzer-ID, Zieladressbuch-ID, CSV-Inhalt und einer Ablaufzeit von 15 Minuten gespeichert.

Die Bestätigung erfolgt über einen separaten CSRF-geschützten POST. Der Entwurf muss weiterhin demselben Benutzer und Adressbuch gehören, darf nicht abgelaufen sein und der Benutzer muss weiterhin Schreibzugriff besitzen. Der Entwurf wird bei der Bestätigung erneut vollständig mit dem zum gespeicherten Format gehörenden Parser geparst und validiert.

Anschließend werden alle Kontakte innerhalb genau einer äußeren Datenbanktransaktion angelegt. Das Kontakt-Repository beteiligt sich an dieser Transaktion und committet nicht jeden einzelnen Kontakt separat. Der Entwurf wird erst nach erfolgreichem Commit verworfen.

## Dublettenerkennung

Vor der Bestätigung werden Importkandidaten anhand gültiger normalisierter E.164-Rufnummern verglichen. Eine Zeile gilt als Dublette, wenn mindestens eine normalisierte Rufnummer bereits im Zieladressbuch vorhanden ist oder dieselbe normalisierte Rufnummer bereits in einer vorherigen Importzeile vorkam.

Namen allein lösen niemals automatisch eine Dublettenentscheidung aus. Mehrdeutige oder nicht normalisierbare Rufnummern gelten nicht als ausreichender Dublettenbeleg.

Dubletten werden standardmäßig übersprungen. Bei einer Dublette zu einem bestehenden Kontakt wird `Zusammenführen` nur angeboten, wenn alle passenden normalisierten Rufnummern eindeutig genau einen bestehenden Kontakt ergeben. Bei mehrdeutigen Treffern wird keine Merge-Option angeboten. `Als separaten Kontakt importieren` bleibt eine ausdrückliche Alternative.

Ein Merge ist bewusst konservativ: Der bestehende Anzeigename bleibt erhalten, bereits befüllte optionale Felder werden niemals automatisch überschrieben, leere optionale Felder dürfen aus dem Import ergänzt werden und nur noch nicht vorhandene Rufnummern werden hinzugefügt. Für die Rufnummerndeduplizierung wird E.164 verwendet; wenn keine Normalisierung möglich ist, dient die bereinigte Rohdarstellung als Fallback. Bereits vorhandene Rufnummernbezeichnungen bleiben erhalten.

Bei der Bestätigung werden Dublettenstatus und Merge-Ziel erneut gegen den aktuellen Stand des Adressbuchs berechnet. Ist ein angeforderter Merge dann nicht mehr eindeutig, wird die gesamte Bestätigung abgelehnt, statt stillschweigend einen neuen Kontakt anzulegen oder ein anderes Ziel auszuwählen.

Das Importergebnis zeigt importierte, zusammengeführte und übersprungene Kontakte getrennt an.

## Sicherheit

Uploads gelten grundsätzlich als nicht vertrauenswürdig.

Die temporäre Upload-Datei wird nur gelesen, wenn PHP einen erfolgreichen Upload meldet und die serverseitige Größenprüfung bestanden wurde. Für die Vorschau wird die Datei nicht in einen dauerhaften Anwendungsspeicher verschoben.

Jede Vorschau per POST benötigt eine gültige Sitzung, Schreibzugriff auf das Zieladressbuch und einen gültigen CSRF-Token.

## Noch geplant

- Source-Record-Zuordnung für synchronisierte Quellen
