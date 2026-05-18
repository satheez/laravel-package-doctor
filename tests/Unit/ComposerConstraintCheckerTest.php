<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Compatibility\ComposerConstraintChecker;

test('^1.0 allows 1.2.0', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->allows('^1.0', '1.2.0'))->toBeTrue();
});

test('^1.0 blocks 2.0.0', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->allows('^1.0', '2.0.0'))->toBeFalse();
});

test('^1.0 isBlocked by 2.0.0', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->isBlocked('^1.0', '2.0.0'))->toBeTrue();
});

test('^1.0 isNotBlocked by 1.5.0', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->isBlocked('^1.0', '1.5.0'))->toBeFalse();
});

test('null constraint returns false', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->allows(null, '1.0.0'))->toBeFalse();
    expect($checker->isBlocked(null, '1.0.0'))->toBeFalse();
});

test('null version returns false', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->allows('^1.0', null))->toBeFalse();
    expect($checker->isBlocked('^1.0', null))->toBeFalse();
});

test('~1.0 allows 1.0.5', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->allows('~1.0', '1.0.5'))->toBeTrue();
});

test('^1.0|^2.0 allows 2.0.0', function (): void {
    $checker = new ComposerConstraintChecker;
    expect($checker->allows('^1.0|^2.0', '2.0.0'))->toBeTrue();
});
