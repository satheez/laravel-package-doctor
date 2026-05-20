# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- CSV report output via `--format=csv`.
- JSON and CSV file exports via `--output=path`.
- `--format=table|json|csv` output selection while keeping `--json` as a JSON shortcut.

## [1.2.0] - TBD

### Added
- Suggested replacement packages for abandoned dependencies when Composer or Packagist provides a replacement.
- Changelog URLs in major-upgrade recommendations when GitHub release or repository metadata is available.
- Project score trend reporting based on the previous scan summary.
- `changelog_url` and `replacement_package` fields in JSON package output.

### Changed
- Console output now shows ignored package counts and score trend indicators.

## [1.1.0] - TBD

### Added
- `--all` flag: opt-in to scanning the full dependency tree including transitive packages.
- Package ignore reasons: ignored packages remain visible in reports with an `Ignored` status and configured reason.

### Changed
- **BREAKING:** `include_transitive` config default changed from `true` to `false`. Scans now cover direct + dev dependencies only by default. Pass `--all` or set `PACKAGE_DOCTOR_INCLUDE_TRANSITIVE=true` to restore the previous behaviour.
- Ignored packages are excluded from the project score calculation.

## [1.0.0] - TBD

### Added
- Initial release.
- `package:doctor` Artisan command.
- Composer dependency health scoring (0–100).
- Status classification: Healthy, Watch, Risky, Critical.
- Security advisory detection via `composer audit`.
- Abandoned package detection.
- Upgrade type detection: patch, minor, major.
- Constraint-blocked upgrade detection.
- Laravel and PHP compatibility checks.
- Packagist metadata collection.
- GitHub repository metadata collection.
- Console table output.
- JSON output (`--json`).
- CI exit codes (`--ci`).
- Offline mode (`--offline`).
- Per-package recommendations.
- Cache support for external metadata.
- Config publishing via `package-doctor-config` tag.
