# Audit Events

## Scope

Open Phonebook persists a deliberately small audit trail for security-sensitive administrative actions.

The first persisted event types cover:

- user activation and deactivation
- platform role changes
- invitation creation
- invitation acceptance
- invitation renewal
- invitation revocation

The audit model is designed for accountability, not surveillance. Ordinary contact browsing is not audited.

## Stored fields

Each audit event stores:

- event type
- actor user ID
- target type
- target ID
- small structured metadata
- UTC creation time

The actor reference may become null if that account is later deleted, while the audit event itself remains.

## Metadata boundary

Audit metadata is validated centrally.

Current limits:

- at most eight entries
- lowercase machine-readable keys only
- primitive boolean/integer/string values only
- string values limited to 120 bytes
- encoded metadata limited to 2 KiB
- keys containing sensitive concepts such as password, token, secret, session, email, phone, contact, content, or authorization are rejected

Current user/invitation events store only bounded values such as previous/new role or an internal invitation-revocation reason.

Audit metadata must never contain plaintext passwords, invitation/reset tokens, session identifiers, provider secrets, contact data, phone numbers, notification payloads, or arbitrary exception text.

## Transaction boundary

Administrative user and invitation changes write the audit event through the same PDO connection and transaction as the protected state change.

If audit persistence fails, the associated administrative database change is rolled back.

Invitation delivery happens after the creation/renewal transaction. If mail configuration or delivery fails, the invitation is revoked in a new audited transaction using a fixed internal reason.

## Reading audit events

Authenticated system administrators with the dedicated `view_audit` permission can open the read-only audit log at `/admin/audit/`. The UI shows the latest 100 events, formats timestamps in the administrator's configured timezone, and renders only actor/target IDs plus the already validated bounded metadata. All dynamic values are HTML-escaped.

The audit UI intentionally has no contact-content search, bulk export, mutation controls, or ordinary contact-access tracking.

Future audit expansion should reuse the same typed event and metadata boundaries for device credentials, OAuth/provider connections, imports, destructive operations, organization administration, and selected authentication/security changes.
