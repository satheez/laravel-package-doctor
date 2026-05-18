<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Collectors;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Http\Message\ResponseInterface;
use Satheez\PackageDoctor\Support\CacheKey;

final class GitHubCollector
{
    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly ClientInterface $client,
        private readonly CacheRepository $cache,
        /** @var array<string, mixed> */
        private readonly array $config,
    ) {}

    /** @return array<string, mixed>|null */
    public function fetchRepository(string $owner, string $repo, bool $offline, bool $noCache): ?array
    {
        if (! $this->isEnabled($offline)) {
            return null;
        }

        $cacheKey = CacheKey::githubRepo($owner, $repo);

        if ($this->cacheEnabled($noCache)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $data = $this->get("/repos/{$owner}/{$repo}");

        if ($data !== null && $this->cacheEnabled($noCache)) {
            $this->cache->put($cacheKey, $data, $this->ttl());
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    public function fetchLatestRelease(string $owner, string $repo, bool $offline, bool $noCache): ?array
    {
        if (! $this->isEnabled($offline) || ! ($this->config['metadata']['github']['fetch_latest_release'] ?? true)) {
            return null;
        }

        $cacheKey = CacheKey::githubRelease($owner, $repo);

        if ($this->cacheEnabled($noCache)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $data = $this->get("/repos/{$owner}/{$repo}/releases/latest");

        if ($data !== null && $this->cacheEnabled($noCache)) {
            $this->cache->put($cacheKey, $data, $this->ttl());
        }

        return $data;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array<string, mixed>|null */
    private function get(string $path): ?array
    {
        $baseUrl = rtrim($this->config['metadata']['github']['base_url'] ?? 'https://api.github.com', '/');
        $timeout = (int) ($this->config['metadata']['github']['timeout_seconds'] ?? 10);
        $token = $this->config['metadata']['github']['token'] ?? null;

        $headers = ['Accept' => 'application/vnd.github+json'];
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            $response = $this->client->request('GET', $baseUrl.$path, [
                'timeout' => $timeout,
                'headers' => $headers,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            return is_array($data) ? $data : null;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response instanceof ResponseInterface) {
                $statusCode = $response->getStatusCode();
                if ($statusCode === 403 || $statusCode === 429) {
                    $this->warnings[] = 'GitHub API rate limit reached. Repository metadata skipped. Tip: Set PACKAGE_DOCTOR_GITHUB_TOKEN for higher limits.';
                }
            }

            return null;
        } catch (GuzzleException) {
            return null;
        }
    }

    private function isEnabled(bool $offline): bool
    {
        return ! $offline && ($this->config['metadata']['github']['enabled'] ?? true);
    }

    private function cacheEnabled(bool $noCache): bool
    {
        return ($this->config['cache']['enabled'] ?? true) && ! $noCache;
    }

    private function ttl(): int
    {
        return (int) ($this->config['cache']['ttl_seconds'] ?? 3600);
    }
}
