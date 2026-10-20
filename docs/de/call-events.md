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

Der Adapter ist absichtlich vom öffentlichen HTTP-Controller getrennt. Ein direkter Yealink-Callback-Endpunkt wird erst aktiviert, wenn ein sicherer Credential-Transport für den Callback dokumentiert und verifiziert ist. Normale Geräte-Tokens dürfen nicht in Callback-URLs stehen.

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
