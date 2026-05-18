<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Support;

use Satheez\PackageDoctor\DTO\ProcessResult;
use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Symfony\Component\Process\Process;

final readonly class ComposerProcess implements ComposerProcessContract
{
    public function __construct(
        private string $binary = 'composer',
        private int $timeout = 120,
    ) {}

    /** @param list<string> $arguments */
    public function run(array $arguments, string $cwd): ProcessResult
    {
        $command = array_merge([$this->binary], $arguments);

        $process = new Process($command, $cwd, timeout: $this->timeout);
        $process->run();

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
        $result = $this->run($arguments, $cwd);

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
