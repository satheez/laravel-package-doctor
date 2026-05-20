<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support;

use Satheez\PackageDoctor\DTO\ProcessResult;
use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\Contracts\TickableComposerProcessContract;
use Symfony\Component\Process\Process;

final readonly class ComposerProcess implements TickableComposerProcessContract
{
    public function __construct(
        private string $binary = 'composer',
        private int $timeout = 120,
    ) {}

    /** @param list<string> $arguments */
    public function run(array $arguments, string $cwd): ProcessResult
    {
        return $this->runWithTicks($arguments, $cwd);
    }

    /**
     * @param  list<string>  $arguments
     * @param  null|callable(): void  $tick
     */
    public function runWithTicks(array $arguments, string $cwd, ?callable $tick = null): ProcessResult
    {
        $command = array_merge([$this->binary], $arguments);

        $process = new Process($command, $cwd, timeout: $this->timeout);

        if ($tick === null) {
            $process->run();

            return new ProcessResult(
                command: $command,
                stdout: $process->getOutput(),
                stderr: $process->getErrorOutput(),
                exitCode: $process->getExitCode() ?? 1,
                successful: $process->isSuccessful(),
            );
        }

        $process->start();

        while ($process->isRunning()) {
            $tick();
            usleep(100000);
            $process->checkTimeout();
        }

        return new ProcessResult(
            command: $command,
            stdout: $process->getOutput(),
            stderr: $process->getErrorOutput(),
            exitCode: $process->getExitCode() ?? 1,
            successful: $process->isSuccessful(),
        );
    }

    /**
     * @param  list<string>  $arguments
     * @return array<mixed>
     */
    public function runJson(array $arguments, string $cwd): array
    {
        return $this->runJsonWithTicks($arguments, $cwd);
    }

    /**
     * @param  list<string>  $arguments
     * @param  null|callable(): void  $tick
     * @return array<mixed>
     */
    public function runJsonWithTicks(array $arguments, string $cwd, ?callable $tick = null): array
    {
        $result = $this->runWithTicks($arguments, $cwd, $tick);

        $decoded = json_decode($result->stdout, true);

        if (! is_array($decoded)) {
            if (! $result->successful) {
                throw new ComposerCommandFailedException(
                    "Composer command failed (exit {$result->exitCode}): ".implode(' ', $result->command)."\n".$result->stderr
                );
            }

            throw new ComposerCommandFailedException(
                'Composer command returned invalid JSON: '.implode(' ', $result->command)
            );
        }

        return $decoded;
    }
}
