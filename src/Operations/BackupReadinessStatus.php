<?php

declare(strict_types=1);

namespace OpenPhonebook\Operations;

enum BackupReadinessStatus: string
{
    case Ready = 'ready';
    case Warning = 'warning';
    case Failed = 'failed';
}
