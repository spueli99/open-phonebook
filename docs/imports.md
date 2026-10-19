# Contact Imports

## Current scope

CSV imports now use an explicit preview-and-confirm flow. Untrusted input is validated and rendered first. Only after explicit confirmation is the same server-side draft parsed again and persisted.

The shared import boundary remains provider-neutral so future vCard and external-source adapters can reuse the same application model.

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

## Preview and confirmation

Rows with an empty `display_name` are skipped and reported as warnings.

A preview containing no importable contacts is rejected.

After a successful preview, the uploaded CSV content is kept only as one short-lived server-side session draft. The browser receives a random one-time draft token, not the CSV contents. The session stores only an HMAC of that token together with the user ID, destination address-book ID, CSV contents, and a 15-minute expiration.

Confirmation is a separate CSRF-protected POST. The draft must still belong to the same user and address book, must not be expired, and the user must still have write access. The CSV is parsed and validated again at confirmation time.

All contacts are then inserted inside one outer database transaction. The contact repository participates in that outer transaction rather than committing each contact independently. The draft is discarded only after successful commit.

## Security

Uploads are treated as untrusted input.

The uploaded temporary file is read only after PHP reports a successful upload and after server-side size checks. Open Phonebook does not move the file into persistent application storage for preview.

All preview POST requests require a valid authenticated session, write access to the target address book, and CSRF validation.

## Still planned

- vCard parser and preview
- duplicate detection
- merge/skip decisions
- explicit handling of duplicate rows before persistence
- source-record tracking for synchronized providers
