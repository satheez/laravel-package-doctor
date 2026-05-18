<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Readers;

use Satheez\PackageDoctor\Exceptions\ComposerFileNotFoundException;
use Satheez\PackageDoctor\Exceptions\InvalidComposerJsonException;

final class ComposerJsonReader
{
    /** @return array{require: array<string, string>, 'require-dev': array<string, string>, php_constraint: string|null, laravel_constraint: string|null} */
    public function read(string $composerJsonPath): array
    {
        if (! file_exists($composerJsonPath)) {
            throw new ComposerFileNotFoundException(
                "composer.json not found at: {$composerJsonPath}"
            );
        }

        $contents = file_get_contents($composerJsonPath);

        if ($contents === false) {
            throw new ComposerFileNotFoundException(
                "Unable to read composer.json at: {$composerJsonPath}"
            );
        }

        $data = json_decode($contents, true);

        if (! is_array($data)) {
            throw new InvalidComposerJsonException(
                "Invalid JSON in composer.json at: {$composerJsonPath}"
            );
        }

        return [
            'require' => $data['require'] ?? [],
            'require-dev' => $data['require-dev'] ?? [],
            'php_constraint' => $data['require']['php'] ?? null,
            'laravel_constraint' => $data['require']['laravel/framework'] ?? null,
        ];
    }
}
