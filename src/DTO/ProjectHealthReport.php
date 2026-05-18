<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class ProjectHealthReport
{
    public function __construct(
        public ProjectInfo $project,
        /** @var list<PackageHealthResult> */
        public array $results,
        /** @var array<string, mixed> */
        public array $summary,
        /** @var list<string> */
        public array $warnings,
    ) {}
}
