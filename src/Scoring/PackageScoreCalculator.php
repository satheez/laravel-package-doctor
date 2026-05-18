<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Enums\PackageStatus;
use Satheez\PackageDoctor\Scoring\Rules\ScoringRule;

final class PackageScoreCalculator
{
    /**
     * @param  list<ScoringRule>  $rules
     * @return array{issues: list<PackageIssue>, score: int, status: PackageStatus}
     */
    public function score(PackageHealthContext $context, array $rules): array
    {
        $maximum = (int) ($context->config['score']['maximum'] ?? 100);
        $minimum = (int) ($context->config['score']['minimum'] ?? 0);
        $thresholds = $context->config['score']['status_thresholds'] ?? [];

        $issues = [];
        $total = $maximum;

        foreach ($rules as $rule) {
            $issue = $rule->evaluate($context);
            if ($issue !== null) {
                $issues[] = $issue;
                $total += $issue->scoreImpact;
            }
        }

        $score = max($minimum, min($maximum, $total));
        $status = PackageStatus::fromScore($score, $thresholds);

        return [
            'issues' => $issues,
            'score' => $score,
            'status' => $status,
        ];
    }
}
