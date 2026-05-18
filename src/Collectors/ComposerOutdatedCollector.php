<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Collectors;

use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

final readonly class ComposerOutdatedCollector
{
    public function __construct(
        private ComposerProcessContract $process,
        /** @var array<string, mixed> */
        private array $config,
    ) {}

    /** @return array<string, array{current: string, latest: string, latest-status: string}> */
    public function collect(ScanOptions $opts, string $workingDir): array
    {
        if (! ($this->config['composer']['commands']['outdated']['enabled'] ?? true)) {
            return [];
        }

        $arguments = ['outdated', '--format=json', '--locked'];

        if ($opts->direct) {
            $arguments[] = '--direct';
        }

        if ($opts->noDev) {
            $arguments[] = '--no-dev';
        }

        try {
            $data = $this->process->runJson($arguments, $workingDir);
        } catch (ProcessTimedOutException|ComposerCommandFailedException) {
            return [];
        }

        $result = [];

        foreach ($data['installed'] ?? [] as $pkg) {
            $name = $pkg['name'] ?? null;
            if ($name === null) {
                continue;
            }

            $result[$name] = [
                'current' => $pkg['version'] ?? 'unknown',
                'latest' => $pkg['latest'] ?? 'unknown',
                'latest-status' => $pkg['latest-status'] ?? 'unknown',
            ];
        }

        return $result;
    }
}
