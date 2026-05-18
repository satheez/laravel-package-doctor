<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Satheez\PackageDoctor\Collectors\PackagistCollector;

function makePackagistConfig(): array
{
    return [
        'metadata' => [
            'packagist' => [
                'enabled' => true,
                'base_url' => 'https://packagist.org',
                'repo_url' => 'https://repo.packagist.org',
                'timeout_seconds' => 10,
                'user_agent' => 'LaravelPackageDoctor/1.0-test',
            ],
        ],
        'cache' => [
            'enabled' => true,
            'ttl_seconds' => 3600,
        ],
    ];
}

function makeCache(): Repository
{
    return new Repository(new ArrayStore);
}

function makeGuzzleClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

test('packagist collector fetches and parses metadata', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/packagist/package.json');
    $client = makeGuzzleClient([new Response(200, [], $fixture)]);

    $collector = new PackagistCollector($client, makeCache(), makePackagistConfig());
    $result = $collector->fetch('spatie/laravel-permission', offline: false, noCache: false);

    expect($result)->not->toBeNull();
    expect($result['name'])->toBe('spatie/laravel-permission');
    expect($result['isAbandoned'])->toBeFalse();
    expect($result['downloads'])->toBe(50000000);
    expect($result['license'])->toBe('MIT');
    expect($result['repositoryUrl'])->toBe('https://github.com/spatie/laravel-permission');
});

test('packagist collector returns null in offline mode', function (): void {
    $client = makeGuzzleClient([]);

    $collector = new PackagistCollector($client, makeCache(), makePackagistConfig());
    $result = $collector->fetch('spatie/laravel-permission', offline: true, noCache: false);

    expect($result)->toBeNull();
});

test('packagist collector returns null when disabled', function (): void {
    $config = makePackagistConfig();
    $config['metadata']['packagist']['enabled'] = false;

    $client = makeGuzzleClient([]);
    $collector = new PackagistCollector($client, makeCache(), $config);
    $result = $collector->fetch('spatie/laravel-permission', offline: false, noCache: false);

    expect($result)->toBeNull();
});

test('packagist collector returns null on HTTP failure', function (): void {
    $client = makeGuzzleClient([new Response(404, [], '{"message":"Not found"}')]);

    $collector = new PackagistCollector($client, makeCache(), makePackagistConfig());
    $result = $collector->fetch('vendor/nonexistent', offline: false, noCache: false);

    expect($result)->toBeNull();
});

test('packagist collector uses cache on second call', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/packagist/package.json');
    $client = makeGuzzleClient([
        new Response(200, [], $fixture),
        // No second response — would throw if called again
    ]);

    $cache = makeCache();
    $collector = new PackagistCollector($client, $cache, makePackagistConfig());

    $result1 = $collector->fetch('spatie/laravel-permission', offline: false, noCache: false);
    $result2 = $collector->fetch('spatie/laravel-permission', offline: false, noCache: false);

    expect($result1)->not->toBeNull();
    expect($result2)->toEqual($result1);
});

test('packagist collector bypasses cache with noCache flag', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/packagist/package.json');
    $client = makeGuzzleClient([
        new Response(200, [], $fixture),
        new Response(200, [], $fixture),
    ]);

    $cache = makeCache();
    $collector = new PackagistCollector($client, $cache, makePackagistConfig());

    $result1 = $collector->fetch('spatie/laravel-permission', offline: false, noCache: true);
    $result2 = $collector->fetch('spatie/laravel-permission', offline: false, noCache: true);

    expect($result1)->not->toBeNull();
    expect($result2)->not->toBeNull();
});
