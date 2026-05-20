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
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Readers\ComposerJsonReader;
use Satheez\PackageDoctor\Readers\ComposerLockReader;
use Satheez\PackageDoctor\Scoring\PackageScoreCalculator;
use Satheez\PackageDoctor\Scoring\RecommendationGenerator;
use Satheez\PackageDoctor\Services\PackageDoctor;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Satheez\PackageDoctor\Support\Contracts\TickableComposerProcessContract;
use Satheez\PackageDoctor\Support\RepositoryUrlParser;
use Satheez\PackageDoctor\Support\VersionComparator;

function fixturesPath(string $relative = ''): string
{
    return __DIR__.'/../Fixtures'.($relative !== '' ? '/'.ltrim($relative, '/') : '');
}

function makeComposerProcess(array $outdatedData, array $auditData, array $licenseData): ComposerProcessContract
{
    return new class($outdatedData, $auditData, $licenseData) implements TickableComposerProcessContract
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

        public function runWithTicks(array $arguments, string $cwd, ?callable $tick = null): ProcessResult
        {
            if ($tick !== null) {
                $tick();
            }

            return $this->run($arguments, $cwd);
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

        public function runJsonWithTicks(array $arguments, string $cwd, ?callable $tick = null): array
        {
            if ($tick !== null) {
                $tick();
            }

            return $this->runJson($arguments, $cwd);
        }
    };
}

function makePackageDoctor(string $fixturePath, array $configOverrides = [], array $composerDataOverrides = []): PackageDoctor
{
    $outdatedData = json_decode(file_get_contents($fixturePath.'/composer-outdated.json'), true);
    $auditData = json_decode(file_get_contents($fixturePath.'/composer-audit.json'), true);
    $licenseData = json_decode(file_get_contents($fixturePath.'/composer-licenses.json'), true);

    if (array_key_exists('outdated', $composerDataOverrides)) {
        $outdatedData = $composerDataOverrides['outdated'];
    }

    if (array_key_exists('audit', $composerDataOverrides)) {
        $auditData = $composerDataOverrides['audit'];
    }

    if (array_key_exists('licenses', $composerDataOverrides)) {
        $licenseData = $composerDataOverrides['licenses'];
    }

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

function makeTemporaryComposerFixture(): string
{
    $sourcePath = fixturesPath('composer/healthy-project');
    $targetPath = sys_get_temp_dir().'/package-doctor-'.bin2hex(random_bytes(6));

    mkdir($targetPath);

    foreach (['composer.json', 'composer.lock', 'composer-outdated.json', 'composer-audit.json', 'composer-licenses.json'] as $file) {
        copy($sourcePath.'/'.$file, $targetPath.'/'.$file);
    }

    return $targetPath;
}

function removeTemporaryComposerFixture(string $fixturePath): void
{
    foreach (glob($fixturePath.'/{,.}*', GLOB_BRACE) ?: [] as $path) {
        $basename = basename($path);

        if ($basename === '.') {
            continue;
        }

        if ($basename === '..') {
            continue;
        }

        if (is_dir($path)) {
            rmdir($path);

            continue;
        }

        unlink($path);
    }

    rmdir($fixturePath);
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
        'ignored_count',
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

test('metadata collection emits heartbeat progress while composer commands run', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'));
    $events = [];

    $doctor->analyze(
        makeServiceScanOptions(),
        function (ScanProgress $progress) use (&$events): void {
            $events[] = $progress;
        },
    );

    $metadataEvents = array_values(array_filter(
        $events,
        fn (ScanProgress $progress): bool => $progress->stage === 'collecting_composer_metadata',
    ));

    expect($metadataEvents)->toHaveCount(4);
    expect($metadataEvents[0]->message)->toBe('Collecting Composer metadata');
    expect($metadataEvents[3]->message)->toBe('Collecting Composer metadata');
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
    expect($report->summary['ignored_count'])->toBe(0);
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
    expect($report->summary['ignored_count'])->toBe(0);
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

test('ignored packages remain visible with configured reason', function (): void {
    $doctor = makePackageDoctor(fixturesPath('composer/healthy-project'), [
        'ignore' => [
            'packages' => ['spatie/laravel-permission' => 'Covered by an internal fork.'],
            'issues' => [],
        ],
    ]);

    $report = $doctor->analyze(makeServiceScanOptions(['packages' => ['spatie/laravel-permission']]));

    expect($report->results)->toHaveCount(1);

    $result = $report->results[0];

    expect($result->package->name)->toBe('spatie/laravel-permission');
    expect($result->metadata)->toBeNull();
    expect($result->status)->toBe(PackageStatus::Ignored);
    expect($result->score)->toBe(100);
    expect($result->issues)->toBe([]);
    expect($result->recommendation->type)->toBe(RecommendationType::IgnoreConfigured);
    expect($result->recommendation->message)->toBe('Ignored: Covered by an internal fork.');
    expect($report->summary['ignored_count'])->toBe(1);
});

test('ignored packages are excluded from weighted project score', function (): void {
    $doctor = makePackageDoctor(
        fixturePath: fixturesPath('composer/healthy-project'),
        configOverrides: [
            'ignore' => [
                'packages' => ['spatie/laravel-permission' => 'Manually reviewed.'],
                'issues' => [],
            ],
        ],
        composerDataOverrides: [
            'outdated' => [
                'installed' => [
                    [
                        'name' => 'guzzlehttp/guzzle',
                        'version' => '7.8.0',
                        'latest' => '8.0.0',
                        'latest-status' => 'semver-major-update',
                        'description' => 'HTTP client',
                    ],
                ],
            ],
        ],
    );

    $report = $doctor->analyze(makeServiceScanOptions([
        'packages' => ['spatie/laravel-permission', 'guzzlehttp/guzzle'],
    ]));

    $guzzleResult = array_values(array_filter(
        $report->results,
        fn (PackageHealthResult $result): bool => $result->package->name === 'guzzlehttp/guzzle',
    ))[0];

    expect($guzzleResult->score)->toBeLessThan(100);
    expect($report->summary['ignored_count'])->toBe(1);
    expect($report->summary['project_score'])->toBe($guzzleResult->score);
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
        fn (PackageHealthResult $r): string => $r->package->name,
        array_filter($reportAll->results, fn (PackageHealthResult $r): bool => $r->package->dependencyType->value === 'transitive'),
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

test('history write failures add a warning without failing the scan', function (): void {
    $basePath = fixturesPath('composer/healthy-project');
    $historyPath = $basePath.'/.package-doctor-history.json';

    if (is_file($historyPath)) {
        unlink($historyPath);
    }

    if (! is_dir($historyPath)) {
        mkdir($historyPath);
    }

    try {
        $doctor = makePackageDoctor($basePath);
        $report = $doctor->analyze(makeServiceScanOptions());

        expect($report->results)->not->toBeEmpty();
        expect($report->warnings)->toContain("Could not write package history to {$historyPath}.");
    } finally {
        rmdir($historyPath);
    }
});

test('history previous score is loaded and latest summary is written', function (): void {
    $basePath = makeTemporaryComposerFixture();
    $historyPath = $basePath.'/.package-doctor-history.json';

    file_put_contents($historyPath, json_encode(['project_score' => 91], JSON_THROW_ON_ERROR));

    try {
        $doctor = makePackageDoctor($basePath);
        $report = $doctor->analyze(makeServiceScanOptions());
        $history = json_decode(file_get_contents($historyPath), true);

        expect($report->summary['previous_score'])->toBe(91);
        expect($history['project_score'])->toBe($report->summary['project_score']);
    } finally {
        removeTemporaryComposerFixture($basePath);
    }
});

test('invalid history is ignored with a warning', function (): void {
    $basePath = makeTemporaryComposerFixture();
    $historyPath = $basePath.'/.package-doctor-history.json';

    file_put_contents($historyPath, '{broken-json');

    try {
        $doctor = makePackageDoctor($basePath);
        $report = $doctor->analyze(makeServiceScanOptions());

        expect($report->summary)->not->toHaveKey('previous_score');
        expect($report->warnings)->toContain("Could not parse package history from {$historyPath}.");
    } finally {
        removeTemporaryComposerFixture($basePath);
    }
});
