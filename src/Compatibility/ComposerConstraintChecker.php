<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Compatibility;

use Composer\Semver\Semver;
use UnexpectedValueException;

final class ComposerConstraintChecker
{
    public function allows(?string $constraint, ?string $version): bool
    {
        if ($constraint === null || $version === null) {
            return false;
        }

        try {
            return Semver::satisfies($version, $constraint);
        } catch (UnexpectedValueException) {
            return false;
        }
    }

    public function isBlocked(?string $constraint, ?string $latestVersion): bool
    {
        if ($constraint === null || $latestVersion === null) {
            return false;
        }

        return ! $this->allows($constraint, $latestVersion);
    }
}
