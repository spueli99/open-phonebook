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

External source definitions. The physical implementation stores an owner, destination address book, provider identifier, encrypted provider refresh token, encrypted incremental sync token, and synchronization timestamps. Google is the first provider.

### source_records

Mapping between one external provider record identifier and one canonical local contact. Google uses the People API `resourceName` as the external identifier. This allows repeatable synchronization without hard-coding provider fields into the contact table.

## Devices

### devices

A phone, intercom, software endpoint, or other consumer. The initial physical schema stores one owner user, name, optional vendor/model metadata, active state, and timestamps.

### device_credentials

Scoped, revocable credentials. Devices do not authenticate as normal users. The physical schema stores scope, HMAC token hash, creation/revocation timestamps, and a future last-used timestamp; plaintext tokens are never stored.

### device_address_books

Explicit many-to-many assignment between devices and address books. Assignment creation requires current read access by the device owner; export-time authorization must revalidate that access.

## Calls

### call_sessions

One derived call lifecycle per call occurrence. Open sessions are looked up by device and external call ID; external IDs may be reused after a terminal session.

The physical schema stores direction, current state, local/remote numbers, optional resolved contact name, and UTC lifecycle timestamps.

Current states include ringing, calling, connected, completed, missed, and cancelled.

### call_events

Append-only normalized lifecycle events linked to a call session.

The physical schema stores the canonical event type, resulting state, and UTC occurrence timestamp.

## Notifications

### notification_channels

User-owned provider endpoints. The physical schema stores a display name, provider identifier, encrypted provider configuration, active state, and timestamps. Provider secrets are not stored in plaintext.

### notification_rules

Owner-scoped conditions connecting one notification channel to a trigger. A rule may optionally target one device owned by the same user; a null device applies to all of that user's devices.

Current triggers are incoming, missed, and completed call notifications. Rules may also store a quiet-hours start/end pair interpreted in the owner's timezone.

### notification_deliveries

Persistent outbound delivery jobs created after call-state commit and quiet-hours evaluation.

The physical schema stores owner/channel references, trigger, rendered title/body, optional encrypted retry payload, status, attempt count, next-available time, worker lock timestamp, last-error timestamp, sent timestamp, and UTC audit timestamps.

Current delivery states are pending, processing, sent, failed, and cancelled. Rendered title/body are cleared in terminal states. Sent/cancelled jobs also clear the retry payload; failed jobs may keep only the encrypted retry payload for explicit owner-initiated retry.

## Time

Persist timestamps in UTC. Convert to the user's configured timezone for display.

## Deletion and retention

Contacts and address books should support soft deletion where practical.

Call history uses configurable retention and may be disabled entirely.


### scheduler_heartbeats

One technical heartbeat row per supported cron task.

The schema stores the task name, last started/succeeded/failed UTC timestamps, last status, aggregate attempted/succeeded/failed counts, and update timestamp.

No user content, provider credentials, exception messages, source identifiers, phone numbers, or notification payloads are stored in this table.
