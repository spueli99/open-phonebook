# Changelog

All notable changes to this project will be documented in this file.

The project is currently in the pre-release architecture and foundation phase.

## Unreleased

### Fixed

- Web setup now shows the one-time installation-success screen after creating the installation lock instead of immediately replacing it with the locked-setup response

### Added

- Responsive dashboard navigation cards with permission-aware administration links and a compact logout header

- Shared responsive application UI foundation with a local CSP-compatible stylesheet for authenticated and account-management views

- CI MariaDB smoke coverage for the complete fresh-install migration chain, update readiness, and migration idempotency
- System-admin operations diagnostics with database, scheduler, and notification-queue health
- Persistent aggregate heartbeats for Google Contacts sync and notification delivery cron jobs
- Configurable scheduler stale thresholds with environment overrides
- Configurable call-history and notification-history retention with daily cron cleanup and scheduler heartbeat
- Owner-scoped recent-call history UI with 50-item cursor pagination
- Dedicated `view_operations` permission
- Server-side backup/restore readiness CLI with JSON manifest output
- Committed Composer dependency lock for reproducible development, CI, deployment, and disaster recovery
- Domain-separated application-key fingerprint verification for disaster recovery
- English and German backup/restore runbooks for database, secrets, install lock, and code/dependency recovery
- Explicit semantic application version identity with Operations-page display
- Update-readiness CLI with machine-readable JSON output and migration compatibility checks
- Explicit migration CLI for production updates with unknown-schema downgrade protection
- English and German release/update/rollback runbooks
- English and German public device HTTP API reference with authentication, formats, limits, examples, and status codes
- Secure web setup installer and versioned migration foundation
- Local email/password authentication foundation
- Email-based password reset/recovery with 30-minute single-use fragment tokens
- Authentication revision tracking to invalidate all prior browser sessions after recovery
- System mail sender configuration and setup field for account-recovery email
- Secure authenticated browser sessions
- Database-backed login throttling with HMAC-derived subject identifiers
- English and German authentication localization
- System-level user administration foundation
- Explicit user-management permissions
- Persistent typed audit events for user administration and the invitation lifecycle
- System-admin read-only audit log UI with dedicated permission and timezone-aware rendering
- Secure invitation creation and acceptance flow
- System email delivery for invitations without exposing plaintext invitation tokens in administrator HTML
- Active invitation listing, revocation, and token-safe renewal
- Guarded platform role management for members and system administrators
- English and German user-management documentation
- Local personal address-book and contact persistence foundation
- Multiple phone numbers per contact with owner-scoped services
- Authenticated personal address-book and contact web UI
- Owner-scoped contact search across names, organizations, and phone data
- Region-aware E.164 phone-number normalization while preserving raw values
- Personal address-book sharing with explicit read or write access
- Preview-only CSV contact import foundation with strict validation and access checks
- Explicit CSV import confirmation with short-lived session drafts and atomic persistence
- E.164-based import duplicate detection with default skip and explicit force-import
- Conservative merge decisions for one unambiguous existing contact
- vCard 2.1/3.0/4.0 preview and confirmed imports through the shared import pipeline
- Google Contacts read-only OAuth source with transactional full-snapshot synchronization
- Incremental Google People API synchronization with encrypted sync tokens and automatic full fallback
- Cron-compatible Google Contacts scheduler without a continuously running worker
- User-owned device management with explicit address-book assignment
- Scoped `phonebook:read` device credentials with rotation and revocation
- Device phonebook exporter interface with JSON, CSV, and Yealink XML adapters
- Sessionless device-facing phonebook endpoint with export-time access revalidation
- Independent `call-events:write` device credentials with owner-managed rotation and revocation
- Per-scope device credential `last_used_at` tracking with owner-visible timestamps
- Generic JSON call-event API with transactional call-session state machine
- Yealink Action URL normalization adapter and scoped caller lookup
- HTTPS-only Yealink Action URL callback endpoint with dedicated `yealink-callback:write` credential and Basic-only authentication
- User-owned encrypted notification channels with Pushover and email providers
- Call notification rules for incoming, missed, and completed calls with optional device scoping
- Per-rule quiet hours evaluated in each user's configured timezone
- Persistent cron-driven notification delivery queue with bounded retry/backoff and stale-worker recovery
- Aggregate notification delivery diagnostics in the management UI
- Owner-scoped recent notification delivery history with localized timestamps
- Notification delivery history filters for status, trigger, and channel with cursor pagination
- Manual retry for failed deliveries using domain-separated encrypted retry payloads
- Queue-based test sends for active notification channels
- Web Push notification provider integrated into the existing queue/rule/history model
- Browser subscription lifecycle with stable browser identifiers and encrypted subscription storage
- Minimal installable PWA manifest and push-only service worker
- VAPID key generation CLI and environment-based Web Push configuration

### Security

- Public device phonebook, call-event, and Yealink callback endpoints now throttle failed credential checks per endpoint/client IP and return `429` after repeated failures
- Sensitive device, Web Push, and OAuth callback responses now apply a shared no-store/no-referrer baseline on both success and early error paths
- Removed unused session-cookie security defaults so fixed HttpOnly/SameSite protections are not misrepresented as operator-tunable settings
- Web setup enforces HTTPS before every setup step/session and preflights config/install-lock writability before database changes
- CI audits the committed Composer lock against published security advisories before merge/release
- Direct Yealink callbacks use a separate revocable credential scope, reject Bearer/query-token authentication, require HTTPS, and whitelist only documented callback parameters
- Device credential last-used timestamps are updated only after successful authenticated device operations; invalid or rejected requests do not count as credential use
- Recent-call history is scoped through device ownership and never exposes another user's call sessions
- Retention cleanup never deletes open call sessions or pending/processing notification jobs; only terminal records older than configured cutoffs are eligible
- Audit log UI is authenticated, system-admin-only, read-only, bounded to the latest 100 events, and HTML-escapes all dynamic values
- Update readiness and migration execution block databases containing migration IDs unknown to the deployed code
- Production update guidance requires a verified backup before schema migration and defines restore-based rollback when older code no longer understands the schema
- Administrative user/invitation mutations persist audit events in the same database transaction; audit failure rolls back the state change
- Audit metadata is bounded and rejects sensitive key classes such as passwords, tokens, sessions, email/phone/contact data, secrets, content, and authorization material
- Operations diagnostics are authenticated, permission-gated, no-store, and aggregate-only
- Scheduler heartbeats never persist exception text, contact/source identifiers, notification content, provider credentials, or secrets
- Backup readiness reports never expose the application key or runtime/provider secrets
- Restore verification fails on wrong key fingerprints, missing/unknown migrations, or missing installation lock
- CI requires `composer.lock` and installs the locked dependency graph instead of resolving fresh versions
- Ambiguous local phone numbers are never assigned a country without explicit region configuration
- Shared address books retain owner-only metadata and sharing administration
- Contact import preview validates untrusted uploads before any persistence and requires write access plus CSRF protection
- CSV confirmation reparses the server-side draft, rechecks write access, and persists all contacts in one transaction
- Import draft tokens are 256-bit random values stored only as HMACs in session state and expire after 15 minutes
- Duplicate status is recalculated at confirmation so newly appearing duplicates are skipped unless explicitly forced
- Import merges never overwrite existing non-empty fields and are rejected when the merge target becomes ambiguous
- Import drafts bind the selected parser format so confirmation cannot switch CSV and vCard interpretation
- Google refresh and incremental sync tokens are AES-256-GCM encrypted at rest; OAuth uses single-use state plus PKCE and the contacts.readonly scope only
- Device tokens are stored only as domain-separated HMAC hashes and require active device and owner state
- Device phonebook exports accept credentials only through Authorization headers, revalidate current address-book access, and return private no-store responses
- CSV phonebook exports neutralize spreadsheet formula prefixes
- Call-event credentials are scope-isolated from phonebook credentials; device deactivation revokes both
- Call-event requests are body-size limited, sessionless, and require explicit `call-events:write` authorization
- Caller lookup is limited to device-assigned address books the owner can still read
- Notification provider secrets are encrypted at rest and never rendered back into the management UI
- Notification changes require authenticated, CSRF-protected owner-scoped requests
- Provider failures are isolated after call-state commit and cannot roll back call history
- Device-facing call requests never contact external notification providers directly
- Queue claims are transactionally locked and stale processing jobs are recoverable after ten minutes
- Delivery history never renders notification content or provider secrets
- Sent/cancelled jobs erase encrypted retry payloads; failed jobs keep only encrypted retry material for explicit owner retry
- Test sends and manual retry are authenticated, CSRF-protected, and owner-scoped
- Web Push subscriptions require authenticated CSRF-protected registration and allowlisted HTTPS push-service endpoints
- Web Push VAPID private keys remain server-side; only the public key is exposed to authenticated browser clients
- The PWA service worker has no fetch handler or private offline cache
- Expired Web Push subscriptions are automatically disabled on push-service 404/410 responses
- Owner-scoped authorization boundary for private address books and contacts
- CSRF protection for address-book and contact mutations
- CSRF protection for login, logout, user administration, and invitation acceptance
- Session ID regeneration after authentication state changes
- Generic invalid-credential responses and unknown-user timing mitigation
- Password-reset requests use generic responses, issuance cooldown, minimum response timing, CSRF-protected fragment-token capture, and domain-separated HMAC token storage
- Successful password reset atomically consumes the token, rotates the password hash, and invalidates existing browser sessions through `auth_revision`
- Application key generation outside the database
- HMAC-only storage of invitation tokens
- Fragment-based invitation links to keep invitation tokens out of ordinary HTTP request URLs
- Protection against self-deactivation and deactivation of the last active system administrator
- Protection against self-demotion and demotion of the last active system administrator
- Renewal invalidates the previous invitation token before exposing a replacement token
- Successful invitation email delivery keeps plaintext tokens out of administrator HTML; failed delivery revokes the newly issued invitation
