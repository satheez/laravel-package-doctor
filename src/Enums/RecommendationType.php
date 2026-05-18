<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Enums;

enum RecommendationType: string
{
    case None = 'none';
    case SafeUpgrade = 'safe_upgrade';
    case UpdateWhenConvenient = 'update_when_convenient';
    case ReviewBeforeUpgrade = 'review_before_upgrade';
    case ReplacePackage = 'replace_package';
    case FixSecurityIssue = 'fix_security_issue';
    case CheckCompatibility = 'check_compatibility';
    case MonitorPackage = 'monitor_package';
    case IgnoreConfigured = 'ignore_configured';
}
