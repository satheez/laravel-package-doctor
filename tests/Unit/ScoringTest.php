<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;
use Satheez\PackageDoctor\Scoring\PackageScoreCalculator;
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
use Satheez\PackageDoctor\Scoring\Rules\SecurityAdvisoryRule;
use Satheez\PackageDoctor\Scoring\Rules\UnknownRepositoryRule;

function makeContext(array $overrides = []): PackageHealthContext
{
    $package = new InstalledPackage(
        name: $overrides['packageName'] ?? 'vendor/package',
        version: '1.0.0',
        dependencyType: DependencyType::Direct,
        constraint: '^1.0',
        sourceUrl: null,
        distUrl: null,
        requires: [],
        extra: [],
    );

    $project = new ProjectInfo(
        phpVersion: '8.2.0',
        laravelVersion: '11.0.0',
        composerVersion: '2.7.0',
        basePath: '/app',
    );

    $options = new ScanOptions(
        json: false,
        ci: false,
        direct: false,
        noDev: false,
        noCache: false,
        scoreBelow: null,
        majorOnly: false,
        safeOnly: false,
        packages: [],
        offline: false,
    );

    $config = [
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
        'popularity' => ['minimum_downloads' => 1000],
        'licenses' => [
            'safe' => ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC'],
            'risky' => ['GPL-3.0'],
            'unknown_license_is_risky' => false,
        ],
        'ignore' => ['packages' => [], 'issues' => []],
    ];

    return new PackageHealthContext(
        package: $package,
        metadata: $overrides['metadata'] ?? null,
        outdatedInfo: $overrides['outdatedInfo'] ?? null,
        auditInfo: $overrides['auditInfo'] ?? null,
        license: $overrides['license'] ?? null,
        project: $project,
        options: $options,
        config: array_replace_recursive($config, $overrides['config'] ?? []),
        laravelCompatible: $overrides['laravelCompatible'] ?? true,
        laravelChecked: $overrides['laravelChecked'] ?? false,
        phpCompatible: $overrides['phpCompatible'] ?? true,
        phpChecked: $overrides['phpChecked'] ?? false,
        isConstraintBlocked: $overrides['isConstraintBlocked'] ?? false,
        upgradeType: $overrides['upgradeType'] ?? UpgradeType::None,
    );
}

// SecurityAdvisoryRule

test('SecurityAdvisoryRule fires when advisories exist', function (): void {
    $ctx = makeContext(['auditInfo' => ['advisories' => ['CVE-001' => []], 'abandoned' => false]]);
    $issue = (new SecurityAdvisoryRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('security_advisory');
    expect($issue->scoreImpact)->toBe(-30);
});

test('SecurityAdvisoryRule does not fire when no advisories', function (): void {
    $ctx = makeContext(['auditInfo' => ['advisories' => [], 'abandoned' => false]]);
    $issue = (new SecurityAdvisoryRule)->evaluate($ctx);

    expect($issue)->toBeNull();
});

test('SecurityAdvisoryRule respects ignore config', function (): void {
    $ctx = makeContext([
        'auditInfo' => ['advisories' => ['CVE-001' => []]],
        'config' => ['ignore' => ['issues' => ['vendor/package' => ['security_advisory' => 'test']]]],
    ]);
    $issue = (new SecurityAdvisoryRule)->evaluate($ctx);

    expect($issue)->toBeNull();
});

// AbandonedPackageRule

test('AbandonedPackageRule fires when audit marks abandoned', function (): void {
    $ctx = makeContext(['auditInfo' => ['advisories' => [], 'abandoned' => true, 'replacement' => null]]);
    $issue = (new AbandonedPackageRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('abandoned');
    expect($issue->scoreImpact)->toBe(-30);
});

test('AbandonedPackageRule fires when metadata marks abandoned', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: '2.0.0',
        latestAllowedVersion: null,
        isAbandoned: true,
        replacementPackage: 'vendor/new-package',
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new AbandonedPackageRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->message)->toContain('vendor/new-package');
});

// ArchivedRepositoryRule

test('ArchivedRepositoryRule fires when GitHub repo is archived', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: 'https://github.com/vendor/package',
        githubOwner: 'vendor',
        githubRepo: 'package',
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: true,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new ArchivedRepositoryRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('repository_archived');
    expect($issue->scoreImpact)->toBe(-25);
});

test('ArchivedRepositoryRule does not fire when not archived', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: false,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);

    expect((new ArchivedRepositoryRule)->evaluate($ctx))->toBeNull();
});

// LaravelCompatibilityRule

test('LaravelCompatibilityRule fires when incompatible and checked', function (): void {
    $ctx = makeContext(['laravelCompatible' => false, 'laravelChecked' => true]);
    $issue = (new LaravelCompatibilityRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('laravel_incompatible');
    expect($issue->scoreImpact)->toBe(-20);
});

test('LaravelCompatibilityRule does not fire when not checked', function (): void {
    $ctx = makeContext(['laravelCompatible' => false, 'laravelChecked' => false]);

    expect((new LaravelCompatibilityRule)->evaluate($ctx))->toBeNull();
});

// PhpCompatibilityRule

test('PhpCompatibilityRule fires when PHP incompatible and checked', function (): void {
    $ctx = makeContext(['phpCompatible' => false, 'phpChecked' => true]);
    $issue = (new PhpCompatibilityRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('php_incompatible');
    expect($issue->scoreImpact)->toBe(-20);
});

// ConstraintBlockedRule

test('ConstraintBlockedRule fires when constraint is blocked', function (): void {
    $ctx = makeContext(['isConstraintBlocked' => true]);
    $issue = (new ConstraintBlockedRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('constraint_blocked');
    expect($issue->scoreImpact)->toBe(-15);
});

test('ConstraintBlockedRule does not fire when not blocked', function (): void {
    $ctx = makeContext(['isConstraintBlocked' => false]);

    expect((new ConstraintBlockedRule)->evaluate($ctx))->toBeNull();
});

// MajorUpgradeAvailableRule

test('MajorUpgradeAvailableRule fires when major upgrade type', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: '2.0.0',
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['upgradeType' => UpgradeType::Major, 'metadata' => $meta]);
    $issue = (new MajorUpgradeAvailableRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('major_upgrade_available');
    expect($issue->scoreImpact)->toBe(-10);
});

test('MajorUpgradeAvailableRule does not fire for minor upgrade', function (): void {
    $ctx = makeContext(['upgradeType' => UpgradeType::Minor]);

    expect((new MajorUpgradeAvailableRule)->evaluate($ctx))->toBeNull();
});

// NoRecentReleaseRule

test('NoRecentReleaseRule fires 18m code when last release over 18 months ago', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: new DateTimeImmutable('-20 months'),
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new NoRecentReleaseRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('no_release_18_months');
    expect($issue->scoreImpact)->toBe(-15);
});

test('NoRecentReleaseRule fires 12m code when last release between 12-18 months ago', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: new DateTimeImmutable('-14 months'),
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new NoRecentReleaseRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('no_release_12_months');
    expect($issue->scoreImpact)->toBe(-8);
});

test('NoRecentReleaseRule does not fire for recent release', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: new DateTimeImmutable('-3 months'),
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);

    expect((new NoRecentReleaseRule)->evaluate($ctx))->toBeNull();
});

// LowDownloadsRule

test('LowDownloadsRule fires when downloads below threshold', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: 500,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new LowDownloadsRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('low_downloads');
    expect($issue->scoreImpact)->toBe(-5);
});

// LicenseRiskRule

test('LicenseRiskRule fires for risky license', function (): void {
    $ctx = makeContext(['license' => 'GPL-3.0']);
    $issue = (new LicenseRiskRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('risky_license');
    expect($issue->scoreImpact)->toBe(-15);
});

test('LicenseRiskRule does not fire for safe license', function (): void {
    $ctx = makeContext(['license' => 'MIT']);

    expect((new LicenseRiskRule)->evaluate($ctx))->toBeNull();
});

// MissingDocumentationRule

test('MissingDocumentationRule fires when no documentation URL', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new MissingDocumentationRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('missing_documentation');
    expect($issue->scoreImpact)->toBe(-5);
});

// UnknownRepositoryRule

test('UnknownRepositoryRule fires when no repository URL', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
        latestAllowedVersion: null,
        isAbandoned: false,
        replacementPackage: null,
        downloads: null,
        license: null,
        repositoryUrl: null,
        githubOwner: null,
        githubRepo: null,
        githubStars: null,
        githubOpenIssues: null,
        githubArchived: null,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
    );
    $ctx = makeContext(['metadata' => $meta]);
    $issue = (new UnknownRepositoryRule)->evaluate($ctx);

    expect($issue)->not->toBeNull();
    expect($issue->code)->toBe('unknown_repository');
    expect($issue->scoreImpact)->toBe(-3);
});

// PackageScoreCalculator

test('Score starts at maximum and subtracts issues', function (): void {
    $ctx = makeContext(['auditInfo' => ['advisories' => ['CVE-001' => []], 'abandoned' => false]]);
    $calc = new PackageScoreCalculator;
    $result = $calc->score($ctx, [new SecurityAdvisoryRule]);

    expect($result['score'])->toBe(70); // 100 - 30
    expect($result['issues'])->toHaveCount(1);
    expect($result['status'])->toBe(PackageStatus::Watch);
});

test('Multiple issues stack correctly', function (): void {
    $ctx = makeContext([
        'auditInfo' => ['advisories' => ['CVE-001' => []], 'abandoned' => true, 'replacement' => null],
        'phpCompatible' => false,
        'phpChecked' => true,
    ]);
    $calc = new PackageScoreCalculator;
    $result = $calc->score($ctx, [
        new SecurityAdvisoryRule,
        new AbandonedPackageRule,
        new PhpCompatibilityRule,
    ]);

    expect($result['score'])->toBe(20); // 100 - 30 - 30 - 20
    expect($result['issues'])->toHaveCount(3);
    expect($result['status'])->toBe(PackageStatus::Critical);
});

test('Score clamps at minimum (0)', function (): void {
    $ctx = makeContext([
        'auditInfo' => ['advisories' => ['CVE-001' => [], 'CVE-002' => [], 'CVE-003' => [], 'CVE-004' => []], 'abandoned' => true, 'replacement' => null],
        'phpCompatible' => false,
        'phpChecked' => true,
        'laravelCompatible' => false,
        'laravelChecked' => true,
    ]);
    $calc = new PackageScoreCalculator;
    $result = $calc->score($ctx, [
        new SecurityAdvisoryRule,
        new AbandonedPackageRule,
        new PhpCompatibilityRule,
        new LaravelCompatibilityRule,
    ]);

    expect($result['score'])->toBe(0);
    expect($result['status'])->toBe(PackageStatus::Critical);
});

test('Healthy package with no issues scores 100', function (): void {
    $ctx = makeContext();
    $calc = new PackageScoreCalculator;
    $result = $calc->score($ctx, [new SecurityAdvisoryRule, new AbandonedPackageRule]);

    expect($result['score'])->toBe(100);
    expect($result['issues'])->toHaveCount(0);
    expect($result['status'])->toBe(PackageStatus::Healthy);
});
