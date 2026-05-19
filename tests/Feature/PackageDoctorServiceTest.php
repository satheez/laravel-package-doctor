<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Satheez\PackageDoctor\Collectors\ComposerAuditCollector;
use Satheez\PackageDoctor\Collectors\ComposerLicenseCollector;
use Satheez\PackageDoctor\Collectors\ComposerOutdatedCollector;
use Satheez\PackageDoctor\Collectors\GitHubCollector;
use Satheez\PackageDoctor\Collectors\PackagistCollector;
use Satheez\PackageDoctor\Compatibility\ComposerConstraintChecker;
use Satheez\PackageDoctor\Compatibility\LaravelCompatibilityChecker;
use Satheez\PackageDoctor\Compatibility\PhpCompatibilityChecker;
use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\ProcessResult;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\DTO\ScanProgress;
use Satheez\PackageDoctor\Readers\ComposerJsonReader;
use Satheez\PackageDoctor\Readers\ComposerLockReader;
use Satheez\PackageDoctor\Scoring\PackageScoreCalculator;
use Satheez\PackageDoctor\Scoring\RecommendationGenerator;
use Satheez\PackageDoctor\Services\PackageDoctor;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Satheez\PackageDoctor\Support\RepositoryUrlParser;
use Satheez\PackageDoctor\Support\VersionComparator;

function fixturesPath(string $relative = ''): string
{
    return __DIR__.'/../Fixtures'.($relative !== '' ? '/'.ltrim($relative, '/') : '');
}

function makeComposerProcess(array $outdatedData, array $auditData, array $licenseData): ComposerProcessContract
{
    return new class($outdatedData, $auditData, $licenseData) implements ComposerProcessContract
    {
        public function __construct(
            private readonly array $outdatedData,
            private readonly array $auditData,
            private readonly array $licenseData,
        ) {}

        public function run(array $arguments, string $cwd): ProcessResult
        {
            return new ProcessResult(
                command: $arguments,
                stdout: '',
                stderr: '',
                exitCode: 0,
                successful: true,
            );
        }

        public function runJson(array $arguments, string $cwd): array
        {
            if (in_array('outdated', $arguments, true)) {
                return $this->outdatedData;
            }

            if (in_array('licenses', $arguments, true)) {
                return $this->licenseData;
            }

            return $this->auditData;
        }
    };
}

function makePackageDoctor(string $fixturePath, array $configOverrides = []): PackageDoctor
{
    $outdatedData = json_decode(file_get_contents($fixturePath.'/composer-outdated.json'), true);
    $auditData = json_decode(file_get_contents($fixturePath.'/composer-audit.json'), true);
    $licenseData = json_decode(file_get_contents($fixturePath.'/composer-licenses.json'), true);

    $process = makeComposerProcess($outdatedData, $auditData, $licenseData);

    $cache = new CacheRepository(new ArrayStore);

    $mock = new MockHandler([new Response(200, [], '{}')]);
    $client = new Client(['handler' => HandlerStack::create($mock)]);

    $config = array_replace_recursive([
        'project' => [
            'base_path' => $fixturePath,
            'composer_json_path' => $fixturePath.'/composer.json',
            'composer_lock_path' => $fixturePath.'/composer.lock',
        ],
        'composer' => [
            'binary' => 'composer',
            'timeout_seconds' => 30,
            'working_directory' => $fixturePath,
            'commands' => [
                'outdated' => ['enabled' => true],
                'audit' => ['enabled' => true],
                'licenses' => ['enabled' => true],
            ],
        ],
        'metadata' => [
            'packagist' => ['enabled' => false],
            'github' => ['enabled' => false],
        ],
        'cache' => ['enabled' => false, 'ttl_seconds' => 3600],
        'scan' => [
            'include_direct' => true,
            'include_dev' => true,
            'include_transitive' => false,
            'exclude_packages' => [],
            'only_packages' => [],
        ],
        'score' => [
            'maximum' => 100,
            'minimum' => 0,
            'deductions' => [
                'security_advisory' => -30,
                'abandoned' => -30,
                'repository_archived' => -25,
                'laravel_incompatible' => -20,
                'php_incompatible' => -20,
                'constraint_blocked' => -15,
                'no_release_18_months' => -15,
                'risky_license' => -15,
                'major_upgrade_available' => -10,
                'no_release_12_months' => -8,
                'low_downloads' => -5,
                'missing_documentation' => -5,
                'unknown_repository' => -3,
            ],
            'status_thresholds' => [
                'healthy' => 90,
                'watch' => 70,
                'risky' => 40,
                'critical' => 0,
            ],
        ],
        'freshness' => [],
        'popularity' => ['minimum_downloads' => 1000],
        'licenses' => [
            'safe' => ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC'],
            'risky' => [],
            'unknown_license_is_risky' => false,
        ],
        'ignore' => ['packages' => [], 'issues' => []],
    ], $configOverrides);

    return new PackageDoctor(
        jsonReader: new ComposerJsonReader,
        lockReader: new ComposerLockReader,
        outdatedCollector: new ComposerOutdatedCollector($process, $config),
        auditCollector: new ComposerAuditCollector($process, $config),
        licenseCollector: new ComposerLicenseCollector($process, $config),
        packagistCollector: new PackagistCollector($client, $cache, $config),
        githubCollector: new GitHubCollector($client, $cache, $config),
        laravelChecker: new LaravelCompatibilityChecker,
        phpChecker: new PhpCompatibilityChecker,
        constraintChecker: new ComposerConstraintChecker,
        versionComparator: new VersionComparator,
        urlParser: new RepositoryUrlParser,
        calculator: new PackageScoreCalculator,
        recommendationGenerator: new RecommendationGenerator,
        config: $config,
    );
}

function makeServiceScanOptions(array $overrides = []): ScanOptions
{
    return new ScanOptions(
        json: $overrides['json'] ?? false,
        ci: $overrides['ci'] ?? false,
        direct: $overrides['direct'] ?? false,
        noDev: $overrides['noDev'] ?? false,
        noCache: $overrides['noCache'] ?? false,
        scoreBelow: $overrides['scoreBelow'] ?? null,
        majorOnly: $overrides['majorOnly'] ?? false,
        safeOnly: $overrides['safeOnly'] ?? false,
        packages: $overrides['packages'] ?? [],
        offline: $overrides['offline'] ?? false,
        all: $overrides['all'] ?? false,
    );
}

// Tests

test('analyze returns report with packages from lock file', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions());

    expect($report->results)->not->toBeEmpty();
    expect($report->project->phpVersion)->not->toBeEmpty();
    expect($report->summary['total_packages'])->toBeGreaterThan(0);
});

test('analyze builds correct summary structure', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions());

    expect($report->summary)->toHaveKeys([
        'total_packages',
        'project_score',
        'healthy_count',
        'watch_count',
        'risky_count',
        'critical_count',
        'safe_upgrade_count',
    ]);

    expect($report->summary['project_score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

test('analyze emits progress updates while scanning packages', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $events = [];

    $report = $doctor->analyze(
        makeServiceScanOptions(),
        function (ScanProgress $progress) use (&$events): void {
            $events[] = $progress;
        },
    );

    $stages = array_map(fn (ScanProgress $progress): string => $progress->stage, $events);
    $packageEvents = array_values(array_filter(
        $events,
        fn (ScanProgress $progress): bool => $progress->stage === 'scanning_packages',
    ));

    expect($stages)->toContain('reading_composer');
    expect($stages)->toContain('collecting_composer_metadata');
    expect($stages)->toContain('scanning_packages');
    expect($stages)->toContain('building_report');
    expect($packageEvents)->not->toBeEmpty();
    expect($packageEvents[0]->current)->toBe(1);
    expect($packageEvents[0]->total)->toBe(count($report->results));
});

test('offline mode skips packagist and github and returns results', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions(['offline' => true]));

    expect($report->results)->not->toBeEmpty();

    foreach ($report->results as $result) {
        expect($result->metadata)->toBeNull();
    }
});

test('missing composer.json returns empty report with warning', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'), [
        'project' => [
            'composer_json_path' => '/nonexistent/composer.json',
        ],
    ]);

    $report = $doctor->analyze(makeServiceScanOptions());

    expect($report->results)->toBeEmpty();
    expect($report->warnings)->not->toBeEmpty();
});

test('missing composer.lock returns empty results with warning', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'), [
        'project' => [
            'composer_lock_path' => '/nonexistent/composer.lock',
        ],
    ]);

    $report = $doctor->analyze(makeServiceScanOptions());

    expect($report->results)->toBeEmpty();
    expect($report->warnings)->not->toBeEmpty();
});

test('score-below filter removes packages above threshold', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $reportAll = $doctor->analyze(makeServiceScanOptions());
    $reportFiltered = $doctor->analyze(makeServiceScanOptions(['scoreBelow' => 50]));

    $highScorePackages = array_filter($reportAll->results, fn (PackageHealthResult $r): bool => $r->score >= 50);

    expect(count($reportFiltered->results))->toBeLessThanOrEqual(count($reportAll->results) - count($highScorePackages));
});

test('noDev filter excludes dev packages', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions(['noDev' => true]));

    foreach ($report->results as $result) {
        expect($result->package->dependencyType->value)->not->toBe('dev');
    }
});

test('direct filter only includes direct dependencies', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions(['direct' => true]));

    foreach ($report->results as $result) {
        expect($result->package->dependencyType->value)->toBe('direct');
    }
});

test('package filter limits to specified packages', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions(['packages' => ['spatie/laravel-permission']]));

    expect($report->results)->toHaveCount(1);
    expect($report->results[0]->package->name)->toBe('spatie/laravel-permission');
});

test('default scan excludes transitive packages', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $report = $doctor->analyze(makeServiceScanOptions());

    foreach ($report->results as $result) {
        expect($result->package->dependencyType->value)->not->toBe('transitive');
    }
});

test('all flag includes transitive packages regardless of config', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $reportDefault = $doctor->analyze(makeServiceScanOptions());
    $reportAll = $doctor->analyze(makeServiceScanOptions(['all' => true]));

    $transitiveNames = array_map(
        fn (\Satheez\PackageDoctor\DTO\PackageHealthResult $r): string => $r->package->name,
        array_filter($reportAll->results, fn (\Satheez\PackageDoctor\DTO\PackageHealthResult $r): bool => $r->package->dependencyType->value === 'transitive'),
    );

    expect($transitiveNames)->not->toBeEmpty();
    expect(count($reportAll->results))->toBeGreaterThan(count($reportDefault->results));
});

test('warnings are preserved in report', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'), [
        'project' => [
            'composer_lock_path' => '/nonexistent/composer.lock',
        ],
    ]);

    $report = $doctor->analyze(makeServiceScanOptions());

    expect($report->warnings)->toBeArray();
    expect(count($report->warnings))->toBeGreaterThan(0);
});
