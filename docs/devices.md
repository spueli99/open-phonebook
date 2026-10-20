# Devices

## Scope

Devices are non-human consumers such as desk phones, intercoms, or software endpoints.

Devices never authenticate with a user's browser session or password.

Each device is owned by exactly one user in the initial model.

## Device credentials

Creating a device generates one credential with the scope:

`phonebook:read`

The plaintext token is shown once immediately after creation or rotation.

The database stores only an HMAC-SHA-256 token hash. The HMAC input is domain-separated for device credentials and keyed by the application key.

A credential is accepted only when:

- the token format is valid
- the HMAC matches an active, non-revoked credential
- the requested scope matches
- the device is active
- the owning user account is active and not deleted

Rotating a credential revokes all currently active credentials for the same device and scope before creating a replacement.

Revoking a credential leaves the device configured but without a usable token.

Deactivating a device automatically revokes its active phonebook credential. Reactivating a device does not silently create a new credential; the owner must explicitly rotate it.

## Address-book assignment

A device may be assigned only address books that its owner can currently read.

Assignments are explicit. A device receives no address book merely because its owner can access it.

Phonebook export requests intersect the stored device assignments with the owner's current address-book access again at request time. This prevents a stale assignment from bypassing a later share revocation.

## Management

Every signed-in user manages only their own devices.

Current management operations:

- create device
- set vendor/model metadata
- activate/deactivate device
- rotate phonebook credential
- revoke phonebook credential
- assign readable address books

## Phonebook exports

The device endpoint is:

`GET /api/v1/device/phonebook/?format=<format>`

Implemented formats are `json`, `csv`, and `yealink`.

Phonebook output is generated through a vendor-neutral exporter interface. The endpoint uses a sessionless device runtime, requires the existing `phonebook:read` credential scope, and does not reuse browser sessions.

Credentials are accepted through the HTTP Authorization header, never through query-string token parameters. Private responses are marked no-store.

One response is limited to 5,000 contacts. The Yealink serializer additionally rejects XML output above 1.5 MB. CSV output neutralizes leading spreadsheet formula characters.

## Deferred

- call-event credentials/scopes
- organization-owned devices
- last-used tracking in the authentication path
- additional vendor exporters
