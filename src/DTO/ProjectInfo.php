<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class ProjectInfo
{
    public function __construct(
        public string $phpVersion,
        public ?string $laravelVersion,
        public ?string $composerVersion,
        public string $basePath,
    ) {}
}
