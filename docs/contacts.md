# Address Books and Contacts

## Scope

The first contacts milestone establishes the local personal address-book domain and persistence boundaries.

Included:

- personal address books owned by one user
- local contacts inside an address book
- multiple phone numbers per contact
- contact and address-book soft deletion
- explicit owner checks before reading or mutating contact data
- transaction-safe creation and updates of contacts with phone numbers

Not included yet:

- address-book sharing
- organization-owned address books
- external contact sources
- imports
- search
- duplicate handling
- contact web UI
- international phone-number normalization

## Ownership

Every address book currently has exactly one `owner_user_id`.

Application services require both the resource identifier and the authenticated user's identifier. A contact operation is permitted only after the parent address book has been resolved as owned by that user.

System-administrator status does not implicitly grant contact access. Private contact data remains outside platform-administration permissions.

## Contacts

A contact currently supports:

- display name
- given name
- family name
- organization name
- notes
- multiple phone numbers

The display name is required. Optional text fields are trimmed and empty values are stored as `NULL`.

## Phone numbers

Each phone-number record stores:

- optional label
- raw/source representation
- nullable normalized E.164 representation
- stable sort order

The first milestone deliberately stores only the validated raw representation. `normalized_e164` remains `NULL` until a real normalization component with explicit region/country context is introduced.

This avoids guessing international numbers from ambiguous local formats.

## Deletion

Address books and contacts use soft deletion. Deleted address books are excluded from ownership lookups, and deleted contacts are excluded from contact queries.

Phone-number rows belong to a contact and are replaced transactionally during contact updates.

## Persistence

Contact creation and update use database transactions so the contact row and its phone-number set cannot be partially committed.

Foreign keys use restrictive deletion for users/address books and cascading deletion only from a contact to its dependent phone-number rows.
