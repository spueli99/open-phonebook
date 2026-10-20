<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

enum HealthStatus: string
{
    case Healthy = 'healthy';
    case Stale = 'stale';
    case Failed = 'failed';
    case NeverRun = 'never_run';
}
