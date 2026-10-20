# Backup and restore

## Goal

A restorable Open Phonebook backup is more than a database export.

The database contains application data and encrypted provider/device-related state, while the application key deliberately lives outside the database. Restoring the database with a different application key can make encrypted integrations unusable and invalidate key-derived device or invitation credentials.

A complete recovery set therefore contains:

1. a complete MariaDB/MySQL database dump
2. the runtime secret configuration, especially the exact application key
3. `storage/installed.lock`
4. the matching Open Phonebook code/release
5. `composer.lock` from the matching repository/deployment
6. a non-secret readiness manifest/fingerprint for verification

Current cache and log files under `storage/` are not required for recovery. Open Phonebook currently has no persistent user-upload directory that must be restored separately.

## Backup readiness check

Run before creating a backup:

`php bin/backup-readiness.php`

Possible exit codes:

- `0`: ready
- `1`: warning
- `2`: failed or the check itself could not run

The check verifies:

- database connectivity
- all migrations expected by the current code are present
- the database does not contain migrations unknown to the current code
- the effective application key exists and has a valid minimum length
- `storage/installed.lock` exists
- whether `config/local.php` exists
- whether `composer.lock` is present
- counts of records whose continued usability depends on the same application key

The report never outputs the application key, database password, OAuth client secret, provider token, notification payload, phone number, or contact data.

### JSON manifest

For a machine-readable, non-secret manifest:

`php bin/backup-readiness.php --json > backup-readiness.json`

The manifest includes the application-key fingerprint but not the key itself.

Store this manifest with the database dump. It is safe to use for later key verification, but it should still be treated as operational metadata rather than published publicly.

## Application-key fingerprint

The readiness check derives a short fingerprint from the effective application key using a dedicated domain-separated HMAC.

The fingerprint is not the application key and cannot be used to decrypt application data.

Its purpose is to detect a restore with the wrong key.

Example restore verification:

`php bin/backup-readiness.php --expected-key-fingerprint=0123456789abcdef01234567`

Use the fingerprint recorded in the original backup manifest.

A mismatch is a hard failure.

## What must be backed up

### 1. Database

Export the complete Open Phonebook database, including all tables.

For MariaDB/MySQL command-line environments, a typical consistent InnoDB dump is:

`mysqldump --single-transaction --quick --default-character-set=utf8mb4 -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME > open-phonebook.sql`

Do not put the database password directly on the command line. Use the interactive `-p` prompt or a protected client option file.

On shared hosting, use the provider database backup/export feature or phpMyAdmin and export the complete database as SQL, preferably compressed.

Do not perform application schema migrations at the same time as the dump.

### 2. Runtime secret configuration

If the installation uses `config/local.php`, back up that file through a secure channel.

The installer writes it with restrictive permissions and it may contain:

- the application key
- database connection settings
- application settings

If runtime values are supplied through environment variables or the hosting control panel instead, document and back up those values in the platform's secret-management process.

Especially critical:

- `OPENPHONEBOOK_APP_KEY` / `app.key`
- Google OAuth client credentials when configured
- the system mail sender configuration used for password recovery
- Web Push VAPID subject/public/private key when configured
- database connection credentials or the information required to recreate them
- relevant base URL / phone-region / operations overrides

The application key must be restored exactly. Generating a new key is not equivalent to restoring the old one.

If Web Push is enabled, the VAPID key pair must also be restored exactly. Rotating it invalidates the relationship with existing browser subscriptions and requires users to subscribe their browsers again.

### 3. Installation lock

Back up:

`storage/installed.lock`

This file is not secret, but restoring it prevents the setup installer from being unintentionally re-enabled.

### 4. Dependency lock

`composer.lock` is committed to the repository and must be retained with the matching code/release.

Composer installation during recovery must use the lock so the same dependency versions are restored.

The readiness check reports a warning if `composer.lock` is missing because dependency resolution would no longer be exactly reproducible.

### 5. Code/release identity

Record the exact Open Phonebook release, Git commit, or deployment artifact used when the backup was created.

A database containing migrations unknown to the restored code is rejected by the readiness check.

Prefer restoring the matching code first. Upgrade separately after recovery has been verified.

## Key-dependent database records

The readiness report counts application-key-dependent records without exposing their content:

- Google/contact-source records with encrypted tokens
- notification channels with encrypted provider configuration
- encrypted notification retry payloads
- active device credentials whose stored hashes are keyed by the application key
- active invitation tokens whose hashes are keyed by the application key
- active password-reset tokens whose hashes use application-key-derived domain-separated material

Login-throttling subject hashes are intentionally not treated as recovery-critical user data. A key change can make old throttle buckets irrelevant, but it does not destroy contacts, accounts, or integration secrets.

## Backup procedure

Recommended sequence:

1. Avoid deployments or database migrations during the backup.
2. Run `php bin/backup-readiness.php`.
3. Save the JSON manifest.
4. Create the complete database dump.
5. Securely back up `config/local.php` or the equivalent environment-managed secrets.
6. Back up `storage/installed.lock`.
7. Back up `composer.lock`.
8. Record the exact application release/commit.
9. Store database and secret backup separately or in an encrypted backup repository.
10. Verify that the dump is non-empty and can be read by the chosen restore tool.
11. Periodically perform a restore test on an isolated environment.

A backup that has never been restore-tested should not be considered proven.

## Restore procedure

Restore into an isolated or maintenance-protected environment first.

Recommended order:

1. Deploy the matching Open Phonebook code/release.
2. Keep scheduled jobs disabled during the restore.
3. Keep public application traffic blocked or otherwise place the site in maintenance at the hosting layer.
4. Create an empty target database with the expected charset/collation support.
5. Import the complete SQL dump.
6. Restore the original runtime configuration/application key.
7. Restore `storage/installed.lock`.
8. Restore the matching `composer.lock`.
9. Install Composer production dependencies for that code version using the lock.
10. Run the readiness check with the fingerprint from the backup manifest.
11. Perform the functional checks below.
12. Re-enable the notification and Google cron jobs.
13. Re-enable public traffic.

Do not run the web installer against a restored database.

Do not create a new application key during restore.

## Restore verification

After importing the database and restoring secrets:

`php bin/backup-readiness.php --expected-key-fingerprint=<fingerprint-from-backup>`

For JSON output:

`php bin/backup-readiness.php --json --expected-key-fingerprint=<fingerprint-from-backup>`

The restore must not be accepted if:

- the key fingerprint mismatches
- expected migrations are missing
- unknown migrations exist
- the application key is unavailable
- the installation lock is missing

A missing `config/local.php` produces a warning rather than a failure when the effective application key is available from environment-managed configuration. A missing `composer.lock` also produces a warning because dependency resolution is then not exact. In that case, verify all required platform secrets independently.

## Functional post-restore checks

After readiness passes:

- sign in with a system administrator account
- open an address book and verify contacts
- open Operations diagnostics and verify database status
- verify notification channels are still present
- send a notification test through the queue
- if Google Contacts is used, verify the source is still connected and run/observe a synchronization
- verify a device phonebook credential on a non-production/test device where practical
- verify cron heartbeats become healthy after jobs are re-enabled

Do not expose production provider credentials to an untrusted restore-test environment.

## Backup storage security

Backups contain personal contacts, authentication metadata, call metadata, encrypted provider configuration, and potentially database-level credential material.

Therefore:

- encrypt backups at rest
- restrict backup access
- protect secret configuration separately
- do not commit backups or `config/local.php` to Git
- avoid sending raw database dumps through ordinary email
- use retention appropriate to the sensitivity of the data
- securely delete expired backup copies where supported
- protect off-site copies with independent credentials and MFA where available

The application-key fingerprint may be stored alongside a dump. The application key itself should be protected as a secret.

## Shared-hosting notes

Open Phonebook remains shared-hosting friendly.

If shell access is unavailable:

- run the readiness CLI through an approved provider shell/cron environment when possible
- export/import the database through the hosting control panel or phpMyAdmin
- download/upload `config/local.php` and `storage/installed.lock` over the provider's protected file-management channel
- record the deployed code/release separately

Never place a database dump or runtime configuration under the public document root for download.

## Updates

Before every schema-changing application update, complete a verified backup using this recovery boundary. The separate [Release and update process](update-release.md) defines update readiness, explicit migration execution, and rollback behavior.

## Disaster-recovery principle

The database and application key form one recovery unit.

The database may be replaceable from backup. The original application key is not derivable from the database and cannot be recreated from its fingerprint.

Loss of the application key can require reconnecting external sources, recreating notification configuration, rotating device credentials, and invalidating outstanding invitations. Protect it accordingly.
