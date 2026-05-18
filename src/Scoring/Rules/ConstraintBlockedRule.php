<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class ConstraintBlockedRule implements ScoringRule
{
    public const CODE = 'constraint_blocked';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        if (! $context->isConstraintBlocked) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -15);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Warning,
            message: 'Composer constraint prevents upgrading to the latest version',
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
