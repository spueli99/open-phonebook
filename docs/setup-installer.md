# Web Setup Installer

## Purpose

Open Phonebook is installable on conventional PHP shared hosting without requiring shell access.

The installer is intentionally small. It uses the same configuration, database, migration, and security services as the rest of the application rather than embedding separate business logic in setup pages.

## Setup flow

1. System and security checks
2. Database connection
3. Application defaults, optional default phone region, and initial administrator
4. Database migrations
5. Runtime configuration write
6. Installation lock and completion

The implementation is exposed below the public document root at:

`/setup/`

After installation the endpoint refuses to run again.

## Environment checks

The first step checks:

- PHP 8.3+
- PDO MySQL
- cURL
- mbstring
- OpenSSL
- DOM
- XMLReader
- XMLWriter
- writable `storage/`
- HTTPS

HTTPS is treated as a requirement rather than a cosmetic warning because setup submits database and administrator credentials. The requirement is enforced before a setup session starts and before any setup step is served, so direct navigation to later steps cannot bypass transport security.

## Database credentials

The installer accepts:

- host
- port
- database name
- database user
- database password

The connection is tested before continuing.

Database credentials are temporarily held in the server-side setup session between setup steps. They are not placed in query strings or hidden form fields.

## Configuration

Successful installation writes `config/local.php` atomically.

Configuration precedence remains:

1. environment variables
2. `config/local.php`
3. repository defaults

`config/local.php` is excluded from Git.

The installer generates a random application key and stores it in the local runtime configuration. The key is not stored in the database.

The application step also accepts an optional ISO 3166-1 alpha-2 default phone region such as `DE`. Leaving it empty deliberately disables region-based interpretation of local/national phone numbers. The value is written as `contacts.default_region` and may be overridden with `OPENPHONEBOOK_DEFAULT_PHONE_REGION`.

For managed/container deployments, the application key may instead be supplied through `OPENPHONEBOOK_APP_KEY`.

## Migrations

Schema changes are versioned.

Each migration implements the `Migration` contract and has a stable migration identifier. Applied migrations are recorded in the `migrations` table.

The migration runner:

- skips already applied migrations
- uses prepared statements for migration metadata
- reports the migration identifier when a migration fails
- rolls back where the database engine can do so safely

Note that MariaDB/MySQL may implicitly commit some DDL statements. Migrations must therefore be designed to be restart-safe and should not rely solely on transactional DDL rollback.

## Initial administrator

The first account receives the generic `system_admin` role.

The installer:

- validates the email address
- requires a display name
- requires at least 12 password characters
- hashes passwords with PHP `password_hash()`
- never stores plaintext passwords

Public self-registration is not part of the installer.

## Request security

The setup UI provides:

- HttpOnly session cookies
- SameSite=Lax
- Secure cookies when using HTTPS
- strict session mode
- CSRF tokens on POST forms
- POST/Redirect/GET flow
- output escaping
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- restrictive Content Security Policy
- noindex/nofollow
- generic public error messages

## Filesystem preflight

Before migrations or first-administrator creation begin, the installer verifies that the local configuration path and installation-lock path can be written. Predictable filesystem permission errors therefore fail before database changes start.

The actual `config/local.php` file and `storage/installed.lock` are still written only after the database work succeeds.

## Installation lock

After all installation steps succeed, `storage/installed.lock` is created.

The installation lock is deliberately written last. This prevents a partially completed setup from being reported as complete.

Re-enabling setup requires deliberate server-side intervention.

## Scheduled jobs after setup

For normal operation, configure the cron-compatible jobs required by the enabled features:

- notification delivery, normally every minute: `php bin/notification-deliveries.php`
- Google Contacts synchronization, for example every five minutes when Google is configured: `php bin/google-contacts-sync.php`
- history retention cleanup, normally once per day: `php bin/history-retention-cleanup.php`

The retention job is safe to schedule even when one retention area is disabled with `0`. Scheduler health for all configured background jobs is visible to system administrators on the Operations page.

## External integrations

Google Contacts, SMTP, Pushover, device credentials, address books, and call-event adapters are not configured by the initial installer. They belong in the authenticated application.

For Google Contacts, configure `OPENPHONEBOOK_GOOGLE_CLIENT_ID` and `OPENPHONEBOOK_GOOGLE_CLIENT_SECRET` (or the equivalent `providers.google` values in `config/local.php`) after installation. The application `base_url` must be HTTPS and the OAuth redirect URI is `<base_url>/sources/google/callback/`.

This keeps installation provider-neutral and allows Open Phonebook to operate without Google or any specific phone vendor.


## Restore is not setup

Disaster recovery must not rerun the web installer against an existing restored database.

Restore the matching code, database, original runtime configuration/application key, and `storage/installed.lock`, then use `php bin/backup-readiness.php` with the fingerprint saved from the backup.

See [Backup and restore](backup-restore.md).


## Optional Web Push / PWA setup

Web Push is configured after the base installation because VAPID is a deployment-level server identity, not per-user setup data.

Generate a key pair once:

`php bin/generate-vapid-keys.php`

Store the resulting keys as runtime secrets and configure a stable VAPID subject:

- `OPENPHONEBOOK_WEB_PUSH_SUBJECT=mailto:admin@example.org` or a stable HTTPS URL
- `OPENPHONEBOOK_WEB_PUSH_PUBLIC_KEY=...`
- `OPENPHONEBOOK_WEB_PUSH_PRIVATE_KEY=...`

Do not regenerate these keys during ordinary deployments. Existing browser subscriptions are tied to the VAPID identity.

After configuration, authenticated users can enable Web Push for each browser from the Notifications page.


## System mail sender

Fresh installations require a valid system mail sender address during the application setup step.

The value is stored as `mail.from` in runtime configuration and is used for password-reset email and future system-generated mail.

Environment-managed deployments may instead set:

`OPENPHONEBOOK_MAIL_FROM`

Password reset also requires the configured `app.base_url` to be a valid HTTPS URL because recovery links are generated from that base URL.
