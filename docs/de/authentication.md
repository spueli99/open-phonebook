# Anmeldung und Sitzungen

## Umfang

Die erste Authentifizierungsbasis von Open Phonebook verwendet E-Mail-Adresse und Passwort für menschliche Benutzer.

Eine öffentliche Registrierung ist nicht vorgesehen. Weitere Verfahren wie Passwort-Zurücksetzen, MFA, OIDC oder LDAP können später ergänzt werden.

## Anmeldung

Beim Anmelden werden unter anderem folgende Schritte durchgeführt:

1. abgeschlossene Installation prüfen
2. HTTPS erzwingen, sofern konfiguriert
3. sichere PHP-Session starten
4. CSRF-Token prüfen
5. Login-Sperre prüfen
6. Benutzer anhand der normalisierten E-Mail-Adresse laden
7. nur aktive und nicht gelöschte Benutzer zulassen
8. Passwort mit `password_verify()` prüfen
9. ältere Passwort-Hashes bei Bedarf automatisch aktualisieren
10. Session-ID nach erfolgreicher Anmeldung erneuern
11. minimale Benutzeridentität serverseitig in der Session speichern
12. CSRF-Token erneuern

Bei falschen Zugangsdaten wird absichtlich nicht verraten, ob die E-Mail-Adresse überhaupt existiert.

## Session-Modell

Browser-Sessions verwenden ausschließlich Cookies mit `HttpOnly`, `SameSite=Lax` und bei HTTPS zusätzlich `Secure`. Außerdem ist der strikte PHP-Session-Modus aktiv; nach relevanten Authentifizierungsänderungen wird die Session-ID erneuert.

Diese Cookie-Schutzmechanismen sind bewusst feste Sicherheitsinvarianten im Code und keine abschaltbaren Betreiberoptionen. Insbesondere werden keine scheinbaren Konfigurationswerte angeboten, mit denen `HttpOnly` oder `SameSite` abgeschwächt werden könnten.

## Login-Schutz

Fehlgeschlagene Anmeldungen werden datenbankgestützt begrenzt.

Aktuelle Regel:

- 5 Fehlversuche
- innerhalb eines 15-Minuten-Fensters
- anschließend 15 Minuten Sperre

Zur Speicherung wird nicht die Kombination aus E-Mail-Adresse und IP-Adresse im Klartext abgelegt, sondern ein HMAC-basierter Hash.

Nach erfolgreicher Anmeldung wird der zugehörige Fehlversuchs-Zähler gelöscht.

## Anwendungsschlüssel

Während der Installation erzeugt Open Phonebook einen zufälligen Anwendungsschlüssel.

Dieser wird außerhalb der Datenbank in der lokalen Laufzeitkonfiguration gespeichert und darf nicht in Git eingecheckt werden.

Alternativ kann er über `OPENPHONEBOOK_APP_KEY` bereitgestellt werden.

## Abmeldung

Die Abmeldung ist nur per POST möglich und benötigt einen gültigen CSRF-Token.

Danach wird die Benutzeridentität aus der Session entfernt, die Session-ID erneuert und der CSRF-Token rotiert.
