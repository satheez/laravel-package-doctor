<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

use Satheez\PackageDoctor\Enums\IssueSeverity;

final readonly class PackageIssue
{
    public function __construct(
        public string $code,
        public IssueSeverity $severity,
        public string $message,
        public int $scoreImpact,
    ) {}
}
