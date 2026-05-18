<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support\Contracts;

use Satheez\PackageDoctor\DTO\ProcessResult;

interface ComposerProcessContract
{
    /** @param list<string> $arguments */
    public function run(array $arguments, string $cwd): ProcessResult;

    /**
     * @param  list<string>  $arguments
     * @return array<mixed>
     */
    public function runJson(array $arguments, string $cwd): array;
}
