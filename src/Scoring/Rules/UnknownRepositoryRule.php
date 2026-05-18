<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class UnknownRepositoryRule implements ScoringRule
{
    public const CODE = 'unknown_repository';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        if (! $context->metadata instanceof PackageMetadata) {
            return null;
        }

        $hasRepo = $context->metadata->repositoryUrl !== null && $context->metadata->repositoryUrl !== '';

        if ($hasRepo) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -3);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Info,
            message: 'No repository URL found',
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
