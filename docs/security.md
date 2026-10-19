# Security Architecture

## Threat model

Open Phonebook stores personal contacts and may store call metadata, provider tokens, device credentials, notification credentials, and authentication metadata.

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
- safe file upload parsing and size limits
- import preview before persistence
- database migrations executed explicitly and transactionally where possible

## Secrets

Provider tokens should be encrypted at rest where practical.

The encryption key must not be stored in the same database as encrypted provider credentials.

Runtime secret configuration must be excluded from Git.

The setup process generates an application key outside the database. It is currently used for keyed security hashes such as login-throttling subjects. Future cryptographic features must define appropriate key derivation or separate keys.

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

Account recovery and password reset must be designed before public multi-user deployment.

Future-compatible authentication:

- TOTP/WebAuthn
- OIDC
- LDAP

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

## Privacy

Call-history retention is configurable and may be disabled.

Authentication throttling does not persist raw email/IP combinations.

Users should eventually be able to export and delete their own application data.

## Logging

Audit administrative and security actions without turning the audit log into contact-access surveillance.

Authentication logs must not include plaintext passwords, provider secrets, session identifiers, or unnecessary personal data.
