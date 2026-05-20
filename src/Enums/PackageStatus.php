<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Enums;

enum PackageStatus: string
{
    case Healthy = 'healthy';
    case Watch = 'watch';
    case Risky = 'risky';
    case Critical = 'critical';
    case Ignored = 'ignored';

    /** @param array<string, int> $thresholds */
    public static function fromScore(int $score, array $thresholds): self
    {
        $healthy = $thresholds['healthy'] ?? 90;
        $watch = $thresholds['watch'] ?? 70;
        $risky = $thresholds['risky'] ?? 40;

        if ($score >= $healthy) {
            return self::Healthy;
        }

        if ($score >= $watch) {
            return self::Watch;
        }

        if ($score >= $risky) {
            return self::Risky;
        }

        return self::Critical;
    }
}
