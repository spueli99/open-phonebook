<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

enum UpdateReadinessStatus: string
{
    case Ready = 'ready';
    case MigrationRequired = 'migration_required';
    case Failed = 'failed';
}
