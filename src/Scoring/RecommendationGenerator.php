<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring;

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\DTO\PackageRecommendation;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\RecommendationType;
use Satheez\PackageDoctor\Enums\UpgradeType;
use Satheez\PackageDoctor\Scoring\Rules\AbandonedPackageRule;
use Satheez\PackageDoctor\Scoring\Rules\ArchivedRepositoryRule;
use Satheez\PackageDoctor\Scoring\Rules\ConstraintBlockedRule;
use Satheez\PackageDoctor\Scoring\Rules\LaravelCompatibilityRule;
use Satheez\PackageDoctor\Scoring\Rules\NoRecentReleaseRule;
use Satheez\PackageDoctor\Scoring\Rules\PhpCompatibilityRule;
use Satheez\PackageDoctor\Scoring\Rules\SecurityAdvisoryRule;

final class RecommendationGenerator
{
    /**
     * @param  list<PackageIssue>  $issues
     */
    public function generate(
        InstalledPackage $package,
        ?PackageMetadata $metadata,
        array $issues,
        int $score,
        PackageStatus $status,
        UpgradeType $upgradeType,
        bool $constraintBlocked,
    ): PackageRecommendation {
        $codes = array_map(fn (PackageIssue $i): string => $i->code, $issues);

        if (in_array(SecurityAdvisoryRule::CODE, $codes, true)) {
            return new PackageRecommendation(
                type: RecommendationType::FixSecurityIssue,
                message: 'Update immediately to resolve known security advisories.',
            );
        }

        if (in_array(AbandonedPackageRule::CODE, $codes, true)) {
            $replacement = $metadata?->replacementPackage;

            return new PackageRecommendation(
                type: RecommendationType::ReplacePackage,
                message: $replacement !== null
                    ? "Replace with {$replacement} — this package is abandoned."
                    : 'Find an alternative — this package is abandoned.',
            );
        }

        if (in_array(ArchivedRepositoryRule::CODE, $codes, true)) {
            return new PackageRecommendation(
                type: RecommendationType::ReplacePackage,
                message: 'Find an alternative — the GitHub repository is archived.',
            );
        }

        if (in_array(LaravelCompatibilityRule::CODE, $codes, true)) {
            return new PackageRecommendation(
                type: RecommendationType::CheckCompatibility,
                message: 'Check package compatibility with your Laravel version before upgrading.',
            );
        }

        if (in_array(PhpCompatibilityRule::CODE, $codes, true)) {
            return new PackageRecommendation(
                type: RecommendationType::CheckCompatibility,
                message: 'Check package compatibility with your PHP version.',
            );
        }

        if ($constraintBlocked || in_array(ConstraintBlockedRule::CODE, $codes, true)) {
            return new PackageRecommendation(
                type: RecommendationType::ReviewBeforeUpgrade,
                message: 'Review and update your Composer constraint to allow the latest version.',
            );
        }

        if ($upgradeType === UpgradeType::Major) {
            return new PackageRecommendation(
                type: RecommendationType::ReviewBeforeUpgrade,
                message: 'Review the changelog before upgrading — a major version is available.',
            );
        }

        if ($upgradeType === UpgradeType::Patch || $upgradeType === UpgradeType::Minor) {
            if ($upgradeType === UpgradeType::Patch || $status === PackageStatus::Healthy) {
                return new PackageRecommendation(
                    type: RecommendationType::SafeUpgrade,
                    message: 'A safe upgrade is available — run composer update.',
                );
            }

            return new PackageRecommendation(
                type: RecommendationType::UpdateWhenConvenient,
                message: 'Update when convenient to stay current.',
            );
        }

        if (in_array(NoRecentReleaseRule::CODE_18, $codes, true)
            || in_array(NoRecentReleaseRule::CODE_12, $codes, true)) {
            return new PackageRecommendation(
                type: RecommendationType::MonitorPackage,
                message: 'Monitor this package for signs of abandonment.',
            );
        }

        return new PackageRecommendation(
            type: RecommendationType::None,
            message: 'No action required.',
        );
    }
}
