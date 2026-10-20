# Roadmap

The roadmap is capability-driven. Version numbers are provisional until development begins.

## Foundation

Completed or in active foundation work:

- Composer/autoloading
- configuration layer
- MariaDB/MySQL connection
- versioned migrations
- web installer
- local email/password authentication
- secure browser sessions
- login throttling
- English/German localization foundation
- system-level user administration
- invitation creation and acceptance
- initial explicit permission model
- active invitation management
- guarded platform role management

Still planned in the foundation area:

- email delivery for invitations
- password reset/recovery
- optional organizations
- organization-scoped authorization policies
- audit-event persistence

## Contacts

Completed or in active work:

- local personal address-book persistence
- local contact persistence
- multiple phone numbers
- owner-scoped contact services
- soft deletion
- personal address-book and contact web UI
- owner-scoped contact search
- libphonenumber-based E.164 normalization with explicit default region
- personal address-book sharing with read/write access

Still planned:

- organization-owned/shared address books

## Contact sources and imports

Completed or in active work:

- shared import-preview application boundary
- strict UTF-8 CSV preview with upload/parser limits
- writable-address-book authorization for previews
- explicit CSV confirmation with short-lived session drafts
- transaction-safe CSV persistence
- E.164-based duplicate detection with default skip and explicit force-import
- conservative merge into one unambiguous existing contact
- vCard 2.1/3.0/4.0 preview and confirmed import
- Google Contacts read-only OAuth connection
- Google Contacts full + incremental sync-token synchronization
- cron-compatible scheduled Google Contacts synchronization

Still planned:

- future CardDAV client/synchronization
- future Microsoft 365 source

## Devices and exports

Completed or in active work:

- device management
- scoped phonebook credentials
- explicit readable address-book assignment
- exporter interface and registry
- sessionless device-facing phonebook endpoint
- JSON exporter
- CSV exporter
- Yealink Remote Phone Book exporter

Still planned:

- future Snom, Grandstream, Fanvil and other adapters
- credential last-used tracking

## Call events

Completed or in active work:

- independent `call-events:write` device credentials
- generic JSON call-event API
- transactional call-session state machine
- Yealink Action URL normalization adapter
- scoped caller lookup through assigned/currently-readable address books

Still planned:

- secure direct Yealink callback transport/endpoint
- configurable call-history retention
- generic endpoint aliases for intercoms and other known callers

## Notifications

Completed or in active work:

- generic notification provider interface and registry
- encrypted user-owned notification channels
- Pushover
- email
- call-event notification rules for incoming, missed, and completed calls
- optional per-device rule scoping
- provider-failure isolation after call-state commit
- per-rule quiet hours in the user's timezone
- persistent cron-driven delivery queue
- bounded retry/backoff and stale-worker recovery
- aggregate delivery diagnostics
- owner-scoped recent delivery history
- manual retry for failed deliveries
- queued test-send action

Still planned:

- history filtering/pagination and retention cleanup
- Web Push/PWA
- future additional providers

## Operations

- health/diagnostics
- backup and restore documentation
- scheduler/cron heartbeat
- Docker deployment option
- API documentation
- release/update process

## Explicit non-goals

- SIP registrar
- call routing
- RTP/media processing
- full PBX replacement
