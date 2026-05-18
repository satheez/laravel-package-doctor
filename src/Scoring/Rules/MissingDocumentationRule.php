<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class MissingDocumentationRule implements ScoringRule
{
    public const CODE = 'missing_documentation';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        if (! $context->metadata instanceof PackageMetadata) {
            return null;
        }

        $hasDoc = $context->metadata->documentationUrl !== null && $context->metadata->documentationUrl !== '';

        if ($hasDoc) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -5);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Info,
            message: 'No documentation URL found',
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
