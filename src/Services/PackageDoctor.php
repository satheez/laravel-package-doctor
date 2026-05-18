<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Services;

use Illuminate\Foundation\Application;
use Satheez\PackageDoctor\Collectors\ComposerAuditCollector;
use Satheez\PackageDoctor\Collectors\ComposerLicenseCollector;
use Satheez\PackageDoctor\Collectors\ComposerOutdatedCollector;
use Satheez\PackageDoctor\Collectors\GitHubCollector;
use Satheez\PackageDoctor\Collectors\PackagistCollector;
use Satheez\PackageDoctor\Compatibility\ComposerConstraintChecker;
use Satheez\PackageDoctor\Compatibility\LaravelCompatibilityChecker;
use Satheez\PackageDoctor\Compatibility\PhpCompatibilityChecker;
use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\DTO\ScanProgress;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Readers\ComposerJsonReader;
use Satheez\PackageDoctor\Readers\ComposerLockReader;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;
use Satheez\PackageDoctor\Scoring\PackageScoreCalculator;
use Satheez\PackageDoctor\Scoring\RecommendationGenerator;
use Satheez\PackageDoctor\Scoring\Rules\AbandonedPackageRule;
use Satheez\PackageDoctor\Scoring\Rules\ArchivedRepositoryRule;
use Satheez\PackageDoctor\Scoring\Rules\ConstraintBlockedRule;
use Satheez\PackageDoctor\Scoring\Rules\LaravelCompatibilityRule;
use Satheez\PackageDoctor\Scoring\Rules\LicenseRiskRule;
use Satheez\PackageDoctor\Scoring\Rules\LowDownloadsRule;
use Satheez\PackageDoctor\Scoring\Rules\MajorUpgradeAvailableRule;
use Satheez\PackageDoctor\Scoring\Rules\MissingDocumentationRule;
use Satheez\PackageDoctor\Scoring\Rules\NoRecentReleaseRule;
use Satheez\PackageDoctor\Scoring\Rules\PhpCompatibilityRule;
use Satheez\PackageDoctor\Scoring\Rules\ScoringRule;
use Satheez\PackageDoctor\Scoring\Rules\SecurityAdvisoryRule;
use Satheez\PackageDoctor\Scoring\Rules\UnknownRepositoryRule;
use Satheez\PackageDoctor\Support\RepositoryUrlParser;
use Satheez\PackageDoctor\Support\VersionComparator;
use Throwable;

final class PackageDoctor
{
    /** @var list<string> */
    private array $warnings = [];

    /** @var list<ScoringRule> */
    private readonly array $rules;

    public function __construct(
        private readonly ComposerJsonReader $jsonReader,
        private readonly ComposerLockReader $lockReader,
        private readonly ComposerOutdatedCollector $outdatedCollector,
        private readonly ComposerAuditCollector $auditCollector,
        private readonly ComposerLicenseCollector $licenseCollector,
        private readonly PackagistCollector $packagistCollector,
        private readonly GitHubCollector $githubCollector,
        private readonly LaravelCompatibilityChecker $laravelChecker,
        private readonly PhpCompatibilityChecker $phpChecker,
        private readonly ComposerConstraintChecker $constraintChecker,
        private readonly VersionComparator $versionComparator,
        private readonly RepositoryUrlParser $urlParser,
        private readonly PackageScoreCalculator $calculator,
        private readonly RecommendationGenerator $recommendationGenerator,
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {
        $this->rules = [
            new SecurityAdvisoryRule,
            new AbandonedPackageRule,
            new ArchivedRepositoryRule,
            new LaravelCompatibilityRule,
            new PhpCompatibilityRule,
            new ConstraintBlockedRule,
            new MajorUpgradeAvailableRule,
            new NoRecentReleaseRule,
            new LowDownloadsRule,
            new LicenseRiskRule,
            new MissingDocumentationRule,
            new UnknownRepositoryRule,
        ];
    }

    /** @param null|callable(ScanProgress): void $progress */
    public function analyze(ScanOptions $opts, ?callable $progress = null): ProjectHealthReport
    {
        $this->warnings = [];
        $this->githubCollector->reset();

        $basePath = $this->config['project']['base_path'] ?? base_path();
        $composerJsonPath = $this->config['project']['composer_json_path'] ?? $basePath.'/composer.json';
        $composerLockPath = $this->config['project']['composer_lock_path'] ?? $basePath.'/composer.lock';
        $workingDir = $this->config['composer']['working_directory'] ?? $basePath;

        $project = $this->buildProjectInfo($basePath);

        $this->reportProgress($progress, 'reading_composer', 'Reading composer files');

        try {
            $jsonData = $this->jsonReader->read($composerJsonPath);
        } catch (Throwable $e) {
            $this->warnings[] = 'Could not read composer.json: '.$e->getMessage();

            return $this->emptyReport($project);
        }

        $packages = $this->lockReader->read($composerLockPath, $jsonData['require'], $jsonData['require-dev']);

        foreach ($this->lockReader->warnings() as $warning) {
            $this->warnings[] = $warning;
        }

        $packages = $this->applyPreScanFilters($packages, $opts);

        $this->reportProgress($progress, 'collecting_composer_metadata', 'Collecting Composer metadata');

        $outdated = $opts->offline ? [] : $this->outdatedCollector->collect($opts, $workingDir);
        $audit = $opts->offline ? [] : $this->auditCollector->collect($workingDir);
        $licenses = $opts->offline ? [] : $this->licenseCollector->collect($workingDir);

        $results = [];
        $totalPackages = count($packages);

        foreach ($packages as $index => $package) {
            $this->reportProgress(
                $progress,
                'scanning_packages',
                "Scanning {$package->name}",
                $index + 1,
                $totalPackages,
            );

            $result = $this->analyzePackage(
                package: $package,
                project: $project,
                jsonData: $jsonData,
                outdated: $outdated,
                audit: $audit,
                licenses: $licenses,
                opts: $opts,
            );

            $results[] = $result;
        }

        $results = $this->applyPostScoringFilters($results, $opts);

        $this->reportProgress($progress, 'building_report', 'Building report');

        foreach ($this->githubCollector->warnings() as $warning) {
            $this->warnings[] = $warning;
        }

        $summary = $this->buildSummary($results);

        return new ProjectHealthReport(
            project: $project,
            results: $results,
            summary: $summary,
            warnings: $this->warnings,
        );
    }

    /** @param null|callable(ScanProgress): void $progress */
    private function reportProgress(?callable $progress, string $stage, string $message, ?int $current = null, ?int $total = null): void
    {
        if ($progress === null) {
            return;
        }

        $progress(new ScanProgress(
            stage: $stage,
            message: $message,
            current: $current,
            total: $total,
        ));
    }

    private function buildProjectInfo(string $basePath): ProjectInfo
    {
        $laravelVersion = null;
        if (class_exists(Application::class)) {
            $laravelVersion = Application::VERSION;
        }

        return new ProjectInfo(
            phpVersion: PHP_VERSION,
            laravelVersion: $laravelVersion,
            composerVersion: null,
            basePath: $basePath,
        );
    }

    /**
     * @param  list<InstalledPackage>  $packages
     * @return list<InstalledPackage>
     */
    private function applyPreScanFilters(array $packages, ScanOptions $opts): array
    {
        $ignoredPackages = array_keys($this->config['ignore']['packages'] ?? []);
        $excludePackages = $this->config['scan']['exclude_packages'] ?? [];
        $onlyPackages = $this->config['scan']['only_packages'] ?? [];

        return array_values(array_filter($packages, function (InstalledPackage $pkg) use (
            $opts, $ignoredPackages, $excludePackages, $onlyPackages
        ): bool {
            if (in_array($pkg->name, $ignoredPackages, true)) {
                return false;
            }

            if (in_array($pkg->name, $excludePackages, true)) {
                return false;
            }

            if ($onlyPackages !== [] && ! in_array($pkg->name, $onlyPackages, true)) {
                return false;
            }

            if ($opts->packages !== [] && ! in_array($pkg->name, $opts->packages, true)) {
                return false;
            }

            if ($opts->direct && $pkg->dependencyType !== DependencyType::Direct) {
                return false;
            }

            if ($opts->noDev && $pkg->dependencyType === DependencyType::Dev) {
                return false;
            }

            $includeDirect = $this->config['scan']['include_direct'] ?? true;
            $includeDev = $this->config['scan']['include_dev'] ?? true;
            $includeTransitive = $this->config['scan']['include_transitive'] ?? false;

            if ($pkg->dependencyType === DependencyType::Direct && ! $includeDirect) {
                return false;
            }

            if ($pkg->dependencyType === DependencyType::Dev && ! $includeDev) {
                return false;
            }

            return ! ($pkg->dependencyType === DependencyType::Transitive && ! $includeTransitive);
        }));
    }

    /**
     * @param  array{require: array<string, string>, 'require-dev': array<string, string>, php_constraint: string|null, laravel_constraint: string|null}  $jsonData
     * @param  array<string, array{current: string, latest: string, latest-status: string}>  $outdated
     * @param  array<string, array{advisories: list<array<string,mixed>>, abandoned: bool, replacement: string|null}>  $audit
     * @param  array<string, string|null>  $licenses
     */
    private function analyzePackage(
        InstalledPackage $package,
        ProjectInfo $project,
        array $jsonData,
        array $outdated,
        array $audit,
        array $licenses,
        ScanOptions $opts,
    ): PackageHealthResult {
        $outdatedInfo = $outdated[$package->name] ?? null;
        $auditInfo = $audit[$package->name] ?? null;
        $license = $licenses[$package->name] ?? null;

        $metadata = $this->fetchMetadata($package, $opts);

        $laravelResult = $this->laravelChecker->check(
            $project->laravelVersion ?? '0.0.0',
            $package->requires,
        );

        $phpResult = $this->phpChecker->check(
            $project->phpVersion,
            $package->requires,
        );

        $latestVersion = ($metadata instanceof PackageMetadata ? $metadata->latestVersion : null) ?? ($outdatedInfo['latest'] ?? null);
        $latestAllowedVersion = $metadata?->latestAllowedVersion;

        $upgradeType = $this->versionComparator->detectUpgradeType(
            $package->version,
            $latestVersion,
        );

        $isConstraintBlocked = $this->constraintChecker->isBlocked(
            $package->constraint,
            $latestVersion,
        );

        $context = new PackageHealthContext(
            package: $package,
            metadata: $metadata,
            outdatedInfo: $outdatedInfo,
            auditInfo: $auditInfo,
            license: $license,
            project: $project,
            options: $opts,
            config: $this->config,
            laravelCompatible: $laravelResult->compatible,
            laravelChecked: $laravelResult->checked,
            phpCompatible: $phpResult->compatible,
            phpChecked: $phpResult->checked,
            isConstraintBlocked: $isConstraintBlocked,
            upgradeType: $upgradeType,
        );

        $scored = $this->calculator->score($context, $this->rules);

        $recommendation = $this->recommendationGenerator->generate(
            package: $package,
            metadata: $metadata,
            issues: $scored['issues'],
            score: $scored['score'],
            status: $scored['status'],
            upgradeType: $upgradeType,
            constraintBlocked: $isConstraintBlocked,
        );

        return new PackageHealthResult(
            package: $package,
            metadata: $metadata,
            score: $scored['score'],
            status: $scored['status'],
            latestVersion: $latestVersion,
            latestAllowedVersion: $latestAllowedVersion,
            upgradeType: $upgradeType,
            isConstraintBlocked: $isConstraintBlocked,
            issues: $scored['issues'],
            recommendation: $recommendation,
        );
    }

    private function fetchMetadata(InstalledPackage $package, ScanOptions $opts): ?PackageMetadata
    {
        $packagistData = null;

        if (! $opts->offline && ($this->config['metadata']['packagist']['enabled'] ?? true)) {
            try {
                $packagistData = $this->packagistCollector->fetch($package->name, $opts->offline, $opts->noCache);
            } catch (Throwable $e) {
                $this->warnings[] = "Could not fetch Packagist data for {$package->name}: ".$e->getMessage();
            }
        }

        $githubOwner = null;
        $githubRepo = null;
        $githubData = null;
        $githubRelease = null;

        $repoUrl = $packagistData['repositoryUrl'] ?? null;

        if ($repoUrl !== null && $this->urlParser->isGitHub($repoUrl)) {
            $parsed = $this->urlParser->parse($repoUrl);
            if ($parsed !== null) {
                $githubOwner = $parsed['owner'];
                $githubRepo = $parsed['repo'];

                if (! $opts->offline && ($this->config['metadata']['github']['enabled'] ?? true)) {
                    try {
                        $githubData = $this->githubCollector->fetchRepository($githubOwner, $githubRepo, $opts->offline, $opts->noCache);
                        $githubRelease = $this->githubCollector->fetchLatestRelease($githubOwner, $githubRepo, $opts->offline, $opts->noCache);
                    } catch (Throwable $e) {
                        $this->warnings[] = "Could not fetch GitHub data for {$package->name}: ".$e->getMessage();
                    }
                }
            }
        }

        if ($packagistData === null && $githubData === null) {
            return null;
        }

        $latestReleaseAt = null;
        if (isset($githubRelease['published_at'])) {
            try {
                $latestReleaseAt = new \DateTimeImmutable($githubRelease['published_at']);
            } catch (Throwable) {
            }
        }

        $githubPushedAt = null;
        if (isset($githubData['pushed_at'])) {
            try {
                $githubPushedAt = new \DateTimeImmutable($githubData['pushed_at']);
            } catch (Throwable) {
            }
        }

        return new PackageMetadata(
            name: $package->name,
            description: $packagistData['description'] ?? $githubData['description'] ?? null,
            latestVersion: $packagistData['latestVersion'] ?? null,
            latestAllowedVersion: null,
            isAbandoned: $packagistData['isAbandoned'] ?? false,
            replacementPackage: $packagistData['replacementPackage'] ?? null,
            downloads: $packagistData['downloads'] ?? null,
            license: $packagistData['license'] ?? null,
            repositoryUrl: $packagistData['repositoryUrl'] ?? null,
            githubOwner: $githubOwner,
            githubRepo: $githubRepo,
            githubStars: $githubData['stargazers_count'] ?? null,
            githubOpenIssues: $githubData['open_issues_count'] ?? null,
            githubArchived: $githubData['archived'] ?? null,
            githubPushedAt: $githubPushedAt,
            latestReleaseAt: $latestReleaseAt,
            documentationUrl: $githubData['homepage'] ?? null,
        );
    }

    /**
     * @param  list<PackageHealthResult>  $results
     * @return list<PackageHealthResult>
     */
    private function applyPostScoringFilters(array $results, ScanOptions $opts): array
    {
        return array_values(array_filter($results, function (PackageHealthResult $result) use ($opts): bool {
            if ($opts->scoreBelow !== null && $result->score >= $opts->scoreBelow) {
                return false;
            }

            if ($opts->majorOnly && $result->upgradeType !== UpgradeType::Major) {
                return false;
            }

            if ($opts->safeOnly && $result->upgradeType !== UpgradeType::Patch) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<PackageHealthResult>  $results
     * @return array<string, mixed>
     */
    private function buildSummary(array $results): array
    {
        if ($results === []) {
            return [
                'total_packages' => 0,
                'project_score' => 100,
                'healthy_count' => 0,
                'watch_count' => 0,
                'risky_count' => 0,
                'critical_count' => 0,
                'safe_upgrade_count' => 0,
            ];
        }

        $weights = [
            DependencyType::Direct->value => 1.0,
            DependencyType::Dev->value => 0.7,
            DependencyType::Transitive->value => 0.4,
        ];

        $weightedSum = 0.0;
        $totalWeight = 0.0;
        $statusCounts = ['healthy_count' => 0, 'watch_count' => 0, 'risky_count' => 0, 'critical_count' => 0];
        $safeUpgradeCount = 0;

        foreach ($results as $result) {
            $weight = $weights[$result->package->dependencyType->value];
            $weightedSum += $result->score * $weight;
            $totalWeight += $weight;

            $statusCounts[$result->status->value.'_count'] += 1;

            if ($result->upgradeType === UpgradeType::Patch || $result->upgradeType === UpgradeType::Minor) {
                $safeUpgradeCount++;
            }
        }

        $projectScore = $totalWeight > 0 ? (int) round($weightedSum / $totalWeight) : 100;

        return [
            'total_packages' => count($results),
            'project_score' => $projectScore,
            ...$statusCounts,
            'safe_upgrade_count' => $safeUpgradeCount,
        ];
    }

    private function emptyReport(ProjectInfo $project): ProjectHealthReport
    {
        return new ProjectHealthReport(
            project: $project,
            results: [],
            summary: $this->buildSummary([]),
            warnings: $this->warnings,
        );
    }
}
