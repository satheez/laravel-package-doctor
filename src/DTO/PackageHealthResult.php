<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Enums\UpgradeType;

final readonly class PackageHealthResult
{
    public function __construct(
        public InstalledPackage $package,
        public ?PackageMetadata $metadata,
        public int $score,
        public PackageStatus $status,
        public ?string $latestVersion,
        public ?string $latestAllowedVersion,
        public UpgradeType $upgradeType,
        public bool $isConstraintBlocked,
        /** @var list<PackageIssue> */
        public array $issues,
        public PackageRecommendation $recommendation,
    ) {}
}
