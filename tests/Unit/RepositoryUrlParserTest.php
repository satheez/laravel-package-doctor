<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Support\RepositoryUrlParser;

test('parses HTTPS GitHub URL', function (): void {
    $parser = new RepositoryUrlParser;
    $result = $parser->parse('https://github.com/spatie/laravel-permission');

    expect($result)->toBe(['owner' => 'spatie', 'repo' => 'laravel-permission']);
});

test('parses HTTPS GitHub URL with .git suffix', function (): void {
    $parser = new RepositoryUrlParser;
    $result = $parser->parse('https://github.com/spatie/laravel-permission.git');

    expect($result)->toBe(['owner' => 'spatie', 'repo' => 'laravel-permission']);
});

test('parses SSH GitHub URL', function (): void {
    $parser = new RepositoryUrlParser;
    $result = $parser->parse('git@github.com:spatie/laravel-permission.git');

    expect($result)->toBe(['owner' => 'spatie', 'repo' => 'laravel-permission']);
});

test('returns null for non-GitHub URL', function (): void {
    $parser = new RepositoryUrlParser;

    expect($parser->parse('https://gitlab.com/spatie/laravel-permission'))->toBeNull();
    expect($parser->parse('https://bitbucket.org/spatie/laravel-permission'))->toBeNull();
});

test('returns null for null URL', function (): void {
    $parser = new RepositoryUrlParser;
    expect($parser->parse(null))->toBeNull();
});

test('returns null for empty URL', function (): void {
    $parser = new RepositoryUrlParser;
    expect($parser->parse(''))->toBeNull();
});

test('isGitHub returns true for GitHub URL', function (): void {
    $parser = new RepositoryUrlParser;
    expect($parser->isGitHub('https://github.com/spatie/laravel-permission'))->toBeTrue();
});

test('isGitHub returns false for non-GitHub URL', function (): void {
    $parser = new RepositoryUrlParser;
    expect($parser->isGitHub('https://gitlab.com/foo/bar'))->toBeFalse();
});
