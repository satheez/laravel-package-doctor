<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Output;

use RuntimeException;
use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\DTO\ProjectHealthReport;

final class CsvReportRenderer
{
    /** @var list<string> */
    private const HEADERS = [
        'package',
        'current_version',
        'latest_version',
        'latest_allowed_version',
        'upgrade_type',
        'constraint_blocked',
        'dependency_type',
        'score',
        'status',
        'issue_count',
        'issue_codes',
        'issue_severities',
        'issue_score_impacts',
        'issue_messages',
        'recommendation_type',
        'recommendation_message',
        'changelog_url',
        'replacement_package',
    ];

    public function render(ProjectHealthReport $report): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary stream for CSV rendering.');
        }

        $this->writeRow($handle, self::HEADERS);

        foreach ($report->results as $result) {
            $this->writeRow($handle, [
                $result->package->name,
                $result->package->version,
                $result->latestVersion ?? '',
                $result->latestAllowedVersion ?? '',
                $result->upgradeType->value,
                $result->isConstraintBlocked ? 'true' : 'false',
                $result->package->dependencyType->value,
                (string) $result->score,
                $result->status->value,
                (string) count($result->issues),
                $this->flattenIssues($result->issues, 'code'),
                $this->flattenIssues($result->issues, 'severity'),
                $this->flattenIssues($result->issues, 'scoreImpact'),
                $this->flattenIssues($result->issues, 'message'),
                $result->recommendation->type->value,
                $result->recommendation->message,
                $result->changelogUrl ?? '',
                $result->replacementPackage ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false) {
            throw new RuntimeException('Unable to read rendered CSV output.');
        }

        return $csv;
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $row
     */
    private function writeRow($handle, array $row): void
    {
        if (fputcsv($handle, $row, ',', '"', '') === false) {
            throw new RuntimeException('Unable to write CSV row.');
        }
    }

    /**
     * @param  list<PackageIssue>  $issues
     * @param  'code'|'severity'|'scoreImpact'|'message'  $field
     */
    private function flattenIssues(array $issues, string $field): string
    {
        return implode('; ', array_map(
            static fn (PackageIssue $issue): string => match ($field) {
                'code' => $issue->code,
                'severity' => $issue->severity->value,
                'scoreImpact' => (string) $issue->scoreImpact,
                'message' => $issue->message,
            },
            $issues,
        ));
    }
}
