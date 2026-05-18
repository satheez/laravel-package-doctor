<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\PackageRecommendation;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Output\CiExitCodeResolver;

function makeCiReport(PackageStatus $status, int $projectScore = 95): ProjectHealthReport
{
    $package = new InstalledPackage(
        name: 'vendor/package',
        version: '1.0.0',
        dependencyType: DependencyType::Direct,
        constraint: '^1.0',
        sourceUrl: null,
        distUrl: null,
        requires: [],
        extra: [],
    );

    $result = new PackageHealthResult(
        package: $package,
        metadata: null,
        score: $projectScore,
        status: $status,
        latestVersion: null,
        latestAllowedVersion: null,
        upgradeType: UpgradeType::None,
        isConstraintBlocked: false,
        issues: [],
        recommendation: new PackageRecommendation(
            type: RecommendationType::None,
            message: 'No action required.',
        ),
    );

    return new ProjectHealthReport(
        project: new ProjectInfo('8.2.0', '11.0.0', null, '/app'),
        results: [$result],
        summary: [
            'total_packages' => 1,
            'project_score' => $projectScore,
            'healthy_count' => $status === PackageStatus::Healthy ? 1 : 0,
            'watch_count' => $status === PackageStatus::Watch ? 1 : 0,
            'risky_count' => $status === PackageStatus::Risky ? 1 : 0,
            'critical_count' => $status === PackageStatus::Critical ? 1 : 0,
            'safe_upgrade_count' => 0,
        ],
        warnings: [],
    );
}

function makeCiScanOptions(): ScanOptions
{
    return new ScanOptions(
        json: false,
        ci: true,
        direct: false,
        noDev: false,
        noCache: false,
        scoreBelow: null,
        majorOnly: false,
        safeOnly: false,
        packages: [],
        offline: false,
    );
}

function makeCiConfig(array $overrides = []): array
{
    return array_replace([
        'fail_on_statuses' => ['critical'],
        'minimum_project_score' => 0,
    ], $overrides);
}

test('Healthy project returns exit code 0', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Healthy, 100),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(),
    );

    expect($code)->toBe(0);
});

test('Critical package returns exit code 2', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Critical, 40),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(['fail_on_statuses' => ['critical']]),
    );

    expect($code)->toBe(2);
});

test('Risky package returns exit code 1 when risky in fail_on_statuses', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Risky, 50),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(['fail_on_statuses' => ['critical', 'risky']]),
    );

    expect($code)->toBe(1);
});

test('Risky package returns 0 when risky not in fail_on_statuses', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Risky, 50),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(['fail_on_statuses' => ['critical']]),
    );

    expect($code)->toBe(0);
});

test('Low project score returns exit code 1', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Watch, 60),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(['minimum_project_score' => 70]),
    );

    expect($code)->toBe(1);
});

test('Project score at minimum threshold returns 0', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Watch, 70),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(['minimum_project_score' => 70]),
    );

    expect($code)->toBe(0);
});

test('Critical status takes priority over low score (returns 2)', function (): void {
    $resolver = new CiExitCodeResolver;
    $code = $resolver->resolve(
        report: makeCiReport(PackageStatus::Critical, 30),
        opts: makeCiScanOptions(),
        ciConfig: makeCiConfig(['fail_on_statuses' => ['critical'], 'minimum_project_score' => 80]),
    );

    expect($code)->toBe(2);
});
