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
- Authentication and security documentation

### Security

- CSRF protection for login and logout
- Session ID regeneration after authentication state changes
- Generic invalid-credential responses and unknown-user timing mitigation
- Application key generation outside the database
