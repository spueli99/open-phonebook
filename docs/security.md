# Security Architecture

## Threat model

Open Phonebook stores personal contacts and may store call metadata, provider tokens, device credentials, notification credentials, authentication metadata, and invitation metadata.

Assume the public web interface and device/export endpoints are internet-accessible.

## Required controls

- HTTPS in production
- secure session cookies
- CSRF protection
- login throttling and endpoint rate limiting
- strict server-side authorization
- output escaping
- prepared database statements
- scoped device tokens
- credential rotation and revocation
- OAuth state validation
- no secrets in source control
- no sensitive values in application logs
- no-store caching policy for HTML responses containing private data or one-time secrets
- safe file upload parsing and size limits
- import preview before persistence
- database migrations executed explicitly and transactionally where possible

## Secrets

Provider tokens should be encrypted at rest where practical. Google refresh tokens are encrypted with AES-256-GCM using HKDF-SHA-256 key material derived from the application key. Access tokens are not persisted.

The encryption key must not be stored in the same database as encrypted provider credentials.

Runtime secret configuration must be excluded from Git.

The setup process generates an application key outside the database. It is currently used for keyed security hashes such as login-throttling subjects and invitation-token hashes. Future cryptographic features must define appropriate key derivation or separate keys.

## Setup and installation

The web installer requires HTTPS before any setup session starts or any setup step is served. Direct navigation to later setup steps cannot bypass this transport requirement.

Before schema migrations or first-administrator creation, the installer preflights writability of the local configuration path and installation-lock path. Predictable filesystem permission failures therefore occur before database changes begin.

## Authentication

Initial authentication uses local email/password accounts created by administrators or setup.

Controls include:

- `password_hash()` / `password_verify()`
- automatic password rehashing
- generic invalid-credential responses
- dummy password verification for unknown users to reduce account-enumeration timing differences
- active/deleted-account enforcement
- session ID regeneration
- CSRF-protected POST login/logout flows
- database-backed login throttling using HMAC-derived subject identifiers

See [Authentication](authentication.md).

## Password reset

Password-reset requests use a generic public response and a per-account five-minute issuance cooldown. The endpoint pads POST handling to a minimum response duration to reduce straightforward timing-based account enumeration.

Reset tokens are 256-bit random values and are stored only as hashes derived from a dedicated HKDF domain (`open-phonebook/password-reset-token/v1`). They expire after 30 minutes and are single-use.

Reset links carry the plaintext token only in the URL fragment. Browser fragments are removed before the token is submitted in a CSRF-protected same-origin POST, keeping reset secrets out of normal request URLs and access logs.

Mail failures do not produce account-specific responses. If delivery fails after a token was created, that token is immediately consumed so an undelivered usable credential is not left behind.

Successful reset atomically consumes the token, replaces the password hash, and increments `users.auth_revision`. Browser sessions validate their stored revision against the current user row and are invalidated after password recovery, account deactivation, or deletion.

The system-mail sender is runtime configuration and may be supplied through `OPENPHONEBOOK_MAIL_FROM`. Reset email requires the configured HTTPS application base URL.

## Invitations

Invitation tokens are capability secrets.

Controls include:

- 256-bit random tokens
- HMAC-derived token hashes at rest
- finite expiration
- single-use semantics
- duplicate-active-invitation prevention per email address
- transactional account creation and acceptance
- fragment-based client transport so tokens are not included in ordinary HTTP request URLs
- immediate fragment removal before the token is submitted in a CSRF-protected same-origin POST

Invitation links are delivered only through the shared validated mail transport and the plaintext token is never rendered back into the administrator browser. Invitation creation and renewal require valid HTTPS base-URL and system-mail configuration. Invalid configuration or a delivery failure revokes the newly issued invitation immediately, preventing an undelivered usable token from remaining active.

See [User Management](user-management.md).

## Administrative safeguards

Initial user-management operations enforce explicit permissions.

The current activation flow prevents both self-deactivation and disabling the final active system administrator.

Role editing and permanent deletion are intentionally deferred until ownership and organization rules exist.

## Imports

vCard and CSV are untrusted input.

Uploads require:

- MIME/extension-independent validation
- file size limits
- parser limits
- preview
- duplicate review
- transactional import
- rollback or safe cancellation

The contact import flow implements successful PHP upload verification, a 2,000,000-byte server-side limit, UTF-8 and NUL-byte validation, a 2,000-row parser limit, explicit supported columns, domain-aligned field limits, CSRF protection, and writable-address-book authorization. Preview itself does not persist contact data. After preview, one 15-minute server-side session draft is bound to the user and target address book through a 256-bit token whose HMAC, not plaintext value, is stored in session state. Confirmation revalidates access, reparses the CSV, and recalculates duplicate status before one outer transaction persists the selected contacts. Duplicate detection uses only valid normalized E.164 values; names alone never suppress an import. Newly appearing duplicates are skipped at confirmation unless an explicit action applies. Merge is allowed only when the current normalized-phone evidence resolves to exactly one existing contact. Existing non-empty contact fields are never automatically overwritten; a stale or ambiguous merge target aborts confirmation. The draft is discarded only after successful commit.

## Google OAuth and contact sources

Google Contacts uses only the `contacts.readonly` OAuth scope. The flow validates a single-use, 10-minute `state` value bound to the signed-in user and target address book and also uses PKCE SHA-256. Only address-book owners may connect, reconnect, or synchronize Google Contacts.

Google OAuth client credentials remain in runtime configuration and are not stored in the database. The database stores only encrypted refresh tokens. Google synchronization performs an initial complete paginated snapshot and then uses encrypted People API sync tokens for incremental updates. Delta responses only delete local source contacts when Google explicitly reports `metadata.deleted=true`; absence from a delta is never interpreted as deletion. Expired sync tokens trigger a fresh full snapshot. Each batch and its replacement sync token are committed transactionally. Only contacts mapped to that source are eligible for source-driven deletion.

## Device credentials

Device credentials are independent from human authentication. Implemented scopes are `phonebook:read`, `call-events:write`, and `yealink-callback:write`. Tokens are 256-bit random values displayed only once; the database stores only a domain-separated HMAC-SHA-256 hash keyed by the application key. Rotation and revocation are scope-specific. Device deactivation revokes all scopes. Device authentication additionally requires an active device and an active, non-deleted owner account.

Device address-book assignments may only be created for books the owner can currently read. Device-facing phonebook exports and caller lookup re-check current owner access as well as the stored assignment before returning contact-derived data. Device requests use a sessionless runtime. The generic call-event endpoint accepts only POST JSON, caps the body at 16 KiB, requires `call-events:write`, and exposes generic client errors rather than internal exception details. Normal device tokens are never accepted from query parameters.

The direct Yealink Action URL callback uses the independent `yealink-callback:write` scope. It is HTTPS-only, accepts only HTTP Basic authentication with the fixed username `device`, rejects Bearer authentication, never reads credentials from query parameters or path segments, and forwards only the documented `event`, `call_id`, `local`, and `remote` query values into normalization. Successful callbacks update only the callback credential's `last_used_at`. Because optional `local` and `remote` values are carried in a GET request URI, production web/proxy access logging must suppress or redact callback query strings when those values are used.

## Notification security

Notification channels and rules are owner-scoped. Provider configuration is serialized and encrypted at rest with the existing AES-256-GCM secret cipher before being stored in the database. Stored provider secrets are not rendered back into the management UI. Notification mutations require authenticated browser sessions and CSRF protection. Rules may reference only channels and devices owned by the same user. Pushover tokens and user/group keys are validated before storage and transport; email addresses are validated and CR/LF header injection is rejected. Provider failures are isolated after call-state commit and do not expose provider secrets in device responses. External providers are contacted only by the cron delivery worker, not by device-facing requests. Quiet hours are evaluated in the user's configured timezone before a delivery job is created. Pending delivery jobs store rendered notification title/body, a domain-separated encrypted retry payload, and references to user/channel; encrypted provider credentials remain in the channel record. When a delivery becomes sent or cancelled, rendered content and retry payload are cleared. When a delivery becomes failed, rendered content is cleared while only the encrypted retry payload remains for an explicit owner-initiated retry. The retry payload uses a distinct HKDF context from provider-secret encryption. Delivery history exposes only owner-scoped metadata. Manual retry and test-send actions require authenticated CSRF-protected POST requests and never expose provider secrets or decrypted retry content. Stale processing locks are recoverable after ten minutes.

## Privacy

Call-history and notification-history retention are configurable independently and may be disabled by setting the respective retention period to `0`. The scheduled cleanup deletes only terminal call sessions and terminal notification-delivery records; open calls and pending/processing delivery jobs are never eligible. Deleting an old failed notification also removes its encrypted retry payload.

Authentication throttling does not persist raw email/IP combinations.

Invitation tokens are not persisted in plaintext.

Password-reset tokens are not persisted in plaintext.

Users should eventually be able to export and delete their own application data.

## Audit and logging

Security-sensitive administrative user and invitation changes are persisted as typed audit events. The event stores actor ID, target type/ID, UTC time, and only small validated metadata. Normal contact browsing is deliberately not audited.

Audit metadata keys are centrally restricted and reject sensitive concepts such as passwords, tokens, secrets, sessions, email addresses, phone/contact data, message content, or authorization material. Current metadata values are bounded internal role/reason identifiers only. Administrative state changes and their audit records use the same PDO transaction; audit failure rolls back the protected mutation.

Authentication logs and future audit expansion must not include plaintext passwords, provider secrets, session identifiers, invitation/reset tokens, arbitrary exception text, notification payloads, or unnecessary personal data.

See [Audit Events](audit.md).


## Operations diagnostics

The operations page is authenticated and permission-gated with `view_operations`; it is not a public health endpoint. It uses the normal no-store HTML security headers.

Scheduler heartbeats persist only task names, UTC timestamps, status, and aggregate attempted/succeeded/failed counts. They deliberately do not persist exception messages, contact/source identifiers, phone numbers, notification recipients, message content, provider configuration, or secrets.

Global notification queue diagnostics expose counts only. Scheduler stale thresholds are configuration values and are not rendered as secret-bearing configuration dumps.


## Update security

The update-readiness CLI exposes only release identity, boolean runtime checks, and migration IDs. It does not output configuration secrets or application data.

The migration CLI refuses to run when the database contains migration IDs unknown to the deployed code, preventing an older or mismatched code release from silently operating on a newer schema. Schema migrations must be preceded by a verified backup, and there are intentionally no automatic down-migrations.

See [Release and Updates](update-release.md).

## Backup and restore security

A complete recovery set includes the database plus the original runtime application key. The application key is deliberately not stored in the database and cannot be reconstructed from encrypted records or from the non-secret backup fingerprint.

The server-side backup readiness CLI exposes only schema metadata, a domain-separated application-key fingerprint, configuration/lock presence flags, and aggregate counts of key-dependent records. It never outputs the application key, database password, provider credentials, contact data, phone numbers, or notification content.

Database dumps and runtime secret configuration must be protected as sensitive backup material, encrypted at rest, access-controlled, and kept outside the public document root. Restores must verify the saved application-key fingerprint before production traffic or cron jobs are re-enabled.


## Web Push and PWA

Web Push uses a stable VAPID key pair stored in runtime configuration. The private VAPID key is a secret and is never rendered to browser clients; only the public VAPID key is exposed to authenticated users who can register a browser subscription.

Push subscription endpoints and encryption material are treated as provider configuration and are encrypted at rest inside notification channels. Subscription mutation requires an authenticated session and CSRF-protected POST requests.

The server restricts browser subscription endpoints to HTTPS and known push-service host suffixes before they can reach the outbound Web Push transport. This prevents an authenticated user from turning the notification worker into a generic SSRF client.

The PWA service worker intentionally has no fetch handler and creates no offline cache. Authenticated pages, contact data, cookies, and application responses are therefore not persisted into a service-worker-controlled cache.

The HTML Content Security Policy allows only the minimum additional same-origin PWA capabilities: service workers, manifest/images, and same-origin subscription requests. It does not allow arbitrary external connections.

Notification-click URLs are normalized and origin-checked before navigation; protocol-relative or cross-origin targets fall back to the application root. Expired subscriptions are automatically deactivated on push-service 404/410 responses.
