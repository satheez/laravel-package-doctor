<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class ScanProgress
{
    public function __construct(
        public string $stage,
        public string $message,
        public ?int $current = null,
        public ?int $total = null,
    ) {}
}
