<?php

declare(strict_types=1);

namespace OpenPhonebook\Audit;

enum AuditTargetType: string
{
    case User = 'user';
    case Invitation = 'invitation';
}
