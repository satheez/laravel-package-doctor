<?php

declare(strict_types=1);

it('loads the package-doctor config', function (): void {
    expect(config('package-doctor'))->toBeArray();
});

it('has the correct security_advisory deduction', function (): void {
    expect(config('package-doctor.score.deductions.security_advisory'))->toBe(-30);
});

it('has the correct abandoned deduction', function (): void {
    expect(config('package-doctor.score.deductions.abandoned'))->toBe(-30);
});

it('has the correct repository_archived deduction', function (): void {
    expect(config('package-doctor.score.deductions.repository_archived'))->toBe(-25);
});

it('has the correct status thresholds', function (): void {
    expect(config('package-doctor.score.status_thresholds.healthy'))->toBe(90);
    expect(config('package-doctor.score.status_thresholds.watch'))->toBe(70);
    expect(config('package-doctor.score.status_thresholds.risky'))->toBe(40);
    expect(config('package-doctor.score.status_thresholds.critical'))->toBe(0);
});

it('has safe licenses configured', function (): void {
    $safeLicenses = config('package-doctor.licenses.safe');

    expect($safeLicenses)->toContain('MIT');
    expect($safeLicenses)->toContain('Apache-2.0');
});

it('has empty ignore packages by default', function (): void {
    expect(config('package-doctor.ignore.packages'))->toBe([]);
});

it('has the default ci minimum score', function (): void {
    expect(config('package-doctor.ci.minimum_project_score'))->toBe(60);
});

it('has all required deduction keys', function (): void {
    $deductions = config('package-doctor.score.deductions');

    $expected = [
        'security_advisory',
        'abandoned',
        'repository_archived',
        'laravel_incompatible',
        'php_incompatible',
        'constraint_blocked',
        'no_release_18_months',
        'risky_license',
        'major_upgrade_available',
        'no_release_12_months',
        'low_downloads',
        'missing_documentation',
        'unknown_repository',
    ];

    foreach ($expected as $key) {
        expect($deductions)->toHaveKey($key);
        expect($deductions[$key])->toBeLessThanOrEqual(0);
    }
});
