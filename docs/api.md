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

Devices use scoped, revocable credentials. Device phonebook requests accept credentials only through the HTTP `Authorization` header:

- Bearer: `Authorization: Bearer <device-token>`
- HTTP Basic for phone compatibility: username `device`, device token as password

Device tokens must never be placed in URLs or query strings.

## Versioning

Breaking API changes require a new API version.

## Call events

Implemented endpoint:

`POST /api/v1/device/call-events/`

It requires a device credential with scope `call-events:write`, which is independent from `phonebook:read`.

The request body must be JSON and is limited to 16 KiB.

Canonical events:

- `ringing`
- `calling`
- `connected`
- `idle`

Initial events require direction `incoming` or `outgoing`; follow-up events are attached by device and external call ID.

The call-session service derives terminal states such as `completed`, `missed`, and `cancelled`. Invalid transitions are rejected.

Vendor-specific payloads are translated by adapters. The Yealink Action URL adapter is implemented as a normalization layer, but no direct callback endpoint is enabled until a secure callback credential transport has been verified.

## Notifications

Notification channels and rules are currently managed through the authenticated web UI and shared application services; no public notification-management API is exposed yet.

Call-event notification dispatch occurs after a successful call-state commit. Current triggers are `incoming`, `missed`, and `completed`. Rules may apply to all of a user's devices or one specific owned device.

Current providers are Pushover and email.

Call-event requests do not contact external notification providers. Matching rules outside quiet hours create persistent delivery jobs; the cron worker performs provider delivery and retries independently.

## Exports

Device phonebook output is generated through exporter interfaces rather than embedding vendor XML in controllers.

Implemented endpoint:

`GET /api/v1/device/phonebook/?format=<format>`

Implemented formats:

- `json`
- `csv`
- `yealink`

The endpoint requires the `phonebook:read` device scope. It intersects explicit device-to-address-book assignments with the owner's current address-book access on every request.

The device runtime is sessionless: device fetches do not create or reuse browser sessions.

Responses containing contact data are marked `Cache-Control: no-store, private` and `X-Content-Type-Options: nosniff`.

## Health

A health endpoint may expose non-sensitive operational status such as:

- application version
- database connectivity
- migration state
- scheduler/cron heartbeat
- last successful background run

Sensitive provider or account data must not be exposed.
