<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class MajorUpgradeAvailableRule implements ScoringRule
{
    public const CODE = 'major_upgrade_available';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        if ($context->upgradeType !== UpgradeType::Major) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -10);
        $latest = ($context->metadata instanceof PackageMetadata ? $context->metadata->latestVersion : null) ?? $context->outdatedInfo['latest'] ?? 'unknown';

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Warning,
            message: "Major upgrade available: {$latest}",
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
