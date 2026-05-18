<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Output;

use Satheez\PackageDoctor\DTO\ProjectHealthReport;

final class JsonReportRenderer
{
    public function render(ProjectHealthReport $report): string
    {
        $packages = [];

        foreach ($report->results as $result) {
            $issues = [];
            foreach ($result->issues as $issue) {
                $issues[] = [
                    'code' => $issue->code,
                    'severity' => $issue->severity->value,
                    'message' => $issue->message,
                    'score_impact' => $issue->scoreImpact,
                ];
            }

            $packages[] = [
                'name' => $result->package->name,
                'current_version' => $result->package->version,
                'latest_version' => $result->latestVersion,
                'latest_allowed_version' => $result->latestAllowedVersion,
                'upgrade_type' => $result->upgradeType->value,
                'constraint_blocked' => $result->isConstraintBlocked,
                'dependency_type' => $result->package->dependencyType->value,
                'score' => $result->score,
                'status' => $result->status->value,
                'issues' => $issues,
                'recommendation' => [
                    'type' => $result->recommendation->type->value,
                    'message' => $result->recommendation->message,
                ],
            ];
        }

        $output = [
            'project' => [
                'php_version' => $report->project->phpVersion,
                'laravel_version' => $report->project->laravelVersion,
                'composer_version' => $report->project->composerVersion,
                'base_path' => $report->project->basePath,
            ],
            'summary' => $report->summary,
            'packages' => $packages,
            'warnings' => $report->warnings,
            'metadata' => [
                'generated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
                'package_doctor_version' => '1.0.0',
            ],
        ];

        return (string) json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
