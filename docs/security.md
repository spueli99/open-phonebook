# Security Architecture

## Threat model

Open Phonebook stores personal contacts and may store call metadata, provider tokens, device credentials, and notification credentials.

Assume the public web interface and device/export endpoints are internet-accessible.

## Required controls

- HTTPS in production
- secure session cookies
- CSRF protection
- rate limiting
- strict server-side authorization
- output escaping
- prepared database statements
- scoped device tokens
- credential rotation and revocation
- OAuth state validation
- no secrets in source control
- no sensitive values in application logs
- safe file upload parsing and size limits
- import preview before persistence
- database migrations executed explicitly and transactionally where possible

## Secrets

Provider tokens should be encrypted at rest where practical.

The encryption key must not be stored in the same database as encrypted provider credentials.

Runtime secret configuration must be excluded from Git.

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

## Authentication

Initial default: accounts are created by administrators/invitations rather than open public registration.

Account recovery and password reset must be designed before public multi-user deployment.

Future-compatible authentication:

- TOTP/WebAuthn
- OIDC
- LDAP

## Privacy

Call-history retention is configurable and may be disabled.

Users should eventually be able to export and delete their own application data.

## Logging

Audit administrative and security actions without turning the audit log into contact-access surveillance.
