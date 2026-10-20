# Release- und Update-Prozess

## Ziel

Open-Phonebook-Updates sollen auch auf klassischem PHP-Shared-Hosting reproduzierbar, überprüfbar und sicher durchgeführt werden können.

Dafür gibt es:

- eine eingecheckte `VERSION`-Datei als Release-Kennung
- `php bin/update-readiness.php` zur Prüfung vor und nach dem Update
- `php bin/migrate.php` für explizite Schema-Migrationen
- die bestehende Backup-/Restore-Readiness als verpflichtende Rückfallebene vor Schemaänderungen

Der Web-Installer ist kein Update-Werkzeug.

## Versionskennung

Im Repository liegt im Root die Datei `VERSION`.

Der Entwicklungsstand verwendet aktuell:

`0.1.0-dev`

Für Releases muss diese Datei auf die jeweilige Release-Version gesetzt werden. Die Operations-Seite zeigt denselben Wert an, damit der installierte Stand ohne Offenlegung von Git-Metadaten erkennbar ist.

## Update-Readiness

Ausführen:

`php bin/update-readiness.php`

Optional als JSON:

`php bin/update-readiness.php --json`

Exit-Codes:

- `0`: bereit, Datenbankschema passt bereits zum Code
- `1`: Update ist grundsätzlich kompatibel, aber bekannte Migrationen stehen noch aus
- `2`: blockiert oder Prüfung konnte nicht sicher durchgeführt werden

Geprüft werden:

- wirksamer Application Key vorhanden
- Installationslock vorhanden
- `composer.lock` vorhanden
- Composer-Autoload vorhanden
- `storage/` beschreibbar
- Migrationsstand der Datenbank ist mit dem Code kompatibel
- keine Migration in der Datenbank, die der eingesetzte Code nicht kennt

Bekannte, noch nicht ausgeführte Migrationen sind während eines Updates normal und führen daher zu `migration_required`, nicht zu einem harten Fehler.

Enthält die Datenbank Migrationen, die der eingesetzte Code nicht kennt, wird das Update hart blockiert. Damit werden versehentliche Downgrades oder falsche Release-Stände erkannt.

## Migrationsbefehl

Schema-Migrationen werden explizit ausgeführt mit:

`php bin/migrate.php`

Der Befehl:

- lädt die normale Runtime-Konfiguration
- verbindet sich mit der konfigurierten Datenbank
- verweigert den Start bei unbekannten Migrationen in der Datenbank
- führt ausschließlich in `Migrations::all()` registrierte Migrationen aus
- verwendet den bestehenden transaktionalen MigrationRunner
- zeigt die ausgeführten Migrations-IDs an
- ist ohne offene Migrationen idempotent

Der Befehl startet nicht den Web-Installer, schreibt keine Konfiguration neu, legt keine Benutzer an, rotiert keine Schlüssel und verändert keine Provider-Credentials.

## Empfohlener Update-Ablauf

1. Soweit möglich Wartungsfenster aktivieren/ankündigen.
2. Google-Sync- und Notification-Cronjobs deaktivieren.
3. `php bin/backup-readiness.php` ausführen.
4. Vollständiges Datenbank- und Secret-/Konfigurationsbackup erstellen und prüfen.
5. Aktuelle `VERSION` und soweit vorhanden Release-/Commit-Kennung dokumentieren.
6. Neuen Code inklusive passender `composer.lock` und `VERSION` bereitstellen.
7. `composer install --no-dev --no-interaction --prefer-dist --no-progress` ausführen.
8. `php bin/update-readiness.php` ausführen.
9. Bei `migration_required`: `php bin/migrate.php` ausführen.
10. `php bin/update-readiness.php` erneut ausführen; Ergebnis muss nun `ready` sein.
11. Funktionale Kurztests durchführen.
12. Cronjobs wieder aktivieren.
13. Scheduler-Status in der Operations-Seite prüfen.
14. Wartungsmodus beenden.

Vor einer Schema-Migration muss ein geprüftes Backup vorhanden sein.

## Shared Hosting

Ohne SSH die vom Provider unterstützte Shell-/Cron-Umgebung verwenden, soweit vorhanden.

Ein Deployment sollte einen konsistenten Release-Stand ersetzen und nicht über längere Zeit einzelne PHP-Dateien im laufenden System austauschen.

Mindestens gemeinsam aus demselben Release bereitstellen:

- Anwendungscode
- `public/`
- anhand des passenden Locks erzeugtes `vendor/` bzw. Möglichkeit zur Installation
- `composer.lock`
- `VERSION`
- Migrationsklassen

Nicht überschreiben:

- `config/local.php`
- Application Key/Environment-Secrets
- `storage/installed.lock`

Kein frisches Entwicklungs-`storage/` über das Produktionsverzeichnis kopieren.

## Rollback-Grenze

Ein reines Code-Rollback ist nur sicher, solange der ältere Code das vorhandene Datenbankschema noch kennt.

Wurde beim Update eine Migration ausgeführt, die der vorherige Release nicht kennt, wird dessen `update-readiness.php` wegen einer unbekannten Migration fehlschlagen. Einen solchen Downgrade nicht erzwingen.

Dann besteht der Rollback aus der Wiederherstellung des vor dem Update erstellten Datenbankbackups zusammen mit dem passenden vorherigen Code-/Release-Stand.

Automatische Down-Migrationen gibt es aktuell bewusst nicht.

## Prüfungen nach dem Update

Nach jedem Update:

- `php bin/update-readiness.php` liefert `ready`
- Systemadministrator-Anmeldung funktioniert
- Operations-Seite zeigt die erwartete Version
- Datenbankstatus ist gesund
- Adressbücher und Kontakte laden
- Testbenachrichtigung kann in die Queue gestellt werden
- Google-Verbindung/Sync funktioniert weiterhin, falls genutzt
- Geräte-Telefonbuchendpoint funktioniert nach Möglichkeit mit Testgerät
- Scheduler-Heartbeats werden nach Reaktivierung der Cronjobs wieder gesund

## Release-Checkliste

Vor einem getaggten Release:

1. CI auf dem Release-Commit ist für alle unterstützten PHP-Versionen grün.
2. `VERSION` entspricht exakt der geplanten Release-Version.
3. `composer.lock` ist eingecheckt und validiert.
4. Changelog ist aktuell.
5. Migrationsliste ist vollständig und geordnet.
6. Update- sowie Backup-/Restore-Dokumentation berücksichtigt neue Betriebsanforderungen.
7. Sicherheitsrelevante Konfigurationsänderungen sind dokumentiert.
8. Ein Upgrade vom vorherigen Release wurde nach Möglichkeit mit einer repräsentativen Datenbankkopie getestet.
9. Ein Restore bleibt anhand der dokumentierten Backup-Grenze möglich.

Tags/Releases müssen auf einen unveränderlichen Commit zeigen. Unter derselben Release-Version darf nicht stillschweigend anderer Code ausgeliefert werden.
