# Operations and health diagnostics

## Scope

Open Phonebook contains cron-driven background work for:

- Google Contacts synchronization
- notification delivery

The operations diagnostics page provides a minimal technical view of whether these jobs are running on schedule.

The page is available only to authenticated system administrators with the `view_operations` permission.

No public unauthenticated health endpoint is exposed.

## Scheduler heartbeats

Each supported scheduler writes a heartbeat to `scheduler_heartbeats`.

The heartbeat contains only technical aggregate data:

- task name
- last start timestamp
- last successful completion timestamp
- last failed completion timestamp
- current/last status
- attempted count
- succeeded count
- failed count

It does not store contact identifiers, source identifiers, phone numbers, notification recipients, provider credentials, or message content.

At the start of a run the status becomes `running`. A completed run becomes `success` or `failed`.

If a process crashes after writing `running`, no false success is recorded. Once the configured age threshold is exceeded, the diagnostics page reports that scheduler as stale.

## Health states

The UI maps scheduler heartbeats to:

- `healthy`: a recent run/start is inside the configured threshold and the last completion did not fail
- `stale`: the most recent start is older than the threshold
- `failed`: the most recent completed run failed
- `never_run`: no heartbeat has ever been recorded

A failed status remains visible until a later scheduler run starts and subsequently completes.

## Default thresholds

Default stale thresholds are:

- Google Contacts sync: 1800 seconds (30 minutes)
- notification delivery: 300 seconds (5 minutes)

They can be changed in `config/local.php` under:

`operations.scheduler_stale_after_seconds`

Environment overrides are also supported:

- `OPENPHONEBOOK_GOOGLE_SYNC_STALE_SECONDS`
- `OPENPHONEBOOK_NOTIFICATION_STALE_SECONDS`

Values below 60 seconds are rejected by the health service.

These thresholds are diagnostic expectations; they do not schedule cron jobs themselves.

## Recommended cron schedule

Notification delivery should normally run once per minute:

`php bin/notification-deliveries.php`

Google Contacts synchronization may run every five minutes unless a deployment intentionally chooses another interval:

`php bin/google-contacts-sync.php`

If a different schedule is used, adjust the stale threshold accordingly.

## Notification queue metrics

The operations page shows global aggregate counts for:

- pending deliveries
- processing deliveries
- failed deliveries

Only counts are shown. Delivery content, recipients, channel credentials, and retry payloads are not exposed.

User-specific delivery history remains in the authenticated notification management area.

## Database check

The page performs a simple `SELECT 1` query and reports whether that query succeeds in the active authenticated request.

A complete database outage can prevent the application from booting before the diagnostics page can be rendered; the in-app database check is therefore not a replacement for external infrastructure monitoring.

## Security

The diagnostics page:

- requires an authenticated session
- requires `view_operations`
- is initially limited to system administrators
- uses the normal no-store HTML security headers
- exposes only aggregate technical metadata
- does not expose configuration values or secrets

Scheduler heartbeat writes never include exception messages because those could contain sensitive provider or account information.

## Future operations work

- backup and restore documentation
- optional history retention/cleanup controls
- Docker deployment option
- release/update process
- external monitoring integration if required
