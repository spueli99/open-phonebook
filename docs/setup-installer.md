# Web Setup Installer

## Purpose

Open Phonebook should be installable on shared hosting without requiring shell access.

The web installer is part of the architecture from the beginning.

## Flow

1. Environment check
2. Database configuration
3. Application defaults
4. Initial administrator
5. Database migrations
6. Installation completion

## Environment checks

At minimum:

- PHP 8.3+
- PDO MySQL
- cURL
- JSON
- mbstring
- OpenSSL
- sessions
- writable runtime storage
- HTTPS warning/check
- database connectivity

## Database step

Collect:

- host
- port
- database name
- username
- password

Provide a connection test before continuing.

## Application defaults

Examples:

- application name
- base URL
- timezone
- default country/region
- default UI language

## Administrator

Create the first system administrator using PHP password_hash().

## Completion and lock

After successful installation, create an installation state that prevents the web installer from running again.

Reinstallation must require deliberate server-side action.

## Configuration

The configuration layer should support the following precedence where appropriate:

1. environment variables
2. runtime/local configuration generated during setup
3. repository defaults

Secrets must not be committed to Git.

## External integrations

Google Contacts, SMTP, Pushover, and device setup belong in the normal authenticated application, not in the initial installer.
