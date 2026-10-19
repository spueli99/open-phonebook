# Roadmap

The roadmap is capability-driven. Version numbers are provisional until development begins.

## Foundation

- Composer/autoloading
- configuration layer
- MariaDB/MySQL connection
- versioned migrations
- web installer
- authentication
- users
- optional organizations
- permissions
- localization infrastructure (English and German)
- audit foundation

## Contacts

- local address books
- local contacts
- multiple phone numbers
- international number normalization
- search
- soft delete
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
