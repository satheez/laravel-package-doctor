<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Support\VersionComparator;

test('1.2.3 to 1.2.4 is patch', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('1.2.3', '1.2.4'))->toBe(UpgradeType::Patch);
});

test('1.2.3 to 1.3.0 is minor', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('1.2.3', '1.3.0'))->toBe(UpgradeType::Minor);
});

test('1.2.3 to 2.0.0 is major', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('1.2.3', '2.0.0'))->toBe(UpgradeType::Major);
});

test('1.2.3 to 1.2.3 is none', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('1.2.3', '1.2.3'))->toBe(UpgradeType::None);
});

test('v1.2.3 to v1.3.0 is minor (v prefix)', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('v1.2.3', 'v1.3.0'))->toBe(UpgradeType::Minor);
});

test('dev-main to 1.0.0 is unknown', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('dev-main', '1.0.0'))->toBe(UpgradeType::Unknown);
});

test('null current returns unknown', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType(null, '1.0.0'))->toBe(UpgradeType::Unknown);
});

test('null latest returns unknown', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('1.0.0', null))->toBe(UpgradeType::Unknown);
});

test('older latest returns none', function (): void {
    $comparator = new VersionComparator;
    expect($comparator->detectUpgradeType('2.0.0', '1.0.0'))->toBe(UpgradeType::None);
});
