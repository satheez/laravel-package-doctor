<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Compatibility;

use Composer\Semver\Semver;
use Satheez\PackageDoctor\DTO\CompatibilityResult;
use UnexpectedValueException;

final class LaravelCompatibilityChecker
{
    private const LARAVEL_PACKAGES = [
        'laravel/framework',
        'illuminate/support',
        'illuminate/console',
        'illuminate/database',
        'illuminate/contracts',
        'illuminate/http',
        'illuminate/auth',
        'illuminate/cache',
        'illuminate/config',
        'illuminate/container',
        'illuminate/events',
        'illuminate/filesystem',
        'illuminate/queue',
        'illuminate/routing',
        'illuminate/session',
        'illuminate/translation',
        'illuminate/validation',
        'illuminate/view',
    ];

    /** @param array<string, string> $packageRequires */
    public function check(string $projectLaravelVersion, array $packageRequires): CompatibilityResult
    {
        foreach ($packageRequires as $dependency => $constraint) {
            if (! $this->isLaravelDependency($dependency)) {
                continue;
            }

            try {
                $satisfies = Semver::satisfies($projectLaravelVersion, $constraint);
            } catch (UnexpectedValueException) {
                continue;
            }

            if (! $satisfies) {
                return new CompatibilityResult(
                    compatible: false,
                    checked: true,
                    reason: "Requires {$dependency} {$constraint}",
                    constraint: $constraint,
                );
            }
        }

        $hasLaravelDep = count(array_filter(
            array_keys($packageRequires),
            $this->isLaravelDependency(...)
        )) > 0;

        return new CompatibilityResult(
            compatible: true,
            checked: $hasLaravelDep,
            reason: null,
            constraint: null,
        );
    }

    private function isLaravelDependency(string $package): bool
    {
        if (in_array($package, self::LARAVEL_PACKAGES, true)) {
            return true;
        }

        return str_starts_with($package, 'illuminate/');
    }
}
