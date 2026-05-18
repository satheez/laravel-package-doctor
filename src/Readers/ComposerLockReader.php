<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Readers;

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\Enums\DependencyType;
use Satheez\PackageDoctor\Exceptions\InvalidComposerLockException;

final class ComposerLockReader
{
    private const VIRTUAL_PACKAGES = ['php', 'php-64bit', 'hhvm'];

    /** @var list<string> */
    private array $warnings = [];

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param  array<string, string>  $rootRequire
     * @param  array<string, string>  $rootRequireDev
     * @return list<InstalledPackage>
     */
    public function read(string $composerLockPath, array $rootRequire, array $rootRequireDev): array
    {
        $this->warnings = [];

        if (! file_exists($composerLockPath)) {
            $this->warnings[] = "composer.lock not found at: {$composerLockPath}. Install dependencies first.";

            return [];
        }

        $contents = file_get_contents($composerLockPath);

        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);

        if (! is_array($data)) {
            throw new InvalidComposerLockException(
                "Invalid JSON in composer.lock at: {$composerLockPath}"
            );
        }

        $packages = [];

        foreach ($data['packages'] ?? [] as $pkg) {
            $name = $pkg['name'] ?? '';
            if ($this->isVirtual($name)) {
                continue;
            }

            $packages[] = $this->buildPackage($pkg, $name, $rootRequire, $rootRequireDev);
        }

        foreach ($data['packages-dev'] ?? [] as $pkg) {
            $name = $pkg['name'] ?? '';
            if ($this->isVirtual($name)) {
                continue;
            }

            $packages[] = $this->buildPackage($pkg, $name, $rootRequire, $rootRequireDev, forceDevSection: true);
        }

        return $packages;
    }

    /**
     * @param  array<string, mixed>  $pkg
     * @param  array<string, string>  $rootRequire
     * @param  array<string, string>  $rootRequireDev
     */
    private function buildPackage(array $pkg, string $name, array $rootRequire, array $rootRequireDev, bool $forceDevSection = false): InstalledPackage
    {
        if ($forceDevSection) {
            $type = DependencyType::Dev;
            $constraint = $rootRequireDev[$name] ?? null;
        } elseif (array_key_exists($name, $rootRequire)) {
            $type = DependencyType::Direct;
            $constraint = $rootRequire[$name];
        } elseif (array_key_exists($name, $rootRequireDev)) {
            $type = DependencyType::Dev;
            $constraint = $rootRequireDev[$name];
        } else {
            $type = DependencyType::Transitive;
            $constraint = null;
        }

        return new InstalledPackage(
            name: $name,
            version: $pkg['version'] ?? 'unknown',
            dependencyType: $type,
            constraint: $constraint,
            sourceUrl: $pkg['source']['url'] ?? null,
            distUrl: $pkg['dist']['url'] ?? null,
            requires: $pkg['require'] ?? [],
            extra: $pkg['extra'] ?? [],
        );
    }

    private function isVirtual(string $name): bool
    {
        if (in_array($name, self::VIRTUAL_PACKAGES, true)) {
            return true;
        }

        return str_starts_with($name, 'ext-') || str_starts_with($name, 'lib-');
    }
}
