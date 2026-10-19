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
- safe file upload parsing and size limits
- import preview before persistence
- database migrations executed explicitly and transactionally where possible

## Secrets

Provider tokens should be encrypted at rest where practical.

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

The initial CSV preview implements the pre-persistence portion of this policy: successful PHP upload verification, a 2,000,000-byte server-side limit, UTF-8 and NUL-byte validation, a 2,000-row parser limit, explicit supported columns, domain-aligned field limits, CSRF protection, and writable-address-book authorization. Preview does not persist uploaded files or contact data.

## Privacy

Call-history retention is configurable and may be disabled.

Authentication throttling does not persist raw email/IP combinations.

Invitation tokens are not persisted in plaintext.

Users should eventually be able to export and delete their own application data.

## Logging

Audit administrative and security actions without turning the audit log into contact-access surveillance.

Authentication logs must not include plaintext passwords, provider secrets, session identifiers, invitation tokens, or unnecessary personal data.
