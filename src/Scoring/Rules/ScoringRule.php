<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Scoring\Rules;

use Satheez\PackageDoctor\DTO\PackageIssue;
use Satheez\PackageDoctor\Scoring\PackageHealthContext;

interface ScoringRule
{
    public function evaluate(PackageHealthContext $context): ?PackageIssue;
}
