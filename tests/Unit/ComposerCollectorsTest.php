<?php

declare(strict_types=1);

use Satheez\PackageDoctor\Collectors\ComposerAuditCollector;
use Satheez\PackageDoctor\Collectors\ComposerLicenseCollector;
use Satheez\PackageDoctor\Collectors\ComposerOutdatedCollector;
use Satheez\PackageDoctor\DTO\ProcessResult;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Exceptions\ComposerCommandFailedException;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;

function makeDefaultConfig(): array
{
    return [
        'composer' => [
            'commands' => [
                'outdated' => ['enabled' => true],
                'audit' => ['enabled' => true],
                'licenses' => ['enabled' => true],
            ],
        ],
    ];
}

function makeScanOptions(array $overrides = []): ScanOptions
{
    return new ScanOptions(
        json: false,
        ci: false,
        direct: $overrides['direct'] ?? false,
        noDev: $overrides['noDev'] ?? false,
        noCache: false,
        scoreBelow: null,
        majorOnly: false,
        safeOnly: false,
        packages: [],
        offline: false,
    );
}

function fakeProcessContract(string $stdout, int $exitCode = 0): ComposerProcessContract
{
    return new class($stdout, $exitCode) implements ComposerProcessContract
    {
        public function __construct(
            private readonly string $stdout,
            private readonly int $exitCode,
        ) {}

        public function run(array $arguments, string $cwd): ProcessResult
        {
            return new ProcessResult(
                command: array_merge(['composer'], $arguments),
                stdout: $this->stdout,
                stderr: '',
                exitCode: $this->exitCode,
                successful: $this->exitCode === 0,
            );
        }

        public function runJson(array $arguments, string $cwd): array
        {
            $decoded = json_decode($this->stdout, true);
            if (! is_array($decoded)) {
                throw new ComposerCommandFailedException('Invalid JSON');
            }

            return $decoded;
        }
    };
}

function noopProcessContract(): ComposerProcessContract
{
    return new class implements ComposerProcessContract
    {
        public function run(array $arguments, string $cwd): ProcessResult
        {
            return new ProcessResult([], '', '', 0, true);
        }

        public function runJson(array $arguments, string $cwd): array
        {
            return [];
        }
    };
}

// ComposerOutdatedCollector tests
test('outdated collector parses installed packages', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/composer/healthy-project/composer-outdated.json');
    $process = fakeProcessContract($fixture);

    $collector = new ComposerOutdatedCollector($process, makeDefaultConfig());
    $result = $collector->collect(makeScanOptions(), sys_get_temp_dir());

    expect($result)->toHaveKey('spatie/laravel-permission');
    expect($result['spatie/laravel-permission']['current'])->toBe('6.0.0');
    expect($result['spatie/laravel-permission']['latest'])->toBe('6.1.0');
});

test('outdated collector returns empty on disabled command', function (): void {
    $config = makeDefaultConfig();
    $config['composer']['commands']['outdated']['enabled'] = false;

    $collector = new ComposerOutdatedCollector(noopProcessContract(), $config);
    $result = $collector->collect(makeScanOptions(), sys_get_temp_dir());

    expect($result)->toBe([]);
});

// ComposerAuditCollector tests
test('audit collector parses advisories', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/composer/healthy-project/composer-audit.json');
    $process = fakeProcessContract($fixture, exitCode: 1);

    $collector = new ComposerAuditCollector($process, makeDefaultConfig());
    $result = $collector->collect(sys_get_temp_dir());

    expect($result)->toHaveKey('acme/vulnerable-pkg');
    expect($result['acme/vulnerable-pkg']['advisories'])->not->toBeEmpty();
    expect($result['acme/vulnerable-pkg']['abandoned'])->toBeFalse();
});

test('audit collector parses abandoned packages', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/composer/healthy-project/composer-audit.json');
    $process = fakeProcessContract($fixture, exitCode: 1);

    $collector = new ComposerAuditCollector($process, makeDefaultConfig());
    $result = $collector->collect(sys_get_temp_dir());

    expect($result)->toHaveKey('acme/abandoned-pkg');
    expect($result['acme/abandoned-pkg']['abandoned'])->toBeTrue();
    expect($result['acme/abandoned-pkg']['replacement'])->toBe('acme/new-pkg');
});

test('audit collector handles non-zero exit with valid JSON', function (): void {
    $json = json_encode(['advisories' => [], 'abandoned' => []]);
    $process = fakeProcessContract($json, exitCode: 1);

    $collector = new ComposerAuditCollector($process, makeDefaultConfig());
    $result = $collector->collect(sys_get_temp_dir());

    expect($result)->toBe([]);
});

// ComposerLicenseCollector tests
test('license collector parses license data', function (): void {
    $fixture = file_get_contents(__DIR__.'/../Fixtures/composer/healthy-project/composer-licenses.json');
    $process = fakeProcessContract($fixture);

    $collector = new ComposerLicenseCollector($process, makeDefaultConfig());
    $result = $collector->collect(sys_get_temp_dir());

    expect($result)->toHaveKey('spatie/laravel-permission');
    expect($result['spatie/laravel-permission'])->toBe('MIT');
    expect($result)->toHaveKey('guzzlehttp/guzzle');
});

test('license collector returns empty on disabled command', function (): void {
    $config = makeDefaultConfig();
    $config['composer']['commands']['licenses']['enabled'] = false;

    $collector = new ComposerLicenseCollector(noopProcessContract(), $config);
    $result = $collector->collect(sys_get_temp_dir());

    expect($result)->toBe([]);
});
