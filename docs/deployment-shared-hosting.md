# Shared Hosting Deployment

Open Phonebook is designed to remain deployable on conventional PHP shared hosting.

## Document root

The web server must expose only:

`public/`

All other application directories must stay outside the public document root.

## Runtime requirements

Initial baseline:

- PHP 8.3+
- MariaDB or MySQL
- PDO MySQL
- cURL
- JSON
- mbstring
- OpenSSL
- sessions
- HTTPS

## Background tasks

The initial platform must not require a long-running worker.

Scheduled jobs will be exposed through controlled cron-compatible entry points. On providers that only support URL-based cron jobs, those endpoints must use scoped credentials and rate limiting.

## Composer

Production deployments may either run Composer on the server or upload a release artifact that already contains the production `vendor/` tree.

A future release process should provide packaged artifacts for users without shell access.

## Security

Do not point a domain at the repository root.

Do not place runtime configuration, OAuth credentials, device tokens, or database backups below `public/`.

The installer will verify common deployment mistakes before allowing installation to complete.
