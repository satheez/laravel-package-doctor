<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Enums;

enum IssueSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Risk = 'risk';
    case Critical = 'critical';
}
