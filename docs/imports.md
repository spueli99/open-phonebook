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

## Duplicate detection

Before confirmation, import candidates are compared by valid normalized E.164 phone numbers. A candidate is marked as a duplicate when at least one of its normalized phone numbers already exists in the target address book, or when the same normalized number appeared in an earlier import candidate.

Names alone never trigger automatic duplicate handling. Ambiguous or non-normalizable phone numbers are not treated as duplicate evidence.

Duplicate candidates are skipped by default. For an existing-contact duplicate, the preview offers `Merge` only when all matching normalized phone numbers resolve to exactly one existing contact. Ambiguous matches never offer merge. `Import as separate contact` remains an explicit alternative.

A merge is conservative: the existing display name is retained, non-empty existing optional fields are never overwritten, empty optional fields may be filled from the import, and only phone numbers not already represented by the same E.164 value (or raw fallback when E.164 is unavailable) are appended. Existing phone labels are retained.

At confirmation time duplicate detection and the merge target are recalculated against the current address-book state. If a requested merge is no longer unambiguous, the entire confirmation is rejected instead of silently importing a new contact or selecting another target.

The confirmation result reports imported, merged, and skipped contacts separately.

## Security

Uploads are treated as untrusted input.

The uploaded temporary file is read only after PHP reports a successful upload and after server-side size checks. Open Phonebook does not move the file into persistent application storage for preview.

All preview POST requests require a valid authenticated session, write access to the target address book, and CSRF validation.

## Still planned

- vCard parser and preview
- source-record tracking for synchronized providers
