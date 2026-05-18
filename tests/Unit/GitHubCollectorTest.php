<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Satheez\PackageDoctor\Collectors\GitHubCollector;

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
        new RequestException('Forbidden', new Request('GET', '/'), new Response(403)),
    ]);

    $collector = new GitHubCollector($client, makeGitHubCache(), makeGitHubConfig());
    $result = $collector->fetchRepository('acme', 'pkg', offline: false, noCache: false);

    expect($result)->toBeNull();
    expect($collector->warnings())->toContain('GitHub API rate limit reached. Repository metadata skipped. Tip: Set PACKAGE_DOCTOR_GITHUB_TOKEN for higher limits.');
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
