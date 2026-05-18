<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Satheez\PackageDoctor\PackageDoctorServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PackageDoctorServiceProvider::class,
        ];
    }
}
