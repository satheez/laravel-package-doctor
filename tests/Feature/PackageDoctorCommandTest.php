<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

it('runs the package:doctor command successfully', function (): void {
    $this->artisan('package:doctor --offline')
        ->assertExitCode(0);
});

it('keeps json output machine readable without progress messages', function (): void {
    $this->artisan('package:doctor --offline --json')
        ->expectsOutputToContain('"packages"')
        ->doesntExpectOutputToContain('Scanning')
        ->doesntExpectOutputToContain('Reading composer')
        ->assertExitCode(0);
});

it('supports json output through the format option', function (): void {
    $this->artisan('package:doctor --offline --format=json')
        ->expectsOutputToContain('"packages"')
        ->doesntExpectOutputToContain('Scanning')
        ->doesntExpectOutputToContain('Reading composer')
        ->assertExitCode(0);
});

it('supports csv output through the format option', function (): void {
    $this->artisan('package:doctor --offline --format=csv')
        ->expectsOutputToContain('package,current_version,latest_version')
        ->doesntExpectOutputToContain('Scanning')
        ->doesntExpectOutputToContain('Reading composer')
        ->assertExitCode(0);
});

it('does not append an empty row to csv stdout', function (): void {
    $exitCode = Artisan::call('package:doctor', [
        '--offline' => true,
        '--format' => 'csv',
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->not->toEndWith(PHP_EOL.PHP_EOL);
});

it('writes json output to a file', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'package-doctor-json-');

    $this->artisan("package:doctor --offline --format=json --output={$path}")
        ->assertExitCode(0);

    $decoded = json_decode(file_get_contents($path), true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKeys(['project', 'summary', 'packages', 'warnings', 'metadata']);

    unlink($path);
});

it('writes csv output to a file', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'package-doctor-csv-');

    $this->artisan("package:doctor --offline --format=csv --output={$path}")
        ->assertExitCode(0);

    expect(file_get_contents($path))->toContain('package,current_version,latest_version');

    unlink($path);
});

it('rejects invalid output formats', function (): void {
    $this->artisan('package:doctor --offline --format=xml')
        ->expectsOutputToContain('Invalid output format')
        ->assertExitCode(3);
});

it('rejects output paths for table output', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'package-doctor-table-');

    $this->artisan("package:doctor --offline --output={$path}")
        ->expectsOutputToContain('The --output option requires --format=json, --format=csv, or --json.')
        ->assertExitCode(3);

    unlink($path);
});

it('rejects conflicting json and csv format options', function (): void {
    $this->artisan('package:doctor --offline --json --format=csv')
        ->expectsOutputToContain('The --json option cannot be combined with --format=csv.')
        ->assertExitCode(3);
});

it('suppresses progress messages in ci mode', function (): void {
    $this->artisan('package:doctor --offline --ci')
        ->doesntExpectOutputToContain('Scanning')
        ->doesntExpectOutputToContain('Reading composer')
        ->assertExitCode(0);
});

it('registers the package:doctor command', function (): void {
    expect($this->app->make(Kernel::class)->all())
        ->toHaveKey('package:doctor');
});

it('accepts the --all flag without error', function (): void {
    $this->artisan('package:doctor --offline --all')
        ->assertExitCode(0);
});

it('returns exit code 3 for missing composer.json', function (): void {
    config(['package-doctor.project.composer_json_path' => '/nonexistent/composer.json']);

    $this->artisan('package:doctor --offline')
        ->assertExitCode(0);
});
