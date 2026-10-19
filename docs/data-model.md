# Data Model

This document defines the initial conceptual model. The physical schema will be introduced through versioned migrations.

## Identity and sharing

### users

Individual accounts.

### organizations

Optional containers for teams, companies, families, communities, or other shared contexts.

A user can operate without an organization.

### organization_members

Membership and role assignment.

## Contacts

### address_books

A named collection of contacts.

The physical schema supports personal address books through `owner_user_id`. Explicit user sharing is stored separately in `address_book_shares`, preserving one unambiguous owner. Organization ownership remains a future extension rather than a nullable ownership shortcut.

### address_book_shares

Grants one existing user either `read` or `write` access to one personal address book. The `(address_book_id, user_id)` pair is unique, so changing access updates a single grant rather than creating duplicates.

### contacts

Canonical local contact records.

The first physical schema includes display name, given name, family name, organization name, notes, timestamps, and soft-delete state.

### contact_phone_numbers

Multiple numbers per contact.

Store both:

- raw/source representation
- normalized international representation where possible

E.164 normalization helps matching but must not be treated as a globally unique person identifier because multiple contacts can legitimately share a number.

## Sources

### contact_sources

External source definitions. The initial physical implementation stores an owner, destination address book, provider identifier, encrypted provider refresh token, and synchronization timestamps. Google is the first provider.

### source_records

Mapping between one external provider record identifier and one canonical local contact. Google uses the People API `resourceName` as the external identifier. This allows repeatable synchronization without hard-coding provider fields into the contact table.

## Devices

### devices

A phone, intercom, software endpoint, or other consumer.

### device_credentials

Scoped, revocable credentials. Devices do not authenticate as normal users.

### device_address_books

Defines which address books are visible to a device.

## Calls

### call_sessions

Derived call lifecycle.

Typical outcomes:

- incoming
- outgoing
- completed
- missed
- cancelled
- ended/unknown

### call_events

Immutable normalized events such as ringing, calling, connected, and idle.

## Notifications

### notification_channels

User-configured provider endpoints.

### notification_rules

Conditions that determine when and how to notify.

## Time

Persist timestamps in UTC. Convert to the user's configured timezone for display.

## Deletion and retention

Contacts and address books should support soft deletion where practical.

Call history uses configurable retention and may be disabled entirely.
