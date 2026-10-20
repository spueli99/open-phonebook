# Betrieb und Systemdiagnose

## Umfang

Open Phonebook besitzt cron-gesteuerte Hintergrundaufgaben für:

- Google-Kontakte-Synchronisierung
- Benachrichtigungszustellung

Die Betriebsdiagnose zeigt minimal und technisch, ob diese Aufgaben planmäßig laufen.

Die Seite ist ausschließlich für angemeldete Systemadministratoren mit der Berechtigung `view_operations` verfügbar.

Es gibt bewusst keinen öffentlichen, nicht authentifizierten Health-Endpunkt.

## Scheduler-Heartbeats

Jeder unterstützte Scheduler schreibt einen Heartbeat in `scheduler_heartbeats`.

Gespeichert werden ausschließlich technische Aggregatdaten:

- Aufgabenname
- Zeitpunkt des letzten Starts
- Zeitpunkt des letzten erfolgreichen Abschlusses
- Zeitpunkt des letzten fehlgeschlagenen Abschlusses
- aktueller/letzter Status
- Anzahl versucht
- Anzahl erfolgreich
- Anzahl fehlgeschlagen

Es werden keine Kontakt- oder Source-IDs, Rufnummern, Empfänger, Provider-Zugangsdaten oder Nachrichtentexte gespeichert.

Beim Start eines Laufs wird der Status auf `running` gesetzt. Ein abgeschlossener Lauf endet mit `success` oder `failed`.

Stürzt ein Prozess nach `running` ab, wird kein falscher Erfolg gespeichert. Nach Überschreiten der konfigurierten Zeitgrenze erscheint der Scheduler als verspätet.

## Zustände

Die Oberfläche bewertet Heartbeats als:

- `healthy`: letzter Start liegt innerhalb der Zeitgrenze und der letzte Abschluss war kein Fehler
- `stale`: letzter Start ist älter als die Zeitgrenze
- `failed`: der letzte abgeschlossene Lauf ist fehlgeschlagen
- `never_run`: es wurde noch nie ein Heartbeat gespeichert

Ein Fehler bleibt sichtbar, bis ein späterer Scheduler-Lauf erneut startet und anschließend abgeschlossen wird.

## Standard-Zeitgrenzen

Standardmäßig gilt:

- Google-Kontakte-Sync: 1800 Sekunden (30 Minuten)
- Benachrichtigungszustellung: 300 Sekunden (5 Minuten)

Anpassung ist in `config/local.php` unter

`operations.scheduler_stale_after_seconds`

möglich.

Zusätzlich stehen Environment-Overrides zur Verfügung:

- `OPENPHONEBOOK_GOOGLE_SYNC_STALE_SECONDS`
- `OPENPHONEBOOK_NOTIFICATION_STALE_SECONDS`

Werte unter 60 Sekunden werden vom Health-Service abgewiesen.

Die Zeitgrenzen dienen ausschließlich der Diagnose und planen selbst keinen Cronjob.

## Empfohlene Cron-Frequenz

Benachrichtigungszustellung normalerweise jede Minute:

`php bin/notification-deliveries.php`

Google-Kontakte-Synchronisierung beispielsweise alle fünf Minuten:

`php bin/google-contacts-sync.php`

Bei abweichenden Cron-Intervallen sollte die jeweilige Stale-Zeitgrenze passend angepasst werden.

## Queue-Metriken

Die Betriebsdiagnose zeigt globale Summen für:

- ausstehende Zustellungen
- laufende Zustellungen
- fehlgeschlagene Zustellungen

Es werden nur Anzahlen angezeigt. Nachrichtentext, Empfänger, Kanal-Secrets und Retry-Payloads werden nicht offengelegt.

Die benutzerspezifische Zustellhistorie verbleibt im geschützten Benachrichtigungsbereich.

## Datenbankprüfung

Die Seite führt im authentifizierten Request ein einfaches `SELECT 1` aus und zeigt, ob diese Abfrage erfolgreich war.

Bei einem vollständigen Datenbankausfall kann die Anwendung bereits vor dem Rendern der Diagnoseseite nicht mehr starten. Die interne Prüfung ersetzt deshalb kein externes Infrastruktur-Monitoring.

## Sicherheit

Die Diagnoseseite:

- erfordert eine authentifizierte Sitzung
- erfordert `view_operations`
- ist zunächst ausschließlich für Systemadministratoren freigegeben
- verwendet die normalen No-Store-Sicherheitsheader
- zeigt nur technische Aggregatdaten
- zeigt keine Konfigurationswerte oder Secrets

Heartbeat-Einträge speichern bewusst keine Exception-Texte, da diese sensible Provider- oder Kontoinformationen enthalten könnten.

## Spätere Operations-Themen

- Backup-/Restore-Dokumentation
- optionale Aufbewahrungs-/Bereinigungsregeln
- Docker-Betrieb
- Release-/Update-Prozess
- bei Bedarf externe Monitoring-Integration
