# Architecture

## Goal

Open Phonebook is a self-hosted multi-user contact hub for IP phones, call events, and notifications.

The product must stay universal. Initial development is driven by real-world requirements, but no core concept may depend on a specific community, building, PBX, or phone vendor.

## Core domains

The core understands:

- users
- authentication and sessions
- permissions
- invitations
- organizations
- memberships
- address books
- contacts
- phone numbers
- contact sources
- devices
- exports
- call sessions and events
- notification channels and rules
- security/admin audit events

It does not understand vendor-specific concepts directly.

## Layering

### Core

Business rules, permissions, contact model, device model, call state model, notification rules, and authentication policy.

### Persistence

Repository interfaces separate application/authentication behavior from PDO-backed storage implementations.

Database schema changes are versioned migrations.

### Adapters

Contact imports use a parser interface and registry that convert untrusted provider/file input into a provider-neutral preview model before persistence. CSV and vCard share the same preview/confirmation boundary. The short-lived session draft stores the selected format so confirmation reparses the original contents with the same adapter before one application transaction writes all contacts. Later external-source adapters should feed the same boundary.

External contact sources use explicit provider adapters and source-record mappings. Google Contacts uses OAuth outside the database for client credentials, encrypted refresh-token persistence, and `source_records` to map provider IDs to canonical local contacts. Google applies the initial read-only snapshot and later incremental sync-token batches through the same transactional boundary. Cron invokes the same application service without requiring a continuously running worker.

Examples:

- contact sources: Google Contacts, future CardDAV/Microsoft 365
- imports: vCard, CSV
- device exporters: Yealink first, later Snom/Grandstream/Fanvil
- call event adapters: Yealink Action URLs first
- notification providers: Pushover and email first, Web Push later

### Application/UI/API

The web UI and REST API use the same application services. Business logic must not live inside templates or installer forms.

The initial web runtime centralizes configuration loading, database creation, secure session startup, application-key validation, and localization access.

Address-book authorization is resolved through a dedicated access boundary. Ownership and explicit personal shares are combined into a read/write access result before contact services operate. Ownership-sensitive operations such as renaming, deleting, or managing shares remain separate and owner-only.

## Authentication and authorization boundary

Human users authenticate through secure browser sessions.

Authorization is modeled separately with explicit permission identifiers. Administrative controllers must validate permissions before performing user-management actions.

Invitation services are separate from authentication. Invitation tokens create accounts but do not themselves create authenticated sessions.

Devices never reuse human sessions or human passwords. Each initial device belongs to one user and authenticates with separately scoped and revocable credentials. `phonebook:read` and `call-events:write` are independent. Device-to-address-book assignments are explicit. Phonebook exports run through a vendor-neutral exporter interface and a sessionless device runtime; every request revalidates the owner's current address-book access so stale assignments cannot preserve revoked access. Call events enter through a generic normalized application boundary, are persisted through a transactional state machine, and vendor-specific Action URL values are translated by adapters before they reach the core. Notification evaluation is a post-commit application step. Matching rules outside quiet hours create persistent delivery jobs; external provider adapters run only from the cron-compatible delivery worker. Provider latency and failures therefore never participate in the call-state transaction or device request. Delivery history is metadata-only in the UI. Failed jobs may retain a separately encrypted retry payload; manual retry decrypts it only inside the application service and requeues the same owner-scoped job. Test sends use the same queue/worker path rather than bypassing delivery infrastructure.

Security-sensitive administrative changes persist typed audit events through the same PDO transaction as the protected state mutation. Audit storage is intentionally separate from contact-access telemetry and accepts only bounded non-sensitive metadata.

See [Authentication](authentication.md), [Permissions](permissions.md), [User Management](user-management.md), and [Audit Events](audit.md).

## Deployment

First-class target: conventional PHP shared hosting with MariaDB/MySQL.

Future-compatible targets:

- Docker
- VM/VPS
- NAS
- environment-variable based deployment

## Background work

The initial architecture must not require a continuously running worker. Scheduled work must be executable through cron-compatible entry points. Queue workers may be added later.

## Non-goal: PBX

Open Phonebook does not provide:

- SIP registration
- SIP routing
- RTP/media handling
- PBX functions

It may consume and normalize call events emitted by phones, intercoms, PBXs, or other adapters.


## Operations diagnostics

Cron-driven application tasks report aggregate lifecycle heartbeats through a small operations repository. The authenticated operations service maps those heartbeats to healthy, stale, failed, or never-run states using configurable thresholds and combines them with database/query and notification-queue aggregate metrics.

The diagnostics layer is deliberately separate from scheduler business logic and does not persist or display provider exceptions or user-derived content.


## Backup and restore boundary

Backup/restore uses standard MariaDB/MySQL dumps rather than a proprietary application archive. Open Phonebook provides a server-side readiness/verification layer around that standard process.

The readiness service compares the database migration set with the migrations known by the current code, checks the effective application key and installation lock, reports whether dependency locking is available, and derives a non-secret domain-separated fingerprint for restore verification.

The database and original application key are one logical recovery unit. Cache/log storage is not part of the current persistent domain model.


## Web Push / PWA

Web Push is implemented as another notification-provider adapter and therefore reuses the same user-owned channels, notification rules, quiet-hours evaluation, persistent delivery queue, retries, test sends, and delivery history as Pushover and email.

The Minishlink Web Push library is isolated behind `WebPushTransportInterface`. Browser subscription data remains in encrypted channel configuration; global VAPID identity remains in runtime configuration.

The PWA layer is intentionally minimal. The root service worker receives push payloads and handles notification clicks but does not provide an offline application cache. This preserves the existing no-store model for authenticated private content.
