<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Collectors;

use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

final readonly class ComposerAuditCollector
{
    public function __construct(
        private ComposerProcessContract $process,
        /** @var array<string, mixed> */
        private array $config,
    ) {}

    /**
     * @return array<string, array{advisories: list<array<string,mixed>>, abandoned: bool, replacement: string|null}>
     */
    public function collect(string $workingDir): array
    {
        if (! ($this->config['composer']['commands']['audit']['enabled'] ?? true)) {
            return [];
        }

        $arguments = ['audit', '--format=json', '--locked'];

        try {
            $result = $this->process->run($arguments, $workingDir);

            $data = json_decode($result->stdout, true);

            if (! is_array($data)) {
                return [];
            }
        } catch (ProcessTimedOutException|ComposerCommandFailedException) {
            return [];
        }

        $packages = [];

        foreach ($data['advisories'] ?? [] as $packageName => $advisories) {
            if (! isset($packages[$packageName])) {
                $packages[$packageName] = ['advisories' => [], 'abandoned' => false, 'replacement' => null];
            }

            $packages[$packageName]['advisories'] = array_values((array) $advisories);
        }

        foreach ($data['abandoned'] ?? [] as $packageName => $info) {
            if (! isset($packages[$packageName])) {
                $packages[$packageName] = ['advisories' => [], 'abandoned' => false, 'replacement' => null];
            }

            $packages[$packageName]['abandoned'] = true;
            $packages[$packageName]['replacement'] = is_array($info) ? ($info['replacedBy'] ?? null) : null;
        }

        return $packages;
    }
}
