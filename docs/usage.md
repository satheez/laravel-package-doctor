# Usage

## Basic Scan

```bash
php artisan package:doctor
```

Scans all packages in `composer.lock` and prints a console health report.

Interactive terminal runs show live progress while the scan is running. Progress is hidden for JSON output, CSV output, `--ci`, and non-interactive output so automation receives only the final report.

## All Options

| Option | Description |
|---|---|
| `--json` | Output results as JSON instead of a console table |
| `--format=table|json|csv` | Select the output format; defaults to `table` |
| `--output=path` | Write JSON or CSV output to a file instead of stdout |
| `--ci` | Enable CI mode — returns a non-zero exit code on failures |
| `--all` | Scan full dependency tree including transitive packages |
| `--direct` | Include only direct dependencies (`require`, no dev) |
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

The explicit format option is equivalent:

```bash
php artisan package:doctor --format=json
```

Pipe to `jq` for filtering:

```bash
php artisan package:doctor --json | jq '.packages[] | select(.status == "critical")'
```

Write the JSON report to a file:

```bash
php artisan package:doctor --format=json --output=package-health.json
```

### CSV report for spreadsheets

```bash
php artisan package:doctor --format=csv
```

Write the CSV report to a file:

```bash
php artisan package:doctor --format=csv --output=package-health.csv
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

Avoid `--no-cache` on large projects unless fresh metadata is required; cached GitHub responses help prevent rate-limit warnings.

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

# Export safe production upgrade candidates to CSV
php artisan package:doctor --no-dev --safe-only --format=csv --output=safe-upgrades.csv

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
