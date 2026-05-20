<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\DTO\ScanProgress;
use Satheez\PackageDoctor\Output\CiExitCodeResolver;
use Satheez\PackageDoctor\Output\ConsoleReportRenderer;
use Satheez\PackageDoctor\Output\CsvReportRenderer;
use Satheez\PackageDoctor\Output\JsonReportRenderer;
use Satheez\PackageDoctor\Services\PackageDoctor;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Throwable;

final class PackageDoctorCommand extends Command
{
    private const OUTPUT_FORMAT_TABLE = 'table';

    private const OUTPUT_FORMAT_JSON = 'json';

    private const OUTPUT_FORMAT_CSV = 'csv';

    /** @var list<string> */
    private const OUTPUT_FORMATS = [
        self::OUTPUT_FORMAT_TABLE,
        self::OUTPUT_FORMAT_JSON,
        self::OUTPUT_FORMAT_CSV,
    ];

    protected $signature = 'package:doctor
        {--json : Output results as JSON}
        {--format=table : Output format: table, json, or csv}
        {--output= : Write json/csv report to a file}
        {--ci : Enable CI mode with exit codes}
        {--direct : Include only direct dependencies}
        {--no-dev : Exclude dev dependencies}
        {--no-cache : Bypass cache for external metadata}
        {--offline : Skip Packagist and GitHub metadata collection}
        {--all : Scan the full dependency tree including transitive packages}
        {--score-below= : Only show packages with score below this value}
        {--major-only : Only show packages with a major upgrade available}
        {--safe-only : Only show packages with safe patch/minor upgrades and no critical issues}
        {--package=* : Scan or display only the given package(s)}';

    protected $description = 'Audit your Laravel project\'s Composer dependencies for upgrade safety, security, and compatibility.';

    public function handle(
        PackageDoctor $doctor,
        JsonReportRenderer $jsonRenderer,
        CsvReportRenderer $csvRenderer,
        ConsoleReportRenderer $consoleRenderer,
        CiExitCodeResolver $exitCodeResolver,
    ): int {
        try {
            $outputFormat = $this->resolveOutputFormat();
            $outputPath = $this->resolveOutputPath($outputFormat);
            $opts = $this->buildScanOptions($outputFormat);
            $progressIndicator = $this->makeProgressIndicator($opts, $outputFormat);
            $progressStarted = false;
            $currentProgressMessage = null;

            $report = $doctor->analyze(
                $opts,
                $progressIndicator instanceof ProgressIndicator
                    ? function (ScanProgress $progress) use ($progressIndicator, &$progressStarted, &$currentProgressMessage): void {
                        $message = $this->formatProgressMessage($progress);

                        if (! $progressStarted) {
                            $progressIndicator->start($message);
                            $progressStarted = true;
                            $currentProgressMessage = $message;

                            return;
                        }

                        if ($message !== $currentProgressMessage) {
                            $progressIndicator->setMessage($message);
                            $currentProgressMessage = $message;
                        }

                        $progressIndicator->advance();
                    }
                : null,
            );

            if ($progressIndicator instanceof ProgressIndicator && $progressStarted) {
                $progressIndicator->finish('Scan complete');
                $this->newLine(2);
            }

            if ($outputFormat === self::OUTPUT_FORMAT_TABLE) {
                $consoleRenderer->render(
                    report: $report,
                    output: $this->getOutput(),
                    outputConfig: config('package-doctor.output', []),
                );
            } else {
                $renderedReport = $outputFormat === self::OUTPUT_FORMAT_JSON
                    ? $jsonRenderer->render($report)
                    : $csvRenderer->render($report);

                if ($outputPath !== null) {
                    $this->writeOutputFile($outputPath, $renderedReport);
                } elseif ($outputFormat === self::OUTPUT_FORMAT_CSV) {
                    $this->getOutput()->write($renderedReport);
                } else {
                    $this->line($renderedReport);
                }
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

    private function buildScanOptions(string $outputFormat): ScanOptions
    {
        $scoreBelow = $this->option('score-below');

        return new ScanOptions(
            json: $outputFormat === self::OUTPUT_FORMAT_JSON,
            ci: (bool) $this->option('ci'),
            direct: (bool) $this->option('direct'),
            noDev: (bool) $this->option('no-dev'),
            noCache: (bool) $this->option('no-cache'),
            scoreBelow: $scoreBelow !== null ? (int) $scoreBelow : null,
            majorOnly: (bool) $this->option('major-only'),
            safeOnly: (bool) $this->option('safe-only'),
            packages: array_values(array_map(strval(...), (array) $this->option('package'))),
            offline: (bool) $this->option('offline'),
            all: (bool) $this->option('all'),
        );
    }

    private function makeProgressIndicator(ScanOptions $opts, string $outputFormat): ?ProgressIndicator
    {
        if ($outputFormat !== self::OUTPUT_FORMAT_TABLE || $opts->ci || ! $this->getOutput()->isDecorated()) {
            return null;
        }

        return new ProgressIndicator($this->getOutput());
    }

    private function resolveOutputFormat(): string
    {
        $formatOption = $this->option('format');

        if (! is_string($formatOption)) {
            throw new InvalidArgumentException('The --format option requires a string value.');
        }

        $format = strtolower($formatOption);

        if (! in_array($format, self::OUTPUT_FORMATS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid output format "%s". Supported formats are: table, json, csv.', $format)
            );
        }

        if ((bool) $this->option('json')) {
            if ($this->input->hasParameterOption('--format') && $format !== self::OUTPUT_FORMAT_JSON) {
                throw new InvalidArgumentException(
                    sprintf('The --json option cannot be combined with --format=%s.', $format)
                );
            }

            return self::OUTPUT_FORMAT_JSON;
        }

        return $format;
    }

    private function resolveOutputPath(string $outputFormat): ?string
    {
        $outputPath = $this->option('output');

        if ($outputPath === null) {
            return null;
        }

        if (! is_string($outputPath)) {
            throw new InvalidArgumentException('The --output option requires a file path.');
        }

        if ($outputFormat === self::OUTPUT_FORMAT_TABLE) {
            throw new InvalidArgumentException('The --output option requires --format=json, --format=csv, or --json.');
        }

        if ($outputPath === '') {
            throw new InvalidArgumentException('The --output option requires a file path.');
        }

        return $outputPath;
    }

    private function writeOutputFile(string $outputPath, string $contents): void
    {
        if (is_dir($outputPath)) {
            throw new InvalidArgumentException('The --output option must point to a file, not a directory.');
        }

        $directory = dirname($outputPath);

        if (! is_dir($directory)) {
            throw new InvalidArgumentException("Output directory does not exist: {$directory}");
        }

        if (! is_writable($directory)) {
            throw new InvalidArgumentException("Output directory is not writable: {$directory}");
        }

        if (file_put_contents($outputPath, $contents) === false) {
            throw new InvalidArgumentException("Unable to write output file: {$outputPath}");
        }
    }

    private function formatProgressMessage(ScanProgress $progress): string
    {
        if ($progress->current !== null && $progress->total !== null) {
            return sprintf('%s (%d/%d)', $progress->message, $progress->current, $progress->total);
        }

        return $progress->message;
    }
}
