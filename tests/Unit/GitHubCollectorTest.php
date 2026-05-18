<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Satheez\PackageDoctor\Collectors\GitHubCollector;
use Satheez\PackageDoctor\Support\CacheKey;

function makeGitHubConfig(): array
{
    return [
        'metadata' => [
            'github' => [
                'enabled' => true,
                'base_url' => 'https://api.github.com',
                'token' => null,
                'timeout_seconds' => 10,
                'fetch_latest_release' => true,
                'fetch_readme_presence' => true,
            ],
        ],
        'cache' => [
            'enabled' => true,
            'ttl_seconds' => 3600,
        ],
    ];
}

function makeGitHubClient(array $responses): Client
{
    $mock = new MockHandler($responses);
    $handlerStack = HandlerStack::create($mock);

    return new Client(['handler' => $handlerStack]);
}

function makeGitHubCache(): Repository
{
    return new Repository(new ArrayStore);
}

test('fetches and parses repository metadata', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/github/repository.json');
    $client = makeGitHubClient([new Response(200, [], $fixture)]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchRepository('spatie', 'laravel-permission', offline: false, noCache: false);

    expect($result)->not->toBeNull();
    expect($result['archived'])->toBeFalse();
    expect($result['stargazers_count'])->toBe(12000);
    expect($result['open_issues_count'])->toBe(50);
});

test('detects archived repository', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/github/archived-repository.json');
    $client = makeGitHubClient([new Response(200, [], $fixture)]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchRepository('acme', 'old-package', offline: false, noCache: false);

    expect($result)->not->toBeNull();
    expect($result['archived'])->toBeTrue();
});

test('fetches latest release metadata', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/github/latest-release.json');
    $client = makeGitHubClient([new Response(200, [], $fixture)]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchLatestRelease('spatie', 'laravel-permission', offline: false, noCache: false);

    expect($result)->not->toBeNull();
    expect($result['tag_name'])->toBe('6.1.0');
    expect($result['published_at'])->toBe('2024-01-01T12:00:00Z');
});

test('returns null when no latest release (404)', function (): void {
    $client = makeGitHubClient([
        new RequestException('Not Found', new Request('GET', '/'), new Response(404)),
    ]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchLatestRelease('acme', 'no-releases', offline: false, noCache: false);

    expect($result)->toBeNull();
});

test('adds warning on rate limit (403)', function (): void {
    $client = makeGitHubClient([
        new RequestException('Forbidden', new Request('GET', '/'), new Response(403, [
            'x-ratelimit-remaining' => '0',
        ])),
    ]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchRepository('acme', 'pkg', offline: false, noCache: false);

    expect($result)->toBeNull();
    expect(str_contains($collector->warnings()[0], 'GitHub API rate limit reached'))->toBeTrue();
    expect(str_contains($collector->warnings()[0], 'PACKAGE_DOCTOR_GITHUB_TOKEN'))->toBeTrue();
});

test('skips further uncached github requests after rate limit', function (): void {
    $history = [];
    $mock = new MockHandler([
        new RequestException(
            'Forbidden',
            new Request('GET', '/'),
            new Response(403, [
                'x-ratelimit-remaining' => '0',
                'x-ratelimit-reset' => '1735689600',
            ]),
        ),
        new Response(200, [], file_get_contents(__DIR__.'/../Fixtures/github/latest-release.json')),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $collector = new GitHubCollector(
        new Client(['handler' => $handlerStack]),
        makeGitHubCache(),
        makeGitHubConfig(),
    );

    $first = $collector->fetchRepository('acme', 'pkg', offline: false, noCache: false);
    $second = $collector->fetchLatestRelease('acme', 'pkg', offline: false, noCache: false);

    expect($first)->toBeNull();
    expect($second)->toBeNull();
    expect($history)->toHaveCount(1);
    expect($collector->warnings())->toHaveCount(1);
    expect($collector->warnings()[0])->toContain('resets at 2025-01-01T00:00:00+00:00');
});

test('returns cached github data after rate limit', function (): void {
    $history = [];
    $mock = new MockHandler([
        new RequestException(
            'Forbidden',
            new Request('GET', '/'),
            new Response(403, ['x-ratelimit-remaining' => '0']),
        ),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));
    $cache = makeGitHubCache();
    $cache->put(CacheKey::githubRelease('acme', 'pkg'), ['tag_name' => '1.2.3'], 3600);

    $collector = new GitHubCollector(
        new Client(['handler' => $handlerStack]),
        $cache,
        makeGitHubConfig(),
    );

    $collector->fetchRepository('acme', 'pkg', offline: false, noCache: false);
    $cached = $collector->fetchLatestRelease('acme', 'pkg', offline: false, noCache: false);

    expect($cached)->toBe(['tag_name' => '1.2.3']);
    expect($history)->toHaveCount(1);
});

test('resets rate limit state between scans', function (): void {
    $history = [];
    $mock = new MockHandler([
        new RequestException(
            'Forbidden',
            new Request('GET', '/'),
            new Response(403, ['x-ratelimit-remaining' => '0']),
        ),
        new Response(200, [], file_get_contents(__DIR__.'/../Fixtures/github/latest-release.json')),
    ]);
    $handlerStack = HandlerStack::create($mock);
    $handlerStack->push(Middleware::history($history));

    $collector = new GitHubCollector(
        new Client(['handler' => $handlerStack]),
        makeGitHubCache(),
        makeGitHubConfig(),
    );

    $collector->fetchRepository('acme', 'pkg', offline: false, noCache: false);
    $collector->reset();

    $result = $collector->fetchLatestRelease('acme', 'pkg', offline: false, noCache: false);

    expect($result)->not->toBeNull();
    expect($history)->toHaveCount(2);
    expect($collector->warnings())->toBe([]);
});

test('returns null in offline mode', function (): void {
    $client = makeGitHubClient([]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchRepository('spatie', 'laravel-permission', offline: true, noCache: false);

    expect($result)->toBeNull();
});

test('returns null when github disabled', function (): void {
    $config = makeGitHubConfig();
    $config['metadata']['github']['enabled'] = false;

    $client = makeGitHubClient([]);
    $collector = new GitHubCollector($client, makeGitHubCache(), $config);
    $result = $collector->fetchRepository('spatie', 'laravel-permission', offline: false, noCache: false);

    expect($result)->toBeNull();
});

test('uses cache on second call', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/github/repository.json');
    $client = makeGitHubClient([new Response(200, [], $fixture)]);

    $cache = makeGitHubCache();
    $collector = new GitHubCollector($client, $cache, makeGitHubConfig());

    $result1 = $collector->fetchRepository('spatie', 'laravel-permission', offline: false, noCache: false);
    $result2 = $collector->fetchRepository('spatie', 'laravel-permission', offline: false, noCache: false);

    expect($result1)->not->toBeNull();
    expect($result2)->toEqual($result1);
});
