<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class ScanOptions
{
    public function __construct(
        public bool $json,
        public bool $ci,
        public bool $direct,
        public bool $noDev,
        public bool $noCache,
        public ?int $scoreBelow,
        public bool $majorOnly,
        public bool $safeOnly,
        /** @var list<string> */
        public array $packages,
        public bool $offline,
        public bool $all = false,
    ) {}
}
