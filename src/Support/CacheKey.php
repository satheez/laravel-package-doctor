<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support;

final class CacheKey
{
    public static function packagist(string $packageName): string
    {
        return 'package-doctor:packagist:'.str_replace('/', '__', $packageName);
    }

    public static function githubRepo(string $owner, string $repo): string
    {
        return "package-doctor:github:{$owner}:{$repo}";
    }

    public static function githubRelease(string $owner, string $repo): string
    {
        return "package-doctor:github-release:{$owner}:{$repo}";
    }

    public static function githubReadme(string $owner, string $repo): string
    {
        return "package-doctor:github-readme:{$owner}:{$repo}";
    }
}
