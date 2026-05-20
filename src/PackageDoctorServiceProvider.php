<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor;

use GuzzleHttp\Client;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Satheez\PackageDoctor\Collectors\ComposerAuditCollector;
use Satheez\PackageDoctor\Collectors\ComposerLicenseCollector;
use Satheez\PackageDoctor\Collectors\ComposerOutdatedCollector;
use Satheez\PackageDoctor\Collectors\GitHubCollector;
use Satheez\PackageDoctor\Collectors\PackagistCollector;
use Satheez\PackageDoctor\Compatibility\ComposerConstraintChecker;
use Satheez\PackageDoctor\Compatibility\LaravelCompatibilityChecker;
use Satheez\PackageDoctor\Compatibility\PhpCompatibilityChecker;
use Satheez\PackageDoctor\Console\PackageDoctorCommand;
use Satheez\PackageDoctor\Output\CiExitCodeResolver;
use Satheez\PackageDoctor\Output\ConsoleReportRenderer;
use Satheez\PackageDoctor\Output\CsvReportRenderer;
use Satheez\PackageDoctor\Output\JsonReportRenderer;
use Satheez\PackageDoctor\Readers\ComposerJsonReader;
use Satheez\PackageDoctor\Readers\ComposerLockReader;
use Satheez\PackageDoctor\Scoring\PackageScoreCalculator;
use Satheez\PackageDoctor\Scoring\RecommendationGenerator;
use Satheez\PackageDoctor\Services\PackageDoctor;
use Satheez\PackageDoctor\Support\ComposerProcess;
use Satheez\PackageDoctor\Support\Contracts\ComposerProcessContract;
use Satheez\PackageDoctor\Support\RepositoryUrlParser;
use Satheez\PackageDoctor\Support\VersionComparator;

final class PackageDoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/package-doctor.php',
            'package-doctor'
        );

        $this->app->singleton(ComposerProcessContract::class, function (): ComposerProcess {
            $config = config('package-doctor', []);

            return new ComposerProcess(
                binary: $config['composer']['binary'] ?? 'composer',
                timeout: (int) ($config['composer']['timeout_seconds'] ?? 120),
            );
        });

        $this->app->singleton(PackageDoctor::class, function (): PackageDoctor {
            $config = config('package-doctor', []);
            $cacheStore = $this->app->make('cache')->store(
                $config['cache']['store'] ?? null
            );
            $cache = new CacheRepository($cacheStore->getStore());

            $httpClient = new Client;
            $process = $this->app->make(ComposerProcessContract::class);

            return new PackageDoctor(
                jsonReader: new ComposerJsonReader,
                lockReader: new ComposerLockReader,
                outdatedCollector: new ComposerOutdatedCollector($process, $config),
                auditCollector: new ComposerAuditCollector($process, $config),
                licenseCollector: new ComposerLicenseCollector($process, $config),
                packagistCollector: new PackagistCollector($httpClient, $cache, $config),
                githubCollector: new GitHubCollector($httpClient, $cache, $config),
                laravelChecker: new LaravelCompatibilityChecker,
                phpChecker: new PhpCompatibilityChecker,
                constraintChecker: new ComposerConstraintChecker,
                versionComparator: new VersionComparator,
                urlParser: new RepositoryUrlParser,
                calculator: new PackageScoreCalculator,
                recommendationGenerator: new RecommendationGenerator,
                config: $config,
            );
        });

        $this->app->singleton(JsonReportRenderer::class, fn (): JsonReportRenderer => new JsonReportRenderer);
        $this->app->singleton(CsvReportRenderer::class, fn (): CsvReportRenderer => new CsvReportRenderer);
        $this->app->singleton(ConsoleReportRenderer::class, fn (): ConsoleReportRenderer => new ConsoleReportRenderer);
        $this->app->singleton(CiExitCodeResolver::class, fn (): CiExitCodeResolver => new CiExitCodeResolver);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/package-doctor.php' => config_path('package-doctor.php'),
            ], 'package-doctor-config');

            $this->commands([
                PackageDoctorCommand::class,
            ]);
        }

        $this->validateConfig();
    }

    private function validateConfig(): void
    {
        $thresholds = config('package-doctor.score.status_thresholds', []);

        if (! empty($thresholds)) {
            $healthy = $thresholds['healthy'] ?? 90;
            $watch = $thresholds['watch'] ?? 70;
            $risky = $thresholds['risky'] ?? 40;
            $critical = $thresholds['critical'] ?? 0;

            if ($healthy <= $watch) {
                throw new InvalidArgumentException(
                    'Invalid package-doctor config: healthy threshold must be greater than watch threshold.'
                );
            }

            if ($watch <= $risky) {
                throw new InvalidArgumentException(
                    'Invalid package-doctor config: watch threshold must be greater than risky threshold.'
                );
            }

            if ($risky <= $critical) {
                throw new InvalidArgumentException(
                    'Invalid package-doctor config: risky threshold must be greater than critical threshold.'
                );
            }
        }

        $deductions = config('package-doctor.score.deductions', []);

        foreach ($deductions as $key => $value) {
            if (is_int($value) && $value > 0) {
                throw new InvalidArgumentException(
                    "Invalid package-doctor config: score deduction '{$key}' must be negative or zero."
                );
            }
        }

        $allowedStatuses = ['healthy', 'watch', 'risky', 'critical'];
        $failOnStatuses = config('package-doctor.ci.fail_on_statuses', []);

        foreach ($failOnStatuses as $status) {
            if (! in_array($status, $allowedStatuses, true)) {
                throw new InvalidArgumentException(
                    "Invalid package-doctor config: unknown status '{$status}' in ci.fail_on_statuses."
                );
            }
        }
    }
}
