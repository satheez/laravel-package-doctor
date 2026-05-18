<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring;

use Satheez\PackageDoctor\DTO\InstalledPackage;
use Satheez\PackageDoctor\DTO\PackageMetadata;
use Satheez\PackageDoctor\DTO\ProjectInfo;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Enums\UpgradeType;

final readonly class PackageHealthContext
{
    public function __construct(
        public InstalledPackage $package,
        public ?PackageMetadata $metadata,
        /** @var array{current: string, latest: string, latest-status: string}|null */
        public ?array $outdatedInfo,
        /** @var array{advisories: list<array<string,mixed>>, abandoned: bool, replacement: string|null}|null */
        public ?array $auditInfo,
        public ?string $license,
        public ProjectInfo $project,
        public ScanOptions $options,
        /** @var array<string, mixed> */
        public array $config,
        public bool $laravelCompatible,
        public bool $laravelChecked,
        public bool $phpCompatible,
        public bool $phpChecked,
        public bool $isConstraintBlocked,
        public UpgradeType $upgradeType,
    ) {}
}
