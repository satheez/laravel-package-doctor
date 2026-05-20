<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support\Contracts;

use Satheez\PackageDoctor\DTO\ProcessResult;

interface TickableComposerProcessContract extends ComposerProcessContract
{
    /**
     * @param  list<string>  $arguments
     * @param  null|callable(): void  $tick
     */
    public function runWithTicks(array $arguments, string $cwd, ?callable $tick = null): ProcessResult;

    /**
     * @param  list<string>  $arguments
     * @param  null|callable(): void  $tick
     * @return array<mixed>
     */
    public function runJsonWithTicks(array $arguments, string $cwd, ?callable $tick = null): array;
}
