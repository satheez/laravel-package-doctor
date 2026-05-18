<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Output;

use Satheez\PackageDoctor\DTO\PackageHealthResult;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReportRenderer
{
    /** @param array<string, mixed> $outputConfig */
    public function render(ProjectHealthReport $report, OutputInterface $output, array $outputConfig): void
    {
        $showTransitive = $outputConfig['show_transitive_by_default'] ?? false;
        $maxIssues = (int) ($outputConfig['max_issues_per_package'] ?? 3);

        $output->writeln('');
        $output->writeln('<info>Laravel Package Doctor</info>');
        $output->writeln('');

        $output->writeln(sprintf(
            'PHP: <comment>%s</comment>  Laravel: <comment>%s</comment>',
            $report->project->phpVersion,
            $report->project->laravelVersion ?? 'unknown',
        ));

        $output->writeln('');
        $output->writeln('<info>Summary</info>');
        $output->writeln(sprintf('  Project score: <comment>%d/100</comment>', $report->summary['project_score'] ?? 0));
        $output->writeln(sprintf('  Packages scanned: <comment>%d</comment>', $report->summary['total_packages'] ?? 0));
        $output->writeln(sprintf(
            '  Healthy: <info>%d</info>  Watch: <comment>%d</comment>  Risky: <fg=red>%d</>  Critical: <fg=red>%d</>',
            $report->summary['healthy_count'] ?? 0,
            $report->summary['watch_count'] ?? 0,
            $report->summary['risky_count'] ?? 0,
            $report->summary['critical_count'] ?? 0,
        ));
        $output->writeln('');

        $results = $report->results;

        if (! $showTransitive) {
            $results = array_filter($results, fn (PackageHealthResult $r): bool => $r->package->dependencyType->value !== 'transitive');
            $results = array_values($results);
        }

        if ($results === []) {
            $output->writeln('<info>No packages to display.</info>');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Package', 'Current', 'Latest', 'Upgrade', 'Score', 'Status', 'Recommendation']);

        foreach ($results as $result) {
            $statusLabel = match ($result->status) {
                PackageStatus::Healthy => '<info>Healthy</info>',
                PackageStatus::Watch => '<comment>Watch</comment>',
                PackageStatus::Risky => '<fg=red>Risky</>',
                PackageStatus::Critical => '<fg=red;options=bold>Critical</>',
            };

            $table->addRow([
                $result->package->name,
                $result->package->version,
                $result->latestVersion ?? '-',
                $result->upgradeType->value,
                $result->score,
                $statusLabel,
                $this->truncate($result->recommendation->message, 40),
            ]);

            $issuesToShow = array_slice($result->issues, 0, $maxIssues);
            foreach ($issuesToShow as $issue) {
                $table->addRow([
                    '  <fg=gray>↳ ['.$issue->code.']</>',
                    '<fg=gray>'.$this->truncate($issue->message, 50).'</>',
                    '', '', '', '', '',
                ]);
            }
        }

        $table->render();

        if ($report->warnings !== []) {
            $output->writeln('');
            $output->writeln('<comment>Warnings:</comment>');
            foreach ($report->warnings as $warning) {
                $output->writeln("  <fg=yellow>⚠</> {$warning}");
            }
        }

        $output->writeln('');
    }

    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3).'...';
    }
}
