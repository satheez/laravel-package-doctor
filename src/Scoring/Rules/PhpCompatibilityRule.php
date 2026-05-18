<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class PhpCompatibilityRule implements ScoringRule
{
    public const CODE = 'php_incompatible';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        if (! $context->phpChecked || $context->phpCompatible) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -20);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Critical,
            message: "Package is not compatible with the installed PHP version ({$context->project->phpVersion})",
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
