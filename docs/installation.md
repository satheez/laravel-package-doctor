# Installation

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11.0`, `^12.0`, or `^13.0` |
| Composer | `2.x` |

## Install

Install as a development dependency:

```bash
composer require --dev satheez/laravel-package-doctor
```

Laravel auto-discovers the service provider via `extra.laravel.providers` in `composer.json`. No manual registration required.

## Publish Configuration (optional)

To customize scoring thresholds, CI settings, or ignore specific packages:

```bash
php artisan vendor:publish --tag=package-doctor-config
```

This creates `config/package-doctor.php` in your project. If you skip this step the package uses sensible defaults.

See [Configuration](configuration.md) for a full walkthrough of every available option.

## GitHub Token (optional)

GitHub metadata (archive status, last push date, latest release, documentation presence) is collected optionally. Without a token, requests use the unauthenticated rate limit (60 req/hr).

Add to your `.env`:

```env
PACKAGE_DOCTOR_GITHUB_TOKEN=ghp_your_token_here
```

For projects with many packages, keep the metadata cache enabled and add the token before running full scans. If GitHub reports a rate limit, Package Doctor stops making further uncached GitHub calls for that run and keeps the rest of the report usable.

GitHub metadata collection can be disabled entirely:

```env
PACKAGE_DOCTOR_GITHUB_ENABLED=false
```

## Verify Installation

```bash
php artisan package:doctor --help
```

## Composer Scripts

The following scripts are available in the package's own `composer.json` for contributors:

```bash
composer test     # Run Pest test suite
composer format   # Run Laravel Pint formatter
composer lint     # Pint in check mode (no changes)
composer stan     # PHPStan static analysis
composer rector   # Rector dry-run
```
