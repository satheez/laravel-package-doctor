<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Collectors;

use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Satheez\PackageDoctor\Support\Contracts\TickableComposerProcessContract;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

final readonly class ComposerLicenseCollector
{
    public function __construct(
        private ComposerProcessContract $process,
        /** @var array<string, mixed> */
        private array $config,
    ) {}

    /**
     * @param  null|callable(): void  $tick
     * @return array<string, string|null>
     */
    public function collect(string $workingDir, ?callable $tick = null): array
    {
        if (! ($this->config['composer']['commands']['licenses']['enabled'] ?? true)) {
            return [];
        }

        $arguments = ['licenses', '--format=json'];

        try {
            $data = $this->process instanceof TickableComposerProcessContract
                ? $this->process->runJsonWithTicks($arguments, $workingDir, $tick)
                : $this->process->runJson($arguments, $workingDir);
        } catch (ProcessTimedOutException|ComposerCommandFailedException) {
            return [];
        }

        $result = [];

        foreach ($data['dependencies'] ?? [] as $name => $info) {
            $licenses = $info['license'] ?? [];
            $result[$name] = is_array($licenses) && count($licenses) > 0 ? implode(', ', $licenses) : null;
        }

        return $result;
    }
}
