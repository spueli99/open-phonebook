<?php

declare(strict_types=1);

namespace OpenPhonebook\Notifications;

interface UserTimezoneRepositoryInterface
{
    public function timezoneForUser(int $userId): string;
}
