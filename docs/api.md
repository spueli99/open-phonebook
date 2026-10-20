# HTTP API reference

## Scope

Open Phonebook currently exposes a deliberately small public HTTP API for devices.

Implemented public API endpoints:

- `GET /api/v1/device/phonebook/?format=<format>`
- `POST /api/v1/device/call-events/`
- `GET /api/v1/device/yealink/action-url/`

Browser-session endpoints used by the web UI are not part of the public device API and are not covered by the compatibility guarantees in this document.

There is currently no public API for user administration, address-book mutation, notification administration, audit data, or operations health.

## Base path and versioning

The current API namespace is:

`/api/v1/`

Breaking public API changes require a new API version. Compatible additions may be made within `v1`.

## Transport security

Production device API requests must use HTTPS.

Device credentials are secrets and must never be placed in:

- query strings
- callback URLs
- Referer-bearing links
- logs
- configuration values that may be rendered publicly

Credentials are accepted only through the HTTP `Authorization` header or the equivalent PHP Basic-Auth server variables.

## Device authentication

Each device credential is scoped.

Current scopes:

- `phonebook:read`
- `call-events:write`
- `yealink-callback:write`

A credential is accepted only when:

- the token has the expected 64-character lowercase hexadecimal format
- its HMAC matches an active credential
- the requested scope matches
- the credential is not revoked
- the device is active
- the owning user is active and not deleted

Credentials may be supplied in either of these forms.

Bearer authentication:

`Authorization: Bearer <device-token>`

HTTP Basic compatibility mode:

- username: `device`
- password: `<device-token>`

Example:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=json'
```

A successful protected operation updates the exact credential's `last_used_at` timestamp. Authentication alone is not enough: rejected requests or failed protected operations do not count as successful use.

### Authentication-failure throttle

Public device endpoints throttle failed credential checks independently by endpoint and client IP address.

Current policy:

- 20 failed credential checks within 15 minutes
- 15-minute block after the threshold is reached
- blocked requests return `429 Too Many Requests`
- blocked responses include `Retry-After: 900`
- successful authentication clears only that endpoint/IP bucket
- the database stores only a domain-separated HMAC of endpoint + client IP, never the plaintext credential, Authorization header, or phone number

## Common response behavior

Private device responses use no-store headers. Successful phonebook and call-event responses include:

- `Cache-Control: no-store, private`
- `Pragma: no-cache`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: no-referrer`

Authentication failures return `401 Unauthorized` and advertise both Bearer and Basic authentication schemes.

The public API does not currently return detailed machine-readable error objects. Clients should rely on HTTP status codes and treat response bodies on error as non-contractual.

---

# Phonebook export

## Endpoint

`GET /api/v1/device/phonebook/?format=<format>`

Required scope:

`phonebook:read`

The endpoint is sessionless and does not create or reuse a browser session.

At request time, the exported contacts are restricted to address books that are both:

1. explicitly assigned to the device
2. still readable by the owning user

A stale device-to-address-book assignment therefore cannot preserve access after a later share revocation.

## Query parameter

### `format`

Required.

Allowed syntax:

- lowercase letters
- digits
- underscore
- hyphen

Implemented values:

- `json`
- `csv`
- `yealink`

Unknown but syntactically valid formats return `404 Not Found`.

Invalid format syntax returns `400 Bad Request`.

## Contact limit

A single phonebook response is limited to 5,000 contacts by the phonebook service.

The Yealink serializer additionally rejects generated XML larger than 1.5 MB.

## JSON format

Request:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=json'
```

Content type:

`application/json; charset=utf-8`

Filename:

`phonebook.json`

Response schema:

```json
{
  "version": 1,
  "contacts": [
    {
      "name": "Alice Example",
      "numbers": [
        {
          "number": "+491701234567",
          "label": "mobile"
        },
        {
          "number": "0211 1234",
          "label": null
        }
      ]
    }
  ]
}
```

Fields:

- `version`: current export schema version, currently `1`
- `contacts[].name`: display name
- `contacts[].numbers[].number`: exported phone number
- `contacts[].numbers[].label`: optional label or `null`

## CSV format

Request:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=csv'
```

Content type:

`text/csv; charset=utf-8`

Filename:

`phonebook.csv`

Columns:

```text
display_name,number,label
```

Each phone number produces one CSV row.

All cells are quoted. Leading spreadsheet-formula characters `=`, `+`, `-`, and `@` are neutralized with a leading apostrophe.

## Yealink format

Request:

```sh
curl --fail-with-body \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  'https://phonebook.example/api/v1/device/phonebook/?format=yealink'
```

Content type:

`application/xml; charset=utf-8`

Filename:

`phonebook.xml`

Structure:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<YealinkIPPhoneDirectory>
  <DirectoryEntry>
    <Name>Alice Example</Name>
    <Telephone>+491701234567</Telephone>
    <Telephone>0211 1234</Telephone>
  </DirectoryEntry>
</YealinkIPPhoneDirectory>
```

## Phonebook status codes

- `200 OK`: export generated successfully
- `400 Bad Request`: invalid `format` syntax
- `401 Unauthorized`: credential missing, malformed, invalid, revoked, wrong scope, inactive device, or inactive/deleted owner
- `429 Too Many Requests`: repeated failed credential checks triggered the endpoint/IP throttle
- `404 Not Found`: syntactically valid but unsupported export format
- `405 Method Not Allowed`: request method is not GET
- `500 Internal Server Error`: export generation failed
- `503 Service Unavailable`: application runtime could not start

For `405`, the response includes:

`Allow: GET`

---

# Call events

## Endpoint

`POST /api/v1/device/call-events/`

Required scope:

`call-events:write`

Content type:

`application/json`

Request body limit:

16 KiB.

The HTTP controller reads at most 16,385 bytes and rejects bodies larger than 16,384 bytes.

## Canonical event object

Example initial incoming call:

```json
{
  "event": "ringing",
  "call_id": "abc-123",
  "direction": "incoming",
  "local_number": "100",
  "remote_number": "+491701234567"
}
```

Fields:

### `event`

Required string, maximum 32 characters.

Supported values:

- `ringing`
- `calling`
- `connected`
- `idle`

### `call_id`

Required non-empty string, maximum 191 characters.

The ID is scoped to the authenticated device.

Open call sessions are matched by device plus `call_id`.

After a session reaches a terminal state, the same external call ID may later be reused for a new call.

### `direction`

Optional in the JSON parser, but required by the call-state service for an initial event.

Supported values:

- `incoming`
- `outgoing`

Initial valid combinations are:

- `ringing` + `incoming`
- `calling` + `outgoing`

Follow-up events attach to the existing session and do not need to repeat direction.

### `local_number`

Optional string, maximum 120 characters.

Empty strings are normalized to `null`.

### `remote_number`

Optional string, maximum 120 characters.

Empty strings are normalized to `null`.

The remote number is used for scoped caller lookup when possible.

## State transitions

Initial state mapping:

- incoming `ringing` -> `ringing`
- outgoing `calling` -> `calling`

Follow-up behavior:

- `connected` -> `connected`
- `idle` after connected -> `completed`
- `idle` after incoming ringing -> `missed`
- `idle` after outgoing calling -> `cancelled`

Repeated `ringing`, `calling`, and `connected` callbacks are idempotent where valid.

Terminal sessions are never reopened.

Invalid state transitions are rejected.

## Caller lookup

Caller lookup is restricted to address books that are:

1. assigned to the authenticated device
2. still readable by the device owner

Only normalized phone-number matching is used.

The resulting contact name is stored on the call session as a historical snapshot.

## Success response

Example:

```json
{
  "call_id": "abc-123",
  "state": "ringing",
  "direction": "incoming",
  "contact_name": "Alice Example"
}
```

`contact_name` may be `null`.

The response reflects the derived call-session state, not merely the submitted event name.

## Example request

```sh
curl --fail-with-body \
  -X POST \
  -H 'Authorization: Bearer DEVICE_TOKEN' \
  -H 'Content-Type: application/json' \
  --data '{
    "event": "ringing",
    "call_id": "abc-123",
    "direction": "incoming",
    "local_number": "100",
    "remote_number": "+491701234567"
  }' \
  'https://phonebook.example/api/v1/device/call-events/'
```

## Call-event status codes

- `200 OK`: event accepted and call-session state returned
- `400 Bad Request`: invalid JSON, invalid/missing fields, unsupported value, or invalid call-state transition
- `401 Unauthorized`: credential missing, malformed, invalid, revoked, wrong scope, inactive device, or inactive/deleted owner
- `405 Method Not Allowed`: request method is not POST
- `429 Too Many Requests`: repeated failed credential checks triggered the endpoint/IP throttle
- `413 Content Too Large`: request body exceeds 16 KiB
- `415 Unsupported Media Type`: Content-Type does not start with `application/json`
- `503 Service Unavailable`: application runtime could not start

For `405`, the response includes:

`Allow: POST`

Notification-provider failures do not change an accepted call event. Notification dispatch happens after the call-state database commit and is isolated from the device response.

---

# Yealink Action URL callback

Endpoint:

`GET /api/v1/device/yealink/action-url/`

Required scope:

`yealink-callback:write`

This endpoint is HTTPS-only and accepts the dedicated callback credential only through HTTP Basic authentication with username `device`. Bearer credentials are rejected. Credentials are never accepted from query parameters or path segments.

Required query fields:

- `event`
- `call_id`

Optional query fields:

- `local`
- `remote`

Only those documented Yealink values are passed into the normalization layer.


Supported adapter mappings include:

- `incoming_call` -> `ringing` / `incoming`
- `outgoing_call` -> `calling` / `outgoing`
- `call_established` -> `connected`
- `on_hook` -> `idle`
- `missed_call` -> `idle`
- `cancel_callout` -> `idle`
- `remote_busy` -> `idle`
- `call_remote_canceled` -> `idle`

Successful callbacks return `200 OK` with a small text response. Invalid credentials return `401`, unsupported or malformed events return `400`, non-GET requests return `405`, repeated failed credential checks return `429`, and non-HTTPS requests are rejected with `400`.

A normal `phonebook:read` or `call-events:write` credential must never be reused for this endpoint. The dedicated callback credential can be rotated or revoked independently.

---

# Public API boundaries

The following areas currently have no public HTTP API:

- users and invitations
- address-book/contact mutation
- contact imports
- Google OAuth/synchronization administration
- device administration and credential rotation
- notification channels/rules/history/retry
- Web Push administration
- audit events
- backup/update operations
- operations health

Those capabilities are currently exposed through authenticated browser workflows, CLI commands, or internal application services.

There is deliberately no unauthenticated health endpoint.

---

# Client compatibility guidance

Device clients should:

- use HTTPS
- keep credentials out of URLs
- send the narrowest required credential scope
- treat `401` as a credential/configuration problem
- treat `400` on call events as a payload or state-transition problem
- respect response content type rather than guessing from the filename
- avoid caching phonebook responses
- tolerate additional compatible JSON fields in future `v1` responses
- not depend on error response bodies

If a credential is rotated or revoked, clients must be updated with the newly issued token; plaintext device tokens cannot be recovered from the server.
