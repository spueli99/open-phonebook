# Geräte

## Umfang

Geräte sind nichtmenschliche Verbraucher wie Tischtelefone, Türsprechstellen oder Software-Endpunkte.

Geräte verwenden niemals die Browser-Sitzung oder das Passwort eines Benutzers.

Im ersten Modell gehört jedes Gerät genau einem Benutzer.

## Geräte-Credentials

Beim Anlegen eines Geräts wird zunächst ein Telefonbuch-Credential erzeugt. Ein Gerät kann unabhängig Credentials für folgende Scopes besitzen:

- `phonebook:read`
- `call-events:write`

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

Beim Deaktivieren eines Geräts werden alle aktiven Geräte-Credentials widerrufen. Eine spätere Reaktivierung erzeugt bewusst keine neuen Tokens; der Besitzer muss die benötigten Scopes ausdrücklich rotieren.

Für jedes Credential wird `last_used_at` erst dann aktualisiert, wenn die Authentifizierung erfolgreich war und auch die geschützte Gerätefunktion selbst erfolgreich abgeschlossen wurde. Ungültige Tokens, fehlerhafte Requests, fehlgeschlagene Telefonbuch-Exporte und abgewiesene Call-Events aktualisieren den Zeitstempel nicht. Ein Fehler beim reinen Telemetrie-Update macht einen ansonsten erfolgreichen Geräte-Request nicht nachträglich fehlerhaft.

Die Geräteverwaltung zeigt die letzte erfolgreiche Nutzung getrennt für die aktiven Scopes `phonebook:read` und `call-events:write` in der Zeitzone des angemeldeten Benutzers an. Ein neu rotiertes Credential wird bis zum ersten erfolgreichen Request als noch nie verwendet angezeigt.

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
- Phonebook-Credential rotieren/widerrufen
- Call-Event-Credential rotieren/widerrufen
- lesbare Adressbücher zuweisen

## Telefonbuch-Exporte

Der Geräte-Endpunkt lautet:

`GET /api/v1/device/phonebook/?format=<format>`

Implementiert sind `json`, `csv` und `yealink`.

Die Ausgabe erfolgt über eine herstellerneutrale Exporter-Schnittstelle. Der Endpunkt verwendet eine sitzungslose Geräte-Laufzeit, verlangt den bestehenden Scope `phonebook:read` und verwendet keine Browser-Sitzung.

Credentials werden ausschließlich über den HTTP-Authorization-Header angenommen, niemals über Token-Parameter in der URL. Private Antworten werden mit No-Store-Headern ausgeliefert.

Ein Abruf ist auf 5.000 Kontakte begrenzt. Der Yealink-Serializer verwirft XML-Ausgaben oberhalb von 1,5 MB. CSV-Ausgaben sichern führende Tabellenkalkulations-Formelzeichen ab.

## Call-Event-Credentials

Anrufereignisse verwenden den unabhängigen Scope `call-events:write`. Rotation oder Widerruf dieses Scopes beeinflusst `phonebook:read` nicht.

Siehe [Anrufereignisse](call-events.md).

## Später

- organisationsweite Geräte
- weitere Hersteller-Exporter
