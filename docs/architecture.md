# Architecture

## Source Layout

```
src/
├── Console/
│   └── PackageDoctorCommand.php         # Artisan command — parses options, delegates to services
├── Services/
│   └── PackageDoctor.php                # Main orchestrator — coordinates all collectors and checkers
├── Readers/
│   ├── ComposerJsonReader.php           # Reads and parses composer.json
│   └── ComposerLockReader.php           # Reads and parses composer.lock
├── Collectors/
│   ├── ComposerOutdatedCollector.php    # Runs `composer outdated` and parses JSON output
│   ├── ComposerAuditCollector.php       # Runs `composer audit` and parses security advisories
│   ├── ComposerLicenseCollector.php     # Runs `composer licenses` and parses license data
│   ├── PackagistCollector.php           # Fetches Packagist metadata (downloads, abandoned, etc.)
│   └── GitHubCollector.php             # Fetches GitHub metadata (archived, last push, releases)
├── Compatibility/
│   ├── LaravelCompatibilityChecker.php  # Checks package Laravel version constraints
│   ├── PhpCompatibilityChecker.php      # Checks package PHP version constraints
│   └── ComposerConstraintChecker.php    # Detects constraint-blocked upgrades
├── Scoring/
│   ├── PackageScoreCalculator.php       # Applies deduction rules to produce a 0–100 score
│   └── Rules/                           # One class per scoring rule (SRP)
├── Output/
│   ├── ConsoleReportRenderer.php        # Renders the console table output
│   ├── JsonReportRenderer.php           # Serialises the report to JSON
│   └── CiExitCodeResolver.php          # Maps report findings to CI exit codes
├── DTO/                                 # Immutable readonly data transfer objects
├── Enums/                               # PackageStatus, UpgradeType, IssueSeverity, etc.
├── Support/                             # ComposerProcess, VersionComparator, RepositoryUrlParser
├── Exceptions/                          # Domain exceptions
└── PackageDoctorServiceProvider.php     # Auto-discovery, service binding, config publishing
```

## Service Flow

```
PackageDoctorCommand
  └── PackageDoctor::analyze($opts)
        ├── ComposerJsonReader     → reads composer.json (project + installed packages)
        ├── ComposerLockReader     → reads composer.lock (locked versions)
        ├── ComposerOutdatedCollector → runs composer outdated → OutdatedData[]
        ├── ComposerAuditCollector    → runs composer audit   → SecurityAdvisory[]
        ├── ComposerLicenseCollector  → runs composer licenses → LicenseData[]
        ├── PackagistCollector     → HTTP → Packagist API → PackagistMeta[]
        ├── GitHubCollector        → HTTP → GitHub API    → GitHubMeta[]
        ├── LaravelCompatibilityChecker → checks illuminate/* constraints
        ├── PhpCompatibilityChecker     → checks php constraint
        ├── ComposerConstraintChecker   → detects constraint-blocked upgrades
        ├── PackageScoreCalculator      → applies scoring rules → score + issues
        ├── RecommendationGenerator     → maps top issue → recommendation
        └── ProjectHealthReport         → assembled DTO
              └── ConsoleReportRenderer / JsonReportRenderer / CiExitCodeResolver
```

## Design Principles

- **CLI-first**: No database, no dashboard, no HTTP server required.
- **Laravel-native**: Uses the service container, config system, and cache; registers as a standard service provider.
- **Dependency-light**: Runtime dependencies are limited to Guzzle (HTTP), Composer Semver (version parsing), and Symfony Process (Composer subprocess execution).
- **Immutable DTOs**: All data crossing service boundaries is carried in `readonly` PHP 8.2+ DTOs.
- **Single-responsibility scoring rules**: Each issue code has its own class in `Scoring/Rules/`, making it easy to add, modify, or disable individual checks.
- **Testable without side effects**: Collectors and HTTP clients are injected via the constructor; tests use fixtures and HTTP mocks.
- **Offline-capable**: The `--offline` flag bypasses all collectors that make external network calls; the remaining checks use only local Composer data.

## Key Contracts

| Interface | Implementation | Purpose |
|---|---|---|
| `ComposerProcessContract` | `ComposerProcess` | Runs Composer subprocesses; swap in tests |

## Data Flow (simplified)

```
composer.json + composer.lock
  → package list with installed versions
  → enriched with outdated / audit / license data (Composer subprocesses)
  → enriched with Packagist + GitHub metadata (HTTP, cached)
  → compatibility checks applied
  → scored: 100 + deductions = clamped score
  → status: Healthy / Watch / Risky / Critical
  → recommendation: one action per package
  → ProjectHealthReport (console | JSON | CI exit code)
```
