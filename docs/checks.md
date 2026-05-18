# Checks Reference

Laravel Package Doctor evaluates every scanned package against the following checks. Each check that fires reduces the package health score and contributes to its overall status.

## Issue Codes

| Code | Score Impact | Data Source | Trigger |
|---|---:|---|---|
| `security_advisory` | −30 | `composer audit` | Package has a known security vulnerability |
| `abandoned` | −30 | `composer audit` / Packagist | Package is marked abandoned on Packagist |
| `repository_archived` | −25 | GitHub API | GitHub repository is archived |
| `laravel_incompatible` | −20 | Package `require` constraints | Package does not declare support for the current Laravel version |
| `php_incompatible` | −20 | Package `require` constraints | Package does not support the current PHP version |
| `constraint_blocked` | −15 | `composer outdated` | A newer version exists but your `composer.json` constraint prevents installing it |
| `no_release_18_months` | −15 | Packagist / GitHub | No release in the past 18 months |
| `risky_license` | −15 | `composer licenses` | Package uses a copyleft or otherwise risky license (GPL, AGPL) |
| `major_upgrade_available` | −10 | `composer outdated` | The latest available version is a major semver bump |
| `no_release_12_months` | −8 | Packagist / GitHub | No release in the past 12 months (but within 18) |
| `low_downloads` | −5 | Packagist | Packagist download count is below the configured threshold |
| `missing_documentation` | −5 | GitHub API | Package has no README detected in its GitHub repository |
| `unknown_repository` | −3 | Packagist | Package has no detectable source repository |

## Issue Severity Levels

| Severity | Typical impact | Example |
|---|---|---|
| `critical` | High score loss, action required | `security_advisory`, `abandoned` |
| `risk` | Moderate score loss, investigate | `constraint_blocked`, `laravel_incompatible` |
| `warning` | Minor score loss, monitor | `no_release_12_months`, `low_downloads` |
| `info` | Minimal impact, informational | `unknown_repository`, `missing_documentation` |

## Issue JSON Shape

Each issue in the JSON output has this structure:

```json
{
  "code": "abandoned",
  "severity": "critical",
  "message": "Package is marked as abandoned on Packagist.",
  "score_impact": -30
}
```

## Detailed Check Descriptions

### `security_advisory`

Package has one or more active CVEs reported by Packagist's security advisories database, surfaced via `composer audit`. This is the highest-priority issue. Update or replace the package immediately.

### `abandoned`

Packagist marks the package as abandoned (i.e., the maintainer has set an `abandoned` flag on the Packagist page). Often paired with a suggested replacement. Do not use in new projects; plan migration for existing usage.

### `repository_archived`

The GitHub repository for this package has been archived by its owner. This is a strong signal that the package is no longer maintained. Treat it similarly to an abandoned package.

### `laravel_incompatible`

The installed version of the package does not declare support for the detected Laravel version in its `require` constraints (`illuminate/support`, `illuminate/console`, `laravel/framework`, etc.). Common when preparing a Laravel major-version upgrade.

### `php_incompatible`

The installed version of the package does not support the running PHP version via its `php` constraint. Must be resolved before upgrading PHP.

### `constraint_blocked`

A newer version of the package is available on Packagist, but your `composer.json` version constraint prevents Composer from installing it. The report shows both the latest-allowed and the latest-available version so you can make an informed constraint update.

### `no_release_18_months`

The package has not published a release in 18 months or more. This does not necessarily mean the package is broken, but it warrants monitoring for signs of abandonment.

### `risky_license`

The package uses a copyleft license (GPL-2.0, GPL-3.0, AGPL-3.0, or a variant). Depending on your project's distribution model, this may have legal implications. Review with your legal team if distributing commercially.

### `major_upgrade_available`

The latest available version is a major semver bump from the installed version. Major upgrades may contain breaking changes. Review the package changelog before updating.

### `no_release_12_months`

No release has been published in 12–17 months. Monitor this package for further signs of inactivity.

### `low_downloads`

Packagist shows fewer downloads than the configured `popularity.low_downloads_threshold` (default: 1000). Very low usage may indicate a less battle-tested package.

### `missing_documentation`

The GitHub repository does not appear to have a README or documentation. This is a minor signal about package maturity.

### `unknown_repository`

Packagist metadata does not include a resolvable source repository URL. Score impact is minimal; mainly informational.
