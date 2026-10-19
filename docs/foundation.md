# Foundation

The initial code foundation deliberately contains very little product logic.

## Repository layout

```text
config/                 repository defaults; local runtime configuration is ignored
database/migrations/    versioned database migrations
docs/                   architecture and development documentation
public/                 the only intended web document root
src/                    application source code
storage/                runtime state; not publicly served and not committed
tests/                  automated tests
tools/                  development/maintenance tools
```

## Configuration

Configuration precedence is:

1. environment variables
2. `config/local.php`
3. `config/defaults.php`

Environment variables currently reserved include:

- `OPENPHONEBOOK_BASE_URL`
- `OPENPHONEBOOK_TIMEZONE`
- `OPENPHONEBOOK_LOCALE`
- `OPENPHONEBOOK_DEBUG`
- `OPENPHONEBOOK_DB_HOST`
- `OPENPHONEBOOK_DB_PORT`
- `OPENPHONEBOOK_DB_NAME`
- `OPENPHONEBOOK_DB_USER`
- `OPENPHONEBOOK_DB_PASSWORD`

The setup installer will generate local runtime configuration. Secrets must never be committed.

## Web root

Only `public/` should be exposed by the web server.

`config/`, `src/`, `storage/`, `database/`, `tests/`, and `vendor/` must remain outside the document root.

## Quality gates

CI currently targets PHP 8.3 and 8.4 and runs:

- Composer validation
- PSR-12 coding-standard checks
- PHPStan level 8
- PHPUnit

## Next implementation step

The next foundation milestone is the secure web installer and versioned migration runner. Product features such as contacts, Google synchronization, device exporters, call events, and notifications will be layered on top rather than mixed into installation code.
