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
- explicit personal address-book sharing with read or write access

Not included yet:

- organization-owned address books
- external contact sources
- imports
- duplicate handling

## Web UI

Authenticated users can manage their own address books from `/address-books/`. The UI supports creating, renaming, opening, and soft-deleting address books as well as creating, editing, and soft-deleting contacts.

Contact forms expose multiple phone-number slots without introducing a JavaScript dependency. Empty phone-number fields are ignored server-side.

Address-book views provide a GET-based search across display name, given name, family name, organization name, phone labels, raw phone-number values, and normalized E.164 values. Search remains scoped to the already authorized address book. SQL LIKE wildcards from user input are escaped before matching.

All state-changing operations use POST and require a valid CSRF token. Detail and edit reads are resolved through owner-scoped application services before data is rendered.

## Sharing

An address-book owner may share a personal address book with another active, non-deleted user by email address.

Two access levels exist:

- `read`: list, open, and search contacts
- `write`: all read operations plus create, edit, and soft-delete contacts

Only the owner may rename or soft-delete the address book itself, manage sharing, or revoke a share. A platform `system_admin` receives no implicit access to private address books.

Sharing with the owner is rejected. Repeating a share operation for the same user updates the existing access level rather than creating duplicates.

## Ownership

Every address book currently has exactly one `owner_user_id`.

Application services require both the resource identifier and the authenticated user's identifier. Contact reads require resolved read access; contact mutations require resolved write access. Address-book metadata mutations and sharing administration continue to require ownership.

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

Phone numbers are normalized with libphonenumber when they can be validated safely. The raw representation remains stored alongside the normalized value.

International numbers beginning with `+` can be normalized without a default region. Local/national numbers are interpreted only when `contacts.default_region` contains an explicit supported ISO 3166-1 alpha-2 country code such as `DE`. When no region is configured, ambiguous local numbers remain with `normalized_e164 = NULL` rather than being guessed.

The default region may also be supplied through `OPENPHONEBOOK_DEFAULT_PHONE_REGION`. New installations can set it in the web installer. Existing stored phone numbers are not rewritten automatically; they receive normalization when the contact is subsequently saved, or through a future explicit maintenance/import operation.

## Deletion

Address books and contacts use soft deletion. Deleted address books are excluded from ownership lookups, and deleted contacts are excluded from contact queries.

Phone-number rows belong to a contact and are replaced transactionally during contact updates.

## Persistence

Contact creation and update use database transactions so the contact row and its phone-number set cannot be partially committed.

Foreign keys use restrictive deletion for users/address books and cascading deletion only from a contact to its dependent phone-number rows.
