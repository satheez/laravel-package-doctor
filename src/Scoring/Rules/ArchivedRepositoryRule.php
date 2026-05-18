<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class ArchivedRepositoryRule implements ScoringRule
{
    public const CODE = 'repository_archived';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        if ($context->metadata?->githubArchived !== true) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -25);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Risk,
            message: 'GitHub repository is archived (read-only)',
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
