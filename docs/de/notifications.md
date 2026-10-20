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
3. Benachrichtigungsregeln auswerten
4. passende Provider aufrufen

Ein Providerfehler kann daher die Anrufhistorie weder zurückrollen noch beschädigen.

Wiederholte Incoming-Callbacks erzeugen keine doppelten Incoming-Benachrichtigungen, weil nur eine neu angelegte eingehende Ringing-Session `incoming` auslöst. Terminale Zustände werden nicht erneut geöffnet.

## Fehlerisolation

Jeder passende Kanal wird unabhängig abgearbeitet. Ein deaktivierter Kanal, unbekannter Provider, eine ungültige verschlüsselte Konfiguration, ein Quotenfehler oder ein Transportfehler blockiert die übrigen Kanäle nicht.

Die erste synchrone Umsetzung wiederholt fehlgeschlagene Zustellungen nicht und speichert noch keine Zustellversuche. Queue, Retry und Zustelldiagnose bleiben spätere Betriebsfunktionen.

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

- Ruhezeiten
- Retry-/Backoff-Queue
- Zustellhistorie und Diagnose
- Testversand
- Web Push/PWA
- weitere Provider
- individuelle Nachrichtentemplates pro Regel
