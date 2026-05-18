# CI/CD Integration

Pass `--ci` to enable CI mode. Without this flag, the command always exits with code `0`.

## Exit Codes

| Code | Meaning |
|---:|---|
| `0` | All checks passed configured thresholds |
| `1` | One or more Risky packages found, or project score is below `ci.minimum_project_score` |
| `2` | One or more Critical packages found |
| `3` | Runtime error (Composer unavailable, misconfiguration, unexpected exception) |

## GitHub Actions

### On every pull request

```yaml
name: Package Health

on:
  pull_request:

jobs:
  package-health:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Check dependency health
        run: php artisan package:doctor --ci
```

### Weekly scheduled audit (JSON artifact)

```yaml
name: Weekly Package Audit

on:
  schedule:
    - cron: '0 8 * * 1'   # Mondays at 08:00 UTC

jobs:
  audit:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          tools: composer

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run audit and save report
        run: php artisan package:doctor --json --ci > package-health.json
        env:
          PACKAGE_DOCTOR_GITHUB_TOKEN: ${{ secrets.PACKAGE_DOCTOR_GITHUB_TOKEN }}

      - name: Upload health report
        uses: actions/upload-artifact@v4
        with:
          name: package-health-report
          path: package-health.json
```

### Production-only gate (no dev packages)

```yaml
- name: Check production dependency health
  run: php artisan package:doctor --no-dev --ci
```

## GitLab CI

```yaml
package-health:
  stage: test
  image: php:8.3
  before_script:
    - apt-get update && apt-get install -y git unzip
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-interaction --prefer-dist
  script:
    - php artisan package:doctor --ci
  only:
    - merge_requests
    - main
```

## Bitbucket Pipelines

```yaml
pipelines:
  pull-requests:
    '**':
      - step:
          name: Package Health Check
          image: php:8.3
          script:
            - apt-get update && apt-get install -y git unzip
            - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
            - composer install --no-interaction --prefer-dist
            - php artisan package:doctor --ci
```

## CI Configuration Tuning

Adjust CI sensitivity in `config/package-doctor.php`:

```php
'ci' => [
    // Fail if the mean project score drops below this value
    'minimum_project_score' => 60,

    // Statuses that trigger a non-zero exit. Add 'risky' to make the gate stricter.
    'fail_on_statuses' => ['critical'],

    // Semantic fail conditions for direct dependencies
    'fail_on_security_advisories'                      => true,
    'fail_on_abandoned_direct_dependencies'            => true,
    'fail_on_laravel_incompatible_direct_dependencies' => true,
],
```

## Restricting to Direct Dependencies in CI

For a tighter gate focused only on your explicitly required packages:

```bash
php artisan package:doctor --direct --ci
```

## Speed Tips

GitHub API calls are the slowest part of a scan. Disable if you don't need archive/release-date checks:

```bash
PACKAGE_DOCTOR_GITHUB_ENABLED=false php artisan package:doctor --ci
```

Or use the cache. It defaults to 3600 seconds, so repeated CI runs within an hour reuse cached Packagist and GitHub responses.

For larger projects, set `PACKAGE_DOCTOR_GITHUB_TOKEN` in CI. If GitHub reports a rate limit, Package Doctor skips further uncached GitHub calls for that run and leaves the rest of the scan intact.
