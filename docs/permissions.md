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
- contacts in owned or otherwise writable address books
- own external contact connections
- permitted devices
- own notification channels and rules
- own call history where applicable

## Organizations

Organization administrators may manage organization-level resources according to policy, including shared address books and assigned devices.

Private personal address books remain inaccessible unless explicitly shared. Personal shares grant either read-only or read/write contact access. Renaming/deleting the address book and managing shares remain owner-only. System administrators receive no implicit access to private contacts.

## Devices

Devices use scoped credentials rather than user sessions. Implemented scopes are `phonebook:read` and `call-events:write`.

A device credential may grant only the operations needed for that device, such as:

- retrieve assigned phonebook export
- submit normalized or vendor-specific call events

Credentials support explicit per-scope rotation and revocation. Rotating or revoking one scope does not affect the other; device deactivation revokes all device scopes. Device owners may assign only address books they can currently read. Device-facing exports and caller lookup revalidate both the stored assignment and the owner's current access.

## Notifications

Notification channels and rules are personal resources. A user may create, activate, deactivate, and delete only their own channels and rules.

A notification rule may reference only:

- a channel owned by that user
- either all of the user's devices or one device owned by that user
- a supported notification trigger

Quiet-hours settings, delivery history, manual retry, and test-send actions are scoped to the rule/channel owner as well. A user may retry only their own failed delivery and only while its original channel still exists and is active. Provider configuration and decrypted retry payloads are never exposed merely because another account has an administrative role.

## Operations diagnostics

The `view_operations` permission grants access to technical scheduler and queue diagnostics.

It is initially granted only to system administrators. The diagnostics expose aggregate technical metadata only and do not reveal contacts, notification content, retry payloads, provider configuration, or secrets.

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
