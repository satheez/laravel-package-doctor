<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support;

use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;
use Satheez\PackageDoctor\Enums\UpgradeType;
use UnexpectedValueException;

final class VersionComparator
{
    public function detectUpgradeType(?string $current, ?string $latest): UpgradeType
    {
        if ($current === null || $latest === null) {
            return UpgradeType::Unknown;
        }

        $current = $this->normalize($current);
        $latest = $this->normalize($latest);

        if ($current === null || $latest === null) {
            return UpgradeType::Unknown;
        }

        try {
            if (! Comparator::greaterThan($latest, $current)) {
                return UpgradeType::None;
            }

            if ($this->isMajorUpgrade($current, $latest)) {
                return UpgradeType::Major;
            }

            if ($this->isMinorUpgrade($current, $latest)) {
                return UpgradeType::Minor;
            }

            if ($this->isPatchUpgrade($current, $latest)) {
                return UpgradeType::Patch;
            }
        } catch (UnexpectedValueException) {
            return UpgradeType::Unknown;
        }

        return UpgradeType::Unknown;
    }

    public function isMajorUpgrade(string $current, string $latest): bool
    {
        $current = $this->normalize($current) ?? $current;
        $latest = $this->normalize($latest) ?? $latest;

        return $this->majorPart($current) !== $this->majorPart($latest)
            && Comparator::greaterThan($latest, $current);
    }

    public function isMinorUpgrade(string $current, string $latest): bool
    {
        $current = $this->normalize($current) ?? $current;
        $latest = $this->normalize($latest) ?? $latest;

        return $this->majorPart($current) === $this->majorPart($latest)
            && $this->minorPart($current) !== $this->minorPart($latest)
            && Comparator::greaterThan($latest, $current);
    }

    public function isPatchUpgrade(string $current, string $latest): bool
    {
        $current = $this->normalize($current) ?? $current;
        $latest = $this->normalize($latest) ?? $latest;

        return $this->majorPart($current) === $this->majorPart($latest)
            && $this->minorPart($current) === $this->minorPart($latest)
            && Comparator::greaterThan($latest, $current);
    }

    private function normalize(string $version): ?string
    {
        $version = ltrim($version, 'v');

        if (str_starts_with($version, 'dev-') || str_ends_with($version, '-dev')) {
            return null;
        }

        try {
            $parser = new VersionParser;

            return $parser->normalize($version);
        } catch (UnexpectedValueException) {
            return null;
        }
    }

    private function majorPart(string $normalized): string
    {
        $parts = explode('.', $normalized);

        return $parts[0];
    }

    private function minorPart(string $normalized): string
    {
        return explode('.', $normalized)[1] ?? '0';
    }
}
