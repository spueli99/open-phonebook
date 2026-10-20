# Backup und Wiederherstellung

## Ziel

Ein wiederherstellbares Open-Phonebook-Backup besteht aus mehr als einem Datenbankexport.

Die Datenbank enthält Anwendungsdaten sowie verschlüsselte bzw. schlüsselabhängige Zustände. Der Application Key liegt bewusst außerhalb der Datenbank. Wird eine Datenbank mit einem anderen Application Key wiederhergestellt, können verschlüsselte Integrationen unbrauchbar werden und schlüsselabhängige Geräte- oder Einladungstokens ihre Gültigkeit verlieren.

Zu einem vollständigen Recovery-Satz gehören deshalb:

1. vollständiger MariaDB-/MySQL-Dump
2. Runtime-Secret-Konfiguration einschließlich des exakten Application Keys
3. `storage/installed.lock`
4. passender Open-Phonebook-Code-/Release-Stand
5. `composer.lock` aus dem Deployment, sofern vorhanden
6. nicht geheimes Readiness-Manifest/Fingerprint zur Kontrolle

Cache- und Logdateien unter `storage/` sind aktuell nicht für die Wiederherstellung erforderlich. Derzeit existiert kein separates persistentes Benutzer-Upload-Verzeichnis.

## Backup-Readiness-Prüfung

Vor dem Backup ausführen:

`php bin/backup-readiness.php`

Exit-Codes:

- `0`: bereit
- `1`: Warnung
- `2`: fehlgeschlagen oder Prüfung konnte nicht ausgeführt werden

Geprüft werden:

- Datenbankverbindung
- alle vom aktuellen Code erwarteten Migrationen vorhanden
- keine dem aktuellen Code unbekannten Migrationen in der Datenbank
- wirksamer Application Key vorhanden und ausreichend lang
- `storage/installed.lock` vorhanden
- Vorhandensein von `config/local.php`
- Vorhandensein von `composer.lock`
- Anzahl von Datensätzen, deren weitere Nutzbarkeit vom gleichen Application Key abhängt

Die Ausgabe enthält niemals den Application Key, Datenbankpasswort, OAuth-Client-Secret, Provider-Token, Nachrichtentext, Rufnummern oder Kontaktdaten.

### JSON-Manifest

Für ein maschinenlesbares, nicht geheimes Manifest:

`php bin/backup-readiness.php --json > backup-readiness.json`

Das Manifest enthält den Application-Key-Fingerprint, aber nicht den Schlüssel selbst.

## Application-Key-Fingerprint

Aus dem wirksamen Application Key wird über eine eigene domain-separierte HMAC ein kurzer Fingerprint abgeleitet.

Der Fingerprint ist kein Schlüssel und kann nicht zum Entschlüsseln von Daten verwendet werden.

Er dient ausschließlich dazu, nach einer Wiederherstellung einen falschen Application Key sicher zu erkennen.

Prüfung nach Restore:

`php bin/backup-readiness.php --expected-key-fingerprint=0123456789abcdef01234567`

Dafür den im ursprünglichen Backup-Manifest gespeicherten Fingerprint verwenden.

Eine Abweichung ist ein harter Fehler.

## Was gesichert werden muss

### 1. Datenbank

Die vollständige Open-Phonebook-Datenbank mit allen Tabellen exportieren.

Für MariaDB/MySQL mit Shell-Zugriff beispielsweise:

`mysqldump --single-transaction --quick --default-character-set=utf8mb4 -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME > open-phonebook.sql`

Das Datenbankpasswort nicht direkt in die Befehlszeile schreiben. Entweder interaktive `-p`-Abfrage oder eine geschützte Client-Konfigurationsdatei verwenden.

Auf Shared Hosting den Datenbankexport des Providers oder phpMyAdmin verwenden und die komplette Datenbank als SQL, möglichst komprimiert, sichern.

Während des Dumps keine Schema-Migrationen/Deployments durchführen.

### 2. Runtime-Secrets

Bei Nutzung von `config/local.php` diese Datei über einen sicheren Kanal sichern.

Sie kann unter anderem enthalten:

- Application Key
- Datenbankverbindung
- Anwendungseinstellungen

Werden Werte stattdessen über Environment-Variablen oder das Hosting-Panel bereitgestellt, müssen diese Werte im Secret-Management des Hostings separat dokumentiert/gesichert werden.

Besonders wichtig:

- `OPENPHONEBOOK_APP_KEY` / `app.key`
- Google-OAuth-Client-Zugangsdaten, falls genutzt
- Datenbankzugang bzw. die Informationen zu dessen Wiederherstellung
- relevante Base-URL-, Rufnummernregions- und Operations-Einstellungen

Der Application Key muss **identisch** wiederhergestellt werden. Ein neu erzeugter Schlüssel ersetzt den alten nicht.

### 3. Installationslock

Sichern:

`storage/installed.lock`

Die Datei ist kein Secret. Ihre Wiederherstellung verhindert aber, dass der Setup-Assistent versehentlich wieder freigeschaltet wird.

### 4. Dependency-Lock

`composer.lock` sichern, wenn die Datei im eingesetzten Deployment vorhanden ist.

Ohne Dependency-Lock kann Composer bei einer Wiederherstellung neuere, weiterhin kompatible Paketversionen auflösen. Die Readiness-Prüfung liefert deshalb bei fehlender `composer.lock` eine Warnung.

Im Repository ist aktuell keine `composer.lock` eingecheckt. Das sollte später in einem separaten Dependency-Management-PR sauber gelöst werden. Bis dahin sollte eine im Deployment erzeugte Lock-Datei bzw. das Deployment-Artefakt mit aufbewahrt werden.

### 5. Code-/Release-Stand

Den exakten Open-Phonebook-Release, Git-Commit oder das eingesetzte Deployment-Artefakt dokumentieren.

Enthält eine wiederhergestellte Datenbank Migrationen, die der eingesetzte Code nicht kennt, schlägt die Readiness-Prüfung fehl.

Zuerst den passenden Code-Stand wiederherstellen; ein Update anschließend separat durchführen.

## Vom Application Key abhängige Datensätze

Der Readiness-Report zählt ohne Inhaltsausgabe:

- Google-/Contact-Source-Datensätze mit verschlüsselten Tokens
- Benachrichtigungskanäle mit verschlüsselter Provider-Konfiguration
- verschlüsselte Notification-Retry-Payloads
- aktive Gerätezugänge mit key-basierten Token-Hashes
- aktive Einladungen mit key-basierten Token-Hashes

Login-Throttling-HMACs gelten bewusst nicht als recovery-kritische Benutzerdaten. Ein Key-Wechsel macht alte Rate-Limit-Buckets gegebenenfalls irrelevant, zerstört aber keine Kontakte, Accounts oder Integrations-Secrets.

## Empfohlener Backup-Ablauf

1. Während des Backups keine Deployments oder DB-Migrationen durchführen.
2. `php bin/backup-readiness.php` ausführen.
3. JSON-Manifest sichern.
4. Vollständigen Datenbankdump erstellen.
5. `config/local.php` bzw. die entsprechenden Environment-Secrets sicher sichern.
6. `storage/installed.lock` sichern.
7. `composer.lock` sichern, sofern vorhanden.
8. Exakten Release-/Commit-Stand dokumentieren.
9. Datenbank und Secret-Backup getrennt oder in einem verschlüsselten Backup-Speicher ablegen.
10. Prüfen, dass der Dump nicht leer und mit dem vorgesehenen Restore-Werkzeug lesbar ist.
11. Regelmäßig einen Restore in einer isolierten Umgebung testen.

Ein Backup ohne jemals getestete Wiederherstellung gilt nicht als nachgewiesen funktionsfähig.

## Restore-Ablauf

Zuerst in einer isolierten oder auf Hosting-Ebene gegen öffentlichen Zugriff geschützten Umgebung wiederherstellen.

Reihenfolge:

1. Passenden Open-Phonebook-Code-/Release-Stand bereitstellen.
2. Cronjobs während des Restores deaktiviert lassen.
3. Öffentlichen Zugriff sperren bzw. auf Hosting-Ebene Maintenance aktivieren.
4. Leere Zieldatenbank anlegen.
5. Vollständigen SQL-Dump importieren.
6. Ursprüngliche Runtime-Konfiguration und den ursprünglichen Application Key wiederherstellen.
7. `storage/installed.lock` wiederherstellen.
8. `composer.lock` wiederherstellen, sofern sie Bestandteil des Deployments/Backups war.
9. Composer-Produktivabhängigkeiten für diesen Code-Stand möglichst anhand des Locks installieren.
10. Readiness-Prüfung mit dem Fingerprint des Backup-Manifests ausführen.
11. Funktionstests durchführen.
12. Notification- und Google-Cronjobs wieder aktivieren.
13. Öffentlichen Zugriff wieder freigeben.

Den Web-Installer **nicht** gegen eine wiederhergestellte Datenbank ausführen.

Beim Restore **keinen neuen Application Key erzeugen**.

## Restore-Verifikation

Nach DB-Import und Rückspielen der Secrets:

`php bin/backup-readiness.php --expected-key-fingerprint=<fingerprint-aus-backup>`

Als JSON:

`php bin/backup-readiness.php --json --expected-key-fingerprint=<fingerprint-aus-backup>`

Die Wiederherstellung darf nicht freigegeben werden, wenn:

- der Fingerprint nicht übereinstimmt
- erwartete Migrationen fehlen
- unbekannte Migrationen vorhanden sind
- der Application Key nicht verfügbar ist
- der Installationslock fehlt

Fehlt `config/local.php`, obwohl der wirksame Application Key aus Environment-/Hosting-Konfiguration verfügbar ist, ergibt sich nur eine Warnung. Auch eine fehlende `composer.lock` erzeugt eine Warnung, da die Dependency-Auflösung dann nicht exakt reproduzierbar ist. In diesem Fall müssen sämtliche erforderlichen Plattform-Secrets separat geprüft werden.

## Funktionstest nach Restore

Nach erfolgreicher Readiness-Prüfung:

- als Systemadministrator anmelden
- Adressbuch öffnen und Kontakte prüfen
- Betriebsdiagnose öffnen und Datenbankstatus prüfen
- Vorhandensein der Benachrichtigungskanäle prüfen
- Testbenachrichtigung über die Queue senden
- bei Google-Nutzung Verbindung prüfen und Synchronisierung ausführen/beobachten
- nach Möglichkeit Geräte-Telefonbuchzugang mit einem Testgerät prüfen
- nach Reaktivierung der Cronjobs gesunde Heartbeats kontrollieren

Produktive Provider-Secrets nicht in einer nicht vertrauenswürdigen Testumgebung verwenden.

## Sicherheit der Backup-Aufbewahrung

Backups enthalten personenbezogene Kontakte, Authentifizierungs- und Anrufmetadaten sowie verschlüsselte Provider-Konfiguration.

Daher:

- Backups verschlüsselt speichern
- Zugriff streng beschränken
- Secret-Konfiguration besonders schützen
- Backups und `config/local.php` niemals in Git einchecken
- rohe Datenbankdumps nicht per gewöhnlicher E-Mail versenden
- angemessene Aufbewahrungsfristen verwenden
- abgelaufene Sicherungen soweit möglich sicher löschen
- Offsite-Sicherungen mit unabhängigen Zugangsdaten und möglichst MFA schützen

Der Key-Fingerprint darf zusammen mit dem Dump gespeichert werden. Der Application Key selbst bleibt ein Secret.

## Hinweise für Shared Hosting

Open Phonebook bleibt Shared-Hosting-tauglich.

Ohne Shell-Zugriff:

- Readiness-CLI soweit möglich über die vom Provider angebotene Shell-/Cron-Umgebung ausführen
- Datenbank über Hosting-Panel oder phpMyAdmin exportieren/importieren
- `config/local.php` und `storage/installed.lock` über einen geschützten Datei-Manager/Transfer sichern
- eingesetzten Code-/Release-Stand separat dokumentieren

Datenbankdump oder Runtime-Konfiguration niemals zum Download unter dem öffentlichen Document Root ablegen.

## Disaster-Recovery-Grundsatz

Datenbank und Application Key bilden gemeinsam eine Recovery-Einheit.

Die Datenbank kann aus einem Backup ersetzt werden. Der ursprüngliche Application Key kann weder aus der Datenbank noch aus seinem Fingerprint rekonstruiert werden.

Geht der Application Key verloren, können unter anderem erneute Verbindungen externer Quellen, neue Benachrichtigungskonfigurationen, Rotation von Gerätezugängen und das Verwerfen offener Einladungen erforderlich werden. Der Schlüssel muss entsprechend geschützt werden.
