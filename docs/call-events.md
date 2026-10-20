# Call events

## Scope

Call events are device-originated metadata about call lifecycle changes. Open Phonebook is not a PBX and does not handle SIP registration, routing, or media.

The initial device scope is:

`call-events:write`

It is independent from `phonebook:read`. Rotating or revoking one scope does not affect the other. Deactivating a device revokes both scopes.

## Generic API

Endpoint:

`POST /api/v1/device/call-events/`

Authentication uses the existing device Authorization-header mechanism and requires `call-events:write`.

The request body must be JSON and is limited to 16 KiB.

Canonical event values:

- `ringing`
- `calling`
- `connected`
- `idle`

Canonical directions:

- `incoming`
- `outgoing`

Direction is required only for the initial `ringing` or `calling` event. Follow-up events attach to the existing device/call-id session.

Example:

```json
{
  "event": "ringing",
  "call_id": "abc-123",
  "direction": "incoming",
  "local_number": "100",
  "remote_number": "+491701234567"
}
```

## Session state

Open sessions are matched by device and external call ID. Once a session is terminal, the same external call ID may be reused by a later call.

Initial states:

- incoming `ringing` -> `ringing`
- outgoing `calling` -> `calling`

Follow-up behavior:

- `connected` -> `connected`
- `idle` after connected -> `completed`
- `idle` after incoming ringing -> `missed`
- `idle` after outgoing calling -> `cancelled`

Repeated ringing/calling/connected callbacks are idempotent. Terminal sessions are never reopened. Invalid transitions are rejected.

## Caller lookup

Caller lookup uses the same authorization boundary as device phonebook exports.

A match is searched only in address books that are both:

1. explicitly assigned to the device
2. currently readable by the device owner

Only normalized phone-number matching is used. A stale device assignment cannot preserve access after a share is revoked.

## Yealink Action URL adapter

The Yealink adapter maps supported Action URL event names to the canonical model:

- `incoming_call` -> ringing/incoming
- `outgoing_call` -> calling/outgoing
- `call_established` -> connected
- `on_hook`, `missed_call`, `cancel_callout`, `remote_busy`, `call_remote_canceled` -> idle

Yealink `$local` and `$remote` SIP URIs are reduced to their user/number component before normalization.

The adapter is intentionally separate from the public HTTP controller. A direct Yealink callback endpoint is not enabled until a secure callback credential transport is documented and verified; normal device tokens must not be placed in callback URLs.

## Persistence

`call_sessions` stores one derived lifecycle per device and external call ID.

`call_events` stores the normalized event sequence and resulting state.

Timestamps are stored in UTC.

## History retention

Terminal call sessions are retained for 180 days by default. The scheduled retention cleanup deletes only sessions whose `ended_at` is older than the configured cutoff. Open/ringing/connected sessions are never selected by retention cleanup. Related `call_events` are removed automatically through the existing database cascade when their terminal session is deleted.

Configure `operations.retention_days.call_history` in `config/local.php` or use `OPENPHONEBOOK_CALL_HISTORY_RETENTION_DAYS`. A value of `0` disables call-history cleanup. Supported values are `0` through `3650` days.

