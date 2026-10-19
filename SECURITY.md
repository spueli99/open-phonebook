# Security Policy

Open Phonebook handles personal contact data, OAuth credentials, device credentials, and optionally call metadata. Security is a core design requirement.

## Principles

- HTTPS only in production
- no secrets committed to Git
- password hashing via PHP's password_hash()
- CSRF protection for state-changing web requests
- secure, HttpOnly, SameSite cookies
- session fixation protection
- rate limiting for authentication, device, webhook, and export endpoints
- scoped and revocable device credentials
- provider tokens encrypted at rest where practical
- encryption keys stored outside the database
- strict tenant and ownership authorization
- UTC storage for timestamps
- minimal sensitive data in logs
- configurable retention for call history
- versioned database migrations
- auditable security-sensitive administration actions

## Reporting vulnerabilities

Please do not publish security vulnerabilities as public issues. Until a dedicated private reporting channel is configured, contact the repository owner through GitHub before disclosing details publicly.

## Supported versions

There is no stable supported release yet.
