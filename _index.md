---
title: "oCIS PHP SDK"
date: 2024-09-27T10:12:41
weight: 1
geekdocRepo: https://github.com/owncloud/ocis-php-sdk
geekdocEditPath: edit/main
geekdocFilePath: README.md
geekdocCollapseSection: true
---

# oCIS PHP SDK

<!-- OSPO-managed README | Generated: 2026-04-16 | v2 -->

[![License](https://img.shields.io/badge/License-Apache--2.0-blue.svg)](LICENSE) [![ownCloud OSPO](https://img.shields.io/badge/OSPO-ownCloud-blue)](https://kiteworks.com/opensource) [![Docker Hub](https://img.shields.io/docker/pulls/owncloud)](https://hub.docker.com/r/owncloud/ocis)

A PHP SDK for interacting with ownCloud Infinite Scale. It wraps the Libre Graph API and provides high-level PHP classes for managing drives, files, users, groups, shares, notifications, and application roles on an oCIS instance, with support for both OIDC and education-specific access tokens.

## Getting Started

Follow the steps below to install and use the oCIS PHP SDK.

### Installation via Composer

```bash
composer config minimum-stability dev
composer config prefer-stable true
composer require owncloud/ocis-php-sdk
```

### Usage

```php
use Owncloud\OcisPhpSdk\Ocis;

$ocis = new Ocis('https://example.ocis.com', $accessToken);
$drives = $ocis->getMyDrives();
```

### Run Tests

```bash
make test-php-unit
make test-php-style
make test-php-phpstan
```

## Documentation

- [Rendered API Documentation](https://owncloud.dev/ocis-php-sdk/)
- [oCIS Documentation](https://doc.owncloud.com/ocis/next/)

## Part of ownCloud Infinite Scale

This SDK is the recommended way for PHP applications to integrate with [oCIS](https://github.com/owncloud/ocis). It is used by the [Moodle oCIS plugin](https://github.com/owncloud/moodle-repository_ocis) and depends on the [libre-graph-api-php](https://github.com/owncloud/libre-graph-api-php) client.

This component is part of the [oCIS Docker image](https://hub.docker.com/r/owncloud/ocis).

## Community & Support

**[Star](https://github.com/owncloud/ocis-php-sdk)** this repo and **Watch** for release notifications!

- [ownCloud Website](https://owncloud.com)
- [Community Discussions](https://github.com/orgs/owncloud/discussions)
- [Matrix Chat](https://app.element.io/#/room/#owncloud:matrix.org)
- [Documentation](https://doc.owncloud.com)
- [Enterprise Support](https://owncloud.com/contact-us/)
- [OSPO Home](https://kiteworks.com/opensource)

## Contributing

We welcome contributions! Please read the [Contributing Guidelines](CONTRIBUTING.md)
and our [Code of Conduct](CODE_OF_CONDUCT.md) before getting started.

### Workflow

- **Rebase Early, Rebase Often!** We use a rebase workflow. Always rebase on the target branch before submitting a PR.
- **Dependabot**: Automated dependency updates are managed via Dependabot. Review and merge dependency PRs promptly.
- **Signed Commits**: All commits **must** be PGP/GPG signed. See [GitHub's signing guide](https://docs.github.com/en/authentication/managing-commit-signature-verification).
- **DCO Sign-off**: Every commit must carry a `Signed-off-by` line:
  ```
  git commit -s -S -m "your commit message"
  ```
- **GitHub Actions Policy**: Workflows may only use actions that are (a) owned by `owncloud`, (b) created by GitHub (`actions/*`), or (c) verified in the GitHub Marketplace.

## Security

**Do not open a public GitHub issue for security vulnerabilities.**

Report vulnerabilities at **<https://security.owncloud.com>** -- see [SECURITY.md](SECURITY.md).

Bug bounty: [YesWeHack ownCloud Program](https://yeswehack.com/programs/owncloud-bug-bounty-program)

## License

This project is licensed under the [Apache-2.0](LICENSE).

## About the ownCloud OSPO

The [Kiteworks Open Source Program Office](https://kiteworks.com/opensource), operating under
the [ownCloud](https://owncloud.com) brand, launched on May 5, 2026, to steward the open source
ecosystem around ownCloud's products. The OSPO ensures transparent governance, license compliance,
community health, and sustainable collaboration between the open source community and
[Kiteworks](https://www.kiteworks.com), which acquired ownCloud in 2023.

- **OSPO Home**: <https://kiteworks.com/opensource>
- **GitHub**: <https://github.com/owncloud>
- **ownCloud**: <https://owncloud.com>

For questions about the OSPO or licensing, contact ospo@kiteworks.com.

> **License status:** This repository is already licensed under Apache-2.0 -- the OSPO target license.
> No migration is required.
