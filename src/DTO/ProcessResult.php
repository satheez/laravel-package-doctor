<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\DTO;

final readonly class ProcessResult
{
    public function __construct(
        /** @var list<string> */
        public array $command,
        public string $stdout,
        public string $stderr,
        public int $exitCode,
        public bool $successful,
    ) {}
}
