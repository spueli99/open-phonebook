# Open Phonebook

Open Phonebook is a self-hosted, multi-user contact hub for IP phones, call events, and notifications.

## Project goals

Open Phonebook is designed to be universal and extensible while solving practical needs first:

- multi-user address books
- optional organizations and shared address books
- local contacts
- external contact sources such as Google Contacts
- vCard and CSV import
- device-specific phonebook exports
- Yealink as the first phone adapter
- generic call-event processing
- notifications such as Pushover and email
- no PBX requirement

The core must remain vendor-neutral. Device vendors, contact providers, and notification services are implemented as adapters.

## Technology direction

- PHP 8.3+
- MariaDB/MySQL
- Composer
- API-first architecture
- versioned database migrations
- responsive web UI
- UI localization: English and German
- source code, API, database schema, and development documentation in English
- shared-hosting friendly, with ALL-INKL as the first deployment target
- future support for Docker and environment-based configuration

## Non-goals

Open Phonebook is not intended to become a PBX. It will not handle SIP registration, routing, RTP, or media processing.

## Status

Pre-release, feature-complete enough for focused 0.1.0 hardening and deployment validation. There is no stable release yet; production use should follow the documented backup, update-readiness, and security guidance.

## Documentation

See the [docs](docs/) directory.

## License

MIT
