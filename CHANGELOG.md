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

### Security

- Ambiguous local phone numbers are never assigned a country without explicit region configuration
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
