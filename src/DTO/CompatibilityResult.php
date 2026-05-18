<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class CompatibilityResult
{
    public function __construct(
        public bool $compatible,
        public bool $checked,
        public ?string $reason,
        public ?string $constraint,
    ) {}
}
