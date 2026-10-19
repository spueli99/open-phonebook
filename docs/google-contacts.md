# Google Contacts

## Scope

Open Phonebook supports an optional read-only Google Contacts source for a personal address book.

The Google integration never writes contacts back to Google.

The OAuth scope is limited to:

`https://www.googleapis.com/auth/contacts.readonly`

## Configuration

Google OAuth client credentials are runtime secrets and are not stored in the database.

Configure either `config/local.php`:

```php
'providers' => [
    'google' => [
        'client_id' => '...',
        'client_secret' => '...',
    ],
],
```

or the environment variables:

- `OPENPHONEBOOK_GOOGLE_CLIENT_ID`
- `OPENPHONEBOOK_GOOGLE_CLIENT_SECRET`

The application `base_url` must be HTTPS. Register this redirect URI in the Google Cloud OAuth client:

`<base_url>/sources/google/callback/`

## OAuth security

The authorization flow uses:

- the read-only contacts scope only
- `access_type=offline`
- explicit consent so a refresh token can be obtained
- OAuth `state` validation
- PKCE with SHA-256
- a 10-minute state lifetime
- state binding to the signed-in user and destination address book
- single-use state consumption

Only an address-book owner may connect or reconnect Google Contacts.

## Token storage

The Google client secret remains in runtime configuration.

The refresh token is encrypted before database persistence with AES-256-GCM using key material derived from the application key through HKDF-SHA-256.

The database stores only the encrypted refresh token. Access tokens are short-lived and are not persisted.

Changing the application key without migrating encrypted provider secrets makes stored provider credentials unreadable.

## Synchronization

Google synchronization uses an initial full snapshot followed by incremental People API sync-token requests.

The People API endpoint is `people/me/connections` with explicit fields:

- names
- organizations
- biographies
- phone numbers

Pagination is followed until all pages are read. The initial full sync requests a `nextSyncToken`, which is encrypted before database persistence. Subsequent syncs use that token and process only changed resources.

Google sync tokens expire after seven days. If Google returns HTTP 410 for an expired token, Open Phonebook automatically performs a new full snapshot and stores the replacement token.

Google `resourceName` values are stored as external source identifiers in `source_records`.

During a full sync:

- mapped contacts are updated
- new Google contacts are created locally
- source records that disappeared from the complete Google snapshot are soft-deleted locally
- only contacts belonging to that Google source are eligible for source-driven deletion
- Google records that cannot currently be mapped into the local contact domain are counted as seen and skipped rather than causing an existing local mapping to be deleted

During an incremental sync:

- only changed Google resources are processed
- `metadata.deleted=true` removes the mapped local source contact
- resources not present in the delta response are left untouched
- `metadata.previousResourceNames` is used to keep the same local contact mapped when Google changes a resource name

The application of each full or incremental batch runs inside one database transaction. The replacement sync token is stored only after that transaction succeeds.

## Scheduled synchronization

A continuously running worker is not required. Configure cron to execute:

```bash
php /path/to/open-phonebook/bin/google-contacts-sync.php
```

The command synchronizes all configured Google sources independently. One failing source does not stop the remaining sources. It exits with status `0` when all sources succeed, `1` when one or more sources fail, and `2` when the scheduler cannot be bootstrapped.

Running the command at least once per day keeps the incremental token comfortably within Google's seven-day validity window. More frequent runs, such as hourly, are also supported.

## Current limitations

- emails, postal addresses, birthdays, photos, and fields not yet represented by the local contact domain are not imported
- local edits to source-managed contacts may be overwritten by the next Google sync
- Google write-back is intentionally not supported
