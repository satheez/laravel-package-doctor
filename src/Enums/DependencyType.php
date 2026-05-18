<?php

declare(strict_types=1);

namespace Satheez\PackageDoctor\Enums;

enum DependencyType: string
{
    case Direct = 'direct';
    case Dev = 'dev';
    case Transitive = 'transitive';
}
