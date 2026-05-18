<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Output;

use Satheez\PackageDoctor\DTO\ProjectHealthReport;
use Satheez\PackageDoctor\DTO\ScanOptions;
use Satheez\PackageDoctor\Enums\PackageStatus;

final class CiExitCodeResolver
{
    /** @param array<string, mixed> $ciConfig */
    public function resolve(ProjectHealthReport $report, ScanOptions $opts, array $ciConfig): int
    {
        $failOnStatuses = $ciConfig['fail_on_statuses'] ?? ['critical'];
        $minScore = (int) ($ciConfig['minimum_project_score'] ?? 0);

        foreach ($report->results as $result) {
            if ($result->status === PackageStatus::Critical && in_array('critical', $failOnStatuses, true)) {
                return 2;
            }
        }

        foreach ($report->results as $result) {
            if ($result->status === PackageStatus::Risky && in_array('risky', $failOnStatuses, true)) {
                return 1;
            }
        }

        if ($minScore > 0 && ($report->summary['project_score'] ?? 100) < $minScore) {
            return 1;
        }

        return 0;
    }
}
