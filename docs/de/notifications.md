# Benachrichtigungen

## Umfang

Benachrichtigungen sind benutzereigene ausgehende Kanäle, die durch Anwendungsereignisse ausgelöst werden.

Anrufereignisse können aktuell über folgende Kanäle zugestellt werden:

- Pushover
- E-Mail
- Web Push/PWA

Die Anbieter sind Adapter hinter einer gemeinsamen Schnittstelle. Die Speicherung eines Anrufzustands hängt nicht von der Erreichbarkeit eines Benachrichtigungsanbieters ab.

## Kanäle

Ein Benachrichtigungskanal gehört genau einem Benutzer.

Aktuell unterstützt:

- `pushover`
- `email`
- `web_push`

Die Anbieter-Konfiguration wird mit der Secret-Cipher des Application Keys verschlüsselt gespeichert. Gespeicherte Zugangsdaten werden nach dem Anlegen in der Weboberfläche nicht erneut angezeigt.

Pushover speichert:

- Application API Token
- User-/Group-Key

E-Mail speichert:

- Empfängeradresse
- Absenderadresse

Ein Web-Push-Kanal speichert Push-Endpoint, öffentlichen Browser-Verschlüsselungsschlüssel/Auth-Secret, Content-Encoding und eine zufällige Browser-ID. Die komplette Subscription-Konfiguration wird wie andere Notification-Kanäle verschlüsselt gespeichert.

Der Besitzer kann Kanäle aktivieren, deaktivieren oder löschen. Eine erneute Registrierung desselben Browsers aktualisiert den bestehenden Kanal, sodass vorhandene Regeln erhalten bleiben.

## Regeln

Eine Regel verbindet einen Kanal mit einem Auslöser und kann optional auf ein eigenes Gerät eingeschränkt werden.

Aktuelle Auslöser:

- `incoming`
- `missed`
- `completed`

Ohne Geräteauswahl gilt die Regel für alle Geräte des Benutzers.

Doppelte Regeln mit gleichem Besitzer, Kanal, optionalem Gerät und Auslöser werden abgewiesen.

## Verknüpfung mit Anrufen

Benachrichtigungen werden erst ausgewertet, nachdem der Call-Event-Service den Anrufzustand erfolgreich gespeichert hat.

Reihenfolge:

1. Geräteereignis authentifizieren und prüfen
2. Call-/Session-Zustand committen
3. passende Benachrichtigungsregeln auswerten
4. Regeln innerhalb einer aktiven Ruhezeit unterdrücken
5. für jeden verbleibenden aktiven Kanal einen persistenten Zustelljob anlegen
6. den Call-Event-Request ohne externen Provider-Aufruf beantworten

Provider-Latenz oder ein Providerfehler können damit weder die Anrufhistorie zurückrollen noch den Geräte-Request verzögern.

## Ruhezeiten

Ruhezeiten werden pro Regel mit lokaler Start- und Endzeit konfiguriert.

Ausgewertet wird in der beim Benutzer hinterlegten Zeitzone, einschließlich Sommer-/Winterzeit.

Normale Fenster wie `13:00–15:00` und Zeiträume über Mitternacht wie `22:00–07:00` werden unterstützt. Der Startzeitpunkt gehört zur Ruhezeit, der Endzeitpunkt nicht mehr.

Während der Ruhezeit unterdrückte Benachrichtigungen werden bewusst verworfen und später nicht nachträglich versendet.

## Zustellqueue und Retry

Die Zustellung übernimmt der cron-kompatible Worker:

`php bin/notification-deliveries.php`

Er sollte üblicherweise einmal pro Minute gestartet werden.

Zustelljobs besitzen einen der Zustände:

- `pending`
- `processing`
- `sent`
- `failed`
- `cancelled`

Der erste Versuch ist sofort fällig. Temporäre Providerfehler werden mit begrenztem Backoff wiederholt:

- nach Versuch 1: 1 Minute
- nach Versuch 2: 5 Minuten
- nach Versuch 3: 15 Minuten
- nach Versuch 4: 60 Minuten
- Versuch 5 ist der letzte Provider-Versuch

Das Claiming erfolgt transaktional mit Datenbank-Row-Lock. Ein `processing`-Job mit einem Lock älter als zehn Minuten wird wieder auf `pending` gesetzt, sodass ein späterer Cron-Lauf einen abgestürzten Worker auffangen kann.

Gelöschte oder deaktivierte Kanäle führen zu `cancelled`. Fehlende Provider oder nicht entschlüsselbare Provider-Konfigurationen führen unmittelbar zu `failed`.

Die Verwaltungsseite zeigt aggregierte Zähler für pending, processing, sent, failed und cancelled.

Zusätzlich werden die 50 letzten Zustellungen des aktuellen Benutzers angezeigt. Die Historie enthält ausschließlich Metadaten wie Kanal, Auslöser, Status, Versuchszahl sowie Erstell-, Fälligkeits-, Fehler- und Versandzeitpunkt. Gerenderter Titel/Nachrichtentext und Provider-Zugangsdaten werden nicht angezeigt.

Sobald ein Job einen terminalen Zustand erreicht, werden gerenderter Titel und Nachrichtentext geleert. Bei `sent` und `cancelled` wird zusätzlich der verschlüsselte Retry-Payload gelöscht. Ein `failed`-Job behält ausschließlich einen separat verschlüsselten Retry-Payload, damit der Besitzer die ursprüngliche Benachrichtigung ausdrücklich erneut versuchen kann, ohne lesbaren anrufbezogenen Nachrichtentext dauerhaft zu speichern.

Die Zustellung arbeitet innerhalb des Retry-Fensters nach dem At-least-once-Prinzip. Stürzt ein Prozess nach erfolgreicher externer Zustellung, aber vor dem lokalen `sent`-Update ab, kann bei Providern ohne Idempotenz ein Duplikat nicht vollständig ausgeschlossen werden.

## Manueller Retry

Nur eine fehlgeschlagene Zustellung des aktuellen Benutzers darf erneut versucht werden.

Zusätzlich muss der ursprüngliche Kanal weiterhin vorhanden und aktiv sein. Der verschlüsselte Retry-Payload wird nur innerhalb des Application Service entschlüsselt, unmittelbar mit neuem Nonce wieder verschlüsselt und derselbe Zustelljob anschließend auf `pending` zurückgesetzt. Die Versuchszahl beginnt wieder bei null.

Der Retry-Payload verwendet eine eigene HKDF-Domain, getrennt von der Verschlüsselung der Provider-Secrets.

## Testversand

Ein aktiver Kanal kann direkt aus der Verwaltung getestet werden.

Der Browser-Request kontaktiert den Provider nicht. Stattdessen wird ein normaler Queue-Job mit dem internen Trigger `test` erzeugt. Der Cron-Worker übernimmt anschließend Versand, Retry, Fehlerisolation und Historie genauso wie bei echten Benachrichtigungen.

Der Trigger `test` ist intern und kann nicht als normale Call-Event-Regel angelegt werden.

## Fehlerisolation

Jeder Queue-Job wird unabhängig verarbeitet. Ein fehlerhafter Kanal blockiert keine anderen Jobs.

Der Worker beendet sich nur dann mit Exit-Code 1, wenn in diesem Lauf ein Job endgültig fehlschlägt. Temporäre Reschedules gelten als normaler Betrieb. Start-/Konfigurationsfehler verwenden Exit-Code 2.

## Pushover

Pushover-Nachrichten werden per HTTPS-POST an die Message API gesendet.

Open Phonebook prüft:

- 30 Zeichen langer Application API Token
- 30 Zeichen langer User-/Group-Key
- Titel maximal 250 UTF-8-Zeichen
- Nachricht maximal 1024 UTF-8-Zeichen
- HTTP-Status 200
- JSON-Antwort mit `status: 1`

Pushover-Zugangsdaten werden als private Provider-Secrets verschlüsselt gespeichert.

## E-Mail

Der erste E-Mail-Provider verwendet den auf klassischem Shared Hosting verfügbaren PHP-Mail-Transport.

Empfänger- und Absenderadresse werden validiert. CR/LF in Headerwerten wird abgewiesen, um Header-Injection zu verhindern.

Ein späterer SMTP- oder Provider-Transport kann dieselbe Mail-Transport-Schnittstelle implementieren, ohne Regeln ändern zu müssen.

## Web Push und PWA

Open Phonebook enthält ein minimales PWA-Manifest und einen Service Worker mit Root-Scope. Der Service Worker verarbeitet ausschließlich Push-Ereignisse und Notification-Klicks. Er greift bewusst nicht in normale Fetch-Requests ein und cached keine authentifizierten HTML-Seiten, Kontakte, Sessions oder andere privaten Anwendungsdaten.

Web Push verwendet VAPID. Das Schlüsselpaar einmalig erzeugen:

`php bin/generate-vapid-keys.php`

Konfiguration:

- `OPENPHONEBOOK_WEB_PUSH_SUBJECT` — stabiler `mailto:`-Subject oder HTTPS-URL
- `OPENPHONEBOOK_WEB_PUSH_PUBLIC_KEY`
- `OPENPHONEBOOK_WEB_PUSH_PRIVATE_KEY`

Der Private Key ist ein Runtime-Secret und wird niemals an den Browser ausgegeben. Nur der Public Key wird in der authentifizierten Benachrichtigungsverwaltung bereitgestellt.

Browser-Subscriptions können nur über authentifizierte, CSRF-geschützte POST-Anfragen registriert werden. Der Server akzeptiert nur HTTPS-Endpoints bekannter Browser-Push-Dienste, damit die Push-Funktion nicht als authentifizierter SSRF-Kanal zu beliebigen Hosts missbraucht werden kann.

Eine zufällige lokale Browser-ID ermöglicht die Zuordnung zum bestehenden Kanal. Die Verwaltungsseite prüft den aktuellen Browserzustand gegen den Server, ohne ihn dabei zu verändern; eine Aktivierung bleibt eine ausdrückliche Benutzeraktion. Bei erneuter Aktivierung wird eine erneuerte Subscription im bestehenden Kanal aktualisiert, ohne Regeln zu verlieren. Wurde LocalStorage gelöscht, wird zusätzlich ein identischer Endpoint wiederverwendet.

Die Zustellung läuft vollständig über die bestehende persistente Notification-Queue. Abgelaufene Subscriptions, die der Push-Dienst mit HTTP 404/410 meldet, werden automatisch deaktiviert; der betreffende Job endet als `cancelled` und wird nicht weiter retryt.

Der Push-Payload enthält nur gerenderten Titel/Nachrichtentext und einen internen Same-Origin-Zielpfad. Der Service Worker normalisiert das Ziel und prüft vor der Navigation zusätzlich die Origin; protokollrelative oder fremde Ziele fallen auf die Anwendungswurzel zurück.

## Sicherheit

Die Verwaltung erfordert eine authentifizierte Browser-Sitzung und CSRF-geschützte POST-Anfragen.

Benutzer können ausschließlich eigene Kanäle, Regeln und Geräte verwenden.

Regeln können weder Kanäle noch Geräte anderer Benutzer referenzieren.

Provider-Secrets werden verschlüsselt gespeichert und nicht in normale Ausgaben geschrieben.

## Später

- Filter/Paginierung für größere Zustellhistorien
- Aufbewahrungs-/Bereinigungsregeln für die Historie
- weitere Provider
- individuelle Nachrichtentemplates pro Regel
