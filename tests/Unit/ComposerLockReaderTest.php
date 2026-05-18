<?php

declare(strict_types=1);

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Readers\ComposerLockReader;

$fixtureBase = __DIR__.'/../Fixtures/composer/healthy-project';

test('reads direct dependencies from composer.lock', function () use ($fixtureBase): void {
    $rootRequire = ['spatie/laravel-permission' => '^6.0', 'guzzlehttp/guzzle' => '^7.8'];
    $rootRequireDev = ['pestphp/pest' => '^3.0', 'laravel/pint' => '^1.0'];

    $reader = new ComposerLockReader;
    $packages = $reader->read($fixtureBase.'/composer.lock', $rootRequire, $rootRequireDev);

    $direct = array_filter($packages, fn (InstalledPackage $p): bool => $p->dependencyType === DependencyType::Direct);
    $names = array_map(fn (InstalledPackage $p): string => $p->name, $direct);

    expect($names)->toContain('spatie/laravel-permission');
    expect($names)->toContain('guzzlehttp/guzzle');
});

test('reads dev dependencies from composer.lock', function () use ($fixtureBase): void {
    $rootRequire = ['spatie/laravel-permission' => '^6.0', 'guzzlehttp/guzzle' => '^7.8'];
    $rootRequireDev = ['pestphp/pest' => '^3.0', 'laravel/pint' => '^1.0'];

    $reader = new ComposerLockReader;
    $packages = $reader->read($fixtureBase.'/composer.lock', $rootRequire, $rootRequireDev);

    $dev = array_filter($packages, fn (InstalledPackage $p): bool => $p->dependencyType === DependencyType::Dev);
    $names = array_map(fn (InstalledPackage $p): string => $p->name, $dev);

    expect($names)->toContain('pestphp/pest');
    expect($names)->toContain('laravel/pint');
});

test('marks unlisted packages as transitive', function () use ($fixtureBase): void {
    $rootRequire = ['spatie/laravel-permission' => '^6.0'];
    $rootRequireDev = [];

    $reader = new ComposerLockReader;
    $packages = $reader->read($fixtureBase.'/composer.lock', $rootRequire, $rootRequireDev);

    $transitive = array_filter($packages, fn (InstalledPackage $p): bool => $p->dependencyType === DependencyType::Transitive);
    $names = array_map(fn (InstalledPackage $p): string => $p->name, $transitive);

    expect($names)->toContain('guzzlehttp/guzzle');
    expect($names)->toContain('guzzlehttp/promises');
});

test('returns empty array when composer.lock is missing', function (): void {
    $reader = new ComposerLockReader;
    $packages = $reader->read('/nonexistent/path/composer.lock', [], []);

    expect($packages)->toBe([]);
});

test('preserves version from composer.lock', function () use ($fixtureBase): void {
    $rootRequire = ['spatie/laravel-permission' => '^6.0'];
    $rootRequireDev = [];

    $reader = new ComposerLockReader;
    $packages = $reader->read($fixtureBase.'/composer.lock', $rootRequire, $rootRequireDev);

    $spatie = array_values(array_filter($packages, fn (InstalledPackage $p): bool => $p->name === 'spatie/laravel-permission'))[0];

    expect($spatie->version)->toBe('6.0.0');
    expect($spatie->constraint)->toBe('^6.0');
});

test('preserves source URL from composer.lock', function () use ($fixtureBase): void {
    $rootRequire = ['spatie/laravel-permission' => '^6.0'];
    $rootRequireDev = [];

    $reader = new ComposerLockReader;
    $packages = $reader->read($fixtureBase.'/composer.lock', $rootRequire, $rootRequireDev);

    $spatie = array_values(array_filter($packages, fn (InstalledPackage $p): bool => $p->name === 'spatie/laravel-permission'))[0];

    expect($spatie->sourceUrl)->toBe('https://github.com/spatie/laravel-permission.git');
});
