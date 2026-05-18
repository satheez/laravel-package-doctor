<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Compatibility\LaravelCompatibilityChecker;
use Satheez\PackageDoctor\Compatibility\PhpCompatibilityChecker;

// LaravelCompatibilityChecker tests

test('Laravel 12 package requiring ^11.0 is incompatible', function (): void {
    $checker = new LaravelCompatibilityChecker;
    $result = $checker->check('12.0.0', ['illuminate/support' => '^11.0']);

    expect($result->compatible)->toBeFalse();
    expect($result->checked)->toBeTrue();
});

test('Laravel 12 package requiring ^11.0|^12.0 is compatible', function (): void {
    $checker = new LaravelCompatibilityChecker;
    $result = $checker->check('12.0.0', ['illuminate/support' => '^11.0|^12.0']);

    expect($result->compatible)->toBeTrue();
    expect($result->checked)->toBeTrue();
});

test('Package without Laravel dependency is compatible (unchecked)', function (): void {
    $checker = new LaravelCompatibilityChecker;
    $result = $checker->check('12.0.0', ['guzzlehttp/guzzle' => '^7.0']);

    expect($result->compatible)->toBeTrue();
    expect($result->checked)->toBeFalse();
});

test('Laravel 11 package requiring laravel/framework ^11.0 is compatible', function (): void {
    $checker = new LaravelCompatibilityChecker;
    $result = $checker->check('11.0.0', ['laravel/framework' => '^11.0']);

    expect($result->compatible)->toBeTrue();
    expect($result->checked)->toBeTrue();
});

test('Laravel 13 package requiring ^10.0|^11.0|^12.0 is incompatible', function (): void {
    $checker = new LaravelCompatibilityChecker;
    $result = $checker->check('13.0.0', ['illuminate/support' => '^10.0|^11.0|^12.0']);

    expect($result->compatible)->toBeFalse();
    expect($result->checked)->toBeTrue();
});

test('All illuminate/* packages are detected as Laravel dependencies', function (): void {
    $checker = new LaravelCompatibilityChecker;

    // illuminate/notifications is not in the hard-coded list but matches illuminate/* prefix
    $result = $checker->check('12.0.0', ['illuminate/notifications' => '^11.0']);

    expect($result->compatible)->toBeFalse();
    expect($result->checked)->toBeTrue();
});

// PhpCompatibilityChecker tests

test('PHP 8.3 package requiring ^8.1 is compatible', function (): void {
    $checker = new PhpCompatibilityChecker;
    $result = $checker->check('8.3.0', ['php' => '^8.1']);

    expect($result->compatible)->toBeTrue();
    expect($result->checked)->toBeTrue();
});

test('PHP 8.3 package requiring ^7.4 is incompatible', function (): void {
    $checker = new PhpCompatibilityChecker;
    $result = $checker->check('8.3.0', ['php' => '^7.4']);

    expect($result->compatible)->toBeFalse();
    expect($result->checked)->toBeTrue();
});

test('Package without PHP constraint is compatible (unchecked)', function (): void {
    $checker = new PhpCompatibilityChecker;
    $result = $checker->check('8.3.0', ['illuminate/support' => '^11.0']);

    expect($result->compatible)->toBeTrue();
    expect($result->checked)->toBeFalse();
});

test('PHP 8.2 package requiring ^8.2 is compatible', function (): void {
    $checker = new PhpCompatibilityChecker;
    $result = $checker->check('8.2.0', ['php' => '^8.2']);

    expect($result->compatible)->toBeTrue();
    expect($result->checked)->toBeTrue();
});

test('PHP 8.2 package requiring ^8.3 is incompatible', function (): void {
    $checker = new PhpCompatibilityChecker;
    $result = $checker->check('8.2.0', ['php' => '^8.3']);

    expect($result->compatible)->toBeFalse();
    expect($result->checked)->toBeTrue();
});
