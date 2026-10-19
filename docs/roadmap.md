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

Still planned:

- international number normalization
- sharing

## Contact sources and imports

- Google Contacts read-only synchronization
- vCard import
- CSV import
- import preview
- duplicate detection and merge decisions
- future CardDAV client/synchronization
- future Microsoft 365 source

## Devices and exports

- device management
- scoped credentials
- address-book assignment
- exporter interface
- Yealink exporter
- JSON exporter
- CSV exporter
- future Snom, Grandstream, Fanvil and other adapters

## Call events

- generic call-event API
- call-session state machine
- Yealink Action URL adapter
- caller lookup
- configurable call-history retention
- generic endpoint aliases for intercoms and other known callers

## Notifications

- Pushover
- email
- notification rules
- quiet hours
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
