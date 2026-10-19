# Google Kontakte

## Umfang

Open Phonebook unterstützt optional eine schreibgeschützte Google-Kontakte-Quelle für ein persönliches Adressbuch.

Open Phonebook schreibt niemals Kontakte zu Google zurück.

Verwendet wird ausschließlich der OAuth-Scope:

`https://www.googleapis.com/auth/contacts.readonly`

## Konfiguration

Die Google-OAuth-Zugangsdaten sind Laufzeitgeheimnisse und werden nicht in der Datenbank gespeichert.

Konfiguration entweder in `config/local.php`:

```php
'providers' => [
    'google' => [
        'client_id' => '...',
        'client_secret' => '...',
    ],
],
```

oder über:

- `OPENPHONEBOOK_GOOGLE_CLIENT_ID`
- `OPENPHONEBOOK_GOOGLE_CLIENT_SECRET`

Die konfigurierte `base_url` muss HTTPS verwenden. Im Google-Cloud-OAuth-Client ist folgende Redirect-URI einzutragen:

`<base_url>/sources/google/callback/`

## OAuth-Sicherheit

Der Autorisierungsablauf verwendet:

- ausschließlich den Contacts-Read-only-Scope
- `access_type=offline`
- ausdrückliche Zustimmung zur Ermittlung eines Refresh-Tokens
- OAuth-`state`-Prüfung
- PKCE mit SHA-256
- eine State-Gültigkeit von 10 Minuten
- Bindung an angemeldeten Benutzer und Zieladressbuch
- einmalige State-Verwendung

Nur der Eigentümer eines Adressbuchs darf Google Kontakte verbinden oder neu verbinden.

## Token-Speicherung

Das Google-Client-Secret bleibt in der Laufzeitkonfiguration.

Der Refresh-Token wird vor der Datenbankspeicherung mit AES-256-GCM verschlüsselt. Das Schlüsselmaterial wird mittels HKDF-SHA-256 aus dem Application Key abgeleitet.

In der Datenbank liegt ausschließlich der verschlüsselte Refresh-Token. Kurzlebige Access-Tokens werden nicht gespeichert.

Wird der Application Key ohne Migration der Provider-Geheimnisse geändert, können gespeicherte Provider-Zugangsdaten nicht mehr entschlüsselt werden.

## Synchronisierung

Die Google-Synchronisierung verwendet zunächst einen vollständigen Snapshot und anschließend inkrementelle People-API-Sync-Token-Abfragen.

Abgerufen wird `people/me/connections` mit ausdrücklich ausgewählten Feldern:

- Namen
- Organisationen
- Biografien/Notizen
- Rufnummern

Alle Seiten werden vollständig abgearbeitet. Beim ersten vollständigen Sync wird ein `nextSyncToken` angefordert und vor der Datenbankspeicherung verschlüsselt. Folgende Synchronisierungen verwenden diesen Token und verarbeiten nur geänderte Ressourcen.

Google-Sync-Tokens laufen nach sieben Tagen ab. Liefert Google für einen abgelaufenen Token HTTP 410, führt Open Phonebook automatisch einen neuen Full-Sync durch und speichert anschließend den neuen Token.

Google-`resourceName` wird als externe Quell-ID in `source_records` gespeichert.

Beim vollständigen Sync:

- werden bereits zugeordnete Kontakte aktualisiert
- werden neue Google-Kontakte lokal angelegt
- werden Quellzuordnungen, die im vollständigen Google-Snapshot nicht mehr vorkommen, lokal soft-gelöscht
- dürfen ausschließlich Kontakte dieser Google-Quelle quellenbedingt gelöscht werden
- gelten Google-Datensätze, die aktuell nicht sauber in die lokale Contact-Domain abgebildet werden können, trotzdem als gesehen und werden übersprungen; dadurch wird eine bestehende lokale Zuordnung nicht versehentlich gelöscht

Beim inkrementellen Sync:

- werden ausschließlich geänderte Google-Ressourcen verarbeitet
- entfernt `metadata.deleted=true` den lokal zugeordneten Quellkontakt
- bleiben Ressourcen, die im Delta nicht vorkommen, unverändert
- wird `metadata.previousResourceNames` genutzt, damit bei einer von Google geänderten Resource-ID weiterhin derselbe lokale Kontakt zugeordnet bleibt

Jeder vollständige oder inkrementelle Batch wird innerhalb einer Datenbanktransaktion angewendet. Der neue Sync-Token wird erst nach erfolgreicher Transaktion gespeichert.

## Zeitgesteuerte Synchronisierung

Ein dauerhaft laufender Worker ist nicht erforderlich. Für cron wird ausgeführt:

```bash
php /pfad/zu/open-phonebook/bin/google-contacts-sync.php
```

Der Befehl synchronisiert alle eingerichteten Google-Quellen unabhängig voneinander. Der Fehler einer Quelle verhindert nicht die Synchronisierung der übrigen. Exitcode `0` bedeutet vollständigen Erfolg, `1` mindestens eine fehlgeschlagene Quelle und `2`, dass der Scheduler nicht gestartet werden konnte.

Eine tägliche Ausführung hält den inkrementellen Token sicher innerhalb der siebentägigen Google-Gültigkeit. Häufigere Läufe, beispielsweise stündlich, sind ebenfalls möglich.

## Aktuelle Grenzen

- E-Mail-Adressen, Postanschriften, Geburtstage, Fotos und noch nicht im lokalen Kontaktmodell vorhandene Felder werden nicht importiert
- lokale Änderungen an quellenverwalteten Kontakten können beim nächsten Google-Sync überschrieben werden
- Schreibzugriff zurück zu Google ist bewusst nicht vorgesehen
