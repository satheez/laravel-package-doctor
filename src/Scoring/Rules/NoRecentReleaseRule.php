<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use DateTimeImmutable;
use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class NoRecentReleaseRule implements ScoringRule
{
    public const CODE_18 = 'no_release_18_months';

    public const CODE_12 = 'no_release_12_months';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        $latestReleaseAt = $context->metadata?->latestReleaseAt;
        $pushedAt = $context->metadata?->githubPushedAt;

        $releaseDate = $latestReleaseAt ?? $pushedAt;

        if (! $releaseDate instanceof DateTimeImmutable) {
            return null;
        }

        $now = new DateTimeImmutable;
        $months18Ago = $now->modify('-18 months');
        $months12Ago = $now->modify('-12 months');

        if ($releaseDate < $months18Ago) {
            if ($this->isIgnored($context, self::CODE_18)) {
                return null;
            }

            $impact = (int) ($context->config['score']['deductions'][self::CODE_18] ?? -15);
            $formatted = $releaseDate->format('Y-m-d');

            return new PackageIssue(
                code: self::CODE_18,
                severity: IssueSeverity::Warning,
                message: "No release in over 18 months (last: {$formatted})",
                scoreImpact: $impact,
            );
        }

        if ($releaseDate < $months12Ago) {
            if ($this->isIgnored($context, self::CODE_12)) {
                return null;
            }

            $impact = (int) ($context->config['score']['deductions'][self::CODE_12] ?? -8);
            $formatted = $releaseDate->format('Y-m-d');

            return new PackageIssue(
                code: self::CODE_12,
                severity: IssueSeverity::Info,
                message: "No release in over 12 months (last: {$formatted})",
                scoreImpact: $impact,
            );
        }

        return null;
    }

    private function isIgnored(PackageHealthContext $context, string $code): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][$code]);
    }
}
