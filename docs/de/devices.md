# Geräte

## Umfang

Geräte sind nichtmenschliche Verbraucher wie Tischtelefone, Türsprechstellen oder Software-Endpunkte.

Geräte verwenden niemals die Browser-Sitzung oder das Passwort eines Benutzers.

Im ersten Modell gehört jedes Gerät genau einem Benutzer.

## Geräte-Credentials

Beim Anlegen eines Geräts wird ein Credential mit folgendem Scope erzeugt:

`phonebook:read`

Der Klartext-Token wird nur unmittelbar nach dem Anlegen oder Rotieren angezeigt.

In der Datenbank wird ausschließlich ein HMAC-SHA-256-Hash gespeichert. Der HMAC-Eingang ist für Geräte-Credentials domänengetrennt und wird mit dem Application Key signiert.

Ein Credential ist nur gültig, wenn:

- das Tokenformat gültig ist
- der HMAC einem aktiven, nicht widerrufenen Credential entspricht
- der angeforderte Scope übereinstimmt
- das Gerät aktiv ist
- das Besitzerkonto aktiv und nicht gelöscht ist

Beim Rotieren werden alle noch aktiven Credentials desselben Geräts und Scopes widerrufen, bevor ein neuer Token erzeugt wird.

Ein Widerruf lässt das Gerät bestehen, entfernt aber den nutzbaren Zugang.

Beim Deaktivieren eines Geräts wird das aktive Phonebook-Credential automatisch widerrufen. Eine spätere Reaktivierung erzeugt bewusst keinen neuen Token; der Besitzer muss anschließend ausdrücklich rotieren.

## Adressbuchzuordnung

Einem Gerät dürfen nur Adressbücher zugewiesen werden, die sein Besitzer aktuell lesen darf.

Die Zuordnung ist ausdrücklich. Ein Gerät erhält nicht automatisch alle Adressbücher seines Besitzers.

Telefonbuch-Export-Endpunkte schneiden die gespeicherte Gerätezuordnung bei jedem Abruf erneut mit dem aktuellen Adressbuchzugriff des Besitzers. So kann eine alte Gerätezuordnung keine später widerrufene Freigabe umgehen.

## Verwaltung

Jeder angemeldete Benutzer verwaltet ausschließlich seine eigenen Geräte.

Aktuell möglich:

- Gerät anlegen
- Hersteller/Modell hinterlegen
- Gerät aktivieren/deaktivieren
- Phonebook-Credential rotieren
- Phonebook-Credential widerrufen
- lesbare Adressbücher zuweisen

## Telefonbuch-Exporte

Der Geräte-Endpunkt lautet:

`GET /api/v1/device/phonebook/?format=<format>`

Implementiert sind `json`, `csv` und `yealink`.

Die Ausgabe erfolgt über eine herstellerneutrale Exporter-Schnittstelle. Der Endpunkt verwendet eine sitzungslose Geräte-Laufzeit, verlangt den bestehenden Scope `phonebook:read` und verwendet keine Browser-Sitzung.

Credentials werden ausschließlich über den HTTP-Authorization-Header angenommen, niemals über Token-Parameter in der URL. Private Antworten werden mit No-Store-Headern ausgeliefert.

Ein Abruf ist auf 5.000 Kontakte begrenzt. Der Yealink-Serializer verwirft XML-Ausgaben oberhalb von 1,5 MB. CSV-Ausgaben sichern führende Tabellenkalkulations-Formelzeichen ab.

## Später

- Call-Event-Credentials/Scopes
- organisationsweite Geräte
- Last-used-Aktualisierung im Authentifizierungspfad
- weitere Hersteller-Exporter
