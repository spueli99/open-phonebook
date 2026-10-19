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

Die erste Umsetzung verwendet einen manuell ausgelösten vollständigen Snapshot-Sync.

Abgerufen wird `people/me/connections` mit ausdrücklich ausgewählten Feldern:

- Namen
- Organisationen
- Biografien/Notizen
- Rufnummern

Alle Seiten werden vollständig abgearbeitet.

Google-`resourceName` wird als externe Quell-ID in `source_records` gespeichert.

Beim Sync:

- werden bereits zugeordnete Kontakte aktualisiert
- werden neue Google-Kontakte lokal angelegt
- werden Quellzuordnungen, die im vollständigen Google-Snapshot nicht mehr vorkommen, lokal soft-gelöscht
- dürfen ausschließlich Kontakte dieser Google-Quelle quellenbedingt gelöscht werden
- gelten Google-Datensätze, die aktuell nicht sauber in die lokale Contact-Domain abgebildet werden können, trotzdem als gesehen und werden übersprungen; dadurch wird eine bestehende lokale Zuordnung nicht versehentlich gelöscht

Die komplette lokale Anwendung des Snapshots erfolgt innerhalb einer Datenbanktransaktion.

## Aktuelle Grenzen

- Synchronisierung wird manuell ausgelöst; zeitgesteuerte Synchronisierung folgt später
- zunächst vollständiger Snapshot statt inkrementeller People-API-Sync-Tokens
- E-Mail-Adressen, Postanschriften, Geburtstage, Fotos und noch nicht im lokalen Kontaktmodell vorhandene Felder werden nicht importiert
- lokale Änderungen an quellenverwalteten Kontakten können beim nächsten Google-Sync überschrieben werden
- Schreibzugriff zurück zu Google ist bewusst nicht vorgesehen
