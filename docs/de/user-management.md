# Benutzerverwaltung

## Umfang

Die erste Benutzerverwaltungsbasis enthält:

- Benutzerübersicht für Systemadministratoren
- Aktivieren und Deaktivieren von Konten
- Erstellen und Verwalten aktiver Einladungen
- sichere Annahme von Einladungen
- erste rollenbasierte Berechtigungen

## Rollen

Aktuell sind folgende Rollen vorgesehen:

- `system_admin`
- `organization_admin`
- `member`

Nur `system_admin` darf derzeit Benutzer systemweit verwalten und Einladungen erstellen.

Die Rolle `organization_admin` ist bereits für das spätere Organisationsmodell definiert. Sie kann über die allgemeine Einladung derzeit bewusst noch nicht vergeben werden, da noch kein Organisationsbezug existiert.

## Schutzregeln

Bei der Benutzerverwaltung gelten unter anderem:

- das eigene Konto kann nicht selbst deaktiviert werden
- der letzte aktive Systemadministrator kann nicht deaktiviert werden
- gelöschte Benutzer werden nicht über die Aktivierungsfunktion verändert
- eine bereits bestehende Aktivierung oder Deaktivierung wird als No-op behandelt

Systemadministratoren können die Plattformrolle anderer, nicht gelöschter Benutzer zwischen `member` und `system_admin` ändern. Die eigene Systemadministratorrolle kann nicht selbst entzogen werden, und der letzte aktive Systemadministrator kann nicht herabgestuft werden. `organization_admin` bleibt bis zur Einführung eines Organisationskontexts gesperrt.

Eine endgültige Löschung ist aktuell noch nicht vorgesehen.

## Einladungen

Einladungen:

- laufen nach drei Tagen ab
- sind nur einmal verwendbar
- verwenden zufällige 256-Bit-Tokens
- speichern in der Datenbank nur einen HMAC-basierten Hash des Tokens
- können nicht für bereits vorhandene Benutzer erstellt werden
- werden für dieselbe E-Mail-Adresse nicht mehrfach parallel aktiv angelegt

Der erzeugte Link wird dem Administrator aktuell einmal angezeigt und kann anschließend über einen geeigneten Kanal an den Empfänger gesendet werden. Aktive Einladungen werden in der Benutzerverwaltung angezeigt und können widerrufen oder erneuert werden. Beim Erneuern wird der bisherige Token ungültig, ein neuer Token erzeugt und die Gültigkeit erneut auf drei Tage gesetzt.

Ein automatischer E-Mail-Versand folgt später zusammen mit der Mail-/Benachrichtigungs-Infrastruktur.

## Schutz des Einladungs-Tokens

Der Link verwendet ein URL-Fragment:

`/invite/accept/#<token>`

Ein URL-Fragment wird vom Browser nicht an den Webserver übertragen. Ein kleines, lokal ausgeliefertes Skript übernimmt den Token in einen CSRF-geschützten POST und entfernt das Fragment sofort aus dem Browser-Verlauf.

Dadurch landet der Einladungs-Token nicht in normalen Request-URLs oder Webserver-Access-Logs.

Anschließend wird der Token nur vorübergehend in der serverseitigen Session gehalten und die weitere Einrichtung erfolgt unter einer sauberen URL.

## Konto anlegen

Beim Annehmen der Einladung werden Name, Passwort, Sprache und Zeitzone erfasst.

Das Passwort muss mindestens 12 Zeichen lang sein und wird mit PHP `password_hash()` gespeichert.

Die Anlage des Benutzers und das Markieren der Einladung als verwendet erfolgen gemeinsam in einer Datenbanktransaktion.
