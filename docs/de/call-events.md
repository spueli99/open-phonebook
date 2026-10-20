# Anrufereignisse

## Umfang

Anrufereignisse sind von Geräten gelieferte Metadaten über den Ablauf eines Anrufs. Open Phonebook ist keine PBX und übernimmt weder SIP-Registrierung noch Routing oder Medienübertragung.

Der Geräte-Scope lautet:

`call-events:write`

Er ist vollständig von `phonebook:read` getrennt. Rotation oder Widerruf eines Scopes beeinflusst den anderen nicht. Beim Deaktivieren eines Geräts werden beide Scopes widerrufen.

## Generische API

Endpunkt:

`POST /api/v1/device/call-events/`

Die Authentifizierung erfolgt über den bestehenden Geräte-`Authorization`-Header und verlangt `call-events:write`.

Der Request-Body muss JSON sein und ist auf 16 KiB begrenzt.

Kanonische Ereignisse:

- `ringing`
- `calling`
- `connected`
- `idle`

Richtungen:

- `incoming`
- `outgoing`

Die Richtung ist nur beim initialen `ringing`- bzw. `calling`-Ereignis erforderlich. Folgeereignisse werden über Gerät und externe `call_id` der bestehenden Session zugeordnet.

## Session-Zustände

Offene Sessions werden über Gerät und externe Call-ID zugeordnet. Nach Abschluss darf dieselbe externe Call-ID bei einem späteren Anruf erneut verwendet werden.

- eingehend `ringing` -> `ringing`
- ausgehend `calling` -> `calling`
- `connected` -> `connected`
- `idle` nach connected -> `completed`
- `idle` nach eingehendem ringing -> `missed`
- `idle` nach ausgehendem calling -> `cancelled`

Wiederholte ringing/calling/connected-Callbacks sind idempotent. Beendete Sessions werden nicht erneut geöffnet. Ungültige Zustandswechsel werden abgewiesen.

## Anruferauflösung

Die Anruferauflösung verwendet dieselbe Berechtigungsgrenze wie Geräte-Telefonbuch-Exporte.

Berücksichtigt werden ausschließlich Adressbücher, die:

1. dem Gerät ausdrücklich zugewiesen sind und
2. vom Gerätebesitzer aktuell gelesen werden dürfen.

Die Zuordnung erfolgt nur über normalisierte Rufnummern. Eine alte Gerätezuordnung kann eine später entzogene Freigabe nicht umgehen.

## Yealink-Action-URL-Adapter

Der Yealink-Adapter bildet unterstützte Action-URL-Ereignisse auf das neutrale Modell ab:

- `incoming_call` -> ringing/incoming
- `outgoing_call` -> calling/outgoing
- `call_established` -> connected
- `on_hook`, `missed_call`, `cancel_callout`, `remote_busy`, `call_remote_canceled` -> idle

SIP-URIs aus Yealink `$local` und `$remote` werden vor der Rufnummernnormalisierung auf den Benutzer-/Rufnummernteil reduziert.

Der direkte Callback-Endpunkt lautet:

`GET /api/v1/device/yealink/action-url/`

Er verlangt ein eigenes Credential mit dem Scope `yealink-callback:write`. Dieses Credential wird ausschließlich per HTTP Basic mit dem Benutzernamen `device` akzeptiert. Bearer-Authentifizierung ist für diesen Endpunkt bewusst nicht zugelassen.

Der Endpunkt verlangt HTTPS und akzeptiert niemals Credentials aus Query-Parametern oder Pfadsegmenten. Das Callback-Credential ist von `phonebook:read` und `call-events:write` vollständig getrennt, kann separat rotiert/widerrufen werden und wird beim Deaktivieren des Geräts automatisch ebenfalls widerrufen.

Bei Yealink-Firmware, die URI-Userinfo in HTTP Basic umsetzt, wird der separate Callback-Token als Basic-Passwort in der Action URL verwendet. Beispiel:

```text
https://device:CALLBACK_TOKEN@phonebook.example/api/v1/device/yealink/action-url/?event=incoming_call&call_id=$call_id&local=$local&remote=$remote
```

Der Eventname muss zur jeweiligen Yealink-Action-URL-Einstellung passen, z. B. `incoming_call`, `outgoing_call`, `call_established`, `on_hook`, `missed_call`, `cancel_callout`, `remote_busy` oder `call_remote_canceled`.

Der Token darf niemals zusätzlich als Query-Parameter übertragen werden. Falls ein Telefon bzw. eine Firmware bei Action URLs aus URI-Userinfo keine HTTP-Basic-Authentifizierung erzeugt, gibt es bewusst keinen unsicheren Query-Token-Fallback. In diesem Fall ist die generische authentifizierte Call-Event-API oder ein für das konkrete Modell verifizierter anderer sicherer Transport zu verwenden.

Da Yealink Action URLs per GET senden, stehen die optionalen Werte `local` und `remote` in der Request-URI und könnten sonst in Access-Logs des Webservers oder Reverse Proxys gespeichert werden. Werden diese Werte produktiv verwendet, muss für den Callback-Pfad die Query im Access-Logging unterdrückt oder geschwärzt werden. Falls Anruferauflösung und Rufnummern-Snapshots nicht benötigt werden, können `local`/`remote` auch vollständig weggelassen werden.

Nach erfolgreicher Verarbeitung antwortet der Callback mit einer kleinen `200 OK`-Textantwort. Die bestehende transaktionale Call-State-Logik wird unverändert verwendet; die Notification-Verarbeitung bleibt nach dem Call-State-Commit isoliert. Erfolgreiche Callbacks aktualisieren ausschließlich `last_used_at` des separaten Callback-Credentials.

## Speicherung

`call_sessions` speichert den abgeleiteten Lebenszyklus je Gerät und externer Call-ID.

`call_events` speichert die normalisierte Ereignisfolge und den daraus entstandenen Zustand.

Zeitstempel werden in UTC gespeichert.

## Anrufhistorie für Benutzer

Angemeldete Benutzer können unter `/calls/` die letzten Anrufe ihrer eigenen Geräte einsehen. Die Abfrage wird über `devices.owner_user_id` strikt auf den angemeldeten Benutzer begrenzt; Anrufsitzungen von Geräten anderer Benutzer werden nicht angezeigt.

Pro Seite werden bis zu 50 Anrufe angezeigt. Ältere Einträge werden per Cursor nachgeladen. Angezeigt werden Zeit, Richtung, aktueller bzw. endgültiger Status, Gegenstellen-Rufnummer, der beim Anruf ermittelte Kontaktname als Snapshot sowie der Gerätename. Die technische Folge einzelner Call-Events wird in der normalen Benutzeransicht bewusst nicht dargestellt.

Kontaktname und Rufnummer werden zunächst nicht direkt mit einem Kontakt verlinkt, weil `call_sessions` keine stabile Kontakt-ID speichert. Eine nachträgliche Verknüpfung nur anhand von Name oder Rufnummer könnte nach späteren Änderungen im Adressbuch auf den falschen Kontakt zeigen.

## Aufbewahrung der Anrufhistorie

Beendete Anrufsitzungen werden standardmäßig 180 Tage aufbewahrt. Die geplante Historienbereinigung löscht ausschließlich Sitzungen, deren `ended_at` älter als der konfigurierte Stichtag ist. Offene, klingelnde oder verbundene Sitzungen werden niemals durch die Retention-Bereinigung ausgewählt. Zugehörige `call_events` werden beim Löschen der beendeten Sitzung über die bestehende Datenbank-Cascade automatisch mit entfernt.

Konfiguration über `operations.retention_days.call_history` in `config/local.php` oder `OPENPHONEBOOK_CALL_HISTORY_RETENTION_DAYS`. Der Wert `0` deaktiviert die Bereinigung der Anrufhistorie. Zulässig sind `0` bis `3650` Tage.

Konfigurierbare Aufbewahrungsfristen bleiben ein späterer Roadmap-Punkt.
