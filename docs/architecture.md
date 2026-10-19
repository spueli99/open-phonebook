# Architecture

## Goal

Open Phonebook is a self-hosted multi-user contact hub for IP phones, call events, and notifications.

The product must stay universal. Initial development is driven by real-world requirements, but no core concept may depend on a specific community, building, PBX, or phone vendor.

## Core domains

The core understands:

- users
- organizations
- memberships
- address books
- contacts
- phone numbers
- contact sources
- devices
- exports
- call sessions and events
- notification channels and rules

It does not understand vendor-specific concepts directly.

## Layering

### Core

Business rules, permissions, contact model, device model, call state model, notification rules.

### Adapters

Examples:

- contact sources: Google Contacts, future CardDAV/Microsoft 365
- imports: vCard, CSV
- device exporters: Yealink first, later Snom/Grandstream/Fanvil
- call event adapters: Yealink Action URLs first
- notification providers: Pushover and email first, Web Push later

### Application/UI/API

The web UI and REST API use the same application services. Business logic must not live inside templates or installer forms.

## Deployment

First-class target: conventional PHP shared hosting with MariaDB/MySQL.

Future-compatible targets:

- Docker
- VM/VPS
- NAS
- environment-variable based deployment

## Background work

The initial architecture must not require a continuously running worker. Scheduled work must be executable through cron-compatible entry points. Queue workers may be added later.

## Non-goal: PBX

Open Phonebook does not provide:

- SIP registration
- SIP routing
- RTP/media handling
- PBX functions

It may consume and normalize call events emitted by phones, intercoms, PBXs, or other adapters.
