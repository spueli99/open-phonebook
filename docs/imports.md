# Contact Imports

## Current scope

The first import milestone is preview-only. It validates untrusted input and renders the contacts that would be considered for a future import, but it does not persist any contact data.

This establishes one shared import boundary before vCard and additional CSV import behavior are added.

## CSV preview

Writable address books expose a CSV preview action.

The current parser accepts UTF-8 CSV with a required header row.

Required column:

- `display_name`

Optional columns:

- `given_name`
- `family_name`
- `organization_name`
- `notes`
- `phone_1`, `phone_1_label`
- `phone_2`, `phone_2_label`
- `phone_3`, `phone_3_label`

Unknown or duplicate columns are rejected rather than silently ignored.

## Limits

The preview enforces:

- maximum upload size: 2,000,000 bytes
- maximum data rows: 2,000
- valid UTF-8
- no NUL bytes
- domain-aligned field length limits
- writable access to the destination address book

The browser file extension and MIME declaration are convenience hints only. The server validates the parsed content independently.

## Preview semantics

Rows with an empty `display_name` are skipped and reported as warnings.

A preview containing no importable contacts is rejected.

Preview rendering does not create, update, or delete contacts. There is intentionally no confirmation/persist action in this milestone.

## Security

Uploads are treated as untrusted input.

The uploaded temporary file is read only after PHP reports a successful upload and after server-side size checks. Open Phonebook does not move the file into persistent application storage for preview.

All preview POST requests require a valid authenticated session, write access to the target address book, and CSRF validation.

## Still planned

- CSV persistence/import confirmation
- vCard parser and preview
- duplicate detection
- merge/skip decisions
- transactional persistence
- safe cancellation/rollback semantics
- source-record tracking for synchronized providers
