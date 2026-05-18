<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Compatibility;

use Composer\Semver\Semver;
use Satheez\PackageDoctor\DTO\CompatibilityResult;
use UnexpectedValueException;

final class PhpCompatibilityChecker
{
    /** @param array<string, string> $packageRequires */
    public function check(string $projectPhpVersion, array $packageRequires): CompatibilityResult
    {
        $constraint = $packageRequires['php'] ?? null;

        if ($constraint === null) {
            return new CompatibilityResult(
                compatible: true,
                checked: false,
                reason: null,
                constraint: null,
            );
        }

        try {
            $satisfies = Semver::satisfies($projectPhpVersion, $constraint);
        } catch (UnexpectedValueException) {
            return new CompatibilityResult(
                compatible: true,
                checked: false,
                reason: null,
                constraint: $constraint,
            );
        }

        if (! $satisfies) {
            return new CompatibilityResult(
                compatible: false,
                checked: true,
                reason: "Requires php {$constraint}",
                constraint: $constraint,
            );
        }

        return new CompatibilityResult(
            compatible: true,
            checked: true,
            reason: null,
            constraint: $constraint,
        );
    }
}
