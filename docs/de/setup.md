# Einrichtung von Open Phonebook

Open Phonebook erhält einen webbasierten Einrichtungsassistenten, damit die Installation auch auf klassischem Shared Hosting ohne SSH möglich ist.

## Voraussetzungen

Der Webserver muss ausschließlich auf das Verzeichnis `public/` zeigen.

Benötigt werden mindestens:

- PHP 8.3
- MariaDB oder MySQL
- PDO MySQL
- cURL
- mbstring
- OpenSSL
- DOM
- XMLReader
- XMLWriter
- PHP-Sessions
- HTTPS
- Schreibrechte für `storage/`

## Ablauf

Der Installer wird über `/setup/` aufgerufen und führt durch:

1. Systemprüfung
2. Datenbankverbindung
3. Grundeinstellungen einschließlich optionaler Standardregion für Rufnummern
4. Anlage des ersten Administrators
5. Datenbankmigrationen
6. Abschluss und Sperre des Installers

Die Datenbank-Zugangsdaten werden beim mehrstufigen Setup nur vorübergehend in der serverseitigen PHP-Session gehalten. Sie werden nicht über URL-Parameter weitergegeben.

Nach erfolgreicher Installation wird die lokale Laufzeitkonfiguration in `config/local.php` abgelegt. Diese Datei ist von Git ausgeschlossen.\n\nOptional kann ein ISO-3166-1-Alpha-2-Ländercode wie `DE` als Standardregion für lokale Rufnummern angegeben werden. Bleibt das Feld leer, werden lokale/nationale Nummern bewusst nicht anhand eines geratenen Landes interpretiert. Die Einstellung wird als `contacts.default_region` gespeichert und kann über `OPENPHONEBOOK_DEFAULT_PHONE_REGION` überschrieben werden.\n\nDer Installer erzeugt außerdem einen zufälligen Anwendungsschlüssel. Er wird außerhalb der Datenbank in der lokalen Konfiguration gespeichert und unter anderem für datenschutzfreundliche, HMAC-basierte Sicherheitskennungen verwendet. Alternativ kann `OPENPHONEBOOK_APP_KEY` genutzt werden.

## Sicherheit

Während des Setups werden unter anderem eingesetzt:

- HTTPS
- sichere Session-Cookies
- CSRF-Schutz
- serverseitige Validierung
- Escaping der HTML-Ausgabe
- restriktive Security-Header
- keine Ausgabe von Datenbankfehlern oder Zugangsdaten an den Browser

Nach erfolgreicher Installation wird `storage/installed.lock` angelegt. Danach ist der Installer gesperrt.

## Nicht Bestandteil der Erstinstallation

Google Contacts, Pushover, SMTP, Telefone und weitere Integrationen werden später innerhalb der angemeldeten Anwendung eingerichtet. Dadurch bleibt die Grundinstallation unabhängig von einzelnen Diensten oder Herstellern.

Für Google Kontakte werden nach der Installation `OPENPHONEBOOK_GOOGLE_CLIENT_ID` und `OPENPHONEBOOK_GOOGLE_CLIENT_SECRET` gesetzt oder die entsprechenden `providers.google`-Werte in `config/local.php` hinterlegt. Die `base_url` muss HTTPS verwenden; als OAuth-Redirect-URI wird `<base_url>/sources/google/callback/` eingetragen.
