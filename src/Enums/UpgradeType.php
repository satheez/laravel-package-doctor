<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Enums;

enum UpgradeType: string
{
    case None = 'none';
    case Patch = 'patch';
    case Minor = 'minor';
    case Major = 'major';
    case Unknown = 'unknown';
}
