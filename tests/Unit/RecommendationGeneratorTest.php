<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\IssueSeverity as IssueSeverityEnum;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Scoring\RecommendationGenerator;
use Satheez\PackageDoctor\Scoring\Rules\AbandonedPackageRule;
use Satheez\PackageDoctor\Scoring\Rules\ArchivedRepositoryRule;
use Satheez\PackageDoctor\Scoring\Rules\ConstraintBlockedRule;
use Satheez\PackageDoctor\Scoring\Rules\LaravelCompatibilityRule;
use Satheez\PackageDoctor\Scoring\Rules\NoRecentReleaseRule;
use Satheez\PackageDoctor\Scoring\Rules\PhpCompatibilityRule;
use Satheez\PackageDoctor\Scoring\Rules\SecurityAdvisoryRule;

function makePackage(string $name = 'vendor/package'): InstalledPackage
{
    return new InstalledPackage(
        name: $name,
        version: '1.0.0',
        dependencyType: DependencyType::Direct,
        constraint: '^1.0',
        sourceUrl: null,
        distUrl: null,
        requires: [],
        extra: [],
    );
}

function makeIssue(string $code): PackageIssue
{
    return new PackageIssue(
        code: $code,
        severity: IssueSeverityEnum::Warning,
        message: 'test',
        scoreImpact: -10,
    );
}

function makeGenerator(): RecommendationGenerator
{
    return new RecommendationGenerator;
}

test('Security advisory yields FixSecurityIssue', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(SecurityAdvisoryRule::CODE)],
        score: 70,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::FixSecurityIssue);
});

test('Abandoned package yields ReplacePackage without replacement', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(AbandonedPackageRule::CODE)],
        score: 70,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::ReplacePackage);
    expect($rec->message)->toContain('abandoned');
});

test('Abandoned package with replacement includes replacement name', function (): void {
    $meta = new PackageMetadata(
        name: 'vendor/package',
        description: null,
        latestVersion: null,
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

    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: $meta,
        issues: [makeIssue(AbandonedPackageRule::CODE)],
        score: 70,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::ReplacePackage);
    expect($rec->message)->toContain('vendor/new-package');
});

test('Archived repository yields ReplacePackage', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(ArchivedRepositoryRule::CODE)],
        score: 75,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::ReplacePackage);
});

test('Laravel incompatible yields CheckCompatibility', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(LaravelCompatibilityRule::CODE)],
        score: 80,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::CheckCompatibility);
});

test('PHP incompatible yields CheckCompatibility', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(PhpCompatibilityRule::CODE)],
        score: 80,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::CheckCompatibility);
});

test('Constraint blocked yields ReviewBeforeUpgrade', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(ConstraintBlockedRule::CODE)],
        score: 85,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::None,
        constraintBlocked: true,
    );

    expect($rec->type)->toBe(RecommendationType::ReviewBeforeUpgrade);
});

test('Major upgrade yields ReviewBeforeUpgrade', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [],
        score: 90,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::Major,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::ReviewBeforeUpgrade);
});

test('Major upgrade recommendation includes changelog URL when available', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [],
        score: 90,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::Major,
        constraintBlocked: false,
        changelogUrl: 'https://github.com/vendor/package/releases',
    );

    expect($rec->type)->toBe(RecommendationType::ReviewBeforeUpgrade);
    expect($rec->message)->toContain('https://github.com/vendor/package/releases');
});

test('Patch upgrade yields SafeUpgrade', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [],
        score: 100,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::Patch,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::SafeUpgrade);
});

test('Minor upgrade on Healthy package yields SafeUpgrade', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [],
        score: 92,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::Minor,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::SafeUpgrade);
});

test('Minor upgrade on Watch package yields UpdateWhenConvenient', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [],
        score: 75,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::Minor,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::UpdateWhenConvenient);
});

test('No release 18m yields MonitorPackage', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(NoRecentReleaseRule::CODE_18)],
        score: 85,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::MonitorPackage);
});

test('No issues yields None recommendation', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [],
        score: 100,
        status: PackageStatus::Healthy,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::None);
});

test('Security beats abandoned in priority', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [
            makeIssue(SecurityAdvisoryRule::CODE),
            makeIssue(AbandonedPackageRule::CODE),
        ],
        score: 40,
        status: PackageStatus::Risky,
        upgradeType: UpgradeType::None,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::FixSecurityIssue);
});

test('Abandoned beats major upgrade in priority', function (): void {
    $rec = makeGenerator()->generate(
        package: makePackage(),
        metadata: null,
        issues: [makeIssue(AbandonedPackageRule::CODE)],
        score: 70,
        status: PackageStatus::Watch,
        upgradeType: UpgradeType::Major,
        constraintBlocked: false,
    );

    expect($rec->type)->toBe(RecommendationType::ReplacePackage);
});
