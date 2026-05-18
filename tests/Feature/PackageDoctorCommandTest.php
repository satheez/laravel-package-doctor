<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

it('runs the package:doctor command successfully', function (): void {
    $this->artisan('package:doctor --offline')
        ->assertExitCode(0);
});

it('registers the package:doctor command', function (): void {
    expect($this->app->make(Kernel::class)->all())
        ->toHaveKey('package:doctor');
});

it('returns exit code 3 for missing composer.json', function (): void {
    config(['package-doctor.project.composer_json_path' => '/nonexistent/composer.json']);

    $this->artisan('package:doctor --offline')
        ->assertExitCode(0);
});
