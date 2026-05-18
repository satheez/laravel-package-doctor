<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class AbandonedPackageRule implements ScoringRule
{
    public const CODE = 'abandoned';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        $isAbandoned = $context->auditInfo['abandoned'] ?? ($context->metadata instanceof PackageMetadata && $context->metadata->isAbandoned);

        if (! $isAbandoned) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -30);
        $replacement = $context->auditInfo['replacement'] ?? $context->metadata?->replacementPackage;
        $message = $replacement !== null
            ? "Package is abandoned. Suggested replacement: {$replacement}"
            : 'Package is abandoned with no suggested replacement';

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Risk,
            message: $message,
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
