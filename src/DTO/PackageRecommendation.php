<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

use Satheez\PackageDoctor\Enums\RecommendationType;

final readonly class PackageRecommendation
{
    public function __construct(
        public RecommendationType $type,
        public string $message,
    ) {}
}
