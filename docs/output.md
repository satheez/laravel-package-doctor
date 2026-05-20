# Output

## Console Output

Default output is a console table. Columns:

Interactive terminal runs show live progress before the table is rendered. Progress is disabled for JSON output, CSV output, CI mode, and non-interactive output.

| Column | Description |
|---|---|
| `Package` | Fully-qualified package name |
| `Current` | Installed version |
| `Latest` | Latest version available on Packagist |
| `Upgrade` | Upgrade type: `patch`, `minor`, `major`, `none`, `unknown` |
| `Score` | Health score (0–100), or `-` for ignored packages |
| `Status` | `Healthy`, `Watch`, `Risky`, `Critical`, or `Ignored` |
| `Recommendation` | Primary action recommendation |

Under each package row, detected issues are shown as indented lines:

```
  ↳ [abandoned]            Package is marked as abandoned on Packagist.
  ↳ [no_release_18_months] No release in over 18 months.
```

The summary above the table shows:

```
Laravel Package Doctor

PHP: 8.3.12  Laravel: 12.5.0

Summary
  Project score: 78 (↑ +3)/100
  Packages scanned: 42
  Healthy: 31  Watch: 6  Risky: 4  Critical: 1  Ignored: 0
```

If a previous scan summary exists, the project score includes a trend indicator such as `(↑ +3)`, `(↓ -2)`, or `(± 0)`. History is stored at `storage/app/package-doctor-history.json` when Laravel storage exists, otherwise `.package-doctor-history.json` in the project base path.

## JSON Output

Pass `--json` or `--format=json` to get a machine-readable report. Add `--output=package-health.json` to write the report to a file instead of stdout. Shape:

```json
{
  "project": {
    "php_version": "8.3.12",
    "laravel_version": "12.5.0"
  },
  "summary": {
    "project_score": 78,
    "total_packages": 42,
    "healthy_count": 31,
    "watch_count": 6,
    "risky_count": 4,
    "critical_count": 1,
    "ignored_count": 0,
    "previous_score": 75
  },
  "packages": [
    {
      "name": "vendor/legacy-helper",
      "current_version": "1.2.0",
      "latest_version": "2.0.0",
      "latest_allowed_version": "1.2.3",
      "upgrade_type": "major",
      "constraint_blocked": true,
      "dependency_type": "direct",
      "score": 42,
      "status": "risky",
      "issues": [
        {
          "code": "constraint_blocked",
          "severity": "risk",
          "message": "Latest version is blocked by your composer.json constraint.",
          "score_impact": -15
        },
        {
          "code": "major_upgrade_available",
          "severity": "risk",
          "message": "A major upgrade is available. Review changelog before updating.",
          "score_impact": -10
        }
      ],
      "recommendation": {
        "type": "review_before_upgrade",
        "message": "Review changelog and update constraint if compatible."
      },
      "changelog_url": "https://github.com/vendor/legacy-helper/releases",
      "replacement_package": null
    }
  ],
  "warnings": []
}
```

## CSV Output

Pass `--format=csv` to get a spreadsheet-friendly report with one row per package. Add `--output=package-health.csv` to write the report to a file instead of stdout.

CSV columns:

| Column | Description |
|---|---|
| `package` | Fully-qualified package name |
| `current_version` | Installed version |
| `latest_version` | Latest version available on Packagist, when known |
| `latest_allowed_version` | Latest version allowed by the current constraint, when known |
| `upgrade_type` | `patch`, `minor`, `major`, `none`, or `unknown` |
| `constraint_blocked` | `true` when the latest version is blocked by the current constraint |
| `dependency_type` | `direct`, `dev`, or `transitive` |
| `score` | Health score from 0 to 100 |
| `status` | `healthy`, `watch`, `risky`, `critical`, or `ignored` |
| `issue_count` | Number of detected issues for the package |
| `issue_codes` | Issue codes joined with `; ` |
| `issue_severities` | Issue severities joined with `; ` |
| `issue_score_impacts` | Score impacts joined with `; ` |
| `issue_messages` | Issue messages joined with `; ` |
| `recommendation_type` | Primary recommendation type |
| `recommendation_message` | Primary recommendation message |
| `changelog_url` | Changelog or releases URL, when available |
| `replacement_package` | Suggested replacement package, when available |

## Upgrade Types

| Type | Example | Meaning |
|---|---|---|
| `patch` | `1.2.3 → 1.2.4` | Bug fix, usually safe |
| `minor` | `1.2.3 → 1.3.0` | New features, usually safe |
| `major` | `1.2.3 → 2.0.0` | May contain breaking changes |
| `none` | `1.2.3 → 1.2.3` | Already at the latest version |
| `unknown` | — | Version could not be parsed |

## Recommendations

Each package receives one primary recommendation based on its highest-severity issue:

| Situation | Recommendation type |
|---|---|
| Security advisory found | `fix_security_issue` |
| Package abandoned | `replace_package` |
| Repository archived | `replace_package` |
| Laravel incompatible | `check_compatibility` |
| PHP incompatible | `check_compatibility` |
| Major upgrade available | `review_before_upgrade` |
| Patch/minor available | `safe_upgrade` |
| No recent release | `monitor_package` |
| Ignored by config | `ignore_configured` |
| Healthy package | `none` |

## Configuring Output

Control what appears in the console table via `config/package-doctor.php`:

```php
'output' => [
    'show_summary'               => true,
    'show_transitive_by_default' => true,
    'max_issues_per_package'     => 5,
    'truncate_package_names'     => false,
],
```
