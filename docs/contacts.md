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
- authenticated web UI for address books and contacts
- CSRF-protected create, update, rename, and delete operations
- owner-scoped contact search by name, organization, phone label, and raw phone number

Not included yet:

- address-book sharing
- organization-owned address books
- external contact sources
- imports
- duplicate handling
- international phone-number normalization

## Web UI

Authenticated users can manage their own address books from `/address-books/`. The UI supports creating, renaming, opening, and soft-deleting address books as well as creating, editing, and soft-deleting contacts.

Contact forms expose multiple phone-number slots without introducing a JavaScript dependency. Empty phone-number fields are ignored server-side.

Address-book views provide a GET-based search across display name, given name, family name, organization name, phone labels, and raw phone-number values. Search remains scoped to the already authorized address book. SQL LIKE wildcards from user input are escaped before matching.

All state-changing operations use POST and require a valid CSRF token. Detail and edit reads are resolved through owner-scoped application services before data is rendered.

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
