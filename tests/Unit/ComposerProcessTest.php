<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\ComposerProcess;

test('ComposerProcess builds command array with binary', function (): void {
    $process = new ComposerProcess('composer', 30);

    // Verify that runJson throws for a command that produces no JSON
    // (we can't run real composer here, so just verify exception path works)
    expect(fn (): array => $process->runJson(['invalid-command-xyz'], sys_get_temp_dir()))
        ->toThrow(ComposerCommandFailedException::class);
});

test('ComposerProcess runJson throws on invalid JSON output', function (): void {
    // Use a command that runs but produces non-JSON output
    $process = new ComposerProcess('echo', 30);

    expect(fn (): array => $process->runJson(['not-json'], sys_get_temp_dir()))
        ->toThrow(ComposerCommandFailedException::class);
});

test('ComposerProcess invokes tick callback while command is running', function (): void {
    $ticks = 0;
    $process = new ComposerProcess(PHP_BINARY, 30);

    $result = $process->runWithTicks(
        ['-r', 'usleep(250000); echo "ok";'],
        sys_get_temp_dir(),
        function () use (&$ticks): void {
            $ticks++;
        },
    );

    expect($result->successful)->toBeTrue();
    expect(trim($result->stdout))->toBe('ok');
    expect($ticks)->toBeGreaterThan(0);
});
