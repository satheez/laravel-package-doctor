<div align="center">

# Laravel Package Doctor

**Laravel-first dependency health and upgrade-risk analyzer for Composer projects.**

[![Tests](https://github.com/satheez/laravel-package-doctor/actions/workflows/tests.yml/badge.svg)](https://github.com/satheez/laravel-package-doctor/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/satheez/laravel-package-doctor.svg)](https://packagist.org/packages/satheez/laravel-package-doctor)
[![Total Downloads](https://img.shields.io/packagist/dt/satheez/laravel-package-doctor.svg)](https://packagist.org/packages/satheez/laravel-package-doctor)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/satheez/laravel-package-doctor.svg)](LICENSE.md)

</div>

---

Laravel Package Doctor answers the question Composer alone does not:

> **Are the packages in this project healthy, safe, maintained, compatible, and worth keeping?**

It scans your Composer dependencies, checks for security advisories, abandoned packages, Laravel and PHP compatibility, upgrade risk, license concerns, and repository health. It combines those signals into a **scored report** with **actionable recommendations** — instead of a raw list of version numbers.

---

## The Problem

Before upgrading Laravel — or when inheriting a project — you need answers to questions like:

- Is this dependency still actively maintained?
- Does it support my target Laravel version?
- Is the latest version blocked by my `composer.json` constraint?
- Is a major breaking upgrade available that needs manual review?
- Which packages are abandoned, archived, or vulnerable?
- What should I update, replace, or investigate before going further?

Composer's built-in commands are powerful but narrow. They answer one question at a time. Package Doctor answers all of them together.

---

## What Composer Gives You vs What Package Doctor Gives You

**`composer outdated` tells you what changed:**

```
vendor/legacy-helper   1.2.0   2.0.0
```

**Package Doctor tells you what it means:**

```
vendor/legacy-helper
  Current:        1.2.0
  Latest:         2.0.0         (Major upgrade — may contain breaking changes)
  Latest allowed: 1.2.3         (Latest version your constraint permits)
  Score:          42 / 100
  Status:         Risky

  Issues:
  ↳ [constraint_blocked]     Latest version is blocked by your composer.json constraint.
  ↳ [major_upgrade_available] A major upgrade is available. Review changelog before updating.

  Recommendation: Review changelog and update constraint if compatible.
```

For a genuinely problematic package:

```
vendor/abandoned-auth
  Current:  0.9.1
  Latest:   0.9.1
  Score:    14 / 100
  Status:   Critical

  Issues:
  ↳ [abandoned]            Package is marked as abandoned on Packagist.
  ↳ [no_release_18_months] No release in over 18 months.
  ↳ [laravel_incompatible] Does not declare support for the current Laravel version.

  Recommendation: Replace this package before upgrading Laravel.
```

---

## Why This vs Other Tools

| | `composer outdated` | `composer audit` | Dependabot / Renovate | Roave + Enlightn | **Laravel Package Doctor** |
|---|:---:|:---:|:---:|:---:|:---:|
| Detect outdated versions | ✓ | – | ✓ (PRs) | – | ✓ |
| Security advisories | – | ✓ | ✓ (alerts) | ✓ | ✓ |
| Abandoned packages | – | ✓ (partial) | – | – | ✓ |
| Repository archived | – | – | – | – | ✓ |
| Laravel version compatibility | – | – | – | – | ✓ |
| PHP version compatibility | – | – | – | – | ✓ |
| Constraint-blocked upgrades | – | – | – | – | ✓ |
| License risk classification | – | – | – | – | ✓ |
| Health score (0–100) | – | – | – | – | ✓ |
| Actionable recommendations | – | – | – | – | ✓ |
| CI exit codes | – | ✓ | n/a | – | ✓ |
| Works offline | ✓ | ✓ | – | – | ✓ |
| Laravel Artisan command | – | – | – | – | ✓ |

> Use **Dependabot / Renovate** to automate routine version bumps.
> Use **`composer audit`** and **Roave security-advisories** as hard security gates.
> Use **Package Doctor** when you need to *understand* what your dependencies mean — before a Laravel upgrade, when inheriting a codebase, or when you want a scored risk report with recommendations instead of a list of raw diffs.

These tools are complementary. Package Doctor composes the data `composer outdated`, `composer audit`, and `composer licenses` produce into one coherent, scored decision layer.

---

## Features

**Scan**
- Reads `composer.json` and `composer.lock`
- Detects direct, dev, and transitive dependencies
- Identifies installed and latest available versions
- Detects patch, minor, and major upgrade types

**Detect**
- Security advisories (`composer audit`)
- Abandoned packages (Packagist)
- Archived GitHub repositories
- Laravel and PHP version incompatibilities
- Constraint-blocked upgrades (latest version not installable)
- Packages with no recent release (12 or 18 month windows)
- Risky licenses (GPL, AGPL variants)
- Low-popularity packages

**Score and Recommend**
- 0–100 health score per package based on weighted deductions
- Status classification: Healthy, Watch, Risky, Critical
- One actionable recommendation per package
- Overall project health score
- Package ignore reasons that keep reviewed packages visible without affecting the project score
- Suggested replacements for abandoned packages when Composer or Packagist provides one
- Changelog links for major-upgrade recommendations when GitHub metadata is available

**Output**
- Readable console table with issue details
- Realtime terminal progress while scans are running
- Machine-readable JSON and CSV output (`--json`, `--format=json`, `--format=csv`)
- JSON and CSV file exports via `--output=path`
- Structured exit codes for CI (`--ci`)
- Offline mode that skips external API calls (`--offline`)
- Metadata caching to reduce API calls on repeated runs
- Project score trend indicators from the previous scan

---

## Installation

```bash
composer require --dev satheez/laravel-package-doctor
```

Laravel auto-discovers the service provider. No manual registration needed.

**Optional:** Publish the config to customise scoring thresholds, CI gates, or package ignores:

```bash
php artisan vendor:publish --tag=package-doctor-config
```

---

## Quick Start

```bash
php artisan package:doctor
```

Normal terminal runs show live scan progress while Composer, Packagist, and GitHub metadata are collected. Progress output is suppressed for JSON, CSV, CI, and non-interactive output so scripts can safely parse the final report.

Example output:

```
Laravel Package Doctor

PHP: 8.3.12  Laravel: 12.5.0

Summary
  Project score: 78 (↑ +3)/100
  Packages scanned: 42
  Healthy: 31  Watch: 6  Risky: 4  Critical: 1  Ignored: 0

 ------------------------------- --------- --------- ------- ----- ---------- ---------------------------
  Package                         Current   Latest    Upgrade Score  Status     Recommendation
 ------------------------------- --------- --------- ------- ----- ---------- ---------------------------
  spatie/laravel-permission        6.17.0    6.18.2   minor   94    Healthy    Safe upgrade available
  barryvdh/laravel-debugbar        3.14.8    3.15.0   minor   86    Watch      Update when convenient
  vendor/legacy-helper             1.2.0     2.0.0    major   42    Risky      Review before upgrading
    ↳ [constraint_blocked]         Latest version is blocked by your composer.json constraint.
    ↳ [major_upgrade_available]    A major upgrade is available. Review changelog.
  vendor/abandoned-auth            0.9.1     0.9.1    none    14    Critical   Replace this package
    ↳ [abandoned]                  Package is marked as abandoned on Packagist.
    ↳ [no_release_18_months]       No release in over 18 months.
    ↳ [laravel_incompatible]       Does not declare support for current Laravel version.
 ------------------------------- --------- --------- ------- ----- ---------- ---------------------------
```

> Output is illustrative. Actual formatting depends on your terminal width and installed packages.

---

## Common Commands

| Command | Description |
|---|---|
| `php artisan package:doctor` | Scan direct + dev dependencies (no transitive) |
| `php artisan package:doctor --all` | Scan full dependency tree including transitive packages |
| `php artisan package:doctor --direct` | Direct dependencies only (great before a Laravel upgrade) |
| `php artisan package:doctor --score-below=70` | Show only Watch, Risky, or Critical packages |
| `php artisan package:doctor --no-dev --ci` | Production packages, gate on CI |
| `php artisan package:doctor --json` | Machine-readable JSON output |
| `php artisan package:doctor --format=csv` | Spreadsheet-friendly CSV output |
| `php artisan package:doctor --format=json --output=package-health.json` | Write a JSON report file |
| `php artisan package:doctor --format=csv --output=package-health.csv` | Write a CSV report file |
| `php artisan package:doctor --offline` | Skip external API calls |

All available options → [docs/usage.md](docs/usage.md)

---

## Health Score

Each package starts at **100**. Issues subtract points. Score is clamped to `[0, 100]`.

| Issue | Score Impact |
|---|---:|
| Security advisory found | −30 |
| Package abandoned | −30 |
| Repository archived | −25 |
| Laravel incompatible | −20 |
| PHP incompatible | −20 |
| Constraint blocks latest major | −15 |
| No release in 18 months | −15 |
| Risky license (GPL/AGPL) | −15 |
| Major upgrade available | −10 |
| No release in 12 months | −8 |
| Low downloads | −5 |
| Missing documentation | −5 |
| Unknown repository | −3 |

| Score | Status | Meaning |
|---:|---|---|
| 90–100 | **Healthy** | Safe and well-maintained |
| 70–89 | **Watch** | Minor concerns worth monitoring |
| 40–69 | **Risky** | Needs review before upgrades |
| 0–39 | **Critical** | Investigate, update, or replace soon |

All scoring is configurable. See [docs/scoring.md](docs/scoring.md).

---

## CI/CD Integration

```yaml
- name: Check dependency health
  run: php artisan package:doctor --ci
```

| Exit Code | Meaning |
|---:|---|
| `0` | All checks passed |
| `1` | Risky packages found, or project score below minimum |
| `2` | Critical packages found |
| `3` | Runtime error |

Full CI recipes for GitHub Actions, GitLab CI, and Bitbucket Pipelines → [docs/ci.md](docs/ci.md)

---

## Configuration Snapshot

The most-tuned settings after publishing `config/package-doctor.php`:

```php
return [
    // Gate values for CI mode
    'ci' => [
        'minimum_project_score' => 60,
        'fail_on_statuses'      => ['critical'],
    ],

    // Scope: skip dev packages, scan only direct deps
    'scan' => [
        'include_dev'        => false,
        'include_transitive' => false,
    ],

    // GitHub token for archive/release metadata
    'metadata' => [
        'github' => [
            'token' => env('PACKAGE_DOCTOR_GITHUB_TOKEN'),
        ],
    ],

    // Silence known false positives
    'ignore' => [
        'packages' => [
            // 'vendor/package' => 'reason',
        ],
    ],
];
```

Full config reference → [docs/configuration.md](docs/configuration.md)

Ignored packages remain visible in the report with an `Ignored` status and the configured reason. They are excluded from the project score so known, reviewed exceptions do not inflate or reduce the health score.

---

## Offline Mode

Skip all external API calls for restricted CI environments or air-gapped machines:

```bash
php artisan package:doctor --offline
```

Offline mode still runs `composer outdated`, `composer audit`, and `composer licenses`, and reads your lock file. Packagist and GitHub checks are skipped.

## GitHub Rate Limits

Large projects can exhaust GitHub's unauthenticated API limit because repository and release metadata are fetched per package. Add a token for a higher limit:

```env
PACKAGE_DOCTOR_GITHUB_TOKEN=ghp_your_token_here
```

If GitHub reports a rate limit during a scan, Package Doctor skips further uncached GitHub calls for the rest of that run, keeps using cached metadata, and includes a single warning in the final report. Keep caching enabled, use `--direct` for focused scans, or set `PACKAGE_DOCTOR_GITHUB_ENABLED=false` when GitHub metadata is not needed.

> **Tip — avoiding rate limits:** By default, Package Doctor scans your direct and dev dependencies only (no transitive packages). Laravel apps can have hundreds of transitive dependencies, so use `--all` deliberately when you need full tree coverage.

## Trend History

Each scan writes the latest summary to `storage/app/package-doctor-history.json` when Laravel's storage directory is available. If not, it writes `.package-doctor-history.json` in the project base path. The next scan uses that file to show the previous project score and a trend indicator beside the current score.

---

## Practical Use Cases

**Before a Laravel upgrade**
```bash
php artisan package:doctor --direct
```
Identify every direct dependency that hasn't declared support for your target Laravel version.

**Gate a production deployment**
```bash
php artisan package:doctor --no-dev --ci
```
Fail the deploy pipeline if any production package is Critical.

**Audit an inherited project**
```bash
php artisan package:doctor --all
```
Get a picture of every dependency — including transitive packages — in a project you're taking over.

**Weekly health check**
```bash
php artisan package:doctor --json --ci
```
Run on a schedule in CI. Store the JSON as an artifact or push it to a dashboard.

**Clean up your dependency list**
```bash
php artisan package:doctor --score-below=70
```
Focus only on packages that need attention without scrolling through the full list.

---

## What This Does Not Do

- **No auto-updates.** It never modifies `composer.json` or runs `composer update`.
- **No database required.** All analysis is done at the CLI level.
- **No dashboard in v1.** Output is console or JSON only.
- **Not a CVE database.** Security advisory data comes from Packagist's advisory feed, which relies on `composer audit`.
- **Not a replacement for reading changelogs.** The score is a signal, not an absolute guarantee. Always review changelogs before major upgrades.

---

## Roadmap

| Version | Highlights |
|---|---|
| **v1.0** | CLI command, full scoring, console + JSON output, CI mode, offline mode |
| **v1.1** | Configurable scoring rules, package ignore reasons, improved recommendations |
| **v1.2** | Suggested replacement packages, changelog URL detection, package trend report |
| **v2.0** | Laravel Pulse card, Filament dashboard, Slack/Telegram alerts, historical reports |

---

## Documentation

| Document | Description |
|---|---|
| [Installation](docs/installation.md) | Requirements, install steps, GitHub token |
| [Usage](docs/usage.md) | Every CLI flag with examples |
| [Configuration](docs/configuration.md) | Full `config/package-doctor.php` reference |
| [Checks Reference](docs/checks.md) | Every issue code and score impact |
| [Scoring](docs/scoring.md) | How the health score is calculated |
| [Output](docs/output.md) | Console and JSON output format |
| [CI/CD Integration](docs/ci.md) | GitHub Actions, GitLab CI, Bitbucket Pipelines |
| [Comparison](docs/comparison.md) | Package Doctor vs Composer, Dependabot, and other tools |
| [Architecture](docs/architecture.md) | Source layout and service flow |
| [FAQ](docs/faq.md) | Common questions |

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for development setup, testing conventions, and pull request guidelines.

## Security

See [SECURITY.md](SECURITY.md) for the vulnerability reporting policy.

## License

MIT — see [LICENSE.md](LICENSE.md).
