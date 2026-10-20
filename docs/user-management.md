# User Management

## Scope

The first user-management foundation provides administrative user visibility, account activation/deactivation, invitation creation, invitation acceptance, and a minimal permission model.

The goal is to establish safe account lifecycle boundaries before address books, organizations, contacts, devices, or notification ownership are added.

## Roles

Initial roles:

- `system_admin`
- `organization_admin`
- `member`

Only `system_admin` is currently granted platform-wide user-management permissions.

`organization_admin` is defined for the future organization model but cannot yet be issued through the generic invitation flow because no organization context exists yet.

Unknown stored roles are treated as `member` by the authorization layer.

## Permissions

Current permission identifiers:

- `manage_users`
- `invite_users`

Authorization is explicit and deny-by-default.

Authentication and authorization remain separate concerns: a valid session identifies a user, while permission checks determine access to administrative operations.

## User administration

System administrators can list users and activate or deactivate other non-deleted accounts.

Safeguards:

- a user cannot deactivate their own account
- the last active system administrator cannot be deactivated
- repeating the current activation state is treated as a no-op
- deleted users are not modified by activation operations

Permanent deletion is intentionally not included yet.

System administrators can change another non-deleted user's platform role between `member` and `system_admin`. A system administrator cannot remove their own administrator role, and the last active system administrator cannot be demoted. `organization_admin` remains unavailable until organization context exists.

## Invitations

Invitations are created by a system administrator.

Current rules:

- email addresses are normalized to lowercase
- existing users cannot be invited
- only one active invitation may exist for an email address
- invitations expire after three days
- invitation tokens are random 256-bit values
- only an HMAC-derived token hash is stored in the database
- invitation tokens are single-use
- accepted or revoked invitations cannot be reused

Invitation links are sent directly to the invited address through the shared hardened mail transport and are never rendered back into the administrator browser. Valid HTTPS `app.base_url` and system-mail sender configuration are required for invitation creation and renewal. If mail configuration is invalid or delivery fails after creation or renewal, the newly issued invitation is immediately revoked so no usable but undelivered token remains. Active invitations are listed in user administration and can be revoked or renewed. Renewing an invitation replaces its token, invalidates the previous link, and starts a fresh three-day validity period.

## Token transport

Invitation links use a URL fragment:

`/invite/accept/#<token>`

Browser fragments are not sent in the HTTP request. A small same-origin script moves the token into a CSRF-protected POST body and immediately removes the fragment from browser history before submitting it.

This design keeps invitation tokens out of ordinary web-server request URLs and access logs.

The server then stores the token temporarily in the server-side session. Subsequent invitation pages use a clean URL.

## Invitation acceptance

A valid invitation creates a new active local account.

The recipient provides:

- display name
- password
- language
- timezone

Passwords require at least 12 characters and are stored using PHP `password_hash()`.

The user row and invitation acceptance marker are committed in the same database transaction.

## Password recovery

Local users can request a password reset from the login page. The public request response does not reveal whether an account exists. Active accounts receive a 30-minute, single-use reset link by system email.

Completing recovery invalidates all prior browser sessions for that account through the authentication revision mechanism.

## Current limitations

Not yet included:

- public registration
- organization-scoped administration
- audit-event persistence
- MFA

These are intentionally separate milestones.
