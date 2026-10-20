# Changelog

All notable changes to this project will be documented in this file.

The project is currently in the pre-release architecture and foundation phase.

## Unreleased

### Added

- Secure web setup installer and versioned migration foundation
- Local email/password authentication foundation
- Secure authenticated browser sessions
- Database-backed login throttling with HMAC-derived subject identifiers
- English and German authentication localization
- System-level user administration foundation
- Explicit user-management permissions
- Secure invitation creation and acceptance flow
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

### Security

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
- Owner-scoped authorization boundary for private address books and contacts
- CSRF protection for address-book and contact mutations
- CSRF protection for login, logout, user administration, and invitation acceptance
- Session ID regeneration after authentication state changes
- Generic invalid-credential responses and unknown-user timing mitigation
- Application key generation outside the database
- HMAC-only storage of invitation tokens
- Fragment-based invitation links to keep invitation tokens out of ordinary HTTP request URLs
- Protection against self-deactivation and deactivation of the last active system administrator
- Protection against self-demotion and demotion of the last active system administrator
- Renewal invalidates the previous invitation token before exposing a replacement token
