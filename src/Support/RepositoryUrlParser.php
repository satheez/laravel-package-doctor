<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support;

final class RepositoryUrlParser
{
    /** @return array{owner: string, repo: string}|null */
    public function parse(?string $url): ?array
    {
        if ($url === null || $url === '') {
            return null;
        }

        // SSH: git@github.com:owner/repo.git
        if (preg_match('/^git@github\.com:([^\/]+)\/([^\/]+?)(?:\.git)?$/', $url, $m)) {
            return ['owner' => $m[1], 'repo' => $m[2]];
        }

        // HTTPS: https://github.com/owner/repo(.git)
        if (preg_match('/^https?:\/\/github\.com\/([^\/]+)\/([^\/]+?)(?:\.git)?$/', $url, $m)) {
            return ['owner' => $m[1], 'repo' => $m[2]];
        }

        return null;
    }

    public function isGitHub(?string $url): bool
    {
        return $this->parse($url) !== null;
    }
}
