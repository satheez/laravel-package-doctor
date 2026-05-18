<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class SecurityAdvisoryRule implements ScoringRule
{
    public const CODE = 'security_advisory';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        $advisories = $context->auditInfo['advisories'] ?? [];

        if (empty($advisories)) {
            return null;
        }

        $count = count($advisories);
        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -30);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Critical,
            message: "Has {$count} known security ".($count === 1 ? 'advisory' : 'advisories'),
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
