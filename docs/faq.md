# FAQ

## General

### Does Package Doctor modify my `composer.json` or `composer.lock`?

No. It is a read-only analysis tool. It runs Composer subprocesses (`composer outdated`, `composer audit`, `composer licenses`) and reads your existing lock and JSON files, but never writes to them.

### Does it require a database?

No. There is no database dependency in v1. All data comes from local Composer files, Composer subprocess output, and optional external API calls (Packagist, GitHub).

### Do I need a GitHub token?

No. GitHub metadata collection is optional. Without a token, GitHub API requests use the unauthenticated rate limit (60 requests per hour per IP). For CI environments with many concurrent runs, add a token:

```env
PACKAGE_DOCTOR_GITHUB_TOKEN=ghp_your_token_here
```

If GitHub reports a rate limit, Package Doctor stops making further uncached GitHub calls for that run, keeps cached GitHub metadata available, and prints one warning in the final report.

Or disable GitHub collection entirely:

```env
PACKAGE_DOCTOR_GITHUB_ENABLED=false
```

### Can I use it without internet access?

Yes. Use `--offline` mode:

```bash
php artisan package:doctor --offline
```

This skips all Packagist and GitHub API calls. Checks limited to Composer-local data (outdated, audit, licenses, lock file) still run.

### Is it safe to install as `--dev`?

Yes. It is designed and intended to be installed as a development dependency. It has no effect on your production application — it registers an Artisan command only.

---

## Accuracy

### Why does a package show as `laravel_incompatible` when it works fine?

Laravel compatibility is checked by inspecting the package's declared `require` constraints for `illuminate/*` packages. If the installed version was published before the current Laravel major version existed, its constraints won't include it — even if the code is compatible in practice.

This is a known limitation of constraint-based compatibility checks. You can suppress the issue for a specific package in config:

```php
'ignore' => [
    'issues' => [
        'vendor/package' => ['laravel_incompatible' => 'Tested, works fine on Laravel 12.'],
    ],
],
```

### Why is a package scored as risky when I know it's actively maintained?

Scoring uses the signals available from Packagist and GitHub metadata. If the maintainer releases infrequently but the package is stable, or if GitHub release pages are sparse, the score may be lower than expected. You can adjust freshness thresholds in config or use the ignore config to suppress specific codes.

### The score for package X doesn't match what I see on Packagist.

Package Doctor caches external metadata for 1 hour (default TTL). Run with `--no-cache` to force fresh data:

```bash
php artisan package:doctor --no-cache
```

---

## CI

### Why does `--ci` return exit code `1` even though no packages are Critical?

Exit code `1` is returned when any package is Risky **and** `risky` is listed in `ci.fail_on_statuses`, OR when the project score falls below `ci.minimum_project_score`. Check your config:

```php
'ci' => [
    'minimum_project_score' => 60,        // ← might be the cause
    'fail_on_statuses'      => ['critical'],
],
```

### Can I fail the build only on security advisories?

Yes. The `ci.fail_on_security_advisories` flag controls this independently of status thresholds. You can set `fail_on_statuses` to `[]` and rely solely on the semantic fail conditions:

```php
'ci' => [
    'fail_on_statuses'           => [],
    'fail_on_security_advisories' => true,
    'fail_on_abandoned_direct_dependencies' => true,
],
```

---

## False Positives

### How do I silence a false positive?

Use the `ignore` config:

```php
'ignore' => [
    'packages' => [
        'internal/private-fork' => 'Private fork, not on Packagist — metadata unavailable.',
    ],
    'issues' => [
        'vendor/known-stable' => ['no_release_12_months' => 'Intentionally release-free; maintained via commits.'],
    ],
],
```

### A transitive dependency is flagged Critical. Should I worry?

Transitive dependency issues are real but lower-priority than direct dependency issues. If the transitive package has a security advisory, you should update the direct dependency that pulls it in, or add the transitive package as a direct dependency at a safe version. Use `--direct` to focus reports on packages you own.

---

## Performance

### The scan is slow. How do I speed it up?

1. **Disable GitHub collection** (the slowest part): `PACKAGE_DOCTOR_GITHUB_ENABLED=false`
2. **Enable caching** (default is on): Repeated runs within an hour reuse cached Packagist and GitHub responses.
3. **Scan direct deps only**: `--direct` skips transitive packages.
4. **Use offline mode**: `--offline` skips all external HTTP.

### Can I cache between CI runs?

Cache is stored using Laravel's configured cache driver. In CI, use a Redis or file-based cache driver and persist the cache directory between runs using your CI platform's caching mechanism.
