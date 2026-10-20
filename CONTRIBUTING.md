# Contributing

Thank you for your interest in Open Phonebook.

## Language

Source code, database names, API names, commit messages, issues, pull requests, and development documentation should be written in English.

The user interface and user documentation are intended to support at least English and German.

## Development principles

- keep the core vendor-neutral
- implement vendors and providers through adapters
- preserve strict authorization boundaries
- add migrations for schema changes
- add or update tests with behavior changes
- avoid introducing a PBX dependency
- keep shared-hosting compatibility in mind

## Dependencies

`composer.lock` is committed and is the source of truth for dependency versions.

For normal development and CI, use:

`composer install`

Before merging dependency-sensitive changes, also run:

`composer audit --locked --no-interaction`

CI performs the same audit against the committed lock file.

CI also starts a real MariaDB service, applies the complete migration chain to an empty database, verifies update readiness, and runs the migration command a second time to prove idempotency.

Do not run `composer update` as part of unrelated feature work. Dependency updates should be intentional, reviewed as their own change, and commit the resulting `composer.lock` diff.

## Pull requests

Keep pull requests focused. Describe the problem, the chosen approach, and any migration, dependency, or security implications.
