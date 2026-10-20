# Benachrichtigungen

## Umfang

Benachrichtigungen sind benutzereigene ausgehende Kanäle, die durch Anwendungsereignisse ausgelöst werden.

Die erste Umsetzung verbindet Anrufereignisse mit:

- Pushover
- E-Mail

Die Anbieter sind Adapter hinter einer gemeinsamen Schnittstelle. Die Speicherung eines Anrufzustands hängt nicht von der Erreichbarkeit eines Benachrichtigungsanbieters ab.

## Kanäle

Ein Benachrichtigungskanal gehört genau einem Benutzer.

Aktuell unterstützt:

- `pushover`
- `email`

Die Anbieter-Konfiguration wird mit der Secret-Cipher des Application Keys verschlüsselt gespeichert. Gespeicherte Zugangsdaten werden nach dem Anlegen in der Weboberfläche nicht erneut angezeigt.

Pushover speichert:

- Application API Token
- User-/Group-Key

E-Mail speichert:

- Empfängeradresse
- Absenderadresse

Der Besitzer kann Kanäle aktivieren, deaktivieren oder löschen.

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

Sobald ein Job einen terminalen Zustand (`sent`, `failed` oder `cancelled`) erreicht, werden sein gerenderter Titel und Nachrichtentext geleert. Die Statusmetadaten bleiben für die Diagnose erhalten, ohne anrufbezogene Inhalte länger als erforderlich zu speichern.

Die Zustellung arbeitet innerhalb des Retry-Fensters nach dem At-least-once-Prinzip. Stürzt ein Prozess nach erfolgreicher externer Zustellung, aber vor dem lokalen `sent`-Update ab, kann bei Providern ohne Idempotenz ein Duplikat nicht vollständig ausgeschlossen werden.

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

## Sicherheit

Die Verwaltung erfordert eine authentifizierte Browser-Sitzung und CSRF-geschützte POST-Anfragen.

Benutzer können ausschließlich eigene Kanäle, Regeln und Geräte verwenden.

Regeln können weder Kanäle noch Geräte anderer Benutzer referenzieren.

Provider-Secrets werden verschlüsselt gespeichert und nicht in normale Ausgaben geschrieben.

## Später

- detaillierte Zustellhistorie und Diagnoseansicht
- manueller Retry
- Testversand
- Web Push/PWA
- weitere Provider
- individuelle Nachrichtentemplates pro Regel
