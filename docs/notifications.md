# Notifications

## Scope

Notifications are user-owned outbound channels triggered by application events.

The first implementation connects call lifecycle events to:

- Pushover
- email

Notification providers are adapters behind a common interface. Call-state persistence does not depend on provider availability.

## Channels

A notification channel belongs to exactly one user.

Current providers:

- `pushover`
- `email`

Provider configuration is encrypted at rest with the application secret cipher. The web UI never displays stored provider credentials after creation.

Pushover configuration stores:

- application API token
- user/group key

Email configuration stores:

- recipient address
- sender address

Channels can be enabled, disabled, or deleted by their owner.

## Rules

A rule connects one channel to one trigger and may optionally be limited to one owned device.

Current triggers:

- `incoming`
- `missed`
- `completed`

A rule without a device applies to all devices owned by the user.

Duplicate rules with the same owner, channel, optional device, and trigger are rejected.

## Call integration

Notification evaluation occurs only after the call-event service has successfully committed the call state.

This ordering is intentional:

1. authenticate and validate the device call event
2. commit the call/session state
3. evaluate matching notification rules
4. suppress rules that are currently inside quiet hours
5. persist one delivery job for every remaining active channel
6. return the accepted call-event response without contacting external providers

Provider latency or failure therefore cannot roll back call history or delay the device request.

Repeated incoming callbacks do not produce duplicate incoming jobs because only a newly created incoming/ringing session triggers `incoming`. Terminal states are not reopened, so missed/completed jobs are created only when that terminal transition is first recorded.

## Quiet hours

Quiet hours are configured per notification rule with a local start and end time.

The user's configured timezone is used for evaluation, including daylight-saving transitions.

Both ordinary windows such as `13:00–15:00` and overnight windows such as `22:00–07:00` are supported. The start is inclusive and the end is exclusive.

A notification suppressed by quiet hours is intentionally discarded. It is not queued for delayed delivery after the quiet window.

## Delivery queue and retry

Notifications are delivered by the cron-compatible worker:

`php bin/notification-deliveries.php`

The worker should normally run once per minute.

Delivery jobs are persisted with one of these states:

- `pending`
- `processing`
- `sent`
- `failed`
- `cancelled`

The first attempt is due immediately. Transient provider failures are retried with bounded backoff:

- after attempt 1: 1 minute
- after attempt 2: 5 minutes
- after attempt 3: 15 minutes
- after attempt 4: 60 minutes
- attempt 5 is the final provider attempt

A worker claim is protected by a database transaction and row lock. A `processing` job whose lock is older than 10 minutes is released back to `pending` so a later cron run can recover from a crashed worker.

Deleted or inactive channels cancel queued jobs. Missing providers or undecryptable provider configuration fail the job permanently.

The management page exposes aggregate delivery counts for pending, processing, sent, failed, and cancelled jobs.

It also shows the 50 most recent delivery records for the current user. The history contains only delivery metadata such as channel, trigger, state, attempt count, created/due/error/sent timestamps, and retry availability. Rendered notification title/body and provider credentials are never shown.

Once a job reaches a terminal state, its rendered title and body are cleared. For `sent` and `cancelled`, the encrypted retry payload is cleared as well. A `failed` job keeps only a separately encrypted retry payload so the owner may explicitly retry the original notification without storing readable call-derived message content.

Delivery is at-least-once within the retry window. A process crash after the external provider accepted a message but before the local `sent` update can result in a duplicate retry; providers without idempotency support cannot eliminate that ambiguity completely.

## Manual retry

Only a failed delivery owned by the current user may be retried.

A retry additionally requires the original channel to still exist and be active. The encrypted retry payload is decrypted only inside the application service, immediately re-encrypted with a fresh nonce, and the same delivery record is reset to `pending` with attempts reset to zero.

The retry payload uses a dedicated HKDF domain separate from provider-secret encryption.

## Test delivery

An active channel can be tested from the management UI.

Test delivery does not contact the provider from the browser request. It creates a normal queue job using the internal `test` trigger, then the cron worker handles transport, retry, failure isolation, and history exactly like a real notification.

The `test` trigger is internal and cannot be configured as a normal call-event rule.

## Failure isolation

Each queued delivery is processed independently. One failed provider or channel does not block other jobs.

The worker exits with status 1 only when a job becomes permanently failed during that run. Transient reschedules remain normal operation. Bootstrap/configuration failures use exit status 2.

## Pushover

Pushover messages are sent as HTTPS POST form requests to the Message API.

Open Phonebook validates:

- 30-character application API token
- 30-character user/group key
- title length up to 250 UTF-8 characters
- message length up to 1024 UTF-8 characters
- HTTP 200 response
- JSON response with `status: 1`

Pushover credentials are private provider secrets and are encrypted at rest.

## Email

The first email provider uses the PHP mail transport available on conventional shared hosting.

Recipient and sender addresses are validated before use. Header values containing CR or LF are rejected to prevent header injection.

A later SMTP/provider-specific transport can implement the same mail transport interface without changing notification rules.

## Security

Notification administration requires an authenticated browser session and CSRF-protected POST requests.

Users may manage only their own channels, rules, and devices.

Rules cannot reference another user's channel or device.

Provider secrets are encrypted at rest and are not written to ordinary application output.

## Deferred

- filtering/pagination for larger delivery histories
- bulk history cleanup/retention policy
- Web Push/PWA
- additional providers
- per-rule custom message templates
