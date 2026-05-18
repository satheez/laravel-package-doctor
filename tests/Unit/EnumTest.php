<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;

test('DependencyType has correct values', function (): void {
    expect(DependencyType::Direct->value)->toBe('direct');
    expect(DependencyType::Dev->value)->toBe('dev');
    expect(DependencyType::Transitive->value)->toBe('transitive');
});

test('UpgradeType has correct values', function (): void {
    expect(UpgradeType::None->value)->toBe('none');
    expect(UpgradeType::Patch->value)->toBe('patch');
    expect(UpgradeType::Minor->value)->toBe('minor');
    expect(UpgradeType::Major->value)->toBe('major');
    expect(UpgradeType::Unknown->value)->toBe('unknown');
});

test('PackageStatus has correct values', function (): void {
    expect(PackageStatus::Healthy->value)->toBe('healthy');
    expect(PackageStatus::Watch->value)->toBe('watch');
    expect(PackageStatus::Risky->value)->toBe('risky');
    expect(PackageStatus::Critical->value)->toBe('critical');
});

test('PackageStatus::fromScore maps correctly', function (): void {
    $thresholds = ['healthy' => 90, 'watch' => 70, 'risky' => 40, 'critical' => 0];

    expect(PackageStatus::fromScore(100, $thresholds))->toBe(PackageStatus::Healthy);
    expect(PackageStatus::fromScore(90, $thresholds))->toBe(PackageStatus::Healthy);
    expect(PackageStatus::fromScore(89, $thresholds))->toBe(PackageStatus::Watch);
    expect(PackageStatus::fromScore(70, $thresholds))->toBe(PackageStatus::Watch);
    expect(PackageStatus::fromScore(69, $thresholds))->toBe(PackageStatus::Risky);
    expect(PackageStatus::fromScore(40, $thresholds))->toBe(PackageStatus::Risky);
    expect(PackageStatus::fromScore(39, $thresholds))->toBe(PackageStatus::Critical);
    expect(PackageStatus::fromScore(0, $thresholds))->toBe(PackageStatus::Critical);
});

test('IssueSeverity has correct values', function (): void {
    expect(IssueSeverity::Info->value)->toBe('info');
    expect(IssueSeverity::Warning->value)->toBe('warning');
    expect(IssueSeverity::Risk->value)->toBe('risk');
    expect(IssueSeverity::Critical->value)->toBe('critical');
});

test('RecommendationType has correct values', function (): void {
    expect(RecommendationType::None->value)->toBe('none');
    expect(RecommendationType::SafeUpgrade->value)->toBe('safe_upgrade');
    expect(RecommendationType::UpdateWhenConvenient->value)->toBe('update_when_convenient');
    expect(RecommendationType::ReviewBeforeUpgrade->value)->toBe('review_before_upgrade');
    expect(RecommendationType::ReplacePackage->value)->toBe('replace_package');
    expect(RecommendationType::FixSecurityIssue->value)->toBe('fix_security_issue');
    expect(RecommendationType::CheckCompatibility->value)->toBe('check_compatibility');
    expect(RecommendationType::MonitorPackage->value)->toBe('monitor_package');
    expect(RecommendationType::IgnoreConfigured->value)->toBe('ignore_configured');
});
