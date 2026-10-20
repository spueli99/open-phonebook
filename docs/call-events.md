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

The direct callback endpoint is:

`GET /api/v1/device/yealink/action-url/`

It requires a dedicated `yealink-callback:write` credential and accepts that credential only through HTTP Basic authentication with username `device`. Bearer authentication is deliberately rejected by this endpoint.

The endpoint requires HTTPS and never accepts credentials from query parameters or path segments. The callback credential is independent from both `phonebook:read` and `call-events:write`, can be rotated/revoked separately, and is automatically revoked when the device is deactivated.

For Yealink firmware that converts URI userinfo to HTTP Basic authentication, configure Action URLs with the dedicated callback token as the Basic password. Example:

```text
https://device:CALLBACK_TOKEN@phonebook.example/api/v1/device/yealink/action-url/?event=incoming_call&call_id=$call_id&local=$local&remote=$remote
```

Configure the event name to match the corresponding Yealink Action URL setting, for example `incoming_call`, `outgoing_call`, `call_established`, `on_hook`, `missed_call`, `cancel_callout`, `remote_busy`, or `call_remote_canceled`.

The token must never be copied into a query-string parameter. If a phone/firmware does not emit HTTP Basic authentication from URI userinfo for Action URLs, Open Phonebook intentionally provides no query-token fallback; use the generic authenticated call-event API or validate another secure transport for that model.

Because Yealink Action URLs are GET requests, optional `local` and `remote` values appear in the request URI and may otherwise be written to web-server or reverse-proxy access logs. Production deployments that include those values must suppress or redact the callback query string in access logging. They may also omit `local`/`remote` entirely when caller lookup and number snapshots are not required.

The callback response is a small `200 OK` text response after the existing transactional call-state service accepts the event. Notification dispatch remains isolated after the call-state commit. Successful callbacks update only the dedicated callback credential's `last_used_at`.

## Persistence

`call_sessions` stores one derived lifecycle per device and external call ID.

`call_events` stores the normalized event sequence and resulting state.

Timestamps are stored in UTC.

## User call history

Authenticated users can open `/calls/` to view recent calls from devices they own. The query is owner-scoped through `devices.owner_user_id`; call sessions from another user's devices are never included.

The page shows up to 50 calls at a time with cursor-based loading of older entries. It displays time, direction, current/final state, remote number, the caller-name snapshot resolved when the call was processed, and device name. The technical event sequence is intentionally not exposed in the normal user view.

Contact names are currently displayed as historical snapshots only. They are not linked back to a contact because `call_sessions` does not store a stable contact ID; generating a link from the name or number alone could point to the wrong contact after later address-book changes.

## History retention

Terminal call sessions are retained for 180 days by default. The scheduled retention cleanup deletes only sessions whose `ended_at` is older than the configured cutoff. Open/ringing/connected sessions are never selected by retention cleanup. Related `call_events` are removed automatically through the existing database cascade when their terminal session is deleted.

Configure `operations.retention_days.call_history` in `config/local.php` or use `OPENPHONEBOOK_CALL_HISTORY_RETENTION_DAYS`. A value of `0` disables call-history cleanup. Supported values are `0` through `3650` days.

