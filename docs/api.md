# API

## General direction

Open Phonebook is API-first.

The initial API namespace is:

`/api/v1/`

The web UI should call the same application services as the API.

## Resource areas

Expected resource groups include:

- users
- organizations
- address-books
- contacts
- contact-sources
- devices
- exports
- call-events
- call-sessions
- notification-channels
- notification-rules
- health

## Authentication

Human API access and browser sessions are distinct from device authentication.

Devices use scoped, revocable credentials.

## Versioning

Breaking API changes require a new API version.

## Call events

The core should accept normalized call events. Vendor-specific payloads are translated by adapters.

Canonical event examples:

- ringing
- calling
- connected
- idle

The call-session service derives final state such as missed, completed, or cancelled.

## Exports

Device phonebook output is generated through exporter interfaces rather than embedding vendor XML in controllers.

## Health

A health endpoint may expose non-sensitive operational status such as:

- application version
- database connectivity
- migration state
- scheduler/cron heartbeat
- last successful background run

Sensitive provider or account data must not be exposed.
