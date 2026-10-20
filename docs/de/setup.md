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

## Geplante Jobs nach der Einrichtung

Für den normalen Betrieb sollten die für die aktivierten Funktionen benötigten Cronjobs eingerichtet werden:

- Benachrichtigungszustellung normalerweise jede Minute: `php bin/notification-deliveries.php`
- Google-Kontakte-Synchronisierung bei aktivierter Google-Anbindung beispielsweise alle fünf Minuten: `php bin/google-contacts-sync.php`
- Historienbereinigung normalerweise einmal täglich: `php bin/history-retention-cleanup.php`

Der Retention-Job kann auch dann gefahrlos geplant werden, wenn ein Aufbewahrungsbereich mit `0` deaktiviert ist. Der Zustand der geplanten Hintergrundjobs wird Systemadministratoren in der Betriebsdiagnose angezeigt.

## Nicht Bestandteil der Erstinstallation

Google Contacts, Pushover, SMTP, Telefone und weitere Integrationen werden später innerhalb der angemeldeten Anwendung eingerichtet. Dadurch bleibt die Grundinstallation unabhängig von einzelnen Diensten oder Herstellern.

Für Google Kontakte werden nach der Installation `OPENPHONEBOOK_GOOGLE_CLIENT_ID` und `OPENPHONEBOOK_GOOGLE_CLIENT_SECRET` gesetzt oder die entsprechenden `providers.google`-Werte in `config/local.php` hinterlegt. Die `base_url` muss HTTPS verwenden; als OAuth-Redirect-URI wird `<base_url>/sources/google/callback/` eingetragen.


## Restore ist kein neues Setup

Bei einer Wiederherstellung darf der Web-Installer nicht gegen die zurückgespielte Datenbank ausgeführt werden.

Stattdessen passenden Code-Stand, Datenbank, ursprüngliche Runtime-Konfiguration/Application Key und `storage/installed.lock` wiederherstellen und anschließend `php bin/backup-readiness.php` mit dem beim Backup gespeicherten Fingerprint prüfen.

Siehe [Backup und Wiederherstellung](backup-restore.md).


## Optionales Web Push / PWA Setup

Web Push wird nach der Basisinstallation eingerichtet. VAPID ist eine serverweite Deployment-Identität und keine benutzerspezifische Setup-Angabe.

Schlüsselpaar einmalig erzeugen:

`php bin/generate-vapid-keys.php`

Die erzeugten Schlüssel als Runtime-Secrets speichern und einen stabilen VAPID-Subject konfigurieren:

- `OPENPHONEBOOK_WEB_PUSH_SUBJECT=mailto:admin@example.org` oder eine stabile HTTPS-URL
- `OPENPHONEBOOK_WEB_PUSH_PUBLIC_KEY=...`
- `OPENPHONEBOOK_WEB_PUSH_PRIVATE_KEY=...`

Diese Schlüssel bei normalen Deployments nicht neu erzeugen. Bestehende Browser-Subscriptions sind an die VAPID-Identität gebunden.

Nach der Konfiguration können angemeldete Benutzer Web Push für jeden Browser in der Benachrichtigungsverwaltung aktivieren.


## Absender für System-E-Mails

Bei einer neuen Installation wird im Anwendungsschritt eine gültige Absenderadresse für System-E-Mails verlangt.

Der Wert wird als `mail.from` in der Runtime-Konfiguration gespeichert und für Passwort-Reset-E-Mails sowie zukünftige systemseitige E-Mails verwendet.

Bei Environment-basierter Konfiguration kann alternativ gesetzt werden:

`OPENPHONEBOOK_MAIL_FROM`

Für Passwort-Reset muss zusätzlich `app.base_url` als gültige HTTPS-URL konfiguriert sein, da daraus der Recovery-Link erzeugt wird.
