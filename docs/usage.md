# Usage

## Basic Scan

```bash
php artisan package:doctor
```

Scans all packages in `composer.lock` and prints a console health report.

## All Options

| Option | Description |
|---|---|
| `--json` | Output results as JSON instead of a console table |
| `--ci` | Enable CI mode — returns a non-zero exit code on failures |
| `--direct` | Include only direct dependencies (`require` + `require-dev`) |
| `--no-dev` | Exclude `require-dev` packages |
| `--no-cache` | Bypass the metadata cache for this run |
| `--offline` | Skip Packagist and GitHub API calls |
| `--score-below=N` | Show only packages with a health score below N |
| `--major-only` | Show only packages where a major upgrade is available |
| `--safe-only` | Show only packages with a safe patch or minor upgrade available |
| `--package=name` | Scan or display only the specified package(s) (repeatable) |

## Worked Examples

### Pre-upgrade audit

Before upgrading Laravel, check which direct dependencies have compatibility issues:

```bash
php artisan package:doctor --direct
```

Focus on packages with `laravel_incompatible` issues in the output.

### Spot risky packages fast

Find everything scoring below 70 (Watch or worse):

```bash
php artisan package:doctor --score-below=70
```

### Review only major breaking upgrades

```bash
php artisan package:doctor --major-only
```

### Check production packages only

Exclude dev dependencies for a production-focused report:

```bash
php artisan package:doctor --no-dev
```

### Scan specific packages

```bash
php artisan package:doctor --package=spatie/laravel-permission --package=barryvdh/laravel-debugbar
```

### JSON report for external tooling

```bash
php artisan package:doctor --json
```

Pipe to `jq` for filtering:

```bash
php artisan package:doctor --json | jq '.packages[] | select(.status == "critical")'
```

### CI gate — fail on critical packages

```bash
php artisan package:doctor --ci
```

### CI gate — full report, then gate

```bash
php artisan package:doctor --json --ci
```

### Offline scan (no external API calls)

```bash
php artisan package:doctor --offline
```

Offline mode uses only `composer.json`, `composer.lock`, `composer outdated`, `composer audit`, and `composer licenses`. Packagist and GitHub calls are skipped.

### Bypass metadata cache

```bash
php artisan package:doctor --no-cache
```

Useful when you have just published a new version and want fresh Packagist data.

### Safe upgrades to batch-apply

```bash
php artisan package:doctor --safe-only
```

Lists packages where a patch or minor upgrade is available with no critical issues blocking the update.

## Combining Options

Options can be combined freely:

```bash
# Show critical production packages as JSON with fresh data
php artisan package:doctor --no-dev --score-below=40 --no-cache --json

# CI gate on direct deps only, skip GitHub for speed
PACKAGE_DOCTOR_GITHUB_ENABLED=false php artisan package:doctor --direct --ci
```

## Exit Codes

Exit codes are only meaningful when `--ci` is passed.

| Code | Meaning |
|---:|---|
| `0` | All packages pass configured thresholds |
| `1` | One or more packages are Risky, or project score is below `ci.minimum_project_score` |
| `2` | One or more packages are Critical |
| `3` | Runtime error (misconfiguration, Composer unavailable, etc.) |

See [CI/CD Integration](ci.md) for full CI setup examples.
