<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

use Satheez\PackageDoctor\Enums\DependencyType;

final readonly class InstalledPackage
{
    public function __construct(
        public string $name,
        public string $version,
        public DependencyType $dependencyType,
        public ?string $constraint,
        public ?string $sourceUrl,
        public ?string $distUrl,
        /** @var array<string, string> */
        public array $requires,
        /** @var array<string, mixed> */
        public array $extra,
    ) {}
}
