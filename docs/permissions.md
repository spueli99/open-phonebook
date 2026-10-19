# Permissions

## Principles

Authorization is deny-by-default.

Every application operation must explicitly validate ownership, organization membership, sharing rights, or scoped device permissions.

Authentication answers who the current user is. Authorization separately decides what that user may access.

## Roles

Initial roles:

- system_admin
- organization_admin
- member

Roles are intentionally generic.

Unknown stored roles degrade to `member` permissions rather than gaining administrative access.

The existence of a role in authenticated session state does not replace resource-level authorization checks.

## Initial permissions

The first permission identifiers are:

- `manage_users`
- `invite_users`

Only `system_admin` currently receives these platform-wide permissions.

`organization_admin` remains reserved for organization-scoped permissions once the organization model exists.

## User administration safeguards

Platform user administration currently enforces:

- no self-deactivation
- no deactivation of the last active system administrator
- no activation changes for deleted users
- idempotent activation requests
- no self-demotion from `system_admin`
- no demotion of the last active system administrator
- no assignment of `organization_admin` without organization context

Platform role changes are currently limited to `member` and `system_admin`. Permanent deletion remains deferred because it affects ownership and authorization across other domains.

## Users

A normal user may eventually manage:

- own profile and security settings
- owned address books
- contacts in writable address books
- own external contact connections
- permitted devices
- own notification channels and rules
- own call history where applicable

## Organizations

Organization administrators may manage organization-level resources according to policy, including shared address books and assigned devices.

Private personal address books remain inaccessible unless explicitly shared.

## Devices

Devices use scoped credentials rather than user sessions.

A device credential may grant only the operations needed for that device, such as:

- retrieve assigned phonebook export
- submit normalized or vendor-specific call events

Credentials must support rotation and revocation.

## System administrators

System administration should focus on platform operations. The UI should avoid exposing private user contacts merely because an account has administrative privileges, even though server operators inherently control the underlying infrastructure.

## Audit

Audit security-sensitive actions such as:

- authentication changes
- user/organization administration
- credential creation, rotation, and revocation
- OAuth/provider connection changes
- imports and destructive operations

Do not create invasive logs of ordinary contact browsing.
