# Scoring

## How the Score Is Calculated

Every package starts with a score of **100**.

Each detected issue subtracts points according to the `score.deductions` configuration. Multiple issues stack. The final score is clamped to `[0, 100]`.

```
score = clamp(100 + sum(deductions), 0, 100)
```

## Score Deductions

| Issue | Deduction |
|---|---:|
| `security_advisory` | −30 |
| `abandoned` | −30 |
| `repository_archived` | −25 |
| `laravel_incompatible` | −20 |
| `php_incompatible` | −20 |
| `constraint_blocked` | −15 |
| `no_release_18_months` | −15 |
| `risky_license` | −15 |
| `major_upgrade_available` | −10 |
| `no_release_12_months` | −8 |
| `low_downloads` | −5 |
| `missing_documentation` | −5 |
| `unknown_repository` | −3 |

Note: `no_release_12_months` and `no_release_18_months` are mutually exclusive — only the higher deduction applies.

## Status Thresholds

| Score Range | Status | Meaning |
|---|---|---|
| 90–100 | **Healthy** | Package appears safe and well-maintained |
| 70–89 | **Watch** | Mostly fine but has minor concerns worth monitoring |
| 40–69 | **Risky** | Needs review before future upgrades or new dependencies |
| 0–39 | **Critical** | Should be updated, replaced, or investigated soon |

`Ignored` is a configured status, not a score threshold. Packages listed in `ignore.packages` are shown as ignored with a score placeholder in console output.

## Project Score

The overall project score is a weighted mean of scanned package scores:

| Dependency type | Weight |
|---|---:|
| Direct | `1.0` |
| Dev | `0.7` |
| Transitive | `0.4` |

Ignored packages are excluded from this calculation. The result appears in the console summary and the JSON output under `summary.project_score`.

## Freshness Thresholds

Staleness deductions are triggered by the time since the last release:

| Threshold | Deduction triggered |
|---|---|
| 12 months without a release | `no_release_12_months` (−8) |
| 18 months without a release | `no_release_18_months` (−15) |
| 36 months without a release | (escalated to `no_release_18_months` threshold in output) |

Configure these in `freshness`:

```php
'freshness' => [
    'watch_after_months_without_release'    => 12,
    'risky_after_months_without_release'    => 18,
    'critical_after_months_without_release' => 36,
],
```

## Popularity Thresholds

```php
'popularity' => [
    'low_downloads_threshold'      => 1000,
    'very_low_downloads_threshold' => 100,
    'low_stars_threshold'          => 10,
],
```

`low_downloads` deduction fires when Packagist monthly downloads fall below `low_downloads_threshold`.

## Customizing Deductions

Override any deduction in the published config:

```php
'score' => [
    'deductions' => [
        'security_advisory' => -30,  // must be ≤ 0
        'abandoned'         => -30,
        // ... rest of defaults
    ],
],
```

All deduction values must be zero or negative. The config validator will throw if a positive value is provided.

## Customizing Status Thresholds

```php
'score' => [
    'status_thresholds' => [
        'healthy'  => 90,  // must be strictly descending
        'watch'    => 70,
        'risky'    => 40,
        'critical' => 0,
    ],
],
```

The config validator enforces `healthy > watch > risky > critical`.
