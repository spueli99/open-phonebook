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

Notification dispatch occurs only after the call-event service has successfully committed the call state.

This ordering is intentional:

1. authenticate and validate the device call event
2. commit the call/session state
3. evaluate notification rules
4. invoke matching providers

A provider failure therefore cannot roll back or corrupt call history.

Repeated incoming callbacks do not produce duplicate incoming notifications because only a newly created incoming/ringing session triggers `incoming`. Terminal states are not reopened, so missed/completed notifications are emitted only when that terminal transition is first recorded.

## Failure isolation

Each matching channel is dispatched independently. A disabled channel, unknown provider, invalid encrypted configuration, quota error, transport error, or other provider exception is isolated from the remaining channels.

The initial synchronous implementation does not retry failed deliveries and does not persist delivery-attempt history. Queueing, retries, and delivery diagnostics remain later operational work.

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

- quiet hours
- retry/backoff queue
- delivery-attempt history and diagnostics
- test-send action
- Web Push/PWA
- additional providers
- per-rule custom message templates
