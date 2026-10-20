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

Invitation links must still be transmitted to recipients through an appropriately secure channel.

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

Device credentials are independent from human authentication. Implemented scopes are `phonebook:read` and `call-events:write`. Tokens are 256-bit random values displayed only once; the database stores only a domain-separated HMAC-SHA-256 hash keyed by the application key. Rotation and revocation are scope-specific. Device deactivation revokes all scopes. Device authentication additionally requires an active device and an active, non-deleted owner account.

Device address-book assignments may only be created for books the owner can currently read. Device-facing phonebook exports and caller lookup re-check current owner access as well as the stored assignment before returning contact-derived data. Device requests use a sessionless runtime. The generic call-event endpoint accepts only POST JSON, caps the body at 16 KiB, requires `call-events:write`, and exposes generic client errors rather than internal exception details. Normal device tokens are never accepted from query parameters. The Yealink Action URL adapter remains separate from a public callback endpoint until secure callback authentication is verified.

## Notification security

Notification channels and rules are owner-scoped. Provider configuration is serialized and encrypted at rest with the existing AES-256-GCM secret cipher before being stored in the database. Stored provider secrets are not rendered back into the management UI. Notification mutations require authenticated browser sessions and CSRF protection. Rules may reference only channels and devices owned by the same user. Pushover tokens and user/group keys are validated before storage and transport; email addresses are validated and CR/LF header injection is rejected. Provider failures are isolated after call-state commit and do not expose provider secrets in device responses. External providers are contacted only by the cron delivery worker, not by device-facing requests. Quiet hours are evaluated in the user's configured timezone before a delivery job is created. Pending delivery jobs store rendered notification title/body, a domain-separated encrypted retry payload, and references to user/channel; encrypted provider credentials remain in the channel record. When a delivery becomes sent or cancelled, rendered content and retry payload are cleared. When a delivery becomes failed, rendered content is cleared while only the encrypted retry payload remains for an explicit owner-initiated retry. The retry payload uses a distinct HKDF context from provider-secret encryption. Delivery history exposes only owner-scoped metadata. Manual retry and test-send actions require authenticated CSRF-protected POST requests and never expose provider secrets or decrypted retry content. Stale processing locks are recoverable after ten minutes.

## Privacy

Call-history retention is configurable and may be disabled.

Authentication throttling does not persist raw email/IP combinations.

Invitation tokens are not persisted in plaintext.

Users should eventually be able to export and delete their own application data.

## Logging

Audit administrative and security actions without turning the audit log into contact-access surveillance.

Authentication logs must not include plaintext passwords, provider secrets, session identifiers, invitation tokens, or unnecessary personal data.


## Operations diagnostics

The operations page is authenticated and permission-gated with `view_operations`; it is not a public health endpoint. It uses the normal no-store HTML security headers.

Scheduler heartbeats persist only task names, UTC timestamps, status, and aggregate attempted/succeeded/failed counts. They deliberately do not persist exception messages, contact/source identifiers, phone numbers, notification recipients, message content, provider configuration, or secrets.

Global notification queue diagnostics expose counts only. Scheduler stale thresholds are configuration values and are not rendered as secret-bearing configuration dumps.


## Backup and restore security

A complete recovery set includes the database plus the original runtime application key. The application key is deliberately not stored in the database and cannot be reconstructed from encrypted records or from the non-secret backup fingerprint.

The server-side backup readiness CLI exposes only schema metadata, a domain-separated application-key fingerprint, configuration/lock presence flags, and aggregate counts of key-dependent records. It never outputs the application key, database password, provider credentials, contact data, phone numbers, or notification content.

Database dumps and runtime secret configuration must be protected as sensitive backup material, encrypted at rest, access-controlled, and kept outside the public document root. Restores must verify the saved application-key fingerprint before production traffic or cron jobs are re-enabled.


## Web Push and PWA

Web Push uses a stable VAPID key pair stored in runtime configuration. The private VAPID key is a secret and is never rendered to browser clients; only the public VAPID key is exposed to authenticated users who can register a browser subscription.

Push subscription endpoints and encryption material are treated as provider configuration and are encrypted at rest inside notification channels. Subscription mutation requires an authenticated session and CSRF-protected POST requests.

The server restricts browser subscription endpoints to HTTPS and known push-service host suffixes before they can reach the outbound Web Push transport. This prevents an authenticated user from turning the notification worker into a generic SSRF client.

The PWA service worker intentionally has no fetch handler and creates no offline cache. Authenticated pages, contact data, cookies, and application responses are therefore not persisted into a service-worker-controlled cache.

Notification-click URLs are constrained to relative same-origin paths. Expired subscriptions are automatically deactivated on push-service 404/410 responses.
