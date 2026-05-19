# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - TBD

### Added
- `--all` flag: opt-in to scanning the full dependency tree including transitive packages.

### Changed
- **BREAKING:** `include_transitive` config default changed from `true` to `false`. Scans now cover direct + dev dependencies only by default. Pass `--all` or set `PACKAGE_DOCTOR_INCLUDE_TRANSITIVE=true` to restore the previous behaviour.

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
