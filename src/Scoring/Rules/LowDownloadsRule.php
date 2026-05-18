<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class LowDownloadsRule implements ScoringRule
{
    public const CODE = 'low_downloads';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        $downloads = $context->metadata?->downloads;

        if ($downloads === null) {
            return null;
        }

        $threshold = (int) ($context->config['popularity']['minimum_downloads'] ?? 1000);

        if ($downloads >= $threshold) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -5);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Info,
            message: "Low download count: {$downloads} (threshold: {$threshold})",
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
