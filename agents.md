# agents.md -- oCIS PHP SDK

## Repository Overview

PHP SDK for interacting with oCIS. Licensed under Apache-2.0. Wraps the Libre Graph API via the libre-graph-api-php client.

## Architecture & Key Paths

- `src/` -- SDK source code
- `tests/` -- Unit and integration tests
- `docs/` -- Generated API documentation
- `Makefile` -- Build and test automation
- `composer.json` -- Composer package definition
- `phpunit.xml` -- PHPUnit configuration
- `phpstan.neon` -- PHPStan configuration
- `phpdoc.dist.xml` -- phpDocumentor configuration

## Development Conventions

- PHP 8.1+ required
- Composer-based dependency management
- PSR-4 autoloading
- Code style via php-cs-fixer
- Static analysis with Phan and PHPStan

## Build & Test Commands

```bash
composer install                     # Install dependencies
make test-php-unit                   # Run unit tests
make test-php-integration            # Run integration tests (requires oCIS)
make test-php-style                  # Check code style
make test-php-phan                   # Run Phan
make test-php-phpstan                # Run PHPStan
make clean                           # Clean build artifacts
```

## Important Constraints

- Licensed under Apache-2.0 (already at the OSPO target license). The broader ownCloud organization is migrating other repositories from copyleft licenses to Apache 2.0.
- Depends on `owncloud/libre-graph-api-php` (dev stability).
- All contributions require a DCO sign-off.


## OSPO Policy Constraints

### GitHub Actions
- **Only** use actions owned by `owncloud`, created by GitHub (`actions/*`), verified on the GitHub Marketplace, or verified by the ownCloud Maintainers.
- Pin all actions to their full commit SHA (not tags): `uses: actions/checkout@<SHA> # vX.Y.Z`
- Never introduce actions from unverified third parties.

### Dependency Management
- Dependabot is configured for automated dependency updates.
- Review and merge Dependabot PRs as part of regular maintenance.
- Do not introduce new dependencies without discussion in an issue first.

### Git Workflow
- **Rebase policy**: Always rebase; never create merge commits. Use `git pull --rebase` and `git rebase` before pushing.
- **Signed commits**: All commits **must** be PGP/GPG signed (`git commit -S -s`).
- **DCO sign-off**: Every commit needs a `Signed-off-by` line (`git commit -s`).
- **Conventional Commits & Squash Merge**: Use the [Conventional Commits](https://www.conventionalcommits.org/) format where the repository enforces it. Many repos use squash merge, where the PR title becomes the commit message on the default branch — apply Conventional Commits format to PR titles as well. A reusable GitHub Actions workflow enforces this.

## Context for AI Agents

The SDK provides high-level PHP classes for oCIS operations (drives, files, users, groups, shares). The `src/` directory contains the public API. Integration tests require a running oCIS instance with Keycloak.
