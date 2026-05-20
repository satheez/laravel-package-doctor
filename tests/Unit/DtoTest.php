<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\CompatibilityResult;
use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\DTO\PackageRecommendation;
use Satheez\PackageDoctor\DTO\ProcessResult;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;

test('ProjectInfo holds values', function (): void {
    $info = new ProjectInfo('8.2.0', '11.0.0', '2.7.0', '/var/www');

    expect($info->phpVersion)->toBe('8.2.0');
    expect($info->laravelVersion)->toBe('11.0.0');
    expect($info->composerVersion)->toBe('2.7.0');
    expect($info->basePath)->toBe('/var/www');
});

test('InstalledPackage holds values', function (): void {
    $pkg = new InstalledPackage(
        name: 'spatie/laravel-permission',
        version: '6.0.0',
        dependencyType: DependencyType::Direct,
        constraint: '^6.0',
        sourceUrl: null,
        distUrl: null,
        requires: ['illuminate/support' => '^11.0'],
        extra: [],
    );

    expect($pkg->name)->toBe('spatie/laravel-permission');
    expect($pkg->dependencyType)->toBe(DependencyType::Direct);
});

test('PackageMetadata holds values', function (): void {
    $meta = new PackageMetadata(
        name: 'spatie/laravel-permission',
        description: 'A package',
        latestVersion: '6.1.0',
        latestAllowedVersion: '6.0.5',
        isAbandoned: false,
        replacementPackage: null,
        downloads: 5000000,
        license: 'MIT',
        repositoryUrl: 'https://github.com/spatie/laravel-permission',
        githubOwner: 'spatie',
        githubRepo: 'laravel-permission',
        githubStars: 12000,
        githubOpenIssues: 50,
        githubArchived: false,
        githubPushedAt: null,
        latestReleaseAt: null,
        documentationUrl: null,
        changelogUrl: 'https://github.com/spatie/laravel-permission/releases',
    );

    expect($meta->name)->toBe('spatie/laravel-permission');
    expect($meta->isAbandoned)->toBeFalse();
    expect($meta->githubStars)->toBe(12000);
    expect($meta->changelogUrl)->toBe('https://github.com/spatie/laravel-permission/releases');
});

test('PackageIssue holds values', function (): void {
    $issue = new PackageIssue(
        code: 'security_advisory',
        severity: IssueSeverity::Critical,
        message: 'Security advisory found.',
        scoreImpact: -30,
    );

    expect($issue->code)->toBe('security_advisory');
    expect($issue->scoreImpact)->toBe(-30);
});

test('PackageRecommendation holds values', function (): void {
    $rec = new PackageRecommendation(
        type: RecommendationType::FixSecurityIssue,
        message: 'Update immediately.',
    );

    expect($rec->type)->toBe(RecommendationType::FixSecurityIssue);
});

test('ProcessResult holds values', function (): void {
    $result = new ProcessResult(
        command: ['composer', 'outdated', '--format=json'],
        stdout: '{}',
        stderr: '',
        exitCode: 0,
        successful: true,
    );

    expect($result->successful)->toBeTrue();
    expect($result->exitCode)->toBe(0);
});

test('CompatibilityResult holds values', function (): void {
    $result = new CompatibilityResult(
        compatible: false,
        checked: true,
        reason: 'Requires illuminate/support ^10.0',
        constraint: '^10.0',
    );

    expect($result->compatible)->toBeFalse();
    expect($result->checked)->toBeTrue();
});

test('ScanOptions holds values', function (): void {
    $opts = new ScanOptions(
        json: false,
        ci: true,
        direct: true,
        noDev: false,
        noCache: false,
        scoreBelow: 70,
        majorOnly: false,
        safeOnly: false,
        packages: ['vendor/pkg'],
        offline: false,
        all: true,
    );

    expect($opts->ci)->toBeTrue();
    expect($opts->direct)->toBeTrue();
    expect($opts->scoreBelow)->toBe(70);
    expect($opts->packages)->toBe(['vendor/pkg']);
    expect($opts->all)->toBeTrue();
});

test('PackageHealthResult holds values', function (): void {
    $pkg = new InstalledPackage('acme/lib', '1.0.0', DependencyType::Direct, '^1.0', null, null, [], []);
    $rec = new PackageRecommendation(RecommendationType::SafeUpgrade, 'Update available.');

    $result = new PackageHealthResult(
        package: $pkg,
        metadata: null,
        score: 85,
        status: PackageStatus::Watch,
        latestVersion: '1.1.0',
        latestAllowedVersion: '1.1.0',
        upgradeType: UpgradeType::Minor,
        isConstraintBlocked: false,
        issues: [],
        recommendation: $rec,
        changelogUrl: 'https://github.com/acme/lib/releases',
        replacementPackage: 'acme/new-lib',
    );

    expect($result->score)->toBe(85);
    expect($result->status)->toBe(PackageStatus::Watch);
    expect($result->upgradeType)->toBe(UpgradeType::Minor);
    expect($result->changelogUrl)->toBe('https://github.com/acme/lib/releases');
    expect($result->replacementPackage)->toBe('acme/new-lib');
});

test('ProjectHealthReport holds values', function (): void {
    $info = new ProjectInfo('8.2.0', '11.0.0', null, '/var/www');
    $report = new ProjectHealthReport($info, [], ['project_score' => 95], []);

    expect($report->summary['project_score'])->toBe(95);
    expect($report->warnings)->toBe([]);
});
