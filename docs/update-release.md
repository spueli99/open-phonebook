# Release and update process

## Goal

Open Phonebook updates must be repeatable, observable, and safe on conventional PHP shared hosting.

The application therefore provides:

- a committed `VERSION` file as release identity
- `php bin/update-readiness.php` for pre-/post-update verification
- `php bin/migrate.php` for explicit schema migration
- backup/restore readiness as the required recovery boundary before schema changes

The web installer is not an update mechanism.

## Version identity

The repository contains a root `VERSION` file.

Development currently uses:

`0.1.0-dev`

Release builds must update this file to the release version. The Operations page displays the same value so operators can identify the deployed application version without exposing Git metadata.

## Update readiness

Run:

`php bin/update-readiness.php`

Optional JSON output:

`php bin/update-readiness.php --json`

Exit codes:

- `0`: ready, schema already matches this code
- `1`: update is structurally valid but one or more known migrations are pending
- `2`: blocked or readiness could not be determined

The check verifies:

- effective application key is available
- installation lock exists
- `composer.lock` exists
- Composer autoload exists
- `storage/` is writable
- database migration state is compatible with the deployed code
- no database migration exists that is unknown to this code

Pending migrations known to the current code are expected during an update and therefore produce `migration_required`, not a hard failure.

A database containing migrations unknown to the current code is a hard failure. This protects against accidental code downgrade or deployment of the wrong release over a newer database.

## Migration command

Run schema migrations explicitly with:

`php bin/migrate.php`

The command:

- loads the normal runtime configuration
- connects to the configured database
- refuses to continue if the database contains migration IDs unknown to the current code
- applies only migrations registered in `Migrations::all()`
- uses the existing transactional migration runner
- prints the migration IDs that were applied
- is idempotent when no migration is pending

It does not run the web installer, rewrite configuration, create users, rotate keys, or touch provider credentials.

## Recommended update sequence

1. Announce/enter a maintenance window where practical.
2. Disable cron-triggered Google synchronization and notification delivery.
3. Run `php bin/backup-readiness.php`.
4. Create and verify a complete database + secret/config backup.
5. Record the current deployed `VERSION` and, where available, release/commit identity.
6. Deploy the new code including its matching `composer.lock` and `VERSION`.
7. Run `composer install --no-dev --no-interaction --prefer-dist --no-progress`.
8. Run `php bin/update-readiness.php`.
9. If status is `migration_required`, run `php bin/migrate.php`.
10. Run `php bin/update-readiness.php` again; it must now return `ready`.
11. Perform application smoke checks.
12. Re-enable cron jobs.
13. Verify scheduler health on the Operations page.
14. End maintenance mode.

Do not run schema migrations before a verified backup exists.

## Shared-hosting deployment

On shared hosting without SSH, use the provider's supported shell/cron runner where available.

A safe deployment layout should replace application code as one coherent release rather than editing individual PHP files in place over a long period.

At minimum, keep these files from the same release together:

- application source
- `public/`
- `vendor/` generated from the matching lock, or the ability to install it
- `composer.lock`
- `VERSION`
- migration classes

Do not overwrite:

- `config/local.php`
- the application key/environment-managed secrets
- `storage/installed.lock`

Do not copy a fresh development `storage/` directory over production.

## Rollback boundary

Code-only rollback is safe only when the database schema is still understood by the older code.

If an update applied a migration that the previous release does not know, `update-readiness.php` on the previous release will fail because the database contains an unknown migration. Do not force the downgrade.

In that case, rollback means restoring the pre-update database and the matching previous code/release from the verified backup.

Open Phonebook does not currently implement automatic down-migrations.

## Post-update checks

After every update:

- `php bin/update-readiness.php` returns `ready`
- system administrator login works
- Operations page shows the expected version
- database health is healthy
- address books and contacts load
- notification test send can be queued
- Google connection/sync remains valid if configured
- device phonebook endpoint works on a test device where practical
- scheduler heartbeats recover after cron jobs are re-enabled

## Release checklist

Before creating a tagged release:

1. CI on the release commit is green for all supported PHP versions, including the locked Composer dependency audit.
2. `VERSION` matches the intended release version.
3. `composer.lock` is committed and validated.
4. Changelog is updated.
5. Migration list is complete and ordered.
6. Update and backup/restore documentation reflects any new operational requirement.
7. Security-sensitive configuration changes are documented.
8. Upgrade from the previous release has been exercised on a representative database backup where practical.
9. A restore test remains possible using the documented backup boundary.

Tags/releases should point to an immutable commit. Do not silently replace code under an existing release version.
