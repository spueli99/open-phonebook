# HTTP-API-Referenz

## Umfang

Open Phonebook stellt aktuell bewusst nur eine kleine öffentliche HTTP-API für Geräte bereit.

Implementierte öffentliche API-Endpunkte:

- `GET /api/v1/device/phonebook/?format=<format>`
- `POST /api/v1/device/call-events/`
- `GET /api/v1/device/yealink/action-url/`

Browser-Endpunkte der Weboberfläche gehören nicht zur öffentlichen Geräte-API und unterliegen nicht den Kompatibilitätszusagen dieser Referenz.

Aktuell gibt es keine öffentliche API für Benutzerverwaltung, Änderungen an Adressbüchern, Benachrichtigungsverwaltung, Audit-Daten oder Betriebsdiagnose.

## Basispfad und Versionierung

Aktueller Namespace:

`/api/v1/`

Inkompatible Änderungen an der öffentlichen API erfordern eine neue API-Version. Kompatible Erweiterungen können innerhalb von `v1` erfolgen.

## Transportsicherheit

Produktive Gerätezugriffe müssen über HTTPS erfolgen.

Geräte-Credentials sind Secrets und dürfen niemals in:

- Query-Strings
- Callback-URLs
- Links mit möglichem Referer
- Logs
- öffentlich gerenderten Konfigurationen

abgelegt werden.

Credentials werden ausschließlich über den HTTP-`Authorization`-Header bzw. die entsprechenden PHP-Basic-Auth-Servervariablen angenommen.

## Geräteauthentifizierung

Jedes Geräte-Credential besitzt einen Scope.

Aktuelle Scopes:

- `phonebook:read`
- `call-events:write`
- `yealink-callback:write`

Ein Credential wird nur akzeptiert, wenn:

- der Token das erwartete Format aus 64 kleingeschriebenen Hex-Zeichen besitzt
- sein HMAC zu einem aktiven Credential passt
- der angeforderte Scope übereinstimmt
- das Credential nicht widerrufen ist
- das Gerät aktiv ist
- der Besitzer aktiv und nicht gelöscht ist

Zwei Authentifizierungsformen werden unterstützt.

Bearer:

`Authorization: Bearer <device-token>`

HTTP Basic für Telefon-Kompatibilität:

- Benutzername: `device`
- Passwort: `<device-token>`

Beispiel:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=json'
```

Nur ein erfolgreich abgeschlossener geschützter Gerätezugriff aktualisiert `last_used_at` des konkret verwendeten Credentials. Eine erfolgreiche Token-Prüfung allein reicht nicht; abgewiesene Requests oder fehlgeschlagene Funktionen zählen nicht als erfolgreiche Nutzung.

## Allgemeines Antwortverhalten

Private Geräteantworten verwenden No-Store-Header. Erfolgreiche Phonebook- und Call-Event-Antworten enthalten:

- `Cache-Control: no-store, private`
- `Pragma: no-cache`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: no-referrer`

Authentifizierungsfehler liefern `401 Unauthorized` und kündigen Bearer- sowie Basic-Authentifizierung an.

Die öffentliche API liefert derzeit keine detaillierten maschinenlesbaren Fehlerobjekte. Clients sollen sich auf HTTP-Statuscodes stützen; Fehler-Response-Bodies sind nicht Teil des stabilen Vertrags.

---

# Telefonbuch-Export

## Endpunkt

`GET /api/v1/device/phonebook/?format=<format>`

Erforderlicher Scope:

`phonebook:read`

Der Endpunkt ist sitzungslos und erstellt bzw. verwendet keine Browser-Sitzung.

Bei jedem Abruf werden ausschließlich Kontakte aus Adressbüchern exportiert, die:

1. dem Gerät ausdrücklich zugewiesen sind
2. für den Gerätebesitzer weiterhin lesbar sind

Eine alte Gerätezuweisung kann damit keinen später widerrufenen Adressbuchzugriff erhalten.

## Query-Parameter

### `format`

Pflichtfeld.

Erlaubte Syntax:

- Kleinbuchstaben
- Ziffern
- Unterstrich
- Bindestrich

Implementierte Werte:

- `json`
- `csv`
- `yealink`

Unbekannte, aber syntaktisch gültige Formate liefern `404 Not Found`.

Ungültige Formatsyntax liefert `400 Bad Request`.

## Kontaktlimit

Eine Phonebook-Antwort ist durch den Phonebook-Service auf 5.000 Kontakte begrenzt.

Der Yealink-Serializer verwirft zusätzlich erzeugtes XML oberhalb von 1,5 MB.

## JSON-Format

Request:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=json'
```

Content-Type:

`application/json; charset=utf-8`

Dateiname:

`phonebook.json`

Antwortschema:

```json
{
  "version": 1,
  "contacts": [
    {
      "name": "Alice Example",
      "numbers": [
        {
          "number": "+491701234567",
          "label": "mobile"
        },
        {
          "number": "0211 1234",
          "label": null
        }
      ]
    }
  ]
}
```

Felder:

- `version`: Version des Exportschemas, aktuell `1`
- `contacts[].name`: Anzeigename
- `contacts[].numbers[].number`: exportierte Rufnummer
- `contacts[].numbers[].label`: optionale Kennzeichnung oder `null`

## CSV-Format

Request:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=csv'
```

Content-Type:

`text/csv; charset=utf-8`

Dateiname:

`phonebook.csv`

Spalten:

```text
display_name,number,label
```

Jede Rufnummer erzeugt eine eigene CSV-Zeile.

Alle Zellen werden gequotet. Führende Tabellenkalkulations-Formelzeichen `=`, `+`, `-` und `@` werden mit einem führenden Apostroph neutralisiert.

## Yealink-Format

Request:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=yealink'
```

Content-Type:

`application/xml; charset=utf-8`

Dateiname:

`phonebook.xml`

Struktur:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<YealinkIPPhoneDirectory>
  <DirectoryEntry>
    <Name>Alice Example</Name>
    <Telephone>+491701234567</Telephone>
    <Telephone>0211 1234</Telephone>
  </DirectoryEntry>
</YealinkIPPhoneDirectory>
```

## Phonebook-Statuscodes

- `200 OK`: Export erfolgreich erzeugt
- `400 Bad Request`: ungültige `format`-Syntax
- `401 Unauthorized`: Credential fehlt, ist fehlerhaft, ungültig, widerrufen, besitzt den falschen Scope oder Gerät/Besitzer sind nicht aktiv
- `404 Not Found`: syntaktisch gültiges, aber nicht unterstütztes Exportformat
- `405 Method Not Allowed`: Methode ist nicht GET
- `500 Internal Server Error`: Exporterzeugung fehlgeschlagen
- `503 Service Unavailable`: Anwendungslaufzeit konnte nicht gestartet werden

Bei `405`:

`Allow: GET`

---

# Anrufereignisse

## Endpunkt

`POST /api/v1/device/call-events/`

Erforderlicher Scope:

`call-events:write`

Content-Type:

`application/json`

Maximale Request-Größe:

16 KiB.

Der Controller liest maximal 16.385 Bytes und verwirft Bodies oberhalb von 16.384 Bytes.

## Kanonisches Event-Objekt

Beispiel für einen beginnenden eingehenden Anruf:

```json
{
  "event": "ringing",
  "call_id": "abc-123",
  "direction": "incoming",
  "local_number": "100",
  "remote_number": "+491701234567"
}
```

### `event`

Pflicht-String, maximal 32 Zeichen.

Unterstützte Werte:

- `ringing`
- `calling`
- `connected`
- `idle`

### `call_id`

Nichtleerer Pflicht-String, maximal 191 Zeichen.

Die ID gilt innerhalb des authentifizierten Geräts.

Offene Calls werden über Gerät plus `call_id` zugeordnet.

Nach Erreichen eines terminalen Zustands darf dieselbe externe Call-ID später erneut verwendet werden.

### `direction`

Im JSON-Parser optional, für ein initiales Event durch die Call-State-Logik jedoch erforderlich.

Unterstützte Werte:

- `incoming`
- `outgoing`

Gültige initiale Kombinationen:

- `ringing` + `incoming`
- `calling` + `outgoing`

Folgeereignisse werden der vorhandenen Sitzung zugeordnet und müssen die Richtung nicht erneut mitsenden.

### `local_number`

Optionaler String, maximal 120 Zeichen.

Leere Strings werden zu `null`.

### `remote_number`

Optionaler String, maximal 120 Zeichen.

Leere Strings werden zu `null`.

Soweit möglich dient die Gegenstellen-Rufnummer dem scoped Caller Lookup.

## Zustandsübergänge

Initial:

- eingehend `ringing` -> `ringing`
- ausgehend `calling` -> `calling`

Folgeereignisse:

- `connected` -> `connected`
- `idle` nach connected -> `completed`
- `idle` nach eingehendem ringing -> `missed`
- `idle` nach ausgehendem calling -> `cancelled`

Wiederholte gültige `ringing`-, `calling`- und `connected`-Callbacks sind idempotent.

Terminale Sitzungen werden nicht erneut geöffnet.

Ungültige Übergänge werden abgewiesen.

## Caller Lookup

Die Namensauflösung ist auf Adressbücher beschränkt, die:

1. dem authentifizierten Gerät zugewiesen sind
2. vom Gerätebesitzer weiterhin gelesen werden dürfen

Es wird ausschließlich über normalisierte Rufnummern gesucht.

Der gefundene Kontaktname wird als historischer Snapshot in der Call-Session gespeichert.

## Erfolgsantwort

Beispiel:

```json
{
  "call_id": "abc-123",
  "state": "ringing",
  "direction": "incoming",
  "contact_name": "Alice Example"
}
```

`contact_name` kann `null` sein.

Die Antwort enthält den abgeleiteten Zustand der Call-Session und nicht nur den eingesendeten Eventnamen.

## Beispielrequest

```sh
curl --fail-with-body \
  -X POST \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  -H 'Content-Type: application/json' \
  --data '{
    "event": "ringing",
    "call_id": "abc-123",
    "direction": "incoming",
    "local_number": "100",
    "remote_number": "+491701234567"
  }' \
  'https://phonebook.example/api/v1/device/call-events/'
```

## Call-Event-Statuscodes

- `200 OK`: Event angenommen und Call-Session-Zustand zurückgegeben
- `400 Bad Request`: ungültiges JSON, fehlerhafte/fehlende Felder, unbekannter Wert oder ungültiger Zustandsübergang
- `401 Unauthorized`: Credential fehlt, ist fehlerhaft, ungültig, widerrufen, besitzt den falschen Scope oder Gerät/Besitzer sind nicht aktiv
- `405 Method Not Allowed`: Methode ist nicht POST
- `413 Content Too Large`: Request-Body größer als 16 KiB
- `415 Unsupported Media Type`: Content-Type beginnt nicht mit `application/json`
- `503 Service Unavailable`: Anwendungslaufzeit konnte nicht gestartet werden

Bei `405`:

`Allow: POST`

Fehler externer Notification-Provider verändern ein bereits akzeptiertes Call-Event nicht. Die Notification-Verarbeitung erfolgt nach dem Datenbank-Commit der Call-State-Änderung und ist vom Geräte-Response isoliert.

---

# Yealink-Action-URL-Callback

Endpunkt:

`GET /api/v1/device/yealink/action-url/`

Erforderlicher Scope:

`yealink-callback:write`

Der Endpunkt ist ausschließlich über HTTPS nutzbar und akzeptiert das separate Callback-Credential nur per HTTP Basic mit dem Benutzernamen `device`. Bearer-Credentials werden abgewiesen. Credentials werden niemals aus Query-Parametern oder Pfadsegmenten gelesen.

Erforderliche Query-Felder:

- `event`
- `call_id`

Optionale Query-Felder:

- `local`
- `remote`

Nur diese dokumentierten Yealink-Werte werden an die Normalisierung weitergegeben.


Unterstützte Adapter-Mappings unter anderem:

- `incoming_call` -> `ringing` / `incoming`
- `outgoing_call` -> `calling` / `outgoing`
- `call_established` -> `connected`
- `on_hook` -> `idle`
- `missed_call` -> `idle`
- `cancel_callout` -> `idle`
- `remote_busy` -> `idle`
- `call_remote_canceled` -> `idle`

Erfolgreiche Callbacks liefern `200 OK` mit einer kleinen Textantwort. Ungültige Credentials liefern `401`, fehlerhafte oder nicht unterstützte Events `400`, andere HTTP-Methoden `405`; nicht per HTTPS eingehende Requests werden mit `400` abgewiesen.

Ein normales `phonebook:read`- oder `call-events:write`-Credential darf für diesen Endpunkt niemals wiederverwendet werden. Das separate Callback-Credential kann unabhängig rotiert oder widerrufen werden.

---

# Grenzen der öffentlichen API

Für folgende Bereiche existiert aktuell keine öffentliche HTTP-API:

- Benutzer und Einladungen
- Änderungen an Adressbüchern/Kontakten
- Kontaktimporte
- Google-OAuth-/Sync-Verwaltung
- Geräteverwaltung und Credential-Rotation
- Notification-Kanäle/-Regeln/-Historie/-Retry
- Web-Push-Verwaltung
- Audit-Ereignisse
- Backup-/Update-Operationen
- Betriebsdiagnose

Diese Funktionen stehen derzeit über authentifizierte Browserabläufe, CLI-Kommandos oder interne Application Services zur Verfügung.

Es gibt bewusst keinen unauthentifizierten Health-Endpunkt.

---

# Hinweise für Clients

Geräteclients sollten:

- HTTPS verwenden
- Credentials niemals in URLs schreiben
- nur den benötigten Credential-Scope verwenden
- `401` als Credential-/Konfigurationsproblem behandeln
- `400` bei Call Events als Payload- oder Zustandsproblem behandeln
- den Response-Content-Type auswerten
- Phonebook-Antworten nicht cachen
- zusätzliche kompatible JSON-Felder innerhalb von `v1` tolerieren
- sich nicht auf Fehler-Response-Bodies verlassen

Nach Rotation oder Widerruf eines Credentials muss der Client mit dem neu ausgegebenen Token aktualisiert werden. Klartext-Geräte-Tokens können vom Server nicht wiederhergestellt werden.
