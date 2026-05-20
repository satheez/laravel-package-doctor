<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Collectors;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Satheez\PackageDoctor\Support\CacheKey;

final readonly class PackagistCollector
{
    public function __construct(
        private ClientInterface $client,
        private CacheRepository $cache,
        /** @var array<string, mixed> */
        private array $config,
    ) {}

    /** @return array<string, mixed>|null */
    public function fetch(string $packageName, bool $offline, bool $noCache): ?array
    {
        if ($offline || ! ($this->config['metadata']['packagist']['enabled'] ?? true)) {
            return null;
        }

        $cacheKey = CacheKey::packagist($packageName);
        $ttl = (int) ($this->config['cache']['ttl_seconds'] ?? 3600);
        $cacheEnabled = ($this->config['cache']['enabled'] ?? true) && ! $noCache;

        if ($cacheEnabled) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $data = $this->fetchFromPackagist($packageName);

        if ($data !== null && $cacheEnabled) {
            $this->cache->put($cacheKey, $data, $ttl);
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    private function fetchFromPackagist(string $packageName): ?array
    {
        $baseUrl = rtrim($this->config['metadata']['packagist']['base_url'] ?? 'https://packagist.org', '/');
        $userAgent = $this->config['metadata']['packagist']['user_agent'] ?? 'LaravelPackageDoctor/1.0';
        $timeout = (int) ($this->config['metadata']['packagist']['timeout_seconds'] ?? 10);

        try {
            $response = $this->client->request('GET', "{$baseUrl}/packages/{$packageName}.json", [
                'timeout' => $timeout,
                'headers' => ['User-Agent' => $userAgent],
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (! is_array($data) || ! isset($data['package'])) {
                return null;
            }

            return $this->normalize($data['package']);
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function normalize(array $package): array
    {
        $versions = $package['versions'] ?? [];
        $latestStable = $this->resolveLatestStable($versions);

        $license = null;
        $latestReleaseAt = null;
        if ($latestStable !== null && isset($versions[$latestStable])) {
            $lics = $versions[$latestStable]['license'] ?? null;
            $license = is_array($lics) ? implode(', ', $lics) : $lics;
            $latestReleaseAt = $versions[$latestStable]['time'] ?? null;
        }

        $repositoryUrl = $package['repository'] ?? null;
        $abandoned = $package['abandoned'] ?? false;
        $replacedBy = is_string($abandoned) ? $abandoned : null;

        return [
            'name' => $package['name'] ?? null,
            'description' => $package['description'] ?? null,
            'latestVersion' => $latestStable,
            'latestReleaseAt' => $latestReleaseAt,
            'isAbandoned' => $abandoned !== false,
            'replacementPackage' => $replacedBy,
            'downloads' => $package['downloads']['total'] ?? null,
            'license' => $license,
            'repositoryUrl' => $repositoryUrl,
            'githubStars' => $package['github_stars'] ?? null,
            'githubOpenIssues' => $package['github_open_issues'] ?? null,
        ];
    }

    /** @param array<mixed> $versions */
    private function resolveLatestStable(array $versions): ?string
    {
        foreach (array_keys($versions) as $tag) {
            if (! str_contains((string) $tag, 'dev') && ! str_contains((string) $tag, 'alpha') && ! str_contains((string) $tag, 'beta') && ! str_contains((string) $tag, 'RC')) {
                return (string) $tag;
            }
        }

        return null;
    }
}
