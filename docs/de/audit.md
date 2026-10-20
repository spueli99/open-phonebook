# Audit-Ereignisse

## Umfang

Open Phonebook speichert bewusst nur einen kleinen Audit-Verlauf für sicherheitsrelevante administrative Aktionen.

Die ersten dauerhaft gespeicherten Ereignistypen umfassen:

- Aktivieren und Deaktivieren von Benutzern
- Änderungen der Plattformrolle
- Erstellen von Einladungen
- Annehmen von Einladungen
- Erneuern von Einladungen
- Widerrufen von Einladungen

Das Audit-Modell dient der Nachvollziehbarkeit und nicht der Überwachung. Normales Lesen von Kontakten wird nicht protokolliert.

## Gespeicherte Felder

Jedes Audit-Ereignis enthält:

- Ereignistyp
- Benutzer-ID des Ausführenden
- Zieltyp
- Ziel-ID
- kleine strukturierte Metadaten
- Erstellungszeitpunkt in UTC

Wird das ausführende Benutzerkonto später gelöscht, darf die Actor-Referenz auf null gesetzt werden; das Audit-Ereignis bleibt erhalten.

## Grenze für Metadaten

Audit-Metadaten werden zentral validiert.

Aktuelle Grenzen:

- höchstens acht Einträge
- nur maschinenlesbare Schlüssel in Kleinbuchstaben
- nur boolesche Werte, Integer oder Strings
- Strings höchstens 120 Byte
- kodierte Metadaten höchstens 2 KiB
- Schlüssel mit sensiblen Begriffen wie Passwort, Token, Secret, Session, E-Mail, Telefon, Kontakt, Inhalt oder Autorisierung werden abgewiesen

Die aktuellen Benutzer- und Einladungsereignisse speichern nur begrenzte Werte wie alte/neue Rolle oder einen festen internen Grund für den Widerruf einer Einladung.

Audit-Metadaten dürfen niemals Klartext-Passwörter, Einladungs-/Reset-Tokens, Session-Kennungen, Provider-Secrets, Kontaktdaten, Rufnummern, Benachrichtigungsinhalte oder beliebige Exception-Texte enthalten.

## Transaktionsgrenze

Administrative Benutzer- und Einladungsänderungen schreiben das Audit-Ereignis über dieselbe PDO-Verbindung und dieselbe Datenbanktransaktion wie die geschützte Zustandsänderung.

Schlägt das Schreiben des Audit-Ereignisses fehl, wird auch die zugehörige administrative Datenbankänderung zurückgerollt.

Der Einladungsversand erfolgt erst nach der Erstellungs-/Erneuerungstransaktion. Ist die Mail-Konfiguration ungültig oder schlägt die Zustellung fehl, wird die Einladung in einer neuen auditierten Transaktion mit einem festen internen Grund widerrufen.

## Lesen der Audit-Ereignisse

Das Repository unterstützt bereits das begrenzte Laden der neuesten Ereignisse für spätere Administrations-/Betriebsfunktionen. Dieser Meilenstein führt zunächst nur die Persistenz ein und stellt noch keine Audit-Log-Ansicht in der Weboberfläche bereit.

Spätere Erweiterungen sollen dieselben typisierten Ereignis- und Metadaten-Grenzen für Geräte-Credentials, OAuth-/Provider-Verbindungen, Importe, destruktive Aktionen, Organisationsverwaltung und ausgewählte Authentifizierungs-/Security-Ereignisse wiederverwenden.
