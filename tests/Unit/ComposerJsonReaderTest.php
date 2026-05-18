<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Exceptions\ComposerFileNotFoundException;
use Satheez\PackageDoctor\Exceptions\InvalidComposerJsonException;
use Satheez\PackageDoctor\Readers\ComposerJsonReader;

$fixtureBase = __DIR__.'/../Fixtures/composer/healthy-project';

test('reads require and require-dev from composer.json', function () use ($fixtureBase): void {
    $reader = new ComposerJsonReader;
    $data = $reader->read($fixtureBase.'/composer.json');

    expect($data['require'])->toHaveKey('spatie/laravel-permission');
    expect($data['require-dev'])->toHaveKey('pestphp/pest');
});

test('extracts php_constraint from composer.json', function () use ($fixtureBase): void {
    $reader = new ComposerJsonReader;
    $data = $reader->read($fixtureBase.'/composer.json');

    expect($data['php_constraint'])->toBe('^8.2');
});

test('extracts laravel_constraint from composer.json', function () use ($fixtureBase): void {
    $reader = new ComposerJsonReader;
    $data = $reader->read($fixtureBase.'/composer.json');

    expect($data['laravel_constraint'])->toBe('^11.0');
});

test('throws ComposerFileNotFoundException when composer.json is missing', function (): void {
    $reader = new ComposerJsonReader;
    $reader->read('/nonexistent/path/composer.json');
})->throws(ComposerFileNotFoundException::class);

test('throws InvalidComposerJsonException when composer.json has invalid JSON', function (): void {
    $tmpPath = sys_get_temp_dir().'/invalid_composer_'.uniqid().'.json';
    file_put_contents($tmpPath, 'not valid json');

    try {
        $reader = new ComposerJsonReader;
        $reader->read($tmpPath);
    } finally {
        @unlink($tmpPath);
    }
})->throws(InvalidComposerJsonException::class);
