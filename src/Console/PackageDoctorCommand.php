<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Console;

use Illuminate\Console\Command;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\DTO\ScanProgress;
use Satheez\PackageDoctor\Output\CiExitCodeResolver;
use Satheez\PackageDoctor\Output\ConsoleReportRenderer;
use Satheez\PackageDoctor\Output\JsonReportRenderer;
use Satheez\PackageDoctor\Services\PackageDoctor;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Throwable;

final class PackageDoctorCommand extends Command
{
    protected $signature = 'package:doctor
        {--json : Output results as JSON}
        {--ci : Enable CI mode with exit codes}
        {--direct : Include only direct dependencies}
        {--no-dev : Exclude dev dependencies}
        {--no-cache : Bypass cache for external metadata}
        {--offline : Skip Packagist and GitHub metadata collection}
        {--score-below= : Only show packages with score below this value}
        {--major-only : Only show packages with a major upgrade available}
        {--safe-only : Only show packages with safe patch/minor upgrades and no critical issues}
        {--package=* : Scan or display only the given package(s)}';

    protected $description = 'Audit your Laravel project\'s Composer dependencies for upgrade safety, security, and compatibility.';

    public function handle(
        PackageDoctor $doctor,
        JsonReportRenderer $jsonRenderer,
        ConsoleReportRenderer $consoleRenderer,
        CiExitCodeResolver $exitCodeResolver,
    ): int {
        try {
            $opts = $this->buildScanOptions();
            $progressIndicator = $this->makeProgressIndicator($opts);
            $progressStarted = false;

            $report = $doctor->analyze(
                $opts,
                $progressIndicator instanceof ProgressIndicator
                    ? function (ScanProgress $progress) use ($progressIndicator, &$progressStarted): void {
                        $message = $this->formatProgressMessage($progress);

                        if (! $progressStarted) {
                            $progressIndicator->start($message);
                            $progressStarted = true;

                            return;
                        }

                        $progressIndicator->setMessage($message);
                        $progressIndicator->advance();
                    }
                : null,
            );

            if ($progressIndicator instanceof ProgressIndicator && $progressStarted) {
                $progressIndicator->finish('Scan complete');
                $this->newLine(2);
            }

            if ($opts->json) {
                $this->line($jsonRenderer->render($report));
            } else {
                $consoleRenderer->render(
                    report: $report,
                    output: $this->getOutput(),
                    outputConfig: config('package-doctor.output', []),
                );
            }

            if ($opts->ci) {
                return $exitCodeResolver->resolve(
                    report: $report,
                    opts: $opts,
                    ciConfig: config('package-doctor.ci', []),
                );
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Package Doctor encountered an error: '.$e->getMessage());

            return 3;
        }
    }

    private function buildScanOptions(): ScanOptions
    {
        $scoreBelow = $this->option('score-below');

        return new ScanOptions(
            json: (bool) $this->option('json'),
            ci: (bool) $this->option('ci'),
            direct: (bool) $this->option('direct'),
            noDev: (bool) $this->option('no-dev'),
            noCache: (bool) $this->option('no-cache'),
            scoreBelow: $scoreBelow !== null ? (int) $scoreBelow : null,
            majorOnly: (bool) $this->option('major-only'),
            safeOnly: (bool) $this->option('safe-only'),
            packages: array_values(array_map(strval(...), (array) $this->option('package'))),
            offline: (bool) $this->option('offline'),
        );
    }

    private function makeProgressIndicator(ScanOptions $opts): ?ProgressIndicator
    {
        if ($opts->json || $opts->ci || ! $this->getOutput()->isDecorated()) {
            return null;
        }

        return new ProgressIndicator($this->getOutput());
    }

    private function formatProgressMessage(ScanProgress $progress): string
    {
        if ($progress->current !== null && $progress->total !== null) {
            return sprintf('%s (%d/%d)', $progress->message, $progress->current, $progress->total);
        }

        return $progress->message;
    }
}
