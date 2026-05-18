<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\IssueSeverity;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

final class LicenseRiskRule implements ScoringRule
{
    public const CODE = 'risky_license';

    public function evaluate(PackageHealthContext $context): ?PackageIssue
    {
        if ($this->isIgnored($context)) {
            return null;
        }

        $license = $context->license ?? $context->metadata?->license;

        if ($license === null || $license === '') {
            return null;
        }

        $safeLicenses = $context->config['licenses']['safe'] ?? [
            'MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC',
        ];

        $riskyLicenses = $context->config['licenses']['risky'] ?? [];

        $isRisky = in_array($license, $riskyLicenses, true)
            || (! in_array($license, $safeLicenses, true)
                && ($context->config['licenses']['unknown_license_is_risky'] ?? false));

        if (! $isRisky) {
            return null;
        }

        $impact = (int) ($context->config['score']['deductions'][self::CODE] ?? -15);

        return new PackageIssue(
            code: self::CODE,
            severity: IssueSeverity::Warning,
            message: "Potentially risky license: {$license}",
            scoreImpact: $impact,
        );
    }

    private function isIgnored(PackageHealthContext $context): bool
    {
        return isset($context->config['ignore']['issues'][$context->package->name][self::CODE]);
    }
}
