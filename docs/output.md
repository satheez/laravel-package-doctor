# Output

## Console Output

Default output is a console table. Columns:

Interactive terminal runs show live progress before the table is rendered. Progress is disabled for JSON output, CI mode, and non-interactive output.

| Column | Description |
|---|---|
| `Package` | Fully-qualified package name |
| `Current` | Installed version |
| `Latest` | Latest version available on Packagist |
| `Upgrade` | Upgrade type: `patch`, `minor`, `major`, `none`, `unknown` |
| `Score` | Health score (0–100) |
| `Status` | `Healthy`, `Watch`, `Risky`, or `Critical` |
| `Recommendation` | Primary action recommendation (truncated to 40 chars) |

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
  Project score: 78/100
  Packages scanned: 42
  Healthy: 31  Watch: 6  Risky: 4  Critical: 1
```

## JSON Output

Pass `--json` to get a machine-readable report. Shape:

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
    "critical_count": 1
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
      }
    }
  ],
  "warnings": []
}
```

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
| Security advisory found | `update_immediately` |
| Package abandoned | `replace_package` |
| Repository archived | `replace_or_isolate` |
| Laravel incompatible | `replace_before_laravel_upgrade` |
| PHP incompatible | `upgrade_or_replace` |
| Major upgrade available | `review_before_upgrade` |
| Patch/minor available | `safe_upgrade_available` |
| No recent release | `monitor_for_activity` |
| Healthy package | `no_action_required` |

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
