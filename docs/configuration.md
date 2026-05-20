# Configuration

Publish the config file to customize Package Doctor's behavior:

```bash
php artisan vendor:publish --tag=package-doctor-config
```

This creates `config/package-doctor.php`. Every value has a sensible default so publishing is optional.

---

## `enabled`

```php
'enabled' => env('PACKAGE_DOCTOR_ENABLED', true),
```

Master switch. Set to `false` to disable the package without uninstalling it.

---

## `project`

```php
'project' => [
    'base_path'          => base_path(),
    'composer_json_path' => base_path('composer.json'),
    'composer_lock_path' => base_path('composer.lock'),
],
```

Paths to your project's Composer files. Override if your project layout is non-standard.

---

## `scan`

```php
'scan' => [
    'include_direct'     => true,
    'include_dev'        => env('PACKAGE_DOCTOR_INCLUDE_DEV', true),
    'include_transitive' => env('PACKAGE_DOCTOR_INCLUDE_TRANSITIVE', false),
    'exclude_packages'   => [],
    'only_packages'      => [],
],
```

| Key | Default | Description |
|---|---|---|
| `include_direct` | `true` | Include direct dependencies |
| `include_dev` | `true` | Include `require-dev` packages |
| `include_transitive` | `false` | Include transitive (indirect) dependencies. Use `--all` CLI flag or set `PACKAGE_DOCTOR_INCLUDE_TRANSITIVE=true` to enable. |
| `exclude_packages` | `[]` | Package names to exclude from the scan |
| `only_packages` | `[]` | If set, scan only these packages |

---

## `composer`

```php
'composer' => [
    'binary'            => env('PACKAGE_DOCTOR_COMPOSER_BINARY', 'composer'),
    'timeout_seconds'   => (int) env('PACKAGE_DOCTOR_COMPOSER_TIMEOUT', 120),
    'working_directory' => base_path(),
    'commands' => [
        'outdated' => ['enabled' => true,  'arguments' => ['outdated', '--format=json', '--locked']],
        'audit'    => ['enabled' => true,  'arguments' => ['audit',    '--format=json', '--locked']],
        'licenses' => ['enabled' => true,  'arguments' => ['licenses', '--format=json']],
    ],
],
```

Override `binary` if Composer is installed at a custom path. Increase `timeout_seconds` for large projects.

---

## `metadata`

```php
'metadata' => [
    'packagist' => [
        'enabled'        => env('PACKAGE_DOCTOR_PACKAGIST_ENABLED', true),
        'timeout_seconds' => (int) env('PACKAGE_DOCTOR_PACKAGIST_TIMEOUT', 10),
    ],
    'github' => [
        'enabled'              => env('PACKAGE_DOCTOR_GITHUB_ENABLED', true),
        'token'                => env('PACKAGE_DOCTOR_GITHUB_TOKEN'),
        'timeout_seconds'      => (int) env('PACKAGE_DOCTOR_GITHUB_TIMEOUT', 10),
        'fetch_latest_release' => true,
        'fetch_readme_presence' => true,
    ],
],
```

Both sources are independent. Disable GitHub if you don't need archive detection or last-release dates.

For large dependency graphs, set `PACKAGE_DOCTOR_GITHUB_TOKEN` and leave caching enabled. When GitHub rate limits a scan, Package Doctor skips further uncached GitHub calls for the rest of that run and reports one warning instead of repeating the same warning for every package.

---

## `cache`

```php
'cache' => [
    'enabled'     => env('PACKAGE_DOCTOR_CACHE_ENABLED', true),
    'ttl_seconds' => (int) env('PACKAGE_DOCTOR_CACHE_TTL', 3600),
    'store'       => env('PACKAGE_DOCTOR_CACHE_STORE'),
    'prefix'      => 'package-doctor',
],
```

External Packagist and GitHub responses are cached to avoid rate limits on repeated runs. Set `store` to any Laravel cache driver name (e.g., `redis`). Defaults to the application default cache store.

---

## `score`

```php
'score' => [
    'minimum' => 0,
    'maximum' => 100,

    'deductions' => [
        'security_advisory'    => -30,
        'abandoned'            => -30,
        'repository_archived'  => -25,
        'laravel_incompatible' => -20,
        'php_incompatible'     => -20,
        'constraint_blocked'   => -15,
        'no_release_18_months' => -15,
        'risky_license'        => -15,
        'major_upgrade_available' => -10,
        'no_release_12_months' => -8,
        'low_downloads'        => -5,
        'missing_documentation' => -5,
        'unknown_repository'   => -3,
    ],

    'status_thresholds' => [
        'healthy'  => 90,
        'watch'    => 70,
        'risky'    => 40,
        'critical' => 0,
    ],
],
```

Deductions are applied and the score is clamped to `[0, 100]`. Status thresholds must be strictly descending.

---

## `freshness`

```php
'freshness' => [
    'watch_after_months_without_release'    => 12,
    'risky_after_months_without_release'    => 18,
    'critical_after_months_without_release' => 36,
],
```

Controls when a package is flagged as stale. Adjust these for your team's risk tolerance.

---

## `popularity`

```php
'popularity' => [
    'low_downloads_threshold'      => 1000,
    'very_low_downloads_threshold' => 100,
    'low_stars_threshold'          => 10,
],
```

Packagist download counts below `low_downloads_threshold` trigger the `low_downloads` deduction.

---

## `licenses`

```php
'licenses' => [
    'safe'   => ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC'],
    'watch'  => ['LGPL-2.1-only', 'LGPL-2.1-or-later', 'LGPL-3.0-only', 'LGPL-3.0-or-later'],
    'risky'  => ['GPL-2.0-only', 'GPL-2.0-or-later', 'GPL-3.0-only', 'GPL-3.0-or-later', 'AGPL-3.0-only', 'AGPL-3.0-or-later'],
    'unknown_license_is_risky' => false,
],
```

Risky licenses trigger the `risky_license` score deduction.

---

## `ci`

```php
'ci' => [
    'minimum_project_score'                       => (int) env('PACKAGE_DOCTOR_CI_MIN_SCORE', 60),
    'fail_on_statuses'                            => ['critical'],
    'fail_on_security_advisories'                  => true,
    'fail_on_abandoned_direct_dependencies'        => true,
    'fail_on_laravel_incompatible_direct_dependencies' => true,
],
```

Active only when `--ci` flag is passed. Valid statuses for `fail_on_statuses`: `healthy`, `watch`, `risky`, `critical`.

---

## `output`

```php
'output' => [
    'default_format'             => env('PACKAGE_DOCTOR_OUTPUT', 'table'),
    'show_summary'               => true,
    'show_top_issues'            => true,
    'show_recommendations'       => true,
    'show_transitive_by_default' => true,
    'max_issues_per_package'     => 5,
    'truncate_package_names'     => false,
],
```

---

## `ignore`

```php
'ignore' => [
    'packages' => [],
    // 'vendor/package' => 'reason'

    'issues' => [],
    // 'vendor/package' => ['code' => 'reason']
],
```

Silence known false positives. Use `ignore.packages` to skip a package entirely, or `ignore.issues` to suppress specific issue codes for a specific package.

Example:

```php
'ignore' => [
    'packages' => [
        'some/internal-package' => 'Private fork, not on Packagist.',
    ],
    'issues' => [
        'legacy/auth' => ['missing_documentation' => 'Internal legacy lib, no public docs needed.'],
    ],
],
```
